<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TareaModel;
use Config\Database;

/**
 * TareaService
 *
 * ✅ Este Service centraliza:
 * - Creación/edición de tareas (multi-asignación con batch)
 * - Calendario (FullCalendar)
 * - Flujo de revisión (opcional, depende de columnas)
 * - ✅ Satisfacción semanal (jueves→miércoles) con:
 *   - cards ordenables (division/area/personal)
 *   - promedio últimas 4 semanas
 *   - histórico (últimas 4 + actual) por tarjeta
 *   - ranking de áreas (para división)
 *   - ranking de usuarios (por área)
 *   - histórico global (últimas 4 + actual)
 *
 * 🔥 IMPORTANTE (para tu vista satisfaccion.php):
 * - Las tarjetas deben traer scope EXACTO: 'division' | 'area' | 'personal'
 * - Deben incluir division_id / area_id cuando aplique
 * - Deben incluir avg_4_weeks e history para que la UI muestre barras
 * - Debe incluir:
 *   - ranking_areas
 *   - ranking_users_by_area (indexado por id_area)
 *   - history_global
 */
class TareaService
{
    // --------------------------------------------------
    // Dependencias
    // --------------------------------------------------
    private TareaModel $tareaModel;

    // --------------------------------------------------
    // Config zona horaria
    // --------------------------------------------------
    private string $tzName = 'America/Guayaquil';

    // --------------------------------------------------
    // Estados (según tu catálogo estado_tarea)
    // --------------------------------------------------
    private int $estadoPendiente   = 1;
    private int $estadoEnProceso   = 2;
    private int $estadoRealizada   = 3;
    private int $estadoNoRealizada = 4;
    private int $estadoCancelada   = 5;

    // Estado para flujo de revisión (si existe)
    private int $estadoEnRevision  = 6;

    // Regla: si edit_count supera esto => forzar No realizada
    private int $maxEditsForRealizada = 2;

    // Cache: existencia de columnas (evita consultar information_schema siempre)
    private array $columnExistsCache = [];

    // Cache: existe columna batch_uid
    private ?bool $hasBatchUidColumn = null;

    public function __construct(?TareaModel $tareaModel = null)
    {
        // Si no inyectas el modelo, se crea uno nuevo
        $this->tareaModel = $tareaModel ?? new TareaModel();
    }

    // ==================================================
    // TIME HELPERS
    // ==================================================

    /**
     * Retorna instancia de TimeZone del sistema (Ecuador).
     */
    private function tz(): \DateTimeZone
    {
        return new \DateTimeZone($this->tzName);
    }

    /**
     * Retorna "hoy" en formato YYYY-mm-dd (zona local).
     */
    private function todayKey(): string
    {
        $now = new \DateTimeImmutable('now', $this->tz());
        return $now->format('Y-m-d');
    }

    /**
     * Parsea fechas que pueden venir como:
     * - datetime-local (YYYY-mm-ddTHH:ii)
     * - timestamp (YYYY-mm-dd HH:ii:ss)
     * - cualquier string parseable por PHP
     *
     * Retorna DateTimeImmutable o null si no se puede parsear.
     */
    private function parseLocalDateTime(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $tz = $this->tz();

        // Caso A: input type="datetime-local" => 2026-02-17T14:30
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $tz);
            return $dt ?: null;
        }

        // Caso B: string timestamp clásico
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $value)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
            return $dt ?: null;
        }

        // Caso C: fallback
        try {
            return new \DateTimeImmutable($value, $tz);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convierte DateTimeImmutable a string para BD (timestamp).
     */
    private function toDbDateTime(\DateTimeImmutable $dt): string
    {
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Devuelve la “clave de día” YYYY-mm-dd.
     */
    private function dayKey(\DateTimeImmutable $dt): string
    {
        return $dt->format('Y-m-d');
    }

    /**
     * Validación de fechas:
     * - inicio obligatorio
     * - fin obligatorio si $requireEnd=true
     * - NO permitir días anteriores a HOY (comparación YYYY-mm-dd)
     * - fin >= inicio (comparación con hora incluida)
     */
    private function validateDates(
        ?string $startRaw,
        ?string $endRaw,
        bool $requireEnd,
        bool $skipPastDayCheckStart = false,
        bool $skipPastDayCheckEnd = false
    ): array {
        $startDt = $this->parseLocalDateTime($startRaw);
        $endDt   = $this->parseLocalDateTime($endRaw);

        if (!$startDt) {
            return ['ok' => false, 'error' => 'La fecha de inicio es obligatoria.', 'startDt' => null, 'endDt' => null];
        }

        if ($requireEnd && !$endDt) {
            return ['ok' => false, 'error' => 'La fecha final es obligatoria.', 'startDt' => $startDt, 'endDt' => null];
        }

        $today = $this->todayKey();

        // ✅ Bloqueo de fechas en el pasado (por día)
        if (!$skipPastDayCheckStart && $this->dayKey($startDt) < $today) {
            return [
                'ok' => false,
                'error' => 'La fecha de inicio no puede ser anterior a la fecha actual.',
                'startDt' => $startDt,
                'endDt' => $endDt,
            ];
        }

        if ($endDt) {
            if (!$skipPastDayCheckEnd && $this->dayKey($endDt) < $today) {
                return [
                    'ok' => false,
                    'error' => 'La fecha final no puede ser anterior a la fecha actual.',
                    'startDt' => $startDt,
                    'endDt' => $endDt,
                ];
            }

            // ✅ Comparación con hora incluida
            if ($endDt < $startDt) {
                return [
                    'ok' => false,
                    'error' => 'La fecha final no puede ser menor a la fecha de inicio.',
                    'startDt' => $startDt,
                    'endDt' => $endDt,
                ];
            }
        }

        return ['ok' => true, 'error' => null, 'startDt' => $startDt, 'endDt' => $endDt];
    }

    /**
     * Comparación por minuto (porque datetime-local no maneja segundos).
     */
    private function sameMinute(?\DateTimeImmutable $a, ?\DateTimeImmutable $b): bool
    {
        if (!$a || !$b) {
            return false;
        }
        return $a->format('Y-m-d H:i') === $b->format('Y-m-d H:i');
    }
    /**
     * ==================================================
     * HELPERS: CADUCIDAD Y CAMBIO SOLO DE HORA
     * ==================================================
     */

    /**
     * Devuelve true si la tarea quedó vencida comparando fecha_fin con ahora.
     */
    private function isExpiredByNow(?string $fechaFinRaw): bool
    {
        $endDt = $this->parseLocalDateTime($fechaFinRaw);

        if (!$endDt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', $this->tz());

        return $endDt < $now;
    }

    /**
     * Construye una nueva fecha_fin manteniendo la MISMA FECHA
     * y reemplazando solo la HORA.
     *
     * Ejemplo:
     * - fecha original: 2026-03-17 10:00:00
     * - newTime: 16:30
     * => 2026-03-17 16:30:00
     */
    private function replaceOnlyTimeOnDate(?string $originalDateTimeRaw, ?string $newTime): ?\DateTimeImmutable
    {
        $originalDateTimeRaw = trim((string)$originalDateTimeRaw);
        $newTime             = trim((string)$newTime);

        if ($originalDateTimeRaw === '' || $newTime === '') {
            return null;
        }

        // Hora esperada: HH:ii
        if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            return null;
        }

        $originalDt = $this->parseLocalDateTime($originalDateTimeRaw);
        if (!$originalDt) {
            return null;
        }

        [$hour, $minute] = explode(':', $newTime);

        return $originalDt->setTime((int)$hour, (int)$minute, 0);
    }
    // ==================================================
    // HELPERS: EXISTENCIA DE COLUMNAS (DB)
    // ==================================================

    /**
     * Cachea existencia de columna (evita consultar information_schema siempre).
     */
    private function columnExists(string $schema, string $table, string $column): bool
    {
        $key = $schema . '.' . $table . '.' . $column;

        // ✅ Si ya se calculó, devolver cache
        if (array_key_exists($key, $this->columnExistsCache)) {
            return (bool) $this->columnExistsCache[$key];
        }

        try {
            $db  = Database::connect();

            // Consultamos información de columnas (PostgreSQL)
            $row = $db->query(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = ?
                   AND table_name   = ?
                   AND column_name  = ?
                 LIMIT 1",
                [$schema, $table, $column]
            )->getRowArray();

            $this->columnExistsCache[$key] = !empty($row);
            return (bool) $this->columnExistsCache[$key];
        } catch (\Throwable $e) {
            // ✅ Si falla, asumimos NO existe para no romper.
            $this->columnExistsCache[$key] = false;
            return false;
        }
    }
    /**
     * =========================================================
     * HELPERS: TAREAS CERRADAS
     * =========================================================
     */
    private function isClosedEstado(int $estadoId): bool
    {
        return in_array($estadoId, [
            $this->estadoRealizada,
            $this->estadoNoRealizada,
            $this->estadoCancelada,
        ], true);
    }
    /**
     * Atajo: columna en public.tareas
     */
    private function taskColumnExists(string $column): bool
    {
        return $this->columnExists('public', 'tareas', $column);
    }

    /**
     * Verifica si existen TODAS las columnas requeridas para el flujo de revisión.
     */
    private function hasReviewFlowColumns(): bool
    {
        return
            $this->taskColumnExists('review_requested_state') &&
            $this->taskColumnExists('review_requested_at') &&
            $this->taskColumnExists('approved_by') &&
            $this->taskColumnExists('approved_at') &&
            $this->taskColumnExists('edit_count');
    }

    private function hasExtendedReviewColumns(): bool
    {
        return
            $this->taskColumnExists('review_action') &&
            $this->taskColumnExists('review_reason') &&
            $this->taskColumnExists('review_requested_by') &&
            $this->taskColumnExists('review_requested_fecha_fin') &&
            $this->taskColumnExists('review_previous_state');
    }
    /**
     * Error estándar cuando faltan columnas de revisión.
     */
    private function reviewColumnsMissingError(): array
    {
        return [
            'success' => false,
            'error'   => 'Faltan columnas para el flujo de revisión (review_requested_state, review_requested_at, approved_by, approved_at, edit_count) o falta el estado 6 "En revisión".',
        ];
    }

    // ==================================================
    // HELPERS: USERS (CENTRALIZADOS) ✅✅✅
    // ==================================================

    /**
     * ✅ Construye un "label" de usuario de forma consistente.
     * - Si la fila ya trae "label" -> lo respeta.
     * - Si no -> arma con nombres + apellidos.
     */
    private function buildUserLabel(array $row): string
    {
        $label = trim((string) ($row['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        $full = trim((string) (($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? '')));
        return ($full !== '' ? $full : 'Usuario');
    }

    /**
     * ✅ Convierte filas de usuario (cualquier formato) a formato dropdown:
     * [
     *   ['id_user' => 1, 'label' => 'Nombre Apellido'],
     *   ...
     * ]
     *
     * @param array $rows            Filas provenientes del Model o query directo.
     * @param int   $excludeUserId   Si > 0, excluye ese id del listado (útil para "no listar al mismo").
     */
    private function mapUsersToDropdown(array $rows, int $excludeUserId = 0): array
    {
        $out = [];

        foreach ($rows as $r) {
            $id = (int) ($r['id_user'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            // Excluir un id específico si te lo piden (ej: current user)
            if ($excludeUserId > 0 && $id === $excludeUserId) {
                continue;
            }

            $out[] = [
                'id_user' => $id,
                'label'   => $this->buildUserLabel($r),
            ];
        }

        return $out;
    }

    /**
     * ✅ Obtiene usuarios "RAW" por área (para validaciones/IDs/etc).
     * - Prioridad: método del Model si existe
     * - Fallback: consulta directa (segura) a BD
     *
     * ⚠️ Importante:
     * - Este método NO aplica permisos, solo retorna miembros del área.
     */
    private function fetchUsersByAreaRaw(int $areaId): array
    {
        // 1) Si el Model tiene la consulta, la respetamos (no cambiamos tu lógica).
        if (method_exists($this->tareaModel, 'getUsersByArea')) {
            return (array) $this->tareaModel->getUsersByArea($areaId);
        }

        // 2) Fallback: consultar directo por cargo.id_area
        //    (esto mantiene tu diseño: USER no guarda id_area, se deriva del cargo)
        try {
            $db = Database::connect();

            return $db->query(
                'SELECT u.id_user, u.nombres, u.apellidos
                 FROM public."USER" u
                 JOIN public.cargo c ON c.id_cargo = u.id_cargo
                 WHERE u.activo = true
                   AND c.id_area = ?
                 ORDER BY u.nombres ASC, u.apellidos ASC',
                [$areaId]
            )->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * ✅ Obtiene usuarios por área listos para combo (dropdown).
     *
     * - Prioridad: método del Model getUsersByAreaForDropdown si existe (tu versión “óptima”).
     * - Fallback: usa fetchUsersByAreaRaw y lo mapea a {id_user,label}.
     *
     * @param int $areaId
     * @param int $excludeUserId (opcional) excluye un id (ej: current user)
     */
    private function fetchUsersByAreaDropdown(int $areaId, int $excludeUserId = 0): array
    {
        // 1) Si tu Model ya tiene una versión optimizada para dropdown, úsala.
        //    Nota: en tu código anterior, este método recibía ($areaId, $currentUserId)
        //    por lo que asumimos que "excluye" a ese usuario (según tu implementación).
        if (method_exists($this->tareaModel, 'getUsersByAreaForDropdown')) {
            $rows = (array) $this->tareaModel->getUsersByAreaForDropdown($areaId, $excludeUserId);
            // Aseguramos formato consistente por si el model devolvió más columnas.
            return $this->mapUsersToDropdown($rows, 0);
        }

        // 2) Fallback
        $raw = $this->fetchUsersByAreaRaw($areaId);
        return $this->mapUsersToDropdown($raw, $excludeUserId);
    }

    /**
     * ✅ Inserta "autoasignación" al inicio si el usuario no está en el listado.
     * Mantiene el mismo comportamiento que ya tenías para super/division.
     */
    private function ensureSelfInDropdown(array $list, int $currentUserId, string $suffix = ' (Autoasignación)'): array
    {
        $exists = false;

        foreach ($list as $u) {
            if ((int) ($u['id_user'] ?? 0) === $currentUserId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            array_unshift($list, [
                'id_user' => $currentUserId,
                'label'   => $this->getCurrentUserLabel($currentUserId) . $suffix,
            ]);
        }

        return $list;
    }

    // ==================================================
    // HELPERS: PERMISOS / SCOPE (JEFATURAS)
    // ==================================================

    /**
     * Determina el scope real de asignación del usuario actual.
     *
     * Prevalencias:
     * 0) Gerencia (id_area=1) => super
     * 1) Jefe de división     => division
     * 2) Jefe de área         => area
     * 3) Normal               => self
     *
     * @return array{mode:string, divisionId:int|null, areaId:int|null}
     */
    private function resolveAssignScope(int $currentUserId, int $currentUserAreaId): array
    {
        // 0) Gerencia
        if ($currentUserAreaId === 1) {
            return ['mode' => 'super', 'divisionId' => null, 'areaId' => null];
        }

        // 1) Jefe de división (si el modelo tiene el método)
        $divisionId = null;
        if (method_exists($this->tareaModel, 'getChiefDivisionId')) {
            $divisionId = $this->tareaModel->getChiefDivisionId($currentUserId);
        }
        if ($divisionId) {
            return ['mode' => 'division', 'divisionId' => (int) $divisionId, 'areaId' => null];
        }

        // 2) Jefe de área (si el modelo tiene el método)
        $areaId = null;
        if (method_exists($this->tareaModel, 'getChiefAreaId')) {
            $areaId = $this->tareaModel->getChiefAreaId($currentUserId);
        }
        if ($areaId) {
            return ['mode' => 'area', 'divisionId' => null, 'areaId' => (int) $areaId];
        }

        // 3) Normal (self): autoasignación
        $fallbackArea = $currentUserAreaId > 0
            ? $currentUserAreaId
            : (int) ((method_exists($this->tareaModel, 'getAreaIdByUser'))
                ? ($this->tareaModel->getAreaIdByUser($currentUserId) ?? 0)
                : 0);

        return [
            'mode' => 'self',
            'divisionId' => null,
            'areaId' => ($fallbackArea > 0 ? $fallbackArea : null),
        ];
    }

    /**
     * ✅ Método público para UI.
     */
    public function getAssignScopeForUi(int $currentUserId, int $currentUserAreaId): array
    {
        return $this->resolveAssignScope($currentUserId, $currentUserAreaId);
    }

    /**
     * Prioridad automática según FECHA FIN respecto a HOY (por día).
     */
    private function autoPriorityFromEnd(\DateTimeImmutable $endDt, ?int $currentPriority = null): int
    {
        $today  = (new \DateTimeImmutable('now', $this->tz()))->setTime(0, 0, 0);
        $endDay = $endDt->setTime(0, 0, 0);

        // Si la tarea es vieja (pasado) y ya tenía prioridad, respetarla
        if ($endDay < $today && $currentPriority && $currentPriority > 0) {
            return $currentPriority;
        }

        $days = (int) floor(($endDay->getTimestamp() - $today->getTimestamp()) / 86400);

        if ($days <= 0) return 4;
        if ($days === 1) return 2;
        if ($days <= 3) return 3;
        return 1;
    }

    /**
     * Label del usuario actual para combos.
     */
    private function getCurrentUserLabel(int $userId): string
    {
        try {
            $db = Database::connect();

            $row = $db->query(
                'SELECT TRIM(nombres || \' \' || apellidos) AS label FROM public."USER" WHERE id_user = ? LIMIT 1',
                [$userId]
            )->getRowArray();

            $base = trim((string) ($row['label'] ?? 'Usuario'));
            return ($base !== '' ? $base : 'Usuario');
        } catch (\Throwable $e) {
            return 'Usuario';
        }
    }

    // ==================================================
    // HELPERS "BATCH"
    // ==================================================

    /**
     * Revisa si existe columna public.tareas.batch_uid
     */
    private function hasBatchUidColumn(): bool
    {
        if ($this->hasBatchUidColumn !== null) {
            return $this->hasBatchUidColumn;
        }

        $this->hasBatchUidColumn = $this->taskColumnExists('batch_uid');
        return $this->hasBatchUidColumn;
    }

    /**
     * Genera UUID v4 en PHP (sin librerías extra).
     */
    private function uuidV4(): string
    {
        $data = random_bytes(16);

        // version 4
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // variant RFC4122
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Obtiene todas las filas "hermanas" del batch.
     * - Si existe batch_uid => por batch_uid
     * - Si no => fallback por firma estable
     */
    private function getBatchRowsForTask(array $task): array
    {
        $db = Database::connect();

        // 1) Si hay batch_uid real, úsalo
        if ($this->hasBatchUidColumn()) {
            $batchUid = (string) ($task['batch_uid'] ?? '');
            if ($batchUid !== '') {
                return $db->query(
                    'SELECT * FROM public.tareas WHERE batch_uid = ? ORDER BY id_tarea ASC',
                    [$batchUid]
                )->getResultArray();
            }
        }

        // 2) Fallback sin batch_uid (firma)
        $asignadoPor = (int) ($task['asignado_por'] ?? 0);
        $createdAt   = (string) ($task['created_at'] ?? '');
        $idArea      = (int) ($task['id_area'] ?? 0);
        $fIni        = (string) ($task['fecha_inicio'] ?? '');
        $fFin        = (string) ($task['fecha_fin'] ?? '');
        $titulo      = (string) ($task['titulo'] ?? '');

        if ($asignadoPor <= 0 || $createdAt === '') {
            // No podemos agrupar; devolvemos solo la fila base
            return [$task];
        }

        return $db->query(
            'SELECT * FROM public.tareas
             WHERE asignado_por = ?
               AND created_at   = ?
               AND id_area      = ?
               AND fecha_inicio = ?
               AND fecha_fin    = ?
               AND titulo       = ?
             ORDER BY id_tarea ASC',
            [$asignadoPor, $createdAt, $idArea, $fIni, $fFin, $titulo]
        )->getResultArray();
    }

    /**
     * Si existe batch_uid en tabla pero la fila no lo tiene,
     * se lo asignamos a TODO el grupo.
     */
    private function ensureBatchUidForGroup(array $groupRows): ?string
    {
        // Si no hay columna, no hacemos nada
        if (!$this->hasBatchUidColumn()) {
            return null;
        }

        // Si ya hay uno en alguna fila, lo usamos
        foreach ($groupRows as $r) {
            $b = (string) ($r['batch_uid'] ?? '');
            if ($b !== '') {
                return $b;
            }
        }

        // Si ninguno tiene, generamos y lo aplicamos
        $batchUid = $this->uuidV4();

        $db = Database::connect();
        $db->transStart();

        try {
            foreach ($groupRows as $r) {
                $id = (int) ($r['id_tarea'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        'UPDATE public.tareas SET batch_uid = ? WHERE id_tarea = ?',
                        [$batchUid, $id]
                    );
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return null;
        }

        $db->transComplete();
        return $db->transStatus() ? $batchUid : null;
    }

    /**
     * Normaliza asignados (asignado_a / asignado_a[]) a array<int> único.
     */
    private function normalizeAssignees($raw): array
    {
        $assignees = [];

        if (is_array($raw)) {
            foreach ($raw as $v) {
                $n = (int) $v;
                if ($n > 0) $assignees[] = $n;
            }
        } else {
            $n = (int) $raw;
            if ($n > 0) $assignees[] = $n;
        }

        return array_values(array_unique($assignees));
    }

    // ==================================================
    // ENDPOINT SEGURO: USERS-BY-AREA  ✅ (UNIFICADO)
    // ==================================================

    /**
     * Retorna SOLO usuarios que el usuario actual puede ver/asignar.
     *
     * ✅ Regla:
     * - Si mode=division o super => autoasignación SIEMPRE disponible,
     *   aunque no pertenezca al área seleccionada.
     *
     * 🔥 Mejora aplicada:
     * - Se unifica la obtención del dropdown usando fetchUsersByAreaDropdown()
     * - Se unifica la inserción de autoasignación con ensureSelfInDropdown()
     * - Se elimina duplicación de closures / mapeos repetidos
     */
    public function getAssignableUsersByArea(int $requestedAreaId, int $currentUserId, int $currentUserAreaId): array
    {
        // Determinar alcance real del usuario actual
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        // SUPER: cualquier área (y autoasignación siempre visible)
        if ($scope['mode'] === 'super') {
            // En tu lógica anterior, se pasaba $currentUserId al model (posible exclusión).
            $list = $this->fetchUsersByAreaDropdown($requestedAreaId, $currentUserId);

            // Garantizar autoasignación visible aunque el user no pertenezca al área
            $list = $this->ensureSelfInDropdown($list, $currentUserId);

            return $list;
        }

        // JEFE DE DIVISIÓN: solo áreas dentro de su división (y autoasignación siempre visible)
        if ($scope['mode'] === 'division') {
            $divisionId = (int) ($scope['divisionId'] ?? 0);
            if ($divisionId <= 0) {
                return [];
            }

            // Validar que el área solicitada pertenece a la división del jefe
            if (method_exists($this->tareaModel, 'isAreaInDivision')) {
                if (!$this->tareaModel->isAreaInDivision($requestedAreaId, $divisionId)) {
                    return [];
                }
            }

            // Dropdown (posible exclusión del mismo usuario, como tu versión anterior)
            $list = $this->fetchUsersByAreaDropdown($requestedAreaId, $currentUserId);

            // Autoasignación siempre disponible para jefe de división
            $list = $this->ensureSelfInDropdown($list, $currentUserId);

            return $list;
        }

        // JEFE DE ÁREA: solo su área (sin forzar autoasignación extra, igual que tu versión anterior)
        if ($scope['mode'] === 'area') {
            $areaId = (int) ($scope['areaId'] ?? 0);
            if ($areaId <= 0) {
                return [];
            }

            // Forzar a su área (ignora requestedAreaId)
            $requestedAreaId = $areaId;

            // Traemos dropdown (mismo comportamiento: sin "inserción obligatoria" extra)
            return $this->fetchUsersByAreaDropdown($requestedAreaId, 0);
        }

        // NORMAL: solo autoasignación
        return [
            [
                'id_user' => $currentUserId,
                'label'   => $this->getCurrentUserLabel($currentUserId) . ' (Autoasignación)'
            ]
        ];
    }

    // ==================================================
    // CREAR TAREA (SOPORTA MULTI-ASIGNACIÓN)
    // ==================================================
    public function createTaskFromPost(array $post, int $asignadoPor): array
    {
        $currentUserId     = $asignadoPor;
        $currentUserAreaId = (int) (session()->get('id_area') ?? 0);

        // ==================================================
        // 0) Scope real de permisos
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        // ==================================================
        // 1) Campos básicos
        // ==================================================
        $titulo      = trim((string) ($post['titulo'] ?? ''));
        $descripcion = trim((string) ($post['descripcion'] ?? ''));
        $idEstado    = (int) ($post['id_estado_tarea'] ?? 0);

        // Área pedida por UI (solo aplica si super/division)
        $idAreaPost  = (int) ($post['id_area'] ?? 0);

        // asignado_a puede venir int o array
        $assignees   = $this->normalizeAssignees($post['asignado_a'] ?? []);

        $fechaInicioRaw = (string) ($post['fecha_inicio'] ?? '');
        $fechaFinRaw    = (string) ($post['fecha_fin'] ?? '');

        // ==================================================
        // 2) Validaciones mínimas
        // ==================================================
        if ($titulo === '') {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }

        if ($idEstado <= 0) {
            return ['success' => false, 'error' => 'El estado es obligatorio.'];
        }

        // CREATE => fin obligatorio (mantiene tu lógica)
        $dateCheck = $this->validateDates($fechaInicioRaw, $fechaFinRaw, true);
        if (!$dateCheck['ok']) {
            return ['success' => false, 'error' => $dateCheck['error'] ?? 'Fechas inválidas.'];
        }

        /** @var \DateTimeImmutable $startDt */
        $startDt = $dateCheck['startDt'];
        /** @var \DateTimeImmutable $endDt */
        $endDt   = $dateCheck['endDt'];

        // ==================================================
        // 3) Determinar área final según scope (igual que ya tienes)
        // ==================================================
        $idAreaFinal = 0;

        if (($scope['mode'] ?? '') === 'super') {
            $idAreaFinal = $idAreaPost;
        } elseif (($scope['mode'] ?? '') === 'division') {
            $idAreaFinal = $idAreaPost;

            $divisionId = (int) ($scope['divisionId'] ?? 0);
            if ($divisionId <= 0) {
                return ['success' => false, 'error' => 'No se pudo determinar la división del jefe.'];
            }
            if ($idAreaFinal <= 0) {
                return ['success' => false, 'error' => 'Debes seleccionar un área.'];
            }

            if (method_exists($this->tareaModel, 'isAreaInDivision')) {
                if (!$this->tareaModel->isAreaInDivision($idAreaFinal, $divisionId)) {
                    return ['success' => false, 'error' => 'Área inválida para tu división.'];
                }
            }
        } else {
            // jefe de área o normal: área fija
            $idAreaFinal = (int) ($scope['areaId'] ?? 0);
            if ($idAreaFinal <= 0) {
                return ['success' => false, 'error' => 'No se pudo determinar tu área.'];
            }
        }

        if ($idAreaFinal <= 0) {
            return ['success' => false, 'error' => 'Área inválida.'];
        }

        // Scope self => forzar autoasignación
        if (($scope['mode'] ?? '') === 'self') {
            $assignees = [$currentUserId];
        }

        if (empty($assignees)) {
            return ['success' => false, 'error' => 'Debes seleccionar al menos un usuario para asignar.'];
        }

        // ✅ Validación de usuarios vs área
        $usuariosArea = $this->getUsersByArea($idAreaFinal);
        $idsArea      = array_map(static fn($u) => (int) ($u['id_user'] ?? 0), $usuariosArea);

        $allowSelfOutsideArea = in_array((string)($scope['mode'] ?? ''), ['division', 'super'], true);

        foreach ($assignees as $uid) {
            if ($allowSelfOutsideArea && (int)$uid === $currentUserId) {
                continue;
            }
            if (!in_array((int) $uid, $idsArea, true)) {
                return ['success' => false, 'error' => 'Uno o más usuarios no pertenecen al área seleccionada.'];
            }
        }

        // Prioridad automática (base)
        $idPrioridadAuto = $this->autoPriorityFromEnd($endDt);

        // ==================================================
        // 4) ✅ RECURRENCIA - solo si viene activada
        // ==================================================
        $recurrenceEnabled = (string)($post['recurrence_enabled'] ?? '0');
        $isRecurrence = in_array($recurrenceEnabled, ['1', 'true', 'on'], true);

        // ==================================================
        // 5) ✅ NO recurrencia => TU FLUJO ACTUAL EXACTO (NO TOCAR)
        // ==================================================
        if (!$isRecurrence) {

            $db = Database::connect();
            $db->transStart();

            try {
                $now = new \DateTimeImmutable('now', $this->tz());
                $createdAt = $now->format('Y-m-d H:i:s');

                $batchUid = null;
                if ($this->hasBatchUidColumn()) {
                    $batchUid = $this->uuidV4();
                }

                foreach ($assignees as $uid) {
                    $payload = [
                        'titulo'          => $titulo,
                        'descripcion'     => ($descripcion !== '' ? $descripcion : null),
                        'id_prioridad'    => $idPrioridadAuto,
                        'id_estado_tarea' => $idEstado,
                        'fecha_inicio'    => $this->toDbDateTime($startDt),
                        'fecha_fin'       => $this->toDbDateTime($endDt),
                        'id_area'         => $idAreaFinal,
                        'asignado_a'      => (int) $uid,
                        'asignado_por'    => $asignadoPor,
                        'created_at'      => $createdAt,
                    ];

                    if ($batchUid !== null) {
                        $payload['batch_uid'] = $batchUid;
                    }

                    $db->table('public.tareas')->insert($payload);
                }
            } catch (\Throwable $e) {
                $db->transRollback();
                return ['success' => false, 'error' => 'Error guardando la tarea.'];
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return ['success' => false, 'error' => 'No se pudo completar la transacción.'];
            }

            return ['success' => true, 'created' => count($assignees)];
        }

        // ==================================================
        // 6) ✅ FLUJO RECURRENCIA
        // ==================================================
        $repeatType  = trim((string)($post['repeat_type'] ?? ''));
        $weeksCount  = (int)($post['weeks_count'] ?? 0);
        $daysOfWeek  = $post['days_of_week'] ?? null;

        // ✅ NUEVO: repeat_until (YYYY-mm-dd) desde la vista
        $repeatUntilRaw = trim((string)($post['repeat_until'] ?? ''));
        $repeatUntilDt  = null;

        if ($repeatUntilRaw !== '') {
            // Normalizamos YYYY-mm-dd
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repeatUntilRaw)) {
                return ['success' => false, 'error' => 'La fecha "Repetir hasta" es inválida.'];
            }

            $repeatUntilDt = new \DateTimeImmutable($repeatUntilRaw . ' 23:59:59', $this->tz());

            // No permitir "hasta" antes del start
            if ($repeatUntilDt < $startDt) {
                return ['success' => false, 'error' => '"Repetir hasta" no puede ser anterior a la fecha inicio.'];
            }
        }

        if (!in_array($repeatType, ['daily', 'weekly'], true)) {
            return ['success' => false, 'error' => 'Tipo de recurrencia inválido.'];
        }

        // Si weeks_count vino vacío o mal y existe repeat_until => lo calculamos
        if (($weeksCount < 1 || $weeksCount > 52) && $repeatUntilDt) {
            $startDateOnly = new \DateTimeImmutable($startDt->format('Y-m-d') . ' 00:00:00', $this->tz());
            $endDateOnly   = new \DateTimeImmutable($repeatUntilDt->format('Y-m-d') . ' 00:00:00', $this->tz());

            $diffDays = (int) floor(($endDateOnly->getTimestamp() - $startDateOnly->getTimestamp()) / 86400);
            $weeksCount = (int) ceil(($diffDays + 1) / 7);

            // clamp
            if ($weeksCount < 1) $weeksCount = 1;
            if ($weeksCount > 52) $weeksCount = 52;
        }

        if ($weeksCount < 1 || $weeksCount > 52) {
            return ['success' => false, 'error' => 'Weeks_count inválido (1..52).'];
        }

        // Normalizar days_of_week
        $days = [];
        if ($repeatType === 'weekly') {
            if (is_array($daysOfWeek)) {
                foreach ($daysOfWeek as $d) {
                    $n = (int)$d;
                    if ($n >= 1 && $n <= 7) $days[] = $n;
                }
            } else {
                $parts = preg_split('/\s*,\s*/', (string)$daysOfWeek, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parts as $p) {
                    $n = (int)$p;
                    if ($n >= 1 && $n <= 7) $days[] = $n;
                }
            }

            $days = array_values(array_unique($days));
            if (empty($days)) {
                return ['success' => false, 'error' => 'Debes seleccionar al menos un día de la semana para recurrencia semanal.'];
            }
        }

        // Duración exacta (para mantener 3pm–4pm)
        $durationSeconds = max(0, $endDt->getTimestamp() - $startDt->getTimestamp());
        if ($durationSeconds <= 0) {
            return ['success' => false, 'error' => 'La duración de la tarea debe ser mayor a 0.'];
        }

        // Datos patrón (DATE + TIME)
        $startDate = $startDt->format('Y-m-d');
        $startTime = $startDt->format('H:i:s');
        $endTime   = $endDt->format('H:i:s');

        // UID de serie (tarea_recurrencia.batch_uid)
        $seriesUid = $this->uuidV4();

        $db = Database::connect();
        $db->transStart();

        try {
            $now = new \DateTimeImmutable('now', $this->tz());
            $createdAt = $now->format('Y-m-d H:i:s');

            // --------------------------------------------------
            // 6.1) Guardar patrón en tarea_recurrencia
            // --------------------------------------------------
            $daysPg = null;
            if ($repeatType === 'weekly') {
                $daysPg = '{' . implode(',', $days) . '}';
            }

            $recPayload = [
                'batch_uid'    => $seriesUid,
                'repeat_type'  => $repeatType,
                'weeks_count'  => $weeksCount,
                'days_of_week' => $daysPg,
                'start_date'   => $startDate,
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'active'       => true,
                'created_by'   => $currentUserId,
                'created_at'   => $createdAt,
            ];

            $db->table('public.tarea_recurrencia')->insert($recPayload);

            // --------------------------------------------------
            // 6.2) Generar ocurrencias
            // - tope: weeks_count * 7
            // - y si existe repeat_until => también corta por fecha
            // --------------------------------------------------
            $startBase = new \DateTimeImmutable($startDate . ' 00:00:00', $this->tz());
            $daysTotal = $weeksCount * 7;

            $createdRows = 0;

            for ($i = 0; $i < $daysTotal; $i++) {

                $day = $startBase->modify("+{$i} days");

                // ✅ Si hay repeat_until, cortamos cuando el día pase el límite
                if ($repeatUntilDt) {
                    $limitDay = new \DateTimeImmutable($repeatUntilDt->format('Y-m-d') . ' 00:00:00', $this->tz());
                    if ($day > $limitDay) {
                        break;
                    }
                }

                $isoDow = (int)$day->format('N');

                if ($repeatType === 'weekly' && !in_array($isoDow, $days, true)) {
                    continue;
                }

                $occStart = new \DateTimeImmutable($day->format('Y-m-d') . ' ' . $startTime, $this->tz());
                $occEnd   = $occStart->modify('+' . $durationSeconds . ' seconds');

                // No crear ocurrencias en días pasados
                $todayKey = $this->todayKey();
                if ($this->dayKey($occStart) < $todayKey) {
                    continue;
                }

                $prio = $this->autoPriorityFromEnd($occEnd);

                // batch_uid por ocurrencia (sirve para edición por lote)
                $occBatchUid = null;
                if ($this->hasBatchUidColumn()) {
                    $occBatchUid = $this->uuidV4();
                }

                foreach ($assignees as $uid) {
                    $payload = [
                        'titulo'          => $titulo,
                        'descripcion'     => ($descripcion !== '' ? $descripcion : null),
                        'id_prioridad'    => $prio,
                        'id_estado_tarea' => $idEstado,
                        'fecha_inicio'    => $this->toDbDateTime($occStart),
                        'fecha_fin'       => $this->toDbDateTime($occEnd),
                        'id_area'         => $idAreaFinal,
                        'asignado_a'      => (int)$uid,
                        'asignado_por'    => $asignadoPor,
                        'created_at'      => $createdAt,

                        // ✅ enlace a la serie (requiere columna recurrence_uid)
                        'recurrence_uid'  => $seriesUid,
                    ];

                    if ($occBatchUid !== null) {
                        $payload['batch_uid'] = $occBatchUid;
                    }

                    $db->table('public.tareas')->insert($payload);
                    $createdRows++;
                }
            }

            if ($createdRows <= 0) {
                throw new \RuntimeException('No se generaron ocurrencias. Revisa semanas/días/fecha base.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['success' => false, 'error' => 'Error guardando recurrencia: ' . $e->getMessage()];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'No se pudo completar la transacción.'];
        }

        return [
            'success'        => true,
            'recurrence_uid' => $seriesUid,
            'created'        => true,
        ];
    }

    // ==================================================
    // EVENTOS FULLCALENDAR
    // ==================================================
    public function getCalendarEvents(int $userId, string $scope, ?int $currentUserAreaId = null): array
    {
        $db = Database::connect();

        // ==================================================
        // Si no viene el área actual, intentamos leerla de sesión
        // ==================================================
        if ($currentUserAreaId === null) {
            $currentUserAreaId = (int) (session()->get('id_area') ?? 0);
        }

        // ==================================================
        // Normalizar scope
        // ==================================================
        $scope = trim(strtolower($scope));
        if ($scope === '') {
            $scope = 'mine';
        }

        // ==================================================
        // Resolver alcance real del usuario actual
        // - super
        // - division
        // - area
        // - self
        // ==================================================
        $realScope = $this->resolveAssignScope($userId, $currentUserAreaId);

        // ==================================================
        // Obtener IDs del equipo según el scope real
        // ==================================================
        $teamUserIds = $this->getTeamUserIdsFromScope($realScope, $userId);

        // ==================================================
        // Builder base
        // ==================================================
        $builder = $db->table('public.tareas t');

        $builder->select([
            't.id_tarea',
            't.titulo',
            't.descripcion',
            't.id_estado_tarea',
            't.fecha_inicio',
            't.fecha_fin',
            't.asignado_a',
            't.asignado_por',
            'p.nombre_prioridad',
            'e.nombre_estado',
            'ar.nombre_area',
            "ua.nombres || ' ' || ua.apellidos AS asignado_a_nombre",
            "up.nombres || ' ' || up.apellidos AS asignado_por_nombre",
        ], false);

        $builder->join('public.prioridad p', 'p.id_prioridad = t.id_prioridad');
        $builder->join('public.estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea');
        $builder->join('public."USER" ua', 'ua.id_user = t.asignado_a', 'left');
        $builder->join('public."USER" up', 'up.id_user = t.asignado_por', 'left');
        $builder->join('public.area ar', 'ar.id_area = t.id_area', 'left');

        // ==================================================
        // Aplicar filtro según scope seleccionado en la UI
        // ==================================================
        switch ($scope) {
            case 'assigned':
                // Actividades asignadas por mí
                $builder->where('t.asignado_por', $userId);
                break;

            case 'team':
                // Solo actividades del equipo/subordinados
                if (empty($teamUserIds)) {
                    return [];
                }

                $builder->whereIn('t.asignado_a', $teamUserIds);
                break;

            case 'all':
                // Mis actividades + equipo
                $ids = $teamUserIds;
                $ids[] = $userId;

                $ids = array_values(array_unique(array_filter(
                    array_map('intval', $ids),
                    static fn($x) => $x > 0
                )));

                if (empty($ids)) {
                    return [];
                }

                $builder->whereIn('t.asignado_a', $ids);
                break;

            case 'mine':
            default:
                // Solo mis actividades
                $builder->where('t.asignado_a', $userId);
                break;
        }

        // ==================================================
        // Ordenar resultados
        // ==================================================
        $rows = $builder
            ->orderBy('t.fecha_inicio', 'DESC')
            ->get()
            ->getResultArray();

        // ==================================================
        // Transformar a formato FullCalendar
        // ==================================================
        $events = [];

        foreach ($rows as $r) {
            $estadoId = (int) ($r['id_estado_tarea'] ?? 0);

            $events[] = [
                'id'    => (string) ($r['id_tarea'] ?? ''),
                'title' => (string) ($r['titulo'] ?? 'Actividad'),
                'start' => $r['fecha_inicio'],
                'end'   => $r['fecha_fin'],
                'extendedProps' => [
                    'descripcion'         => $r['descripcion'] ?? '',
                    'prioridad'           => $r['nombre_prioridad'] ?? '',
                    'estado'              => $r['nombre_estado'] ?? '',
                    'id_estado_tarea'     => $estadoId,

                    // Para saber desde frontend si ya está cerrada
                    'is_closed'           => in_array($estadoId, [
                        $this->estadoRealizada,
                        $this->estadoNoRealizada,
                        $this->estadoCancelada,
                    ], true),

                    'area'                => $r['nombre_area'] ?? '',
                    'asignado_a'          => (int) ($r['asignado_a'] ?? 0),
                    'asignado_a_nombre'   => $r['asignado_a_nombre'] ?? '',
                    'asignado_por'        => (int) ($r['asignado_por'] ?? 0),
                    'asignado_por_nombre' => $r['asignado_por_nombre'] ?? '',
                ],
            ];
        }

        return $events;
    }

    // ==================================================
    // MARCAR COMO REALIZADA (simple)
    // ==================================================
    public function markDone(int $taskId, int $currentUserId): array
    {
        $task = $this->tareaModel->find($taskId);

        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        if ((int) $task['asignado_a'] !== $currentUserId) {
            return ['success' => false, 'error' => 'No autorizado.'];
        }

        try {
            $now = new \DateTimeImmutable('now', $this->tz());

            $db = Database::connect();
            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update([
                    'id_estado_tarea' => $this->estadoRealizada,
                    'completed_at' => $now->format('Y-m-d H:i:sP'),
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error actualizando la tarea.'];
        }

        return ['success' => true];
    }

    // ==================================================
    // UPDATE / REASIGNAR (SOPORTA MULTI-UPDATE)
    // ==================================================
    public function updateTask(
        int $idTarea,
        array $data,
        int $currentUserId,
        int $currentUserAreaId
    ): array {
        /**
         * =========================================================
         * TareaService::updateTask()
         * =========================================================
         * OBJETIVO (según tu requerimiento):
         * ✅ fecha_inicio: SIEMPRE fija (no editable)
         * ✅ fecha_fin:
         *    - Usuario NO puede cambiar directo
         *    - Solo cambia directo si:
         *        a) Gerencia/super
         *        b) Supervisor DIRECTO de TODOS los asignados del batch (cadena jefe inmediato)
         *    - Si no puede cambiar directo y viene fecha_fin manipulada:
         *        -> NO se aplica, pero TAMPOCO se rompe el guardado normal.
         *
         * ✅ edit_count:
         *    - NO se incrementa aquí (porque el conteo es "solo si se aprueba")
         *    - Se incrementa en reviewBatch() cuando action=approve y review_action=date_change
         *    - (opcional) también en requestReviewChange() cuando supervisor aplica directo
         * =========================================================
         */

        $db = Database::connect();

        // ==================================================
        // 1) Cargar fila base
        // ==================================================
        $task = $this->tareaModel->find($idTarea);
        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        // ==================================================
        // 2) Scope real (super/division/area/self)
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        // ==================================================
        // 3) Autorización base (incluye jefes por alcance)
        // ==================================================
        $isAssignee = ((int)($task['asignado_a'] ?? 0) === $currentUserId);
        $isCreator  = ((int)($task['asignado_por'] ?? 0) === $currentUserId);
        $isSuper    = ($currentUserAreaId === 1);

        // Jefaturas dentro de alcance (si tu canEditTask() lo permite)
        $isBossAllowed = $this->canEditTask($task, $currentUserId, $scope);

        if (!$isAssignee && !$isCreator && !$isSuper && !$isBossAllowed) {
            return ['success' => false, 'error' => 'No autorizado.'];
        }

        // ==================================================
        // 4) Regla final por alcance/estado (si canEditTask ya bloquea estados cerrados, etc.)
        // ==================================================
        if (!$this->canEditTask($task, $currentUserId, $scope)) {
            return ['success' => false, 'error' => 'No tienes permisos para editar esta tarea.'];
        }

        // ==================================================
        // 5) Grupo batch
        // ==================================================
        $groupRows = $this->getBatchRowsForTask($task);

        // batch_uid consistente si existe
        $batchUid = $this->ensureBatchUidForGroup($groupRows);

        // ==================================================
        // 6) Asignados actuales (del grupo)
        // ==================================================
        $existingAssignees = [];
        foreach ($groupRows as $r) {
            $existingAssignees[] = (int)($r['asignado_a'] ?? 0);
        }
        $existingAssignees = array_values(array_unique(array_filter($existingAssignees)));

        // ==================================================
        // 7) Asignados entrantes (normalizados)
        // ==================================================
        $incomingAssignees = $this->normalizeAssignees($data['asignado_a'] ?? []);

        // ✅ Quien puede gestionar asignados/campos globales
        $isBoss = in_array((string)($scope['mode'] ?? 'self'), ['division', 'area'], true);
        $canManageAssignees = ($isCreator || $isSuper || $isBoss);

        if (!$canManageAssignees) {
            // Seguridad: usuario normal no puede reasignar ni tocar otros usuarios
            $incomingAssignees = [$currentUserId];
        } else {
            if (empty($incomingAssignees)) {
                return ['success' => false, 'error' => 'Debes seleccionar al menos un usuario.'];
            }
        }

        // ==================================================
        // 8) Determinar área final según scope
        // ==================================================
        $idAreaPost  = (int)($data['id_area'] ?? ($task['id_area'] ?? 0));
        $idAreaFinal = 0;

        if (($scope['mode'] ?? '') === 'super') {
            // Gerencia puede escoger cualquier área
            $idAreaFinal = $idAreaPost;
        } elseif (($scope['mode'] ?? '') === 'division') {
            // Jefe de división puede escoger un área, pero debe pertenecer a su división
            $idAreaFinal = $idAreaPost;

            $divisionId = (int)($scope['divisionId'] ?? 0);
            if ($divisionId <= 0) return ['success' => false, 'error' => 'No se pudo determinar la división del jefe.'];
            if ($idAreaFinal <= 0) return ['success' => false, 'error' => 'Debes seleccionar un área.'];

            if (method_exists($this->tareaModel, 'isAreaInDivision')) {
                if (!$this->tareaModel->isAreaInDivision($idAreaFinal, $divisionId)) {
                    return ['success' => false, 'error' => 'Área inválida para tu división.'];
                }
            }
        } else {
            // Jefe de área o usuario normal: área fija
            $idAreaFinal = (int)($scope['areaId'] ?? ($task['id_area'] ?? 0));
        }

        if ($idAreaFinal <= 0) {
            return ['success' => false, 'error' => 'Área inválida.'];
        }

        // ==================================================
        // 9) Validar usuarios vs área (solo si puede gestionar)
        // ==================================================
        if ($canManageAssignees) {
            $usuariosArea = $this->getUsersByArea($idAreaFinal);
            $idsArea      = array_map(static fn($u) => (int)($u['id_user'] ?? 0), $usuariosArea);

            // jefe division / super puede autoasignarse aunque no pertenezca al área elegida
            $allowSelfOutsideArea = in_array((string)($scope['mode'] ?? ''), ['division', 'super'], true);

            foreach ($incomingAssignees as $uid) {
                if ($allowSelfOutsideArea && (int)$uid === $currentUserId) {
                    continue;
                }
                if (!in_array((int)$uid, $idsArea, true)) {
                    return ['success' => false, 'error' => 'Uno o más usuarios no pertenecen al área seleccionada.'];
                }
            }
        }

        // ==================================================
        // 10) ✅ BLOQUE CRÍTICO: REGLA FECHAS (CADENA JEFE INMEDIATO)
        // ==================================================

        // 10.1) fecha_inicio SIEMPRE fija (blindaje)
        // Si viene en POST, la ignoramos.
        if (array_key_exists('fecha_inicio', $data)) {
            $data['fecha_inicio'] = (string)($task['fecha_inicio'] ?? '');
        }

        // 10.2) Detectar si el POST intenta tocar fecha_fin
        $hasEndPost = array_key_exists('fecha_fin', $data);

        // 10.3) Validar “supervisor directo de TODOS” los asignados que quedarán
        // - Si el usuario puede gestionar asignados, validamos contra incomingAssignees (lo final).
        // - Si NO puede, validamos contra los que ya existen (pero igual NO podrá cambiar fecha_fin, porque no es supervisor directo en modo self).
        $assigneesToCheck = $existingAssignees;
        if ($canManageAssignees) {
            $assigneesToCheck = $incomingAssignees;
        }

        $isDirectSupervisorOfAll = false;

        if (!empty($assigneesToCheck)) {
            if ($isSuper) {
                // Gerencia siempre pasa
                $isDirectSupervisorOfAll = true;
            } else {
                $ok = true;

                foreach ($assigneesToCheck as $assigneeId) {
                    $rowSup = $db->query(
                        'SELECT id_supervisor
                       FROM public."USER"
                      WHERE id_user = ?
                      LIMIT 1',
                        [(int)$assigneeId]
                    )->getRowArray();

                    $supId = (int)($rowSup['id_supervisor'] ?? 0);

                    // ✅ Cadena: solo jefe inmediato
                    if (!($supId > 0 && $supId === $currentUserId)) {
                        $ok = false;
                        break;
                    }
                }

                $isDirectSupervisorOfAll = $ok;
            }
        }

        // 10.4) Permiso real para cambio DIRECTO de fechas
        $canDirectChangeDates = ($isSuper || $isDirectSupervisorOfAll);

        // ==================================================
        // 11) Construir update común (campos compartidos por el batch)
        // ==================================================
        $commonUpdate = [];

        // Área: solo si puede gestionar o es super
        if ($canManageAssignees || $isSuper) {
            $commonUpdate['id_area'] = $idAreaFinal;
        }

        // Título
        if (($canManageAssignees || $isSuper) && isset($data['titulo'])) {
            $commonUpdate['titulo'] = trim((string)$data['titulo']);
        }

        // Descripción
        if (($canManageAssignees || $isSuper) && array_key_exists('descripcion', $data)) {
            $desc = trim((string)$data['descripcion']);
            $commonUpdate['descripcion'] = ($desc !== '' ? $desc : null);
        }

        // Estado (si lo usas)
        $estadoPost = null;
        if (($canManageAssignees || $isSuper) && isset($data['id_estado_tarea'])) {
            $estadoPost = (int)$data['id_estado_tarea'];
        }

        // ==================================================
        // 12) ✅ FECHA FIN:
        // - Solo se aplica si canDirectChangeDates
        // - Si NO tiene permiso y viene en POST:
        //     -> se ignora sin romper guardado (no error)
        // - edit_count NO se incrementa aquí (conteo solo si se aprueba)
        // ==================================================
        if ($hasEndPost) {

            $incomingEndRaw = trim((string)($data['fecha_fin'] ?? ''));

            // Si viene vacío y se pretendía cambiar, mantenemos tu regla de "obligatoria"
            // PERO: si el usuario NO tiene permiso, no lo vamos a considerar un error aquí.
            if ($incomingEndRaw === '') {
                if ($canDirectChangeDates) {
                    return ['success' => false, 'error' => 'La fecha final es obligatoria.'];
                } else {
                    // No tiene permiso para tocar fecha_fin -> ignoramos
                    unset($data['fecha_fin']);
                    $hasEndPost = false;
                }
            }

            if ($hasEndPost && $canDirectChangeDates) {

                $incomingEndDt = $this->parseLocalDateTime($incomingEndRaw);
                if (!$incomingEndDt) {
                    return ['success' => false, 'error' => 'La fecha final es obligatoria.'];
                }

                // Fecha inicio fija desde DB
                $startRaw = (string)($task['fecha_inicio'] ?? '');
                $startDt  = $this->parseLocalDateTime($startRaw);

                if (!$startDt) {
                    return ['success' => false, 'error' => 'No se pudo leer la fecha de inicio actual.'];
                }

                // Validaciones:
                // - fin >= inicio
                // - fin no en pasado (por día)
                $todayKey = $this->todayKey();
                if ($this->dayKey($incomingEndDt) < $todayKey) {
                    return ['success' => false, 'error' => 'La fecha final no puede ser anterior a la fecha actual.'];
                }

                if ($incomingEndDt < $startDt) {
                    return ['success' => false, 'error' => 'La fecha final no puede ser menor a la fecha de inicio.'];
                }

                // ✅ Aplicar SOLO fecha_fin (fecha_inicio NO se toca)
                $commonUpdate['fecha_fin'] = $this->toDbDateTime($incomingEndDt);

                // Prioridad automática
                $commonUpdate['id_prioridad'] = $this->autoPriorityFromEnd(
                    $incomingEndDt,
                    (int)($task['id_prioridad'] ?? 0)
                );
            }

            if ($hasEndPost && !$canDirectChangeDates) {
                /**
                 * ✅ Usuario NO puede cambiar fecha fin directo.
                 * - No devolvemos error para no romper edición de título/desc
                 * - Ignoramos el intento
                 * - El cambio real debe ir por requestReviewChange('date_change') con motivo
                 */
                unset($data['fecha_fin']);
                $hasEndPost = false;
            }
        }

        // ==================================================
        // 13) Cambios de asignados: keep / add / cancel
        // ==================================================
        $toKeep   = array_values(array_intersect($existingAssignees, $incomingAssignees));
        $toAdd    = array_values(array_diff($incomingAssignees, $existingAssignees));
        $toCancel = array_values(array_diff($existingAssignees, $incomingAssignees));

        if (!$canManageAssignees) {
            $toKeep   = [$currentUserId];
            $toAdd    = [];
            $toCancel = [];
        }

        // ==================================================
        // 14) Valores finales (para inserts)
        // ==================================================
        $tituloFinal = (string)($commonUpdate['titulo'] ?? ($task['titulo'] ?? ''));
        $descFinal   = array_key_exists('descripcion', $commonUpdate)
            ? $commonUpdate['descripcion']
            : ($task['descripcion'] ?? null);

        $estadoFinal = (int)(($estadoPost ?? ($task['id_estado_tarea'] ?? 0)) ?: ($task['id_estado_tarea'] ?? 0));

        $fechaIniFinal  = (string)($task['fecha_inicio'] ?? '');
        $fechaFinFinal  = (string)($commonUpdate['fecha_fin'] ?? ($task['fecha_fin'] ?? ''));
        $prioridadFinal = (int)($commonUpdate['id_prioridad'] ?? ($task['id_prioridad'] ?? 0));
        $areaFinal      = (int)($commonUpdate['id_area'] ?? ($task['id_area'] ?? $idAreaFinal));

        // ==================================================
        // 15) Transacción: updates + inserts + cancelados
        // ==================================================
        $db->transStart();

        try {
            $now    = new \DateTimeImmutable('now', $this->tz());
            $nowStr = $now->format('Y-m-d H:i:s');

            $table = $db->table('public.tareas');

            // --- A) Actualizar existentes (keep + cancel)
            foreach ($groupRows as $r) {
                $rowId    = (int)($r['id_tarea'] ?? 0);
                $assignee = (int)($r['asignado_a'] ?? 0);

                if ($rowId <= 0 || $assignee <= 0) continue;

                // Cancelar removidos (solo si puede gestionar)
                if ($canManageAssignees && in_array($assignee, $toCancel, true)) {
                    $cancelUpdate = $commonUpdate;

                    $cancelUpdate['id_estado_tarea'] = $this->estadoCancelada; // 5
                    $cancelUpdate['completed_at']    = null;

                    if ($this->hasBatchUidColumn() && $batchUid) {
                        $cancelUpdate['batch_uid'] = $batchUid;
                    }

                    $table->where('id_tarea', $rowId)->update($cancelUpdate);
                    continue;
                }

                // Mantener => actualizar
                if (in_array($assignee, $toKeep, true)) {
                    $rowUpdate = $commonUpdate;

                    // Estado global (si se envió)
                    if ($estadoPost !== null) {
                        $rowUpdate['id_estado_tarea'] = (int)$estadoPost;

                        if ((int)$estadoPost === $this->estadoRealizada) {
                            $rowUpdate['completed_at'] = $nowStr;
                        } else {
                            $rowUpdate['completed_at'] = null;
                        }
                    }

                    if ($this->hasBatchUidColumn() && $batchUid) {
                        $rowUpdate['batch_uid'] = $batchUid;
                    }

                    if (!empty($rowUpdate)) {
                        $table->where('id_tarea', $rowId)->update($rowUpdate);
                    }
                }
            }

            // --- B) Insertar nuevos asignados
            if ($canManageAssignees && !empty($toAdd)) {
                foreach ($toAdd as $uid) {

                    $insert = [
                        'titulo'          => $tituloFinal,
                        'descripcion'     => $descFinal,
                        'id_prioridad'    => $prioridadFinal,
                        'id_estado_tarea' => $estadoFinal,
                        'fecha_inicio'    => $fechaIniFinal,  // ✅ fija
                        'fecha_fin'       => $fechaFinFinal,  // ✅ puede venir del commonUpdate si supervisor cambió directo
                        'id_area'         => $areaFinal,
                        'asignado_a'      => (int)$uid,
                        'asignado_por'    => (int)($task['asignado_por'] ?? $currentUserId),
                        'created_at'      => (string)($task['created_at'] ?? $nowStr),
                    ];

                    if ($this->hasBatchUidColumn() && $batchUid) {
                        $insert['batch_uid'] = $batchUid;
                    }

                    // ✅ No tocamos edit_count aquí (solo se cuenta cuando se aprueba)
                    // Si necesitas heredar valor existente sin incrementarlo:
                    if ($this->taskColumnExists('edit_count')) {
                        $insert['edit_count'] = (int)($task['edit_count'] ?? 0);
                    }

                    $insert['completed_at'] = ($estadoFinal === $this->estadoRealizada) ? $nowStr : null;

                    // Limpieza de revisión (seguridad)
                    if ($this->hasReviewFlowColumns()) {
                        $insert['review_requested_state']     = null;
                        $insert['review_requested_at']        = null;
                        $insert['review_action']              = null;
                        $insert['review_reason']              = null;
                        $insert['review_requested_by']        = null;
                        $insert['review_requested_fecha_fin'] = null;
                        $insert['review_previous_state']      = null;
                        $insert['approved_by']                = null;
                        $insert['approved_at']                = null;
                    }

                    $table->insert($insert);
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['success' => false, 'error' => 'Error actualizando la tarea.'];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'No se pudo completar la transacción.'];
        }

        return [
            'success'   => true,
            'updated'   => count($toKeep),
            'added'     => count($toAdd),
            'canceled'  => count($toCancel),
            'batch_uid' => ($this->hasBatchUidColumn() ? (string)($batchUid ?? '') : ''),
        ];
    }

    // ==================================================
    // CATÁLOGOS / PASSTHROUGH
    // ==================================================
    public function getDivisionByUser(int $idUser): ?array
    {
        return method_exists($this->tareaModel, 'getDivisionByUser')
            ? $this->tareaModel->getDivisionByUser($idUser)
            : null;
    }

    public function getAreasByDivision(int $idDivision): array
    {
        return method_exists($this->tareaModel, 'getAreasByDivision')
            ? $this->tareaModel->getAreasByDivision($idDivision)
            : [];
    }

    /**
     * ✅ Unificado: ahora siempre usa el "motor" central.
     * - Si existe el método del Model -> lo usa
     * - Si no -> fallback a query directa (por cargo.id_area)
     *
     * ⚠️ Este método retorna "RAW" (no dropdown), porque lo usas para validaciones.
     */
    public function getUsersByArea(int $idArea): array
    {
        return $this->fetchUsersByAreaRaw($idArea);
    }

    public function getPrioridades(): array
    {
        return method_exists($this->tareaModel, 'getPrioridades')
            ? $this->tareaModel->getPrioridades()
            : [];
    }

    public function getEstadosTarea(): array
    {
        return method_exists($this->tareaModel, 'getEstadosTarea')
            ? $this->tareaModel->getEstadosTarea()
            : [];
    }
    /**
     * ==================================================
     * HELPERS: VENCIMIENTO / ALERTAS
     * ==================================================
     */

    /**
     * Determina si una tarea ya venció.
     *
     * Reglas:
     * - Solo cuenta si fecha_fin existe
     * - Se compara contra "ahora" en zona local
     */
    private function isTaskExpired(?string $fechaFinRaw): bool
    {
        $endDt = $this->parseLocalDateTime($fechaFinRaw);
        if (!$endDt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', $this->tz());
        return $endDt < $now;
    }

    /**
     * Devuelve:
     * -1 => venció
     *  0 => vence hoy
     *  1 => vence mañana
     *  n => faltan n días
     *
     * Comparación por día calendario local.
     */
    private function getDaysUntilDue(?string $fechaFinRaw): ?int
    {
        $endDt = $this->parseLocalDateTime($fechaFinRaw);
        if (!$endDt) {
            return null;
        }

        $today  = (new \DateTimeImmutable('now', $this->tz()))->setTime(0, 0, 0);
        $endDay = $endDt->setTime(0, 0, 0);

        return (int) floor(($endDay->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /**
     * Marca automáticamente como NO REALIZADA
     * todas las tareas vencidas activas.
     *
     * ⚠️ Solo toca tareas con estado:
     * - 1 Pendiente
     * - 2 En proceso
     *
     * NO toca:
     * - 3 Realizada
     * - 4 No realizada
     * - 5 Cancelada
     * - 6 En revisión
     */
    private function markExpiredTasksAsNoRealizada(): int
    {
        try {
            // ==================================================
            // 1) Conexión y hora actual en zona local
            // ==================================================
            $db    = Database::connect();
            $nowDt = new \DateTimeImmutable('now', $this->tz());
            $now   = $nowDt->format('Y-m-d H:i:s');

            // ==================================================
            // 2) Ventana de gracia
            // --------------------------------------------------
            // Esta ventana evita que una tarea recién devuelta
            // desde revisión al estado anterior sea marcada
            // inmediatamente como "No realizada" antes de que
            // el frontend abra el modal para actualizar la hora.
            //
            // Ejemplo:
            // - supervisor cancela solicitud
            // - la tarea vuelve a Pendiente / En proceso
            // - pero su fecha_fin ya quedó vencida
            // - si se recarga demasiado rápido, esta función
            //   podría capturarla enseguida
            //
            // Con esta gracia de 2 minutos, si approved_at es
            // reciente, la tarea NO se toca todavía.
            // ==================================================
            $graceCutoff = $nowDt->modify('-2 minutes')->format('Y-m-d H:i:s');

            // ==================================================
            // 3) Builder base
            // ==================================================
            $builder = $db->table('public.tareas');

            // ==================================================
            // 4) Solo tareas ACTIVAS:
            // - Pendiente
            // - En proceso
            //
            // NO toca:
            // - Realizada
            // - No realizada
            // - Cancelada
            // - En revisión
            // ==================================================
            $builder->whereIn('id_estado_tarea', [
                $this->estadoPendiente,
                $this->estadoEnProceso,
            ]);

            // ==================================================
            // 5) Solo tareas que tienen fecha_fin
            //    y que ya vencieron respecto a "ahora"
            // ==================================================
            $builder->where('fecha_fin IS NOT NULL', null, false);
            $builder->where('fecha_fin <', $now);

            // ==================================================
            // 6) Blindaje extra con approved_at
            // --------------------------------------------------
            // Si la tabla tiene approved_at, entonces:
            // - si approved_at es NULL => sí puede marcarse
            // - si approved_at es viejo (< graceCutoff) => sí puede marcarse
            // - si approved_at es muy reciente => NO la toca aún
            //
            // Esto protege las tareas recién procesadas
            // desde revisión.
            // ==================================================
            if ($this->taskColumnExists('approved_at')) {
                $builder->groupStart();
                $builder->where('approved_at IS NULL', null, false);
                $builder->orWhere('approved_at <', $graceCutoff);
                $builder->groupEnd();
            }

            // ==================================================
            // 7) Payload de cierre automático
            // ==================================================
            $payload = [
                'id_estado_tarea' => $this->estadoNoRealizada,
                'completed_at'    => $now,
            ];

            // ==================================================
            // 8) Si existen columnas de revisión base,
            //    limpiar por seguridad
            // ==================================================
            if ($this->hasReviewFlowColumns()) {
                $payload['review_requested_state'] = null;
                $payload['review_requested_at']    = null;
                $payload['approved_by']            = null;
                $payload['approved_at']            = null;
            }

            // ==================================================
            // 9) Si existen columnas extendidas de revisión,
            //    también limpiar por seguridad
            // ==================================================
            if ($this->hasExtendedReviewColumns()) {
                $payload['review_action']              = null;
                $payload['review_reason']              = null;
                $payload['review_requested_by']        = null;
                $payload['review_requested_fecha_fin'] = null;
                $payload['review_previous_state']      = null;
            }

            // ==================================================
            // 10) Ejecutar update masivo
            // ==================================================
            $builder->update($payload);

            // ==================================================
            // 11) Retornar número de filas afectadas
            // ==================================================
            return (int) $db->affectedRows();
        } catch (\Throwable $e) {
            // ==================================================
            // 12) Si ocurre un error, devolvemos 0 para no romper
            //    el flujo de gestión
            // ==================================================
            return 0;
        }
    }
    /**
     * Construye alertas para el modal de vencimiento.
     *
     * Solo muestra alertas de tareas ACTIVAS asignadas al usuario:
     * - vence hoy
     * - vence mañana
     *
     * NO muestra:
     * - cerradas
     * - en revisión
     * - vencidas ya convertidas a no realizada
     */
    private function buildDueAlertsForTasks(array $tasks, int $currentUserId): array
    {
        $alerts = [];

        foreach ($tasks as $t) {
            $estadoId  = (int) ($t['id_estado_tarea'] ?? 0);
            $asignadoA = (int) ($t['asignado_a'] ?? 0);

            // Solo tareas activas del usuario
            if ($asignadoA !== $currentUserId) {
                continue;
            }

            if (!in_array($estadoId, [$this->estadoPendiente, $this->estadoEnProceso], true)) {
                continue;
            }

            $days = $this->getDaysUntilDue((string) ($t['fecha_fin'] ?? ''));

            if ($days === null) {
                continue;
            }

            // Solo hoy o mañana
            if (!in_array($days, [0, 1], true)) {
                continue;
            }

            $alerts[] = [
                'id_tarea'    => (int) ($t['id_tarea'] ?? 0),
                'titulo'      => (string) ($t['titulo'] ?? 'Actividad'),
                'nombre_area' => (string) ($t['nombre_area'] ?? '-'),
                'fecha_fin'   => (string) ($t['fecha_fin'] ?? ''),
                'days_left'   => $days,

                // ✅ ESTO FALTABA
                'due_label'   => ($days === 0 ? 'hoy' : 'mañana'),

                'mensaje'     => $days === 0
                    ? 'Esta tarea vence hoy. Se recomienda cambiar la fecha si no lograrás completarla; de lo contrario, al vencer quedará como No realizada.'
                    : 'Esta tarea vence mañana. Se recomienda revisar la fecha y reprogramarla si es necesario.',
            ];
        }

        return $alerts;
    }
    // ==================================================
    // GESTIÓN (mis tareas / asignadas / equipo / revisión)
    // ==================================================
    public function getTasksForManagement(int $idUser, ?int $currentUserAreaId = null): array
    {
        // ==================================================
        // 0) Contexto del usuario actual
        // ==================================================
        if ($currentUserAreaId === null) {
            $currentUserAreaId = (int) (session()->get('id_area') ?? 0);
        }

        // ==================================================
        // 0.1) Auto-marcar vencidas como No realizada
        // ==================================================
        $expiredUpdated = $this->markExpiredTasksAsNoRealizada();

        // ==================================================
        // 1) Scope real del usuario logueado
        // ==================================================
        $scope = $this->resolveAssignScope($idUser, $currentUserAreaId);

        // ==================================================
        // 2) Usuarios del equipo según scope
        // ==================================================
        $teamUserIds = $this->getTeamUserIdsFromScope($scope, $idUser);

        $db = Database::connect();

        // ==================================================
        // 3) Detectar si existen columnas del flujo de revisión
        // ==================================================
        $hasReviewCols = $this->hasReviewFlowColumns();
        $hasExtendedReviewCols = method_exists($this, 'hasExtendedReviewColumns')
            ? $this->hasExtendedReviewColumns()
            : false;

        /**
         * ==================================================
         * Helper: Builder base con SELECT + JOINs
         * ==================================================
         */
        $baseSelect = function () use ($db, $hasReviewCols, $hasExtendedReviewCols) {
            $b = $db->table('public.tareas t');

            // --------------------------------------------------
            // Campos base
            // --------------------------------------------------
            $select = [
                't.id_tarea',
                't.titulo',
                't.descripcion',
                't.fecha_inicio',
                't.fecha_fin',

                // ✅ NUEVO: para detectar tareas repetidas/serie
                't.batch_uid',
                't.recurrence_uid',

                // ✅ NUEVO: evidencia
                't.has_evidence',
                't.evidence_url',
                't.evidence_note',

                't.id_estado_tarea',
                't.asignado_a',
                't.asignado_por',
                't.id_area',
                'p.nombre_prioridad',
                'e.nombre_estado',
                'ar.nombre_area',
                'ar.id_division',
                'ua.nombres || \' \' || ua.apellidos AS asignado_a_nombre',
                'up.nombres || \' \' || up.apellidos AS asignado_por_nombre',
                'ua.id_supervisor AS asignado_a_supervisor',
            ];

            // --------------------------------------------------
            // Campos del flujo de revisión base
            // --------------------------------------------------
            if ($hasReviewCols) {
                $select[] = 't.edit_count';
                $select[] = 't.review_requested_state';
                $select[] = 't.review_requested_at';
                $select[] = 'er.nombre_estado AS nombre_estado_solicitado';
            } else {
                $select[] = '0 AS edit_count';
                $select[] = 'NULL::int AS review_requested_state';
                $select[] = 'NULL::timestamp AS review_requested_at';
                $select[] = 'NULL::text AS nombre_estado_solicitado';
            }

            // --------------------------------------------------
            // Campos extendidos del flujo de revisión
            // --------------------------------------------------
            if ($hasExtendedReviewCols) {
                $select[] = 't.review_action';
                $select[] = 't.review_reason';
                $select[] = 't.review_requested_fecha_fin';
                $select[] = 't.review_requested_by';
                $select[] = 'ur.nombres || \' \' || ur.apellidos AS review_requested_by_nombre';
                $select[] = 't.review_previous_state';
                $select[] = 'ep.nombre_estado AS nombre_estado_anterior';
            } else {
                $select[] = 'NULL::text AS review_action';
                $select[] = 'NULL::text AS review_reason';
                $select[] = 'NULL::timestamp AS review_requested_fecha_fin';
                $select[] = 'NULL::int AS review_requested_by';
                $select[] = 'NULL::text AS review_requested_by_nombre';
                $select[] = 'NULL::int AS review_previous_state';
                $select[] = 'NULL::text AS nombre_estado_anterior';
            }

            $b->select($select, false);

            // --------------------------------------------------
            // Joins base
            // --------------------------------------------------
            $b->join('public.prioridad p', 'p.id_prioridad = t.id_prioridad');
            $b->join('public.estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea');
            $b->join('public.area ar', 'ar.id_area = t.id_area', 'left');
            $b->join('public."USER" ua', 'ua.id_user = t.asignado_a', 'left');
            $b->join('public."USER" up', 'up.id_user = t.asignado_por', 'left');

            // Estado solicitado
            if ($hasReviewCols) {
                $b->join('public.estado_tarea er', 'er.id_estado_tarea = t.review_requested_state', 'left');
            }

            // Usuario que solicitó la revisión
            if ($hasExtendedReviewCols) {
                $b->join('public."USER" ur', 'ur.id_user = t.review_requested_by', 'left');
                $b->join('public.estado_tarea ep', 'ep.id_estado_tarea = t.review_previous_state', 'left');
            }

            return $b;
        };

        // ==================================================
        // 4) Mis tareas
        // ==================================================
        $misTareas = $baseSelect()
            ->where('t.asignado_a', $idUser)
            ->orderBy('t.fecha_inicio', 'DESC')
            ->get()
            ->getResultArray();

        // ==================================================
        // 4.1) ✅ Mis tareas diarias (SOLO repetidas del día)
        // ==================================================
        $misDiarias = [
            'activas'  => [],
            'revision' => [],
            'cerradas' => [],
        ];

        // Rango HOY en TZ del sistema (America/Guayaquil)
        $todayStart = (new \DateTimeImmutable('now', $this->tz()))->setTime(0, 0, 0);
        $todayEnd   = (new \DateTimeImmutable('now', $this->tz()))->setTime(23, 59, 59);

        foreach ($misTareas as $t) {

            // ✅ 1) Debe ser tarea repetida/serie
            $recUid = trim((string)($t['recurrence_uid'] ?? ''));
            $batUid = trim((string)($t['batch_uid'] ?? ''));

            if ($recUid === '' && $batUid === '') {
                continue;
            }

            // ✅ 2) Debe caer HOY por fecha_inicio
            $rawStart = (string)($t['fecha_inicio'] ?? '');
            if ($rawStart === '') {
                continue;
            }

            try {
                // Viene como timestamptz: "2026-03-03 08:00:00-05"
                $startDt = new \DateTimeImmutable($rawStart);
            } catch (\Throwable $e) {
                continue;
            }

            if ($startDt < $todayStart || $startDt > $todayEnd) {
                continue;
            }

            // ✅ 3) Clasificación por estado (según tus IDs)
            $estado = (int)($t['id_estado_tarea'] ?? 0);

            // Activas: Pendiente (1) o En proceso (2)
            if (in_array($estado, [$this->estadoPendiente, $this->estadoEnProceso], true)) {
                $misDiarias['activas'][] = $t;
                continue;
            }

            // En revisión: (6)
            if ($estado === $this->estadoEnRevision) {
                $misDiarias['revision'][] = $t;
                continue;
            }

            // Cerradas: Realizada (3) / No realizada (4) / Cancelada (5)
            $misDiarias['cerradas'][] = $t;
        }

        // ==================================================
        // 5) Tareas asignadas por mí
        // ==================================================
        $tareasAsignadas = $baseSelect()
            ->where('t.asignado_por', $idUser)
            ->where('t.asignado_a <>', $idUser)
            ->orderBy('t.fecha_inicio', 'DESC')
            ->get()
            ->getResultArray();

        // ==================================================
        // 6) Tareas de mi equipo
        // ==================================================
        $tareasEquipo = [];

        if (!empty($teamUserIds)) {
            $b = $baseSelect()
                ->whereIn('t.asignado_a', $teamUserIds);

            if (($scope['mode'] ?? '') === 'area') {
                $areaId = (int) ($scope['areaId'] ?? 0);
                if ($areaId > 0) {
                    $b->where('t.id_area', $areaId);
                }
            }

            if (($scope['mode'] ?? '') === 'division') {
                $divisionId = (int) ($scope['divisionId'] ?? 0);
                if ($divisionId > 0) {
                    $b->where('ar.id_division', $divisionId);
                }
            }

            $tareasEquipo = $b
                ->orderBy('t.fecha_inicio', 'DESC')
                ->get()
                ->getResultArray();
        }

        // ==================================================
        // 7) Pendientes de revisión
        // ==================================================
        $pendientesRevision = [];

        if ($hasReviewCols) {
            $pendientesBuilder = $baseSelect()
                ->where('t.id_estado_tarea', $this->estadoEnRevision);

            // --------------------------------------------------
            // REGLA NUEVA:
            // - super ve todo
            // - los demás solo ven solicitudes de sus subordinados directos
            // --------------------------------------------------
            if (($scope['mode'] ?? '') !== 'super') {
                $pendientesBuilder->where('ua.id_supervisor', $idUser);
            }

            $pendientesRevision = $pendientesBuilder
                ->orderBy('t.review_requested_at', 'DESC')
                ->get()
                ->getResultArray();
        }

        // ==================================================
        // 8) Alertas de vencimiento
        // ==================================================
        $dueAlerts = $this->buildDueAlertsForTasks($misTareas, $idUser);

        // ==================================================
        // 9) Respuesta
        // ==================================================
        return [
            'assignScope'        => $scope,
            'misTareas'          => $misTareas,

            // ✅ NUEVO
            'misDiarias'         => $misDiarias,

            'tareasAsignadas'    => $tareasAsignadas,
            'tareasEquipo'       => $tareasEquipo,
            'pendientesRevision' => $pendientesRevision,
            'hasReviewFlow'      => $hasReviewCols,
            'expiredUpdated'     => $expiredUpdated,
            'dueAlerts'          => $dueAlerts,
        ];
    }
    /**
     * Devuelve IDs subordinados según scope:
     * - super    => todos activos menos yo
     * - division => usuarios de mi división menos yo
     * - area     => usuarios de mi área menos yo
     * - self     => []
     */
    private function getTeamUserIdsFromScope(array $scope, int $currentUserId): array
    {
        $mode = (string) ($scope['mode'] ?? 'self');

        if ($mode === 'super') {
            return $this->getAllActiveUserIdsExcept($currentUserId);
        }

        if ($mode === 'division') {
            $divisionId = (int) ($scope['divisionId'] ?? 0);
            if ($divisionId <= 0) return [];

            if (!method_exists($this->tareaModel, 'getUsersByDivision')) {
                return [];
            }

            $rows = $this->tareaModel->getUsersByDivision($divisionId);
            $ids  = array_map(static fn($u) => (int) ($u['id_user'] ?? 0), $rows);

            return array_values(array_unique(array_filter($ids, static fn($x) => $x > 0 && $x !== $currentUserId)));
        }

        if ($mode === 'area') {
            $areaId = (int) ($scope['areaId'] ?? 0);
            if ($areaId <= 0) return [];

            $rows = $this->getUsersByArea($areaId);
            $ids  = array_map(static fn($u) => (int) ($u['id_user'] ?? 0), $rows);

            return array_values(array_unique(array_filter($ids, static fn($x) => $x > 0 && $x !== $currentUserId)));
        }

        return [];
    }

    /**
     * Todos los usuarios activos menos el usuario actual.
     */
    private function getAllActiveUserIdsExcept(int $currentUserId): array
    {
        try {
            $db = Database::connect();

            $rows = $db->query(
                'SELECT id_user FROM public."USER" WHERE activo = true AND id_user <> ?',
                [$currentUserId]
            )->getResultArray();

            $ids = [];
            foreach ($rows as $r) {
                $ids[] = (int) ($r['id_user'] ?? 0);
            }

            return array_values(array_unique(array_filter($ids, static fn($x) => $x > 0)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * getTaskById para edición multi:
     * - Devuelve la fila base
     * - asignado_a como array de ids del batch (excluye cancelados)
     */
    public function getTaskById(int $idTarea, int $currentUserId): ?array
    {
        // ==================================================
        // 1) Cargar tarea base
        // ==================================================
        $task = $this->tareaModel->find($idTarea);
        if (!$task) {
            return null;
        }

        // ==================================================
        // 2) Permisos actuales (NO rompemos tu lógica)
        // ==================================================
        $isAssignee = ((int) ($task['asignado_a'] ?? 0) === $currentUserId);
        $isCreator  = ((int) ($task['asignado_por'] ?? 0) === $currentUserId);
        $isSuper    = ((int) (session()->get('id_area') ?? 0) === 1);

        $scope = $this->resolveAssignScope(
            $currentUserId,
            (int) (session()->get('id_area') ?? 0)
        );

        $isBossAllowed = $this->canEditTask($task, $currentUserId, $scope);

        // ✅ Si no tiene permiso, no devolvemos nada
        if (!$isAssignee && !$isCreator && !$isSuper && !$isBossAllowed) {
            return null;
        }

        // ==================================================
        // 3) Si es batch, traemos filas del grupo
        //    (tu lógica actual)
        // ==================================================
        $groupRows = $this->getBatchRowsForTask($task);

        $ids = [];
        foreach ($groupRows as $r) {
            // ✅ Cancelado NO se preselecciona
            if ((int) ($r['id_estado_tarea'] ?? 0) === $this->estadoCancelada) {
                continue;
            }
            $ids[] = (int) ($r['asignado_a'] ?? 0);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        // ✅ Mantenemos la salida esperada por tu vista:
        // asignado_a como array (multi)
        $task['asignado_a'] = $ids;

        // ==================================================
        // 4) ✅ NUEVO: banderas y metadatos para UI / reglas
        // ==================================================

        // 4.1) ¿El usuario logueado es uno de los asignados?
        $task['is_current_user_assignee'] = in_array($currentUserId, $ids, true);

        // 4.2) Supervisor directo del usuario logueado (cadena jefe inmediato)
        //      (sirve para validar y/o mostrar mensajes)
        $task['current_user_supervisor_id'] = 0;

        // 4.3) Metadatos de cada asignado (id_supervisor, nombre completo)
        $task['assignees_meta'] = [];

        // ==================================================
        // 5) Consultas adicionales (sin afectar lo existente)
        // ==================================================
        $db = \Config\Database::connect();

        // 5.1) Supervisor del usuario logueado
        try {
            $rowMe = $db->query(
                'SELECT id_supervisor
               FROM public."USER"
              WHERE id_user = ?
              LIMIT 1',
                [$currentUserId]
            )->getRowArray();

            $task['current_user_supervisor_id'] = (int) ($rowMe['id_supervisor'] ?? 0);
        } catch (\Throwable $e) {
            // Si falla, dejamos 0
            $task['current_user_supervisor_id'] = 0;
        }

        // 5.2) Meta de asignados (solo si hay ids)
        if (!empty($ids)) {
            try {
                // ⚠️ Query Builder con whereIn
                $rows = $db->table('public."USER" u')
                    ->select([
                        'u.id_user',
                        'u.nombres',
                        'u.apellidos',
                        'u.id_supervisor',
                    ])
                    ->whereIn('u.id_user', $ids)
                    ->get()
                    ->getResultArray();

                $meta = [];
                foreach ($rows as $r) {
                    $id = (int) ($r['id_user'] ?? 0);
                    if ($id <= 0) continue;

                    $full = trim((string)($r['nombres'] ?? '') . ' ' . (string)($r['apellidos'] ?? ''));

                    $meta[] = [
                        'id_user'       => $id,
                        'label'         => ($full !== '' ? $full : ('Usuario #' . $id)),
                        'id_supervisor' => (int) ($r['id_supervisor'] ?? 0),
                    ];
                }

                $task['assignees_meta'] = $meta;
            } catch (\Throwable $e) {
                $task['assignees_meta'] = [];
            }
        }

        // ==================================================
        // 6) Batch uid (tu lógica)
        // ==================================================
        if ($this->hasBatchUidColumn()) {
            $task['batch_uid'] = (string) ($task['batch_uid'] ?? '');
        }

        return $task;
    }

    /**
     * =========================================================
     * getBusinessWeekRange()
     * =========================================================
     * Semana negocio: JUEVES 00:00 → MIÉRCOLES 23:59
     * Query usa fin EXCLUSIVO: jueves siguiente 00:00
     *
     * ✅ REGLA DE GRACIA:
     * - Si HOY es JUEVES y la hora local es ANTES de 12:00,
     *   entonces la semana activa debe ser la SEMANA ANTERIOR
     *   (jueves anterior → miércoles anterior).
     *
     * Esto hace que el jueves por la mañana, al guardar/consultar,
     * se trabaje todavía con el corte del miércoles anterior.
     * =========================================================
     */
    private function getBusinessWeekRange(): array
    {
        // -------------------------------------------------
        // 1) "Ahora" en TZ local
        // -------------------------------------------------
        $now = new \DateTimeImmutable('now', $this->tz());

        // 1=Lunes ... 7=Domingo
        $dow = (int) $now->format('N');

        // -------------------------------------------------
        // 2) Calcular jueves de la semana (sin gracia aún)
        // JUEVES = 4
        // -------------------------------------------------
        $daysSinceThursday = ($dow >= 4) ? ($dow - 4) : ($dow + 3);

        // Inicio jueves 00:00 (semana normal)
        $start = $now->modify("-{$daysSinceThursday} days")->setTime(0, 0, 0);

        // -------------------------------------------------
        // 3) ✅ REGLA DE GRACIA
        // Si es jueves y es antes de 12:00 => usar semana anterior
        // -------------------------------------------------
        if ($dow === 4) {
            $hour = (int) $now->format('H');

            // Antes del medio día (12:00)
            if ($hour < 12) {
                $start = $start->modify('-7 days'); // jueves anterior 00:00
            }
        }

        // -------------------------------------------------
        // 4) Fin EXCLUSIVO (jueves siguiente 00:00)
        // -------------------------------------------------
        $endExclusive = $start->modify('+7 days')->setTime(0, 0, 0);

        // -------------------------------------------------
        // 5) Fin visible (miércoles)
        // -------------------------------------------------
        $endDisplay = $start->modify('+6 days')->setTime(0, 0, 0);

        // cutKey = miércoles de corte (clave para guardar en historico.semana)
        $cutKey = $endDisplay->format('Y-m-d');

        return [
            'start'        => $start,
            'endExclusive' => $endExclusive,
            'inicioLabel'  => $start->format('Y-m-d'),
            'finLabel'     => $endDisplay->format('Y-m-d'),

            // ✅ NO rompe nada (key adicional)
            'cutKey'       => $cutKey,
        ];
    }
    /**
     * Retorna la fecha (YYYY-mm-dd) del MIÉRCOLES de corte
     * según la semana de negocio (con regla de gracia).
     * Úsalo para GUARDAR en historico.semana.
     */
    public function getBusinessWeekRangePublic(): array
    {
        // ✅ Llama al método privado interno (misma lógica)
        return $this->getBusinessWeekRange();
    }
    public function getSemanaCorteKey(): string
    {
        $range = $this->getBusinessWeekRange();
        return (string) ($range['cutKey'] ?? $range['finLabel'] ?? '');
    }
    private function buildWeekLabel(\DateTimeImmutable $weekStart): string
    {
        $visibleEnd = $weekStart->modify('+6 days')->setTime(0, 0, 0);
        return $weekStart->format('Y-m-d') . '→' . $visibleEnd->format('Y-m-d');
    }

    private function getChiefDivisionInfo(int $userId): ?array
    {
        try {
            $db = Database::connect();
            $row = $db->query(
                'SELECT id_division, nombre_division
                 FROM public.division
                 WHERE id_jf_division = ?
                 LIMIT 1',
                [$userId]
            )->getRowArray();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getChiefAreasInfo(int $userId): array
    {
        try {
            $db = Database::connect();
            return $db->query(
                'SELECT id_area, nombre_area, id_division
                 FROM public.area
                 WHERE id_jf_area = ?
                 ORDER BY nombre_area ASC',
                [$userId]
            )->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getUserIdsByDivision(int $divisionId): array
    {
        try {
            $db = Database::connect();

            $rows = $db->query(
                'SELECT DISTINCT u.id_user
                 FROM public."USER" u
                 JOIN public.cargo c ON c.id_cargo = u.id_cargo
                 LEFT JOIN public.area a ON a.id_area = c.id_area
                 WHERE u.activo = true
                   AND (
                        a.id_division = ?
                        OR c.id_division = ?
                   )',
                [$divisionId, $divisionId]
            )->getResultArray();

            $ids = [];
            foreach ($rows as $r) {
                $id = (int)($r['id_user'] ?? 0);
                if ($id > 0) $ids[] = $id;
            }
            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getUserIdsByArea(int $areaId): array
    {
        try {
            $db = Database::connect();

            $rows = $db->query(
                'SELECT DISTINCT u.id_user
                 FROM public."USER" u
                 JOIN public.cargo c ON c.id_cargo = u.id_cargo
                 WHERE u.activo = true
                   AND c.id_area = ?',
                [$areaId]
            )->getResultArray();

            $ids = [];
            foreach ($rows as $r) {
                $id = (int)($r['id_user'] ?? 0);
                if ($id > 0) $ids[] = $id;
            }
            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function calcSatisfaccionChain(
        array $userIds,
        \DateTimeImmutable $start,
        \DateTimeImmutable $endExclusive,
        ?int $divisionId = null,
        ?int $areaId = null
    ): array {
        $db = Database::connect();

        $b = $db->table('public.tareas t');

        // --------------------------------------------------
        // ✅ Solo estados que cuentan para satisfacción
        // --------------------------------------------------
        $b->whereIn('t.id_estado_tarea', [$this->estadoRealizada, $this->estadoNoRealizada]);

        // --------------------------------------------------
        // ✅ La semana se mide por completed_at (cierre real)
        // --------------------------------------------------
        $b->where('t.completed_at IS NOT NULL', null, false);
        $b->where('t.completed_at >=', $start->format('Y-m-d H:i:s'));
        $b->where('t.completed_at <',  $endExclusive->format('Y-m-d H:i:s'));

        // --------------------------------------------------
        // ✅ Select de conteos
        // --------------------------------------------------
        $b->select("
        SUM(CASE WHEN t.id_estado_tarea = {$this->estadoRealizada} THEN 1 ELSE 0 END) AS realizadas,
        SUM(CASE WHEN t.id_estado_tarea = {$this->estadoNoRealizada} THEN 1 ELSE 0 END) AS no_realizadas
    ", false);

        // --------------------------------------------------
        // ✅ Scopes:
        // - Si viene areaId => cuenta TODO el área (NO por usuarios, NO por asignado_por)
        // - Si viene divisionId => cuenta TODA la división (NO por usuarios)
        // - Si NO viene ninguno => es personal => filtra por asignado_a = userId
        // --------------------------------------------------
        if ($areaId !== null && $areaId > 0) {
            $b->where('t.id_area', $areaId);
        } elseif ($divisionId !== null && $divisionId > 0) {
            $b->join('public.area ar', 'ar.id_area = t.id_area', 'left');
            $b->where('ar.id_division', $divisionId);
        } else {
            // Personal: SOLO ejecutadas por el usuario (asignado_a)
            $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn($x) => $x > 0)));
            if (empty($userIds)) {
                return ['realizadas' => 0, 'no_realizadas' => 0, 'porcentaje' => 0.0];
            }

            // Si por error llega más de 1, igual funciona como IN
            $b->whereIn('t.asignado_a', $userIds);
        }

        $row = $b->get()->getRowArray();

        $realizadas   = (int)($row['realizadas'] ?? 0);
        $noRealizadas = (int)($row['no_realizadas'] ?? 0);
        $total = $realizadas + $noRealizadas;

        $porcentaje = ($total > 0) ? (float) number_format(($realizadas / $total) * 100, 2, '.', '') : 0.00;

        return [
            'realizadas'    => $realizadas,
            'no_realizadas' => $noRealizadas,
            'porcentaje'    => $porcentaje,
        ];
    }
    /**
     * getLast4WeeksAverage()
     *
     * ✅ Promedio de las últimas 4 semanas (NO incluye la actual)
     */
    private function getLast4WeeksAverage(
        array $userIds,
        \DateTimeImmutable $currentWeekStart,
        ?int $divisionId = null,
        ?int $areaId = null
    ): float {
        $total = 0.0;
        $count = 0;

        for ($i = 1; $i <= 4; $i++) {
            $wStart = $currentWeekStart->modify("-{$i} week")->setTime(0, 0, 0);
            $wEndEx = $wStart->modify('+7 days')->setTime(0, 0, 0);

            $data = $this->calcSatisfaccionChain($userIds, $wStart, $wEndEx, $divisionId, $areaId);

            $total += (float) ($data['porcentaje'] ?? 0);
            $count++;
        }

        return ($count > 0) ? round($total / $count, 2) : 0.0;
    }
    private function getUserRankingInArea(int $areaId, \DateTimeImmutable $start, \DateTimeImmutable $endExclusive): array
    {
        try {
            $db = Database::connect();

            $sql = "
            SELECT
                u.id_user AS user_id,
                TRIM(u.nombres || ' ' || u.apellidos) AS nombre,
                SUM(CASE WHEN t.id_estado_tarea = {$this->estadoRealizada} THEN 1 ELSE 0 END) AS realizadas,
                SUM(CASE WHEN t.id_estado_tarea = {$this->estadoNoRealizada} THEN 1 ELSE 0 END) AS no_realizadas
            FROM public.\"USER\" u
            JOIN public.cargo c ON c.id_cargo = u.id_cargo
            LEFT JOIN public.tareas t
                ON (
                    t.id_area = ?
                    AND t.completed_at IS NOT NULL
                    AND t.completed_at >= ?
                    AND t.completed_at < ?
                    AND t.id_estado_tarea IN ({$this->estadoRealizada}, {$this->estadoNoRealizada})
                    -- ✅ Ranking personal: SOLO ejecutor
                    AND t.asignado_a = u.id_user
                )
            WHERE u.activo = true
              AND c.id_area = ?
            GROUP BY u.id_user, u.nombres, u.apellidos
        ";

            $rows = $db->query($sql, [
                $areaId,
                $start->format('Y-m-d H:i:s'),
                $endExclusive->format('Y-m-d H:i:s'),
                $areaId,
            ])->getResultArray();

            $ranking = [];

            foreach ($rows as $r) {
                $real = (int) ($r['realizadas'] ?? 0);
                $noR  = (int) ($r['no_realizadas'] ?? 0);
                $tot  = $real + $noR;

                $pct = ($tot > 0) ? (float) number_format(($real / $tot) * 100, 2, '.', '') : 0.00;

                $ranking[] = [
                    'user_id'       => (int) ($r['user_id'] ?? 0),
                    'nombre'        => (string) ($r['nombre'] ?? ''),
                    'porcentaje'    => $pct,
                    'realizadas'    => $real,
                    'no_realizadas' => $noR,
                ];
            }

            usort($ranking, fn($a, $b) => ($b['porcentaje'] <=> $a['porcentaje']));

            return $ranking;
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function getAreaRankingInDivision(int $divisionId, \DateTimeImmutable $start, \DateTimeImmutable $endExclusive): array
    {
        try {
            $db = Database::connect();

            $sql = "
            SELECT
                a.id_area AS area_id,
                a.nombre_area AS area,
                SUM(CASE WHEN t.id_estado_tarea = {$this->estadoRealizada} THEN 1 ELSE 0 END) AS realizadas,
                SUM(CASE WHEN t.id_estado_tarea = {$this->estadoNoRealizada} THEN 1 ELSE 0 END) AS no_realizadas
            FROM public.area a
            LEFT JOIN public.tareas t
                ON (
                    t.id_area = a.id_area
                    AND t.completed_at IS NOT NULL
                    AND t.completed_at >= ?
                    AND t.completed_at < ?
                    AND t.id_estado_tarea IN ({$this->estadoRealizada}, {$this->estadoNoRealizada})
                )
            WHERE a.id_division = ?
            GROUP BY a.id_area, a.nombre_area
        ";

            $rows = $db->query($sql, [
                $start->format('Y-m-d H:i:s'),
                $endExclusive->format('Y-m-d H:i:s'),
                $divisionId,
            ])->getResultArray();

            $ranking = [];

            foreach ($rows as $r) {
                $real = (int) ($r['realizadas'] ?? 0);
                $noR  = (int) ($r['no_realizadas'] ?? 0);
                $tot  = $real + $noR;

                $pct = ($tot > 0) ? round(($real / $tot) * 100, 2) : 0.0;

                $ranking[] = [
                    'area_id'       => (int) ($r['area_id'] ?? 0),
                    'area'          => (string) ($r['area'] ?? 'Área'),
                    'porcentaje'    => $pct,
                    'realizadas'    => $real,
                    'no_realizadas' => $noR,
                ];
            }

            usort($ranking, fn($a, $b) => ($b['porcentaje'] <=> $a['porcentaje']));

            return $ranking;
        } catch (\Throwable $e) {
            return [];
        }
    }
    public function getSatisfaccionResumen(int $idUser): array
    {
        $range = $this->getBusinessWeekRange();
        $start = $range['start'];
        $endExclusive = $range['endExclusive'];

        $cards = [];

        $rankingUsersByArea = [];

        $divInfo = $this->getChiefDivisionInfo($idUser);

        $rankingAreas = [];

        if (!empty($divInfo)) {
            $divisionId   = (int) $divInfo['id_division'];
            $divisionName = (string) $divInfo['nombre_division'];

            $idsDivision = $this->getUserIdsByDivision($divisionId);

            if (!in_array($idUser, $idsDivision, true)) {
                $idsDivision[] = $idUser;
            }

            $dataDivision = $this->calcSatisfaccionChain(
                $idsDivision,
                $start,
                $endExclusive,
                $divisionId,
                null
            );

            // ✅ Históricos oficiales desde public.historico_division (y fallback si faltan filas)
            $historyDivision = $this->buildHistorySeriesFromHistorico($idsDivision, $start, $divisionId, null);
            $avgDivision4    = $this->getLast4WeeksAverageFromHistorico($idsDivision, $start, $divisionId, null);

            $cards[] = array_merge($dataDivision, [
                'titulo'      => 'Satisfacción Global División: ' . $divisionName,
                'scope'       => 'division',
                'division_id' => $divisionId,
                'avg_4_weeks' => $avgDivision4,
                'history'     => $historyDivision,
            ]);

            $rankingAreas = $this->getAreaRankingInDivision($divisionId, $start, $endExclusive);

            $db = Database::connect();
            $areas = $db->query(
                'SELECT id_area, nombre_area
             FROM public.area
             WHERE id_division = ?
             ORDER BY nombre_area ASC',
                [$divisionId]
            )->getResultArray();

            foreach ($areas as $a) {
                $areaId   = (int) $a['id_area'];
                $areaName = (string) $a['nombre_area'];

                $idsArea = $this->getUserIdsByArea($areaId);

                if (!in_array($idUser, $idsArea, true)) {
                    $idsArea[] = $idUser;
                }

                $dataArea = $this->calcSatisfaccionChain(
                    $idsArea,
                    $start,
                    $endExclusive,
                    null,
                    $areaId
                );

                // ✅ Históricos oficiales desde public.historico_area (y fallback si faltan filas)
                $historyArea = $this->buildHistorySeriesFromHistorico($idsArea, $start, null, $areaId);
                $avgArea4    = $this->getLast4WeeksAverageFromHistorico($idsArea, $start, null, $areaId);

                $cards[] = array_merge($dataArea, [
                    'titulo'      => 'Área: ' . $areaName,
                    'scope'       => 'area',
                    'area_id'     => $areaId,
                    'avg_4_weeks' => $avgArea4,
                    'history'     => $historyArea,
                ]);

                if (!isset($rankingUsersByArea[$areaId])) {
                    $rankingUsersByArea[$areaId] = $this->getUserRankingInArea($areaId, $start, $endExclusive);
                }
            }
        }

        // =========================================================
        // ✅ MI ÁREA A CARGO (FIX: usar historico_area)
        // =========================================================
        $areasJefe = $this->getChiefAreasInfo($idUser);

        foreach ($areasJefe as $a) {
            $areaId   = (int) $a['id_area'];
            $areaName = (string) $a['nombre_area'];

            $idsArea = $this->getUserIdsByArea($areaId);

            if (!in_array($idUser, $idsArea, true)) {
                $idsArea[] = $idUser;
            }

            $dataArea = $this->calcSatisfaccionChain(
                $idsArea,
                $start,
                $endExclusive,
                null,
                $areaId
            );

            // ✅ FIX AQUÍ: ahora también jala de public.historico_area
            $historyArea = $this->buildHistorySeriesFromHistorico($idsArea, $start, null, $areaId);
            $avgArea4    = $this->getLast4WeeksAverageFromHistorico($idsArea, $start, null, $areaId);

            $cards[] = array_merge($dataArea, [
                'titulo'      => 'Mi Área a cargo: ' . $areaName,
                'scope'       => 'area',
                'area_id'     => $areaId,
                'avg_4_weeks' => $avgArea4,
                'history'     => $historyArea,
            ]);

            if (!isset($rankingUsersByArea[$areaId])) {
                $rankingUsersByArea[$areaId] = $this->getUserRankingInArea($areaId, $start, $endExclusive);
            }
        }

        $personal = $this->calcSatisfaccionChain([$idUser], $start, $endExclusive, null, null);

        // ✅ PERSONAL: ahora jala de public.historico.satisfaccion (y fallback si falta)
        $historyPersonal = $this->buildHistorySeriesFromHistorico([$idUser], $start, null, null);
        $avgPersonal4    = $this->getLast4WeeksAverageFromHistorico([$idUser], $start, null, null);

        $cards[] = array_merge($personal, [
            'titulo'      => 'Mi porcentaje de satisfacción',
            'scope'       => 'personal',
            'avg_4_weeks' => $avgPersonal4,
            'history'     => $historyPersonal,
        ]);

        usort($cards, fn($a, $b) => ((float)($b['porcentaje'] ?? 0) <=> (float)($a['porcentaje'] ?? 0)));

        $historyGlobal = [];
        foreach ($cards as $c) {
            $sc = (string) ($c['scope'] ?? '');
            if ($sc === 'division' && !empty($c['history'])) {
                $historyGlobal = (array) $c['history'];
                break;
            }
        }
        if (empty($historyGlobal)) {
            foreach ($cards as $c) {
                $sc = (string) ($c['scope'] ?? '');
                if ($sc === 'area' && !empty($c['history'])) {
                    $historyGlobal = (array) $c['history'];
                    break;
                }
            }
        }
        if (empty($historyGlobal)) {
            $historyGlobal = $historyPersonal;
        }

        return [
            'inicio' => $range['inicioLabel'],
            'fin'    => $range['finLabel'],
            'cards'  => $cards,
            'ranking_areas' => $rankingAreas,
            'ranking_users_by_area' => $rankingUsersByArea,
            'history_global' => $historyGlobal,
        ];
    }
    public function getSatisfaccionActual(int $idUser): array
    {
        $resumen = $this->getSatisfaccionResumen($idUser);

        $inicio = (string) ($resumen['inicio'] ?? '');
        $fin    = (string) ($resumen['fin'] ?? '');

        $cards = (array) ($resumen['cards'] ?? []);
        $personal = null;

        foreach ($cards as $c) {
            if (($c['scope'] ?? '') === 'personal') {
                $personal = $c;
                break;
            }
        }

        $porcentaje = (float) ($personal['porcentaje'] ?? 0);
        $realizadas = (int)   ($personal['realizadas'] ?? 0);
        $noReal     = (int)   ($personal['no_realizadas'] ?? 0);

        return [
            'porcentaje'    => $porcentaje,
            'realizadas'    => $realizadas,
            'no_realizadas' => $noReal,
            'inicio'        => $inicio,
            'fin'           => $fin,
        ];
    }
    public function getSatisfaccionParaPlan(int $idUser): array
    {
        // --------------------------------------------------
        // 1) Reusar el resumen ya existente (misma semana jueves→miércoles)
        // --------------------------------------------------
        $resumen = $this->getSatisfaccionResumen($idUser);

        $inicio = (string) ($resumen['inicio'] ?? '');
        $fin    = (string) ($resumen['fin'] ?? '');

        $cards = (array) ($resumen['cards'] ?? []);

        $division = null;
        $miArea   = null;
        $personal = null;

        // --------------------------------------------------
        // 2) Buscar cards por prioridad:
        //    división > mi área a cargo > personal
        // --------------------------------------------------
        foreach ($cards as $c) {
            $scope = (string) ($c['scope'] ?? '');

            // ✅ Card de división (si existe, manda)
            if ($scope === 'division' && $division === null) {
                $division = $c;
                continue;
            }

            // ✅ Card de "Mi Área a cargo" (solo si NO hay división)
            if ($scope === 'area' && $miArea === null) {
                $titulo = (string) ($c['titulo'] ?? '');
                if (stripos($titulo, 'Mi Área a cargo:') === 0) {
                    $miArea = $c;
                    continue;
                }
            }

            // ✅ Personal (fallback)
            if ($scope === 'personal' && $personal === null) {
                $personal = $c;
                continue;
            }
        }

        // --------------------------------------------------
        // 3) Precedencia final
        // --------------------------------------------------
        $pick = $division ?? $miArea ?? $personal ?? [];

        return [
            'titulo'        => (string) ($pick['titulo'] ?? 'Mi porcentaje de satisfacción'),
            'scope'         => (string) ($pick['scope'] ?? 'personal'),
            'porcentaje'    => (float)  ($pick['porcentaje'] ?? 0),
            'realizadas'    => (int)    ($pick['realizadas'] ?? 0),
            'no_realizadas' => (int)    ($pick['no_realizadas'] ?? 0),
            'inicio'        => $inicio,
            'fin'           => $fin,
        ];
    }
    // ==================================================
    // REGLA DE EDICIÓN
    // ==================================================
    /**
     * canEditTask()
     *
     * ✅ Define quién puede abrir/editar una tarea en el formulario (Editar/Reasignar).
     * - NO valida campos específicos (eso ya lo controla updateTask()).
     * - Aquí solo decidimos si el usuario tiene derecho a editar según su rol/scope.
     *
     * REGLAS:
     * - super    (gerencia: id_area=1) => puede editar todo
     * - division (jefe división)       => puede editar tareas de áreas dentro de su división
     * - area     (jefe área)           => puede editar tareas de su área
     * - self     (usuario normal)      => puede editar tareas donde sea asignado_a (o creador si aplica)
     *
     * BLOQUEO DE ESTADOS CERRADOS:
     * - Si la tarea está en estado 3/4/5 (Realizada/No realizada/Cancelada), no se edita
     *   excepto super (gerencia).
     */
    private function canEditTask(array $task, int $currentUserId, array $scope): bool
    {
        // ----------------------------
        // 1) Datos base de la tarea
        // ----------------------------
        $taskAreaId   = (int) ($task['id_area'] ?? 0);
        $taskAssignee = (int) ($task['asignado_a'] ?? 0);
        $taskCreator  = (int) ($task['asignado_por'] ?? 0);
        $taskEstado   = (int) ($task['id_estado_tarea'] ?? 0);

        // ----------------------------
        // 2) Modo del usuario (scope)
        // ----------------------------
        $mode       = (string) ($scope['mode'] ?? 'self');
        $divisionId = (int) ($scope['divisionId'] ?? 0);
        $areaId     = (int) ($scope['areaId'] ?? 0);

        // ----------------------------
        // 3) Gerencia => todo
        // ----------------------------
        if ($mode === 'super') {
            return true;
        }

        // ----------------------------
        // 4) Bloqueo de estados cerrados (excepto super)
        // ----------------------------
        // 3 = Realizada, 4 = No realizada, 5 = Cancelada
        if (in_array($taskEstado, [3, 4, 5], true)) {
            return false;
        }

        // ----------------------------
        // 5) Usuario normal (self)
        // ----------------------------
        // ✅ Puede editar si:
        // - la tarea está asignada a él, o
        // - él fue el creador
        if ($mode === 'self') {
            return ($taskAssignee === $currentUserId) || ($taskCreator === $currentUserId);
        }

        // ----------------------------
        // 6) Jefe de Área (area)
        // ----------------------------
        // ✅ Puede editar si la tarea pertenece a su área (subordinados incluidos)
        if ($mode === 'area') {
            if ($areaId <= 0) return false;
            return ($taskAreaId === $areaId);
        }

        // ----------------------------
        // 7) Jefe de División (division)
        // ----------------------------
        // ✅ Puede editar si el área de la tarea pertenece a su división
        if ($mode === 'division') {
            if ($divisionId <= 0) return false;
            if ($taskAreaId <= 0) return false;

            // Si tu model ya tiene helper, lo usamos:
            if (method_exists($this->tareaModel, 'isAreaInDivision')) {
                return (bool) $this->tareaModel->isAreaInDivision($taskAreaId, $divisionId);
            }

            // Fallback seguro: consulta directa para verificar relación área -> división
            $db = \Config\Database::connect();
            $row = $db->table('public.area')
                ->select('id_area')
                ->where('id_area', $taskAreaId)
                ->where('id_division', $divisionId)
                ->get()
                ->getFirstRow('array');

            return !empty($row);
        }

        // ----------------------------
        // 8) Cualquier otro caso => no
        // ----------------------------
        return false;
    }
    /**
     * cancelTask()
     *
     * ✅ Cancelación directa:
     * - NO pasa por revisión
     * - Fuerza id_estado_tarea = 5 (Cancelada)
     * - Valida permisos: super / asignador / jefe área / jefe división (según scope)
     */
    public function cancelTask(
        int $taskId,
        int $currentUserId,
        int $currentUserAreaId,
        ?string $reason = null
    ): array {
        // ==================================================
        // 1) Validar columnas necesarias
        // ==================================================
        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        if (method_exists($this, 'hasExtendedReviewColumns') && !$this->hasExtendedReviewColumns()) {
            return [
                'success' => false,
                'error'   => 'Faltan columnas extendidas de revisión (review_action, review_reason, review_requested_by, review_requested_fecha_fin, review_previous_state). Ejecuta el ALTER TABLE.',
            ];
        }

        // ==================================================
        // 2) Buscar tarea
        // ==================================================
        $task = $this->tareaModel->find($taskId);

        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        // ==================================================
        // 3) Validar que NO esté cerrada
        // ==================================================
        $estadoActual = (int) ($task['id_estado_tarea'] ?? 0);

        if (in_array($estadoActual, [$this->estadoRealizada, $this->estadoNoRealizada, $this->estadoCancelada], true)) {
            return ['success' => false, 'error' => 'Esta tarea ya está cerrada y no se puede cancelar.'];
        }

        if ($estadoActual === $this->estadoEnRevision) {
            return ['success' => false, 'error' => 'Esta tarea ya está en revisión. Espera aprobación del supervisor.'];
        }

        // ==================================================
        // 4) Contexto real del actor
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        $asignadoA = (int) ($task['asignado_a'] ?? 0);

        $rowSup = $db->query(
            'SELECT id_supervisor
         FROM public."USER"
         WHERE id_user = ?
         LIMIT 1',
            [$asignadoA]
        )->getRowArray();

        $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

        $isAssignee         = ($asignadoA === $currentUserId);
        $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);
        $isSuperUser        = (($scope['mode'] ?? '') === 'super');

        // ==================================================
        // 5) Regla nueva de autorización
        // ==================================================
        // ✅ Solo permitido:
        // - asignado => solicita revisión
        // - supervisor directo => cancela directo
        // - super/gerencia => cancela directo
        if (!$isAssignee && !$isDirectSupervisor && !$isSuperUser) {
            return ['success' => false, 'error' => 'No tienes permisos para cancelar esta tarea.'];
        }

        // ==================================================
        // 6) Motivo
        // ==================================================
        $reason = trim((string) ($reason ?? ''));

        // ✅ Si es el asignado, el motivo es obligatorio
        if ($isAssignee && $reason === '') {
            return ['success' => false, 'error' => 'Debes escribir un motivo para solicitar la cancelación.'];
        }

        // ==================================================
        // 7) CASO A: USUARIO ASIGNADO
        //    => solicita revisión
        // ==================================================
        if ($isAssignee) {

            if ($assignedSupervisorId <= 0) {
                return ['success' => false, 'error' => 'No tienes supervisor asignado (id_supervisor).'];
            }

            $payload = [
                // pasa a revisión
                'id_estado_tarea'            => $this->estadoEnRevision,

                // estado solicitado
                'review_requested_state'     => $this->estadoCancelada,
                'review_requested_at'        => $now,

                // datos extendidos
                'review_action'              => 'cancel',
                'review_reason'              => $reason,
                'review_requested_by'        => $currentUserId,
                'review_requested_fecha_fin' => null,
                'review_previous_state'      => $estadoActual,

                // limpieza aprobación previa
                'approved_by'                => null,
                'approved_at'                => null,

                // no se cierra todavía
                'completed_at'               => null,
            ];

            try {
                $db->table('public.tareas')
                    ->where('id_tarea', $taskId)
                    ->update($payload);
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'No se pudo enviar la cancelación a revisión.'];
            }

            return ['success' => true, 'message' => 'Cancelación enviada a revisión de tu supervisor.'];
        }

        // ==================================================
        // 8) CASO B: SUPERVISOR DIRECTO / SUPER
        //    => cancela directo
        // ==================================================
        $payload = [
            'id_estado_tarea'            => $this->estadoCancelada,
            'completed_at'               => null,

            // limpieza revisión
            'review_requested_state'     => null,
            'review_requested_at'        => null,
            'review_action'              => null,
            'review_reason'              => null,
            'review_requested_by'        => null,
            'review_requested_fecha_fin' => null,
            'review_previous_state'      => null,

            // auditoría
            'approved_by'                => $currentUserId,
            'approved_at'                => $now,
        ];

        try {
            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update($payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error cancelando la actividad.'];
        }

        return ['success' => true, 'message' => 'Actividad cancelada correctamente.'];
    }
    // ==================================================
    // FLUJO DE REVISIÓN (OPCIONAL, SI EXISTEN COLUMNAS)
    // ==================================================
    public function requestOrSetEstado(
        int $taskId,
        int $requestedEstado,
        int $currentUserId,
        int $currentUserAreaId,
        ?string $reason = null,
        bool $hasEvidence = false,
        ?string $evidenceUrl = null,
        ?string $evidenceNote = null
    ): array {

        // ==================================================
        // 0) Verificar columnas del flujo revisión
        // ==================================================
        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        if (method_exists($this, 'hasExtendedReviewColumns') && !$this->hasExtendedReviewColumns()) {
            return [
                'success' => false,
                'error'   => 'Faltan columnas extendidas de revisión (review_action, review_reason, review_requested_by, review_requested_fecha_fin, review_previous_state). Ejecuta el ALTER TABLE.',
            ];
        }

        // ==================================================
        // 0.1) Validación / normalización de evidencia
        // ==================================================
        $evidenceUrl  = trim((string) ($evidenceUrl ?? ''));
        $evidenceNote = trim((string) ($evidenceNote ?? ''));

        // Si no tiene evidencia, limpiamos todo
        if (!$hasEvidence) {
            $evidenceUrl  = '';
            $evidenceNote = '';
        }

        // Si marcó que sí tiene evidencia, el link es obligatorio
        if ($hasEvidence && $evidenceUrl === '') {
            return [
                'success' => false,
                'error'   => 'Debes ingresar el enlace de evidencia.',
            ];
        }

        // Validación básica de URL
        if ($hasEvidence && !filter_var($evidenceUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error'   => 'El enlace de evidencia no es válido.',
            ];
        }

        // ==================================================
        // 1) Validación estado permitido
        // ==================================================
        if (!in_array($requestedEstado, [$this->estadoRealizada, $this->estadoNoRealizada], true)) {
            return ['success' => false, 'error' => 'Estado inválido.'];
        }

        // ==================================================
        // 2) Cargar tarea
        // ==================================================
        $task = $this->tareaModel->find($taskId);
        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        $estadoActual = (int) ($task['id_estado_tarea'] ?? 0);

        // ==================================================
        // 3) No permitir si ya está cerrada o en revisión
        // ==================================================
        if (in_array($estadoActual, [$this->estadoRealizada, $this->estadoNoRealizada, $this->estadoCancelada], true)) {
            return ['success' => false, 'error' => 'Esta tarea ya está cerrada y no se puede modificar.'];
        }

        if ($estadoActual === $this->estadoEnRevision) {
            return ['success' => false, 'error' => 'Esta tarea ya está en revisión. Espera aprobación del supervisor.'];
        }

        // ==================================================
        // 4) Contexto real del actor
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        $asignadoA = (int) ($task['asignado_a'] ?? 0);

        $rowSup = $db->query(
            'SELECT id_supervisor
         FROM public."USER"
         WHERE id_user = ?
         LIMIT 1',
            [$asignadoA]
        )->getRowArray();

        $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

        $isAssignee         = ($asignadoA === $currentUserId);
        $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);
        $isSuperUser        = (($scope['mode'] ?? '') === 'super');

        // ==================================================
        // 5) Solo permitido:
        //    - usuario asignado => solicita revisión
        //    - supervisor directo => cierra directo
        //    - super/gerencia => cierra directo
        // ==================================================
        if (!$isAssignee && !$isDirectSupervisor && !$isSuperUser) {
            return ['success' => false, 'error' => 'No autorizado para cambiar el estado de esta tarea.'];
        }

        // ==================================================
        // 6) Si excede límite de ediciones:
        //    - si es usuario normal => se envía/queda como No realizada
        //    - si supervisor directo o super => se cierra como No realizada
        // ==================================================
        $editCount = (int) ($task['edit_count'] ?? 0);
        $forcedNoRealizada = ($editCount > $this->maxEditsForRealizada);

        if ($forcedNoRealizada) {
            $requestedEstado = $this->estadoNoRealizada;
        }

        // ==================================================
        // 7) CASO A: USUARIO ASIGNADO
        //    => NO cierra directo, solo solicita revisión
        // ==================================================
        if ($isAssignee) {

            // Debe existir supervisor real
            if ($assignedSupervisorId <= 0) {
                return ['success' => false, 'error' => 'No tienes supervisor asignado (id_supervisor).'];
            }

            $reason = trim((string) ($reason ?? ''));

            $payload = [
                // pasa a revisión
                'id_estado_tarea'            => $this->estadoEnRevision,

                // qué estado pidió
                'review_requested_state'     => $requestedEstado,
                'review_requested_at'        => $now,

                // datos extendidos
                'review_action'              => 'state',
                'review_reason'              => ($reason !== '' ? $reason : null),
                'review_requested_by'        => $currentUserId,
                'review_requested_fecha_fin' => null,
                'review_previous_state'      => $estadoActual,

                // limpieza aprobación previa
                'approved_by'                => null,
                'approved_at'                => null,

                // todavía no cierra
                'completed_at'               => null,

                // ✅ NUEVO: evidencia
                'has_evidence'               => $hasEvidence,
                'evidence_url'               => ($hasEvidence ? $evidenceUrl : null),
                'evidence_note'              => ($hasEvidence && $evidenceNote !== '' ? $evidenceNote : null),
            ];

            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update($payload);

            if ($forcedNoRealizada) {
                return [
                    'success' => true,
                    'message' => 'La tarea superó el límite de ediciones y fue enviada a revisión para marcarse como No realizada.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Enviado a revisión de tu supervisor.',
            ];
        }

        // ==================================================
        // 8) CASO B: SUPERVISOR DIRECTO / SUPER
        //    => cierra directo
        // ==================================================
        $payload = [
            'id_estado_tarea'            => $requestedEstado,

            // limpieza revisión
            'review_requested_state'     => null,
            'review_requested_at'        => null,
            'review_action'              => null,
            'review_reason'              => null,
            'review_requested_by'        => null,
            'review_requested_fecha_fin' => null,
            'review_previous_state'      => null,

            // auditoría
            'approved_by'                => $currentUserId,
            'approved_at'                => $now,

            // cierre real
            'completed_at' => in_array(
                $requestedEstado,
                [$this->estadoRealizada, $this->estadoNoRealizada],
                true
            ) ? $now : null,

            // ✅ NUEVO: evidencia
            'has_evidence'               => $hasEvidence,
            'evidence_url'               => ($hasEvidence ? $evidenceUrl : null),
            'evidence_note'              => ($hasEvidence && $evidenceNote !== '' ? $evidenceNote : null),
        ];

        $db->table('public.tareas')
            ->where('id_tarea', $taskId)
            ->update($payload);

        return [
            'success' => true,
            'message' => ($requestedEstado === $this->estadoRealizada)
                ? 'La tarea fue marcada como Realizada.'
                : 'La tarea fue marcada como No realizada.',
        ];
    }
    /**
     * ==================================================
     * SOLICITAR REVISIÓN (CAMBIO DE FECHA FIN o CANCELACIÓN)
     * ==================================================
     *
     * Regla que pediste:
     * - Si el usuario intenta:
     *   a) Cambiar fecha (solo FECHA FIN)
     *   b) Cancelar
     *   => NO se aplica directo, se envía a REVISIÓN a su supervisor.
     *
     * - Debe exigir MOTIVO.
     * - Fecha inicio queda FIJA (no se permite modificar).
     *
     * NOTA:
     * - La tarea pasa a estado 6 (En revisión).
     * - NO se cambia la fecha real ni se cancela real todavía.
     * - Se guarda lo solicitado en columnas review_*.
     */
    public function requestReviewChange(
        int $taskId,
        string $action,               // 'date_change' | 'cancel'
        ?string $requestedEndRaw,     // solo para date_change
        string $reason,
        int $currentUserId,
        int $currentUserAreaId
    ): array {

        // ==================================================
        // 1) Validar columnas necesarias
        // ==================================================
        if (!$this->hasReviewFlowColumns() || !$this->hasExtendedReviewColumns()) {
            return [
                'success' => false,
                'error'   => 'Faltan columnas para revisión extendida. Ejecuta el ALTER TABLE (review_action, review_reason, review_requested_by, review_requested_fecha_fin, review_previous_state).',
            ];
        }

        // ==================================================
        // 2) Acción válida
        // ==================================================
        if (!in_array($action, ['date_change', 'cancel'], true)) {
            return ['success' => false, 'error' => 'Acción inválida.'];
        }

        // ==================================================
        // 3) Motivo obligatorio
        // ==================================================
        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'error' => 'Debes escribir el motivo.'];
        }

        // ==================================================
        // 4) Cargar tarea
        // ==================================================
        $task = $this->tareaModel->find($taskId);
        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        $estadoActual = (int)($task['id_estado_tarea'] ?? 0);

        // ==================================================
        // 5) No permitir si ya está cerrada
        // ==================================================
        if (in_array($estadoActual, [
            $this->estadoRealizada,
            $this->estadoNoRealizada,
            $this->estadoCancelada
        ], true)) {
            return ['success' => false, 'error' => 'Esta tarea ya está cerrada y no se puede modificar.'];
        }

        // Si ya está en revisión, no duplicar
        if ($estadoActual === $this->estadoEnRevision) {
            return ['success' => false, 'error' => 'Esta tarea ya está en revisión.'];
        }

        // ==================================================
        // 6) Conexión / contexto
        // ==================================================
        $db = Database::connect();

        // Scope del usuario actual (solo para detectar super)
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);
        $isSuperUser = (($scope['mode'] ?? '') === 'super');

        // ==================================================
        // 7) Obtener asignados REALES del batch
        //    (regla cadena jefe inmediato aplica a TODOS)
        // ==================================================
        $groupRows = $this->getBatchRowsForTask($task);

        $assignees = [];
        foreach ($groupRows as $r) {
            $aid = (int)($r['asignado_a'] ?? 0);
            if ($aid > 0) $assignees[] = $aid;
        }
        $assignees = array_values(array_unique($assignees));

        if (empty($assignees)) {
            // fallback: al menos el asignado_a actual
            $a = (int)($task['asignado_a'] ?? 0);
            if ($a > 0) $assignees = [$a];
        }

        // ==================================================
        // 8) Identidad del actor
        // - isAssignee: solo si el currentUser es asignado de ESA fila
        // - isDirectSupervisorOfAll: solo si es supervisor directo de TODOS los asignados del batch
        // ==================================================
        $asignadoA = (int)($task['asignado_a'] ?? 0);
        $isAssignee = ($asignadoA === $currentUserId);

        $isDirectSupervisorOfAll = false;
        if ($isSuperUser) {
            $isDirectSupervisorOfAll = true;
        } else {
            $ok = true;

            foreach ($assignees as $assigneeId) {
                $rowSup = $db->query(
                    'SELECT id_supervisor
                   FROM public."USER"
                  WHERE id_user = ?
                  LIMIT 1',
                    [(int)$assigneeId]
                )->getRowArray();

                $supId = (int)($rowSup['id_supervisor'] ?? 0);

                if (!($supId > 0 && $supId === $currentUserId)) {
                    $ok = false;
                    break;
                }
            }

            $isDirectSupervisorOfAll = $ok;
        }

        // ==================================================
        // 9) Autorización final
        // ✅ Solo:
        // - asignado => solicita revisión
        // - supervisor directo de TODOS => aplica directo
        // - super => aplica directo
        // ==================================================
        if (!$isAssignee && !$isDirectSupervisorOfAll && !$isSuperUser) {
            return ['success' => false, 'error' => 'No autorizado para solicitar o aplicar este cambio.'];
        }

        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        // ==================================================
        // 10) CASO A: Supervisor directo (de todos) / Super
        // ==================================================
        if ($isDirectSupervisorOfAll || $isSuperUser) {

            // --------------------------------------------------
            // A1) Cancelación directa (NO toca edit_count)
            // --------------------------------------------------
            if ($action === 'cancel') {

                $payload = [
                    'id_estado_tarea'            => $this->estadoCancelada,
                    'completed_at'               => null,

                    // Limpieza total de revisión
                    'review_requested_state'     => null,
                    'review_requested_at'        => null,
                    'review_action'              => null,
                    'review_reason'              => null,
                    'review_requested_by'        => null,
                    'review_requested_fecha_fin' => null,
                    'review_previous_state'      => null,

                    // Auditoría
                    'approved_by'                => $currentUserId,
                    'approved_at'                => $now,
                ];

                try {
                    $db->table('public.tareas')
                        ->where('id_tarea', $taskId)
                        ->update($payload);
                } catch (\Throwable $e) {
                    return ['success' => false, 'error' => 'No se pudo cancelar la tarea directamente.'];
                }

                return ['success' => true, 'message' => 'La tarea fue cancelada directamente por el supervisor.'];
            }

            // --------------------------------------------------
            // A2) Cambio de fecha directo
            // ✅ Aquí SÍ cuenta edit_count (porque ya se aprobó)
            // ✅ Pero si edit_count >= 3, BLOQUEA (no deja cambiar más)
            // --------------------------------------------------
            if ($action === 'date_change') {

                if (trim((string)$requestedEndRaw) === '') {
                    return ['success' => false, 'error' => 'Debes seleccionar la nueva fecha fin.'];
                }

                // ✅ Bloqueo por máximo 3 cambios
                if ($this->taskColumnExists('edit_count')) {
                    $currentEditCount = (int)($task['edit_count'] ?? 0);
                    if ($currentEditCount >= 3) {
                        return ['success' => false, 'error' => 'Esta tarea ya alcanzó el máximo de 3 cambios de fecha.'];
                    }
                }

                $startRaw = (string)($task['fecha_inicio'] ?? '');
                $startDt  = $this->parseLocalDateTime($startRaw);
                $endDt    = $this->parseLocalDateTime((string)$requestedEndRaw);

                if (!$startDt) return ['success' => false, 'error' => 'No se pudo leer la fecha inicio actual.'];
                if (!$endDt)   return ['success' => false, 'error' => 'Fecha fin solicitada inválida.'];

                if ($endDt < $startDt) {
                    return ['success' => false, 'error' => 'La fecha fin solicitada no puede ser menor a la fecha inicio.'];
                }

                $today = $this->todayKey();
                if ($this->dayKey($endDt) < $today) {
                    return ['success' => false, 'error' => 'La fecha fin solicitada no puede ser anterior a la fecha actual.'];
                }

                // Recalcular prioridad
                $newPriority = $this->autoPriorityFromEnd(
                    $endDt,
                    (int)($task['id_prioridad'] ?? 0)
                );

                // ✅ edit_count solo si cambió realmente (por minuto)
                $payloadEditCount = null;
                if ($this->taskColumnExists('edit_count')) {
                    $taskEndDt = $this->parseLocalDateTime((string)($task['fecha_fin'] ?? ''));
                    $changed = true;

                    if ($taskEndDt && $this->sameMinute($taskEndDt, $endDt)) {
                        $changed = false;
                    }

                    if ($changed) {
                        $payloadEditCount = ((int)($task['edit_count'] ?? 0)) + 1;
                    }
                }

                $payload = [
                    'fecha_fin'                  => $this->toDbDateTime($endDt),
                    'id_prioridad'               => $newPriority,

                    // Mantener estado actual
                    'id_estado_tarea'            => $estadoActual,
                    'completed_at'               => null,

                    // Limpieza total de revisión
                    'review_requested_state'     => null,
                    'review_requested_at'        => null,
                    'review_action'              => null,
                    'review_reason'              => null,
                    'review_requested_by'        => null,
                    'review_requested_fecha_fin' => null,
                    'review_previous_state'      => null,

                    // Auditoría
                    'approved_by'                => $currentUserId,
                    'approved_at'                => $now,
                ];

                if ($payloadEditCount !== null) {
                    // ✅ Si aquí ya quedó en 4, igual se bloqueó arriba porque >=3 antes de sumar.
                    $payload['edit_count'] = $payloadEditCount;
                }

                try {
                    $db->table('public.tareas')
                        ->where('id_tarea', $taskId)
                        ->update($payload);
                } catch (\Throwable $e) {
                    return ['success' => false, 'error' => 'No se pudo cambiar la fecha directamente.'];
                }

                return ['success' => true, 'message' => 'La fecha fue actualizada directamente por el supervisor.'];
            }
        }

        // ==================================================
        // 11) CASO B: Usuario asignado -> manda a REVISIÓN
        // (NO toca edit_count)
        // ==================================================
        if (!$isAssignee) {
            return ['success' => false, 'error' => 'Solo el usuario asignado puede solicitar revisión.'];
        }

        // Supervisor directo del asignado (solo para validar que exista)
        $rowSup = $db->query(
            'SELECT id_supervisor
           FROM public."USER"
          WHERE id_user = ?
          LIMIT 1',
            [$currentUserId]
        )->getRowArray();

        $directSupervisorId = (int)($rowSup['id_supervisor'] ?? 0);
        if ($directSupervisorId <= 0) {
            return ['success' => false, 'error' => 'No tienes supervisor asignado (id_supervisor).'];
        }

        $payload = [
            // Pasa a revisión
            'id_estado_tarea'        => $this->estadoEnRevision,

            // Guardar estado previo
            'review_previous_state'  => $estadoActual,

            // Guardar solicitud
            'review_action'          => $action,
            'review_reason'          => $reason,
            'review_requested_by'    => $currentUserId,
            'review_requested_at'    => $now,

            // Limpiar aprobación previa
            'approved_by'            => null,
            'approved_at'            => null,
        ];

        // --------------------------------------------------
        // B1) date_change solicitado (NO toca edit_count)
        // --------------------------------------------------
        if ($action === 'date_change') {

            if (trim((string)$requestedEndRaw) === '') {
                return ['success' => false, 'error' => 'Debes seleccionar la nueva fecha fin.'];
            }

            $startRaw = (string)($task['fecha_inicio'] ?? '');
            $startDt  = $this->parseLocalDateTime($startRaw);
            $endDt    = $this->parseLocalDateTime((string)$requestedEndRaw);

            if (!$startDt) return ['success' => false, 'error' => 'No se pudo leer la fecha inicio actual.'];
            if (!$endDt)   return ['success' => false, 'error' => 'Fecha fin solicitada inválida.'];

            if ($endDt < $startDt) {
                return ['success' => false, 'error' => 'La fecha fin solicitada no puede ser menor a la fecha inicio.'];
            }

            $today = $this->todayKey();
            if ($this->dayKey($endDt) < $today) {
                return ['success' => false, 'error' => 'La fecha fin solicitada no puede ser anterior a la fecha actual.'];
            }

            $payload['review_requested_fecha_fin'] = $this->toDbDateTime($endDt);
            $payload['review_requested_state']     = $estadoActual;
        }

        // --------------------------------------------------
        // B2) cancel solicitado
        // --------------------------------------------------
        if ($action === 'cancel') {
            $payload['review_requested_state']     = $this->estadoCancelada;
            $payload['review_requested_fecha_fin'] = null;
        }

        // --------------------------------------------------
        // Guardar solicitud
        // --------------------------------------------------
        try {
            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update($payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'No se pudo enviar a revisión.'];
        }

        return ['success' => true, 'message' => 'Solicitud enviada a revisión de tu supervisor.'];
    }
    public function reviewBatch(
        array $taskIds,
        string $action,
        int $currentUserId,
        int $currentUserAreaId
    ): array {

        // ==================================================
        // 0) Validaciones de columnas necesarias
        // ==================================================
        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        if (method_exists($this, 'hasExtendedReviewColumns') && !$this->hasExtendedReviewColumns()) {
            return [
                'success' => false,
                'error'   => 'Faltan columnas extendidas de revisión (review_action, review_reason, review_requested_by, review_requested_fecha_fin, review_previous_state). Ejecuta el ALTER TABLE.',
            ];
        }

        // ==================================================
        // 1) Normalizar IDs
        // ==================================================
        $clean = [];
        foreach ($taskIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[] = $n;
            }
        }
        $clean = array_values(array_unique($clean));

        if (empty($clean)) {
            return ['success' => false, 'error' => 'No seleccionaste tareas.'];
        }

        // ==================================================
        // 2) Validar acción
        // ==================================================
        if (!in_array($action, ['approve', 'cancel_request', 'force_not_done'], true)) {
            return ['success' => false, 'error' => 'Acción inválida.'];
        }

        // ==================================================
        // 3) Scope para detectar gerencia
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        /**
         * ==================================================
         * Aquí guardaremos tareas que al volver al estado anterior
         * quedaron vencidas y necesitan actualización de hora.
         * ==================================================
         */
        $expiredTasks = [];

        /**
         * ==================================================
         * Contador real de tareas procesadas.
         * ==================================================
         */
        $processed = 0;

        // ==================================================
        // 4) Transacción
        // ==================================================
        $db->transStart();

        try {
            foreach ($clean as $taskId) {

                // --------------------------------------------------
                // 4.1) Cargar tarea
                // --------------------------------------------------
                $task = $this->tareaModel->find((int) $taskId);
                if (!$task) {
                    continue;
                }

                // Solo procesamos si está en revisión
                if ((int)($task['id_estado_tarea'] ?? 0) !== $this->estadoEnRevision) {
                    continue;
                }

                // --------------------------------------------------
                // 4.2) Permisos: SOLO supervisor directo o super
                // --------------------------------------------------
                $asignadoA = (int) ($task['asignado_a'] ?? 0);

                $rowSup = $db->query(
                    'SELECT id_supervisor
                 FROM public."USER"
                 WHERE id_user = ?
                 LIMIT 1',
                    [$asignadoA]
                )->getRowArray();

                $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

                $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);
                $isSuperUser        = (($scope['mode'] ?? '') === 'super');

                if (!$isDirectSupervisor && !$isSuperUser) {
                    continue;
                }

                // --------------------------------------------------
                // 4.3) Datos de revisión
                // --------------------------------------------------
                $reviewAction = trim((string) ($task['review_action'] ?? ''));
                $prevState    = (int) ($task['review_previous_state'] ?? 0);

                if ($prevState <= 0) {
                    $prevState = $this->estadoEnProceso;
                }

                $requestedBy = (int)($task['review_requested_by'] ?? 0);
                $decision    = ($action === 'cancel_request') ? 'rejected' : 'approved';

                // ==================================================
                // 4.4) CANCELAR SOLICITUD
                // - Devuelve la tarea al estado anterior
                // - Limpia la revisión pendiente
                // - Si quedó vencida, la devolvemos al frontend
                //   para actualizar solo la hora
                // ==================================================
                if ($action === 'cancel_request') {

                    $payload = [
                        'id_estado_tarea'            => $prevState,
                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,
                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                        'completed_at'               => null,
                    ];

                    $db->table('public.tareas')
                        ->where('id_tarea', (int)$taskId)
                        ->update($payload);

                    /**
                     * ============================================
                     * Verificamos si al volver al estado anterior
                     * la tarea ya quedó vencida.
                     *
                     * OJO:
                     * Aquí NO la marcamos como no realizada.
                     * Solo la reportamos al frontend.
                     * ============================================
                     */
                    $taskFechaFin = (string)($task['fecha_fin'] ?? '');

                    if (
                        !$this->isClosedEstado($prevState)
                        && $taskFechaFin !== ''
                        && $this->isExpiredByNow($taskFechaFin)
                    ) {
                        $expiredTasks[] = [
                            'id_tarea'  => (int)$taskId,
                            'titulo'    => (string)($task['titulo'] ?? 'Actividad'),
                            'fecha_fin' => $taskFechaFin,
                            'estado'    => $prevState,
                            'message'   => 'La tarea volvió a su estado anterior, pero ya está caducada. Debes actualizar solo la hora o marcarla como no realizada.',
                        ];
                    }

                    if ($requestedBy > 0) {
                        $details = [
                            'id_tarea'            => (int)$taskId,
                            'titulo'              => (string)($task['titulo'] ?? ''),
                            'decision'            => 'rejected',
                            'action'              => 'cancel_request',
                            'requested_state'     => $task['review_requested_state'] ?? null,
                            'previous_state'      => $task['review_previous_state'] ?? null,
                            'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                            'requested_reason'    => $task['review_reason'] ?? null,
                            'result_estado'       => $payload['id_estado_tarea'] ?? null,
                            'result_fecha_fin'    => null,
                        ];

                        $this->insertDecisionLog(
                            (int)$taskId,
                            $requestedBy,
                            (int)$currentUserId,
                            'rejected',
                            'cancel_request',
                            $details
                        );

                        $this->updateLatestReviewLogDecision(
                            (int)$taskId,
                            $requestedBy,
                            'rejected',
                            (int)$currentUserId,
                            null
                        );
                    }

                    $processed++;
                    continue;
                }

                // ==================================================
                // 4.5) FORZAR COMO NO REALIZADA
                // ==================================================
                if ($action === 'force_not_done') {

                    $payload = [
                        'id_estado_tarea'            => $this->estadoNoRealizada,
                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,
                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                        'completed_at'               => $now,
                    ];

                    $db->table('public.tareas')
                        ->where('id_tarea', (int)$taskId)
                        ->update($payload);

                    if ($requestedBy > 0) {
                        $details = [
                            'id_tarea'            => (int)$taskId,
                            'titulo'              => (string)($task['titulo'] ?? ''),
                            'decision'            => 'approved',
                            'action'              => 'force_not_done',
                            'requested_state'     => $task['review_requested_state'] ?? null,
                            'previous_state'      => $task['review_previous_state'] ?? null,
                            'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                            'requested_reason'    => $task['review_reason'] ?? null,
                            'result_estado'       => $payload['id_estado_tarea'] ?? null,
                            'result_fecha_fin'    => null,
                        ];

                        $this->insertDecisionLog(
                            (int)$taskId,
                            $requestedBy,
                            (int)$currentUserId,
                            'approved',
                            'force_not_done',
                            $details
                        );

                        $this->updateLatestReviewLogDecision(
                            (int)$taskId,
                            $requestedBy,
                            'approved',
                            (int)$currentUserId,
                            null
                        );
                    }

                    $processed++;
                    continue;
                }

                // ==================================================
                // 4.6) APROBAR
                // ==================================================

                // --------------------------------------------------
                // A) Cancelación solicitada => Cancelada
                // --------------------------------------------------
                if ($reviewAction === 'cancel') {

                    $payload = [
                        'id_estado_tarea'            => $this->estadoCancelada,
                        'completed_at'               => null,

                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,

                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                    ];

                    $db->table('public.tareas')
                        ->where('id_tarea', (int)$taskId)
                        ->update($payload);

                    if ($requestedBy > 0) {
                        $details = [
                            'id_tarea'            => (int)$taskId,
                            'titulo'              => (string)($task['titulo'] ?? ''),
                            'decision'            => $decision,
                            'action'              => $reviewAction,
                            'requested_state'     => $task['review_requested_state'] ?? null,
                            'previous_state'      => $task['review_previous_state'] ?? null,
                            'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                            'requested_reason'    => $task['review_reason'] ?? null,
                            'result_estado'       => $payload['id_estado_tarea'] ?? null,
                            'result_fecha_fin'    => null,
                        ];

                        $this->insertDecisionLog(
                            (int)$taskId,
                            $requestedBy,
                            (int)$currentUserId,
                            $decision,
                            $reviewAction,
                            $details
                        );

                        $this->updateLatestReviewLogDecision(
                            (int)$taskId,
                            $requestedBy,
                            $decision,
                            (int)$currentUserId,
                            null
                        );
                    }

                    $processed++;
                    continue;
                }

                // --------------------------------------------------
                // B) Cambio de fecha solicitado
                // SOLO aquí incrementa edit_count
                // --------------------------------------------------
                if ($reviewAction === 'date_change') {

                    $reqFin = trim((string) ($task['review_requested_fecha_fin'] ?? ''));

                    if ($reqFin === '') {
                        $payload = [
                            'id_estado_tarea'            => $prevState,

                            'review_requested_state'     => null,
                            'review_requested_at'        => null,
                            'review_action'              => null,
                            'review_reason'              => null,
                            'review_requested_by'        => null,
                            'review_requested_fecha_fin' => null,
                            'review_previous_state'      => null,

                            'approved_by'                => $currentUserId,
                            'approved_at'                => $now,
                            'completed_at'               => null,
                        ];

                        $db->table('public.tareas')
                            ->where('id_tarea', (int)$taskId)
                            ->update($payload);

                        if ($requestedBy > 0) {
                            $details = [
                                'id_tarea'            => (int)$taskId,
                                'titulo'              => (string)($task['titulo'] ?? ''),
                                'decision'            => $decision,
                                'action'              => $reviewAction,
                                'requested_state'     => $task['review_requested_state'] ?? null,
                                'previous_state'      => $task['review_previous_state'] ?? null,
                                'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                                'requested_reason'    => $task['review_reason'] ?? null,
                                'result_estado'       => $payload['id_estado_tarea'] ?? null,
                                'result_fecha_fin'    => null,
                            ];

                            $this->insertDecisionLog(
                                (int)$taskId,
                                $requestedBy,
                                (int)$currentUserId,
                                $decision,
                                $reviewAction,
                                $details
                            );

                            $this->updateLatestReviewLogDecision(
                                (int)$taskId,
                                $requestedBy,
                                $decision,
                                (int)$currentUserId,
                                null
                            );
                        }

                        $processed++;
                        continue;
                    }

                    $editCount = (int) ($task['edit_count'] ?? 0);

                    if ($editCount >= 3) {
                        $payload = [
                            'id_estado_tarea'            => $prevState,

                            'review_requested_state'     => null,
                            'review_requested_at'        => null,
                            'review_action'              => null,
                            'review_reason'              => null,
                            'review_requested_by'        => null,
                            'review_requested_fecha_fin' => null,
                            'review_previous_state'      => null,

                            'approved_by'                => $currentUserId,
                            'approved_at'                => $now,
                            'completed_at'               => null,
                        ];

                        $db->table('public.tareas')
                            ->where('id_tarea', (int)$taskId)
                            ->update($payload);

                        if ($requestedBy > 0) {
                            $details = [
                                'id_tarea'            => (int)$taskId,
                                'titulo'              => (string)($task['titulo'] ?? ''),
                                'decision'            => 'rejected',
                                'action'              => $reviewAction,
                                'requested_state'     => $task['review_requested_state'] ?? null,
                                'previous_state'      => $task['review_previous_state'] ?? null,
                                'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                                'requested_reason'    => $task['review_reason'] ?? null,
                                'result_estado'       => $payload['id_estado_tarea'] ?? null,
                                'result_fecha_fin'    => null,
                            ];

                            $this->insertDecisionLog(
                                (int)$taskId,
                                $requestedBy,
                                (int)$currentUserId,
                                'rejected',
                                $reviewAction,
                                $details
                            );

                            $this->updateLatestReviewLogDecision(
                                (int)$taskId,
                                $requestedBy,
                                'rejected',
                                (int)$currentUserId,
                                'Máximo de 3 cambios de fecha alcanzado.'
                            );
                        }

                        $processed++;
                        continue;
                    }

                    $endDt = $this->parseLocalDateTime($reqFin);

                    $newPriority = (int) ($task['id_prioridad'] ?? 1);
                    if ($endDt) {
                        $newPriority = $this->autoPriorityFromEnd($endDt, (int)($task['id_prioridad'] ?? 0));
                    }

                    $payload = [
                        'fecha_fin'                  => $endDt ? $this->toDbDateTime($endDt) : $reqFin,
                        'id_prioridad'               => $newPriority,
                        'id_estado_tarea'            => $prevState,

                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,

                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                        'completed_at'               => null,
                    ];

                    if ($this->taskColumnExists('edit_count')) {
                        $payload['edit_count'] = $editCount + 1;
                    }

                    $db->table('public.tareas')
                        ->where('id_tarea', (int)$taskId)
                        ->update($payload);

                    if ($requestedBy > 0) {
                        $details = [
                            'id_tarea'            => (int)$taskId,
                            'titulo'              => (string)($task['titulo'] ?? ''),
                            'decision'            => $decision,
                            'action'              => $reviewAction,
                            'requested_state'     => $task['review_requested_state'] ?? null,
                            'previous_state'      => $task['review_previous_state'] ?? null,
                            'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                            'requested_reason'    => $task['review_reason'] ?? null,
                            'result_estado'       => $payload['id_estado_tarea'] ?? null,
                            'result_fecha_fin'    => $payload['fecha_fin'] ?? null,
                        ];

                        $this->insertDecisionLog(
                            (int)$taskId,
                            $requestedBy,
                            (int)$currentUserId,
                            $decision,
                            $reviewAction,
                            $details
                        );

                        $this->updateLatestReviewLogDecision(
                            (int)$taskId,
                            $requestedBy,
                            $decision,
                            (int)$currentUserId,
                            null
                        );
                    }

                    $processed++;
                    continue;
                }

                // --------------------------------------------------
                // C) Legacy / compatibilidad (realizada / no realizada)
                // --------------------------------------------------
                $editCount = (int) ($task['edit_count'] ?? 0);
                $forcedNoRealizada = ($editCount > $this->maxEditsForRealizada);

                $req = (int) ($task['review_requested_state'] ?? 0);

                $finalEstado = in_array($req, [$this->estadoRealizada, $this->estadoNoRealizada], true)
                    ? $req
                    : $this->estadoNoRealizada;

                if ($forcedNoRealizada) {
                    $finalEstado = $this->estadoNoRealizada;
                }

                $payload = [
                    'id_estado_tarea'            => $finalEstado,
                    'review_requested_state'     => null,
                    'review_requested_at'        => null,

                    'review_action'              => null,
                    'review_reason'              => null,
                    'review_requested_by'        => null,
                    'review_requested_fecha_fin' => null,
                    'review_previous_state'      => null,

                    'approved_by'                => $currentUserId,
                    'approved_at'                => $now,

                    'completed_at' => in_array($finalEstado, [$this->estadoRealizada, $this->estadoNoRealizada], true)
                        ? $now
                        : null,
                ];

                $db->table('public.tareas')
                    ->where('id_tarea', (int)$taskId)
                    ->update($payload);

                if ($requestedBy > 0) {
                    $details = [
                        'id_tarea'            => (int)$taskId,
                        'titulo'              => (string)($task['titulo'] ?? ''),
                        'decision'            => $decision,
                        'action'              => ($reviewAction !== '' ? $reviewAction : 'state'),
                        'requested_state'     => $task['review_requested_state'] ?? null,
                        'previous_state'      => $task['review_previous_state'] ?? null,
                        'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                        'requested_reason'    => $task['review_reason'] ?? null,
                        'result_estado'       => $payload['id_estado_tarea'] ?? null,
                        'result_fecha_fin'    => null,
                    ];

                    $this->insertDecisionLog(
                        (int)$taskId,
                        $requestedBy,
                        (int)$currentUserId,
                        $decision,
                        ($reviewAction !== '' ? $reviewAction : 'state'),
                        $details
                    );

                    $this->updateLatestReviewLogDecision(
                        (int)$taskId,
                        $requestedBy,
                        $decision,
                        (int)$currentUserId,
                        null
                    );
                }

                $processed++;
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return [
                'success' => false,
                'error'   => 'Error procesando revisión: ' . $e->getMessage(),
            ];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'No se pudo completar la transacción.'];
        }

        if ($processed <= 0) {
            return [
                'success' => false,
                'error'   => 'No se procesó ninguna tarea. Verifica que estén en revisión y que seas el supervisor directo.',
            ];
        }

        // ==================================================
        // RESPUESTA FINAL
        // ==================================================
        if ($action === 'cancel_request') {
            return [
                'success'           => true,
                'message'           => 'La solicitud fue cancelada y la tarea volvió a su estado anterior.',
                'needs_time_update' => !empty($expiredTasks),
                'expired_tasks'     => $expiredTasks,
            ];
        }

        if ($action === 'force_not_done') {
            return [
                'success'           => true,
                'message'           => 'La tarea fue marcada como no realizada.',
                'needs_time_update' => false,
                'expired_tasks'     => [],
            ];
        }

        return [
            'success'           => true,
            'message'           => 'Las tareas fueron aprobadas correctamente.',
            'needs_time_update' => false,
            'expired_tasks'     => [],
        ];
    }
    /**
     * buildHistorySeries()
     *
     * ✅ Construye la serie: últimas 4 semanas + actual
     * usando el mismo motor calcSatisfaccionChain()
     *
     * Retorna:
     * [
     *   ['label' => 'YYYY-mm-dd→YYYY-mm-dd', 'value' => 62.5],
     *   ...
     * ]
     */
    private function buildHistorySeries(
        array $userIds,
        \DateTimeImmutable $currentWeekStart,
        ?int $divisionId = null,
        ?int $areaId = null
    ): array {
        $series = [];

        // 4 semanas previas
        for ($i = 4; $i >= 1; $i--) {
            $wStart = $currentWeekStart->modify("-{$i} week")->setTime(0, 0, 0);
            $wEndEx = $wStart->modify('+7 days')->setTime(0, 0, 0);

            $data = $this->calcSatisfaccionChain($userIds, $wStart, $wEndEx, $divisionId, $areaId);

            $series[] = [
                'label' => $this->buildWeekLabel($wStart),
                'value' => (float) ($data['porcentaje'] ?? 0),
            ];
        }

        // semana actual
        $curEndEx = $currentWeekStart->modify('+7 days')->setTime(0, 0, 0);
        $curData  = $this->calcSatisfaccionChain($userIds, $currentWeekStart, $curEndEx, $divisionId, $areaId);

        $series[] = [
            'label' => $this->buildWeekLabel($currentWeekStart),
            'value' => (float) ($curData['porcentaje'] ?? 0),
        ];

        return $series;
    }
    /**
     * =========================================================
     * HISTÓRICO OFICIAL DESDE TABLAS:
     * - public.historico_division
     * - public.historico_area
     *
     * ✅ Objetivo:
     * - Llenar "history" (últimas 4 + actual) con valores REALES guardados
     * - Llenar "avg_4_weeks" con promedio REAL de esas 4 semanas previas
     *
     * ✅ No invasivo:
     * - Si NO hay fila en historico_* para una semana, cae al motor actual
     *   (calcSatisfaccionChain) para no dejarlo vacío.
     * =========================================================
     */

    /**
     * Devuelve el miércoles de corte (Y-m-d) para un weekStart (jueves).
     * weekStart = jueves 00:00
     * corte = weekStart + 6 días (miércoles)
     */
    private function cutKeyFromWeekStart(\DateTimeImmutable $weekStart): string
    {
        return $weekStart->modify('+6 days')->setTime(0, 0, 0)->format('Y-m-d');
    }

    /**
     * Lee satisfaccion desde historico_division (si existe).
     * Retorna null si no hay fila.
     */
    private function fetchHistoricoDivisionPct(int $divisionId, string $cutKeyYmd): ?float
    {
        try {
            $db = Database::connect();

            $row = $db->query(
                'SELECT satisfaccion
             FROM public.historico_division
             WHERE id_division = ?
               AND semana = ?
             LIMIT 1',
                [$divisionId, $cutKeyYmd]
            )->getRowArray();

            if (!$row) {
                return null;
            }

            return (float)($row['satisfaccion'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lee satisfaccion desde historico_area (si existe).
     * Retorna null si no hay fila.
     */
    private function fetchHistoricoAreaPct(int $areaId, string $cutKeyYmd): ?float
    {
        try {
            $db = Database::connect();

            $row = $db->query(
                'SELECT satisfaccion
             FROM public.historico_area
             WHERE id_area = ?
               AND semana = ?
             LIMIT 1',
                [$areaId, $cutKeyYmd]
            )->getRowArray();

            if (!$row) {
                return null;
            }

            return (float)($row['satisfaccion'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * Lee satisfaccion desde public.historico (PERSONAL).
     * Retorna null si no hay fila.
     *
     * IMPORTANTE:
     * - historico.semana = miércoles de corte (cutKey)
     * - historico.id_user = usuario
     * - historico.satisfaccion = porcentaje ya guardado
     */
    private function fetchHistoricoPersonalPct(int $userId, string $cutKeyYmd): ?float
    {
        try {
            $db = Database::connect();

            $row = $db->query(
                'SELECT satisfaccion
             FROM public.historico
             WHERE id_user = ?
               AND semana  = ?
             LIMIT 1',
                [$userId, $cutKeyYmd]
            )->getRowArray();

            if (!$row) {
                return null;
            }

            // Puede venir null en BD si no se guardó en esa semana
            if ($row['satisfaccion'] === null) {
                return null;
            }

            return (float) ($row['satisfaccion'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Construye "history" (últimas 4 + actual) tomando:
     * 1) historico_division / historico_area si existe fila
     * 2) si no existe => fallback a calcSatisfaccionChain()
     */
    /**
     * Construye "history" (últimas 4 + actual) tomando:
     * 1) historico_division / historico_area / historico (personal) si existe fila
     * 2) si no existe => fallback a calcSatisfaccionChain()
     */
    private function buildHistorySeriesFromHistorico(
        array $userIds,
        \DateTimeImmutable $currentWeekStart,
        ?int $divisionId = null,
        ?int $areaId = null
    ): array {
        $series = [];

        /**
         * =========================================================
         * ✅ Detectar si es PERSONAL
         * - Personal = no divisionId y no areaId
         * - Para personal necesitamos 1 solo id_user
         * =========================================================
         */
        $isPersonal = (($divisionId === null || $divisionId <= 0) && ($areaId === null || $areaId <= 0));

        $personalUserId = null;
        if ($isPersonal) {
            $tmp = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn($x) => $x > 0)));
            if (count($tmp) === 1) {
                $personalUserId = (int) $tmp[0];
            }
        }

        // ---------------------------------------------------------
        // Recorremos últimas 4 semanas previas (en orden)
        // ---------------------------------------------------------
        for ($i = 4; $i >= 1; $i--) {

            $wStart = $currentWeekStart->modify("-{$i} week")->setTime(0, 0, 0);
            $wEndEx = $wStart->modify('+7 days')->setTime(0, 0, 0);

            // miércoles de corte (clave para historico_*.semana e historico.semana)
            $cutKey = $this->cutKeyFromWeekStart($wStart);

            // 1) intentar histórico oficial
            $pct = null;

            if ($divisionId !== null && $divisionId > 0) {
                // ✅ División
                $pct = $this->fetchHistoricoDivisionPct($divisionId, $cutKey);
            } elseif ($areaId !== null && $areaId > 0) {
                // ✅ Área
                $pct = $this->fetchHistoricoAreaPct($areaId, $cutKey);
            } elseif ($isPersonal && $personalUserId !== null && $personalUserId > 0) {
                // ✅ PERSONAL: public.historico.satisfaccion
                $pct = $this->fetchHistoricoPersonalPct($personalUserId, $cutKey);
            }

            // 2) fallback si no hay histórico (no dejamos vacío)
            if ($pct === null) {
                $data = $this->calcSatisfaccionChain($userIds, $wStart, $wEndEx, $divisionId, $areaId);
                $pct  = (float) ($data['porcentaje'] ?? 0);
            }

            $series[] = [
                'label' => $this->buildWeekLabel($wStart),
                'value' => (float) number_format(max(0, min(100, $pct)), 2, '.', ''),
            ];
        }

        // ---------------------------------------------------------
        // Semana actual (última barra)
        // ---------------------------------------------------------
        $curStart  = $currentWeekStart->setTime(0, 0, 0);
        $curEndEx  = $curStart->modify('+7 days')->setTime(0, 0, 0);
        $curCutKey = $this->cutKeyFromWeekStart($curStart);

        $curPct = null;

        if ($divisionId !== null && $divisionId > 0) {
            $curPct = $this->fetchHistoricoDivisionPct($divisionId, $curCutKey);
        } elseif ($areaId !== null && $areaId > 0) {
            $curPct = $this->fetchHistoricoAreaPct($areaId, $curCutKey);
        } elseif ($isPersonal && $personalUserId !== null && $personalUserId > 0) {
            $curPct = $this->fetchHistoricoPersonalPct($personalUserId, $curCutKey);
        }

        if ($curPct === null) {
            $curData = $this->calcSatisfaccionChain($userIds, $curStart, $curEndEx, $divisionId, $areaId);
            $curPct  = (float) ($curData['porcentaje'] ?? 0);
        }

        $series[] = [
            'label' => $this->buildWeekLabel($curStart),
            'value' => (float) number_format(max(0, min(100, $curPct)), 2, '.', ''),
        ];

        return $series;
    }

    /**
     * Promedio últimas 4 semanas (NO incluye la actual),
     * usando histórico oficial si existe (y fallback si no).
     */
    /**
     * Promedio últimas 4 semanas (NO incluye la actual),
     * usando histórico oficial si existe (y fallback si no).
     *
     * ✅ División -> historico_division
     * ✅ Área     -> historico_area
     * ✅ Personal -> historico (columna satisfaccion)
     */
    private function getLast4WeeksAverageFromHistorico(
        array $userIds,
        \DateTimeImmutable $currentWeekStart,
        ?int $divisionId = null,
        ?int $areaId = null
    ): float {
        $sum = 0.0;
        $cnt = 0;

        // ✅ Detectar PERSONAL
        $isPersonal = (($divisionId === null || $divisionId <= 0) && ($areaId === null || $areaId <= 0));

        $personalUserId = null;
        if ($isPersonal) {
            $tmp = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn($x) => $x > 0)));
            if (count($tmp) === 1) {
                $personalUserId = (int) $tmp[0];
            }
        }

        for ($i = 1; $i <= 4; $i++) {

            $wStart = $currentWeekStart->modify("-{$i} week")->setTime(0, 0, 0);
            $wEndEx = $wStart->modify('+7 days')->setTime(0, 0, 0);

            $cutKey = $this->cutKeyFromWeekStart($wStart);

            $pct = null;

            // 1) Intentar histórico
            if ($divisionId !== null && $divisionId > 0) {
                $pct = $this->fetchHistoricoDivisionPct($divisionId, $cutKey);
            } elseif ($areaId !== null && $areaId > 0) {
                $pct = $this->fetchHistoricoAreaPct($areaId, $cutKey);
            } elseif ($isPersonal && $personalUserId !== null && $personalUserId > 0) {
                $pct = $this->fetchHistoricoPersonalPct($personalUserId, $cutKey);
            }

            // 2) Fallback: calcular si no hay fila histórica
            if ($pct === null) {
                $data = $this->calcSatisfaccionChain($userIds, $wStart, $wEndEx, $divisionId, $areaId);
                $pct  = (float) ($data['porcentaje'] ?? 0);
            }

            $sum += (float) $pct;
            $cnt++;
        }

        return ($cnt > 0) ? round($sum / $cnt, 2) : 0.0;
    }
// =========================================================
// DECISION NOTIFICATIONS (modal para solicitantes)
// =========================================================

    /**
     * Obtiene notificaciones NO VISTAS para el usuario que solicitó la revisión.
     * Usa: tarea_review_decision_log
     */
    public function getDecisionNotifications(int $requestedBy): array
    {
        // ==================================================
        // ✅ Conexión a BD
        // ==================================================
        $db = Database::connect();

        try {
            // ==================================================
            // ✅ Consultar SOLO notificaciones no leídas
            // - requested_by: usuario que hizo la solicitud
            // - seen_by_requester = false: aún no vistas
            // ==================================================
            $rows = $db->table('public.tarea_review_decision_log l')
                ->select([
                    'l.id_log',
                    'l.id_tarea',
                    'l.requested_by',
                    'l.decided_by',
                    'l.decision',
                    'l.action',
                    'l.decided_at',
                    'l.details',
                    'l.seen_by_requester',
                    'u.nombres AS decided_by_nombres',
                    'u.apellidos AS decided_by_apellidos',
                    't.titulo AS titulo_tarea',
                ])
                ->join('public."USER" u', 'u.id_user = l.decided_by', 'left')
                ->join('public.tareas t', 't.id_tarea = l.id_tarea', 'left')
                ->where('l.requested_by', $requestedBy)
                ->where('l.seen_by_requester', false)
                ->orderBy('l.decided_at', 'DESC')
                ->limit(50)
                ->get()
                ->getResultArray();

            // ==================================================
            // ✅ Normalizar salida para la vista
            // ==================================================
            foreach ($rows as &$r) {
                // ----------------------------------------------
                // Nombre del supervisor que aprobó/rechazó
                // ----------------------------------------------
                $fullName = trim(
                    (string)($r['decided_by_nombres'] ?? '') . ' ' .
                        (string)($r['decided_by_apellidos'] ?? '')
                );

                $r['approved_by_nombre'] = ($fullName !== '') ? $fullName : 'Supervisor';

                // ----------------------------------------------
                // Título amigable de la tarea
                // ----------------------------------------------
                $titulo = trim((string)($r['titulo_tarea'] ?? ''));
                $r['titulo'] = ($titulo !== '') ? $titulo : 'Actividad';

                // ----------------------------------------------
                // Decodificar details (jsonb)
                // Puede venir:
                // - array
                // - string JSON
                // - null
                // ----------------------------------------------
                $decodedDetails = [];

                if (isset($r['details']) && $r['details'] !== null && $r['details'] !== '') {
                    if (is_array($r['details'])) {
                        $decodedDetails = $r['details'];
                    } else {
                        $tmp = json_decode((string)$r['details'], true);
                        if (is_array($tmp)) {
                            $decodedDetails = $tmp;
                        }
                    }
                }

                $r['details_array'] = $decodedDetails;

                // ----------------------------------------------
                // Campos útiles ya preparados para la vista
                // ----------------------------------------------
                $r['requested_state']     = $decodedDetails['requested_state'] ?? null;
                $r['previous_state']      = $decodedDetails['previous_state'] ?? null;
                $r['requested_fecha_fin'] = $decodedDetails['requested_fecha_fin'] ?? null;
                $r['requested_reason']    = $decodedDetails['requested_reason'] ?? null;
                $r['result_estado']       = $decodedDetails['result_estado'] ?? null;
                $r['result_fecha_fin']    = $decodedDetails['result_fecha_fin'] ?? null;

                // ----------------------------------------------
                // Compatibles con tu JS / modal actual
                // ----------------------------------------------
                $r['action_label'] = match ((string)($r['action'] ?? '')) {
                    'cancel'      => 'Cancelación',
                    'date_change' => 'Cambio de fecha',
                    'state'       => 'Cambio de estado',
                    default       => 'Solicitud',
                };

                $r['decision_label'] = match (strtolower((string)($r['decision'] ?? ''))) {
                    'approved' => 'APROBADA',
                    'rejected' => 'RECHAZADA',
                    default    => 'PROCESADA',
                };
            }
            unset($r);

            return $rows;
        } catch (\Throwable $e) {
            log_message('error', 'getDecisionNotifications error: ' . $e->getMessage());
            return [];
        }
    }
    /**
     * Marca como vistas TODAS las notificaciones del solicitante.
     */
    public function markDecisionNotificationsAsSeen(int $requestedBy): int
    {
        // ==================================================
        // ✅ Conexión a la base de datos
        // ==================================================
        $db = Database::connect();

        try {
            // ==================================================
            // ✅ Validación mínima
            // ==================================================
            if ($requestedBy <= 0) {
                return 0;
            }

            // ==================================================
            // ✅ Marcar como leídas SOLO las no vistas
            // ==================================================
            $db->table('public.tarea_review_decision_log')
                ->where('requested_by', $requestedBy)
                ->where('seen_by_requester', false)
                ->update([
                    'seen_by_requester' => true,
                ]);

            // ==================================================
            // ✅ Retornar cuántas filas fueron afectadas
            // ==================================================
            return (int) $db->affectedRows();
        } catch (\Throwable $e) {
            // ==================================================
            // ✅ Log de error para depuración
            // ==================================================
            log_message('error', 'markDecisionNotificationsAsSeen error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Inserta una fila en tarea_review_decision_log (jsonb details).
     */
    private function insertDecisionLog(
        int $idTarea,
        int $requestedBy,
        int $decidedBy,
        string $decision,
        string $action,
        array $details
    ): void {
        $db = Database::connect();

        try {
            // ==================================================
            // ✅ Validaciones mínimas
            // ==================================================
            if ($idTarea <= 0 || $requestedBy <= 0 || $decidedBy <= 0) {
                return;
            }

            $decision = strtolower(trim($decision));
            $action   = trim($action);

            if (!in_array($decision, ['approved', 'rejected'], true)) {
                return;
            }

            if ($action === '') {
                $action = 'state';
            }

            $payload = [
                'id_tarea'          => $idTarea,
                'requested_by'      => $requestedBy,
                'decided_by'        => $decidedBy,
                'decision'          => $decision,
                'action'            => $action,
                'decided_at'        => date('Y-m-d H:i:sP'),
                'details'           => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'seen_by_requester' => false,
            ];

            $db->table('public.tarea_review_decision_log')->insert($payload);
        } catch (\Throwable $e) {
            log_message('error', 'insertDecisionLog error: ' . $e->getMessage());
        }
    }

    /**
     * Completa la decisión en el ÚLTIMO registro de tarea_review_log
     * (deja constancia del resultado).
     */
    private function updateLatestReviewLogDecision(
        int $idTarea,
        int $requestedBy,
        string $decision,
        int $decidedBy,
        ?string $decidedReason = null
    ): void {
        $db = Database::connect();

        try {
            // ==================================================
            // ✅ Validaciones mínimas
            // ==================================================
            if ($idTarea <= 0 || $requestedBy <= 0 || $decidedBy <= 0) {
                return;
            }

            $decision = strtolower(trim($decision));

            if (!in_array($decision, ['approved', 'rejected'], true)) {
                return;
            }

            // ==================================================
            // ✅ Verificar si existe la tabla tarea_review_log
            // para no romper si en algún ambiente no existe
            // ==================================================
            $tableExists = $db->query("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'tarea_review_log'
            LIMIT 1
        ")->getRowArray();

            if (!$tableExists) {
                return;
            }

            // ==================================================
            // ✅ Buscar el último log de revisión de esa tarea
            // y de ese usuario solicitante
            // ==================================================
            $last = $db->table('public.tarea_review_log')
                ->select('id_review_log')
                ->where('id_tarea', $idTarea)
                ->where('requested_by', $requestedBy)
                ->orderBy('requested_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!$last || empty($last['id_review_log'])) {
                return;
            }

            // ==================================================
            // ✅ Actualizar decisión final
            // ==================================================
            $db->table('public.tarea_review_log')
                ->where('id_review_log', (int) $last['id_review_log'])
                ->update([
                    'decision'       => $decision,
                    'decided_at'     => date('Y-m-d H:i:sP'),
                    'decided_by'     => $decidedBy,
                    'decided_reason' => ($decidedReason !== null && trim($decidedReason) !== '')
                        ? trim($decidedReason)
                        : null,
                ]);
        } catch (\Throwable $e) {
            log_message('error', 'updateLatestReviewLogDecision error: ' . $e->getMessage());
        }
    }
    /**
     * ==================================================
     * supervisorReviewAction()
     * ==================================================
     * Acciones nuevas para supervisor:
     *
     * - cancel_request
     *   => cancela la solicitud y devuelve la tarea al estado anterior
     *
     * - approve_done
     *   => aprueba y marca la tarea como REALIZADA
     *
     * - force_not_done
     *   => fuerza la tarea como NO REALIZADA
     *
     * REGLAS:
     * - Solo puede actuar el supervisor directo del asignado
     *   o un usuario super/gerencia.
     * - Solo procesa tareas que estén en estado EN REVISIÓN.
     * - Limpia review_* al finalizar la decisión.
     * - Registra notificación en tarea_review_decision_log.
     */
    public function supervisorReviewAction(
        array $taskIds,
        string $action,
        int $currentUserId,
        int $currentUserAreaId
    ): array {
        // ==================================================
        // 0) Validaciones de columnas
        // ==================================================
        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        if (method_exists($this, 'hasExtendedReviewColumns') && !$this->hasExtendedReviewColumns()) {
            return [
                'success' => false,
                'error'   => 'Faltan columnas extendidas de revisión (review_action, review_reason, review_requested_by, review_requested_fecha_fin, review_previous_state). Ejecuta el ALTER TABLE.',
            ];
        }

        // ==================================================
        // 1) Validar acción
        // ==================================================
        if (!in_array($action, ['cancel_request', 'approve_done', 'force_not_done'], true)) {
            return [
                'success' => false,
                'error'   => 'Acción inválida para supervisor.',
            ];
        }

        // ==================================================
        // 2) Normalizar IDs
        // ==================================================
        $clean = [];
        foreach ($taskIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[] = $n;
            }
        }

        $clean = array_values(array_unique($clean));

        if (empty($clean)) {
            return [
                'success' => false,
                'error'   => 'No seleccionaste tareas.',
            ];
        }

        // ==================================================
        // 3) Scope del actor actual
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);
        $isSuperUser = (($scope['mode'] ?? '') === 'super');

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        $processed = 0;

        // ==================================================
        // 4) Transacción
        // ==================================================
        $db->transStart();

        try {
            foreach ($clean as $taskId) {

                // ----------------------------------------------
                // 4.1) Buscar tarea
                // ----------------------------------------------
                $task = $this->tareaModel->find((int) $taskId);
                if (!$task) {
                    continue;
                }

                // Solo tareas en revisión
                if ((int)($task['id_estado_tarea'] ?? 0) !== $this->estadoEnRevision) {
                    continue;
                }

                // ----------------------------------------------
                // 4.2) Validar supervisor directo o super
                // ----------------------------------------------
                $asignadoA = (int) ($task['asignado_a'] ?? 0);

                $rowSup = $db->query(
                    'SELECT id_supervisor
                     FROM public."USER"
                     WHERE id_user = ?
                     LIMIT 1',
                    [$asignadoA]
                )->getRowArray();

                $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

                $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);

                if (!$isDirectSupervisor && !$isSuperUser) {
                    continue;
                }

                // ----------------------------------------------
                // 4.3) Datos base de revisión
                // ----------------------------------------------
                $reviewAction = trim((string) ($task['review_action'] ?? ''));
                $prevState    = (int) ($task['review_previous_state'] ?? 0);
                $requestedBy  = (int) ($task['review_requested_by'] ?? 0);

                if ($prevState <= 0) {
                    $prevState = $this->estadoEnProceso;
                }

                $payload = [];

                // ----------------------------------------------
                // 4.4) Acción: cancelar solicitud
                // => volver al estado anterior
                // ----------------------------------------------
                if ($action === 'cancel_request') {
                    $payload = [
                        'id_estado_tarea'            => $prevState,
                        'completed_at'               => null,

                        // limpieza revisión
                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,

                        // auditoría
                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                    ];

                    $decisionForLog = 'rejected';
                    $actionForLog   = ($reviewAction !== '' ? $reviewAction : 'state');
                }

                // ----------------------------------------------
                // 4.5) Acción: aprobar como realizada
                // ----------------------------------------------
                if ($action === 'approve_done') {
                    $payload = [
                        'id_estado_tarea'            => $this->estadoRealizada,
                        'completed_at'               => $now,

                        // limpieza revisión
                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,

                        // auditoría
                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                    ];

                    $decisionForLog = 'approved';
                    $actionForLog   = 'approve_done';
                }

                // ----------------------------------------------
                // 4.6) Acción: forzar como no realizada
                // ----------------------------------------------
                if ($action === 'force_not_done') {
                    $payload = [
                        'id_estado_tarea'            => $this->estadoNoRealizada,
                        'completed_at'               => $now,

                        // limpieza revisión
                        'review_requested_state'     => null,
                        'review_requested_at'        => null,
                        'review_action'              => null,
                        'review_reason'              => null,
                        'review_requested_by'        => null,
                        'review_requested_fecha_fin' => null,
                        'review_previous_state'      => null,

                        // auditoría
                        'approved_by'                => $currentUserId,
                        'approved_at'                => $now,
                    ];

                    $decisionForLog = 'approved';
                    $actionForLog   = 'force_not_done';
                }

                // ----------------------------------------------
                // 4.7) Guardar update
                // ----------------------------------------------
                $db->table('public.tareas')
                    ->where('id_tarea', (int) $taskId)
                    ->update($payload);

                // ----------------------------------------------
                // 4.8) Registrar log / notificación
                // ----------------------------------------------
                if ($requestedBy > 0) {
                    $details = [
                        'id_tarea'            => (int) $taskId,
                        'titulo'              => (string) ($task['titulo'] ?? ''),
                        'decision'            => $decisionForLog,
                        'action'              => $actionForLog,
                        'requested_state'     => $task['review_requested_state'] ?? null,
                        'previous_state'      => $task['review_previous_state'] ?? null,
                        'requested_fecha_fin' => $task['review_requested_fecha_fin'] ?? null,
                        'requested_reason'    => $task['review_reason'] ?? null,
                        'result_estado'       => $payload['id_estado_tarea'] ?? null,
                        'result_fecha_fin'    => $payload['fecha_fin'] ?? null,
                    ];

                    $this->insertDecisionLog(
                        (int) $taskId,
                        $requestedBy,
                        (int) $currentUserId,
                        $decisionForLog,
                        $actionForLog,
                        $details
                    );

                    $this->updateLatestReviewLogDecision(
                        (int) $taskId,
                        $requestedBy,
                        $decisionForLog,
                        (int) $currentUserId,
                        null
                    );
                }

                $processed++;
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return [
                'success' => false,
                'error'   => 'Error procesando acción del supervisor: ' . $e->getMessage(),
            ];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return [
                'success' => false,
                'error'   => 'No se pudo completar la transacción.',
            ];
        }

        if ($processed <= 0) {
            return [
                'success' => false,
                'error'   => 'No se procesó ninguna tarea. Verifica que estén en revisión y que seas el supervisor directo.',
            ];
        }

        // ==================================================
        // 5) Mensajes finales
        // ==================================================
        if ($action === 'cancel_request') {
            return [
                'success' => true,
                'message' => 'La solicitud fue cancelada y la tarea volvió a su estado anterior.',
            ];
        }

        if ($action === 'approve_done') {
            return [
                'success' => true,
                'message' => 'La tarea fue aprobada y marcada como realizada.',
            ];
        }

        return [
            'success' => true,
            'message' => 'La tarea fue marcada como no realizada.',
        ];
    }
    public function updateOnlyEndTime(
        int $taskId,
        string $newTime,
        int $currentUserId,
        int $currentUserAreaId
    ): array {
        // ==================================================
        // 1) Buscar tarea
        // ==================================================
        $task = $this->tareaModel->find($taskId);

        if (!$task) {
            return [
                'success' => false,
                'error'   => 'Tarea no encontrada.',
            ];
        }

        // ==================================================
        // 2) No permitir sobre tareas cerradas
        // ==================================================
        $estadoActual = (int) ($task['id_estado_tarea'] ?? 0);

        if ($this->isClosedEstado($estadoActual)) {
            return [
                'success' => false,
                'error'   => 'La tarea ya está cerrada y no se puede actualizar la hora.',
            ];
        }

        // ==================================================
        // 3) Resolver alcance del actor actual
        // ==================================================
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $asignadoA = (int) ($task['asignado_a'] ?? 0);

        $db = Database::connect();

        $rowSup = $db->query(
            'SELECT id_supervisor
         FROM public."USER"
         WHERE id_user = ?
         LIMIT 1',
            [$asignadoA]
        )->getRowArray();

        $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

        $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);
        $isSuperUser        = (($scope['mode'] ?? '') === 'super');

        if (!$isDirectSupervisor && !$isSuperUser) {
            return [
                'success' => false,
                'error'   => 'No autorizado para actualizar la hora de esta tarea.',
            ];
        }

        // ==================================================
        // 4) Validar fecha_fin original
        // ==================================================
        $originalFechaFin = trim((string) ($task['fecha_fin'] ?? ''));
        if ($originalFechaFin === '') {
            return [
                'success' => false,
                'error'   => 'La tarea no tiene fecha fin.',
            ];
        }

        // ==================================================
        // 5) Validar formato de hora recibido
        // Esperado: HH:MM
        // ==================================================
        $newTime = trim($newTime);

        if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            return [
                'success' => false,
                'error'   => 'La hora enviada no es válida. Usa el formato HH:MM.',
            ];
        }

        // ==================================================
        // 6) Reemplazar SOLO la hora en la fecha fin original
        // ==================================================
        $newEndDt = $this->replaceOnlyTimeOnDate($originalFechaFin, $newTime);

        if (!$newEndDt) {
            return [
                'success' => false,
                'error'   => 'No se pudo construir la nueva fecha fin.',
            ];
        }

        // ==================================================
        // 7) Leer fecha inicio actual
        // ==================================================
        $startDt = $this->parseLocalDateTime((string) ($task['fecha_inicio'] ?? ''));

        if (!$startDt) {
            return [
                'success' => false,
                'error'   => 'No se pudo leer la fecha inicio actual.',
            ];
        }

        // ==================================================
        // 8) Validar que la nueva hora no deje fin < inicio
        // ==================================================
        if ($newEndDt < $startDt) {
            return [
                'success' => false,
                'error'   => 'La nueva hora no puede dejar la fecha fin antes de la fecha inicio.',
            ];
        }

        // ==================================================
        // 9) Validar que quede vigente respecto a "ahora"
        // ==================================================
        $nowDt = new \DateTimeImmutable('now', $this->tz());

        if ($newEndDt <= $nowDt) {
            return [
                'success' => false,
                'error'   => 'La nueva hora debe ser posterior a la hora actual para que la tarea vuelva a estar vigente.',
            ];
        }

        // ==================================================
        // 10) Recalcular prioridad automática
        // ==================================================
        $newPriority = $this->autoPriorityFromEnd(
            $newEndDt,
            (int) ($task['id_prioridad'] ?? 0)
        );

        // ==================================================
        // 11) Auditoría
        // ==================================================
        $now = $nowDt->format('Y-m-d H:i:s');

        // ==================================================
        // 12) Payload
        // - solo cambia fecha_fin
        // - no toca fecha_inicio
        // - deja completed_at en null por seguridad
        // - limpia approved_* solo actualizando con esta acción
        // ==================================================
        $payload = [
            'fecha_fin'    => $this->toDbDateTime($newEndDt),
            'id_prioridad' => $newPriority,
            'completed_at' => null,
            'approved_by'  => $currentUserId,
            'approved_at'  => $now,
        ];

        // ==================================================
        // 13) Guardar
        // ==================================================
        try {
            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update($payload);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'No se pudo actualizar la hora límite.',
            ];
        }

        return [
            'success'       => true,
            'message'       => 'La hora límite fue actualizada correctamente.',
            'new_fecha_fin' => $payload['fecha_fin'],
        ];
    }
}
