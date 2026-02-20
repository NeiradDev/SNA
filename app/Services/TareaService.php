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

        // Scope real de permisos
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        // Campos básicos
        $titulo      = trim((string) ($post['titulo'] ?? ''));
        $descripcion = trim((string) ($post['descripcion'] ?? ''));
        $idEstado    = (int) ($post['id_estado_tarea'] ?? 0);

        // Área pedida por UI (solo aplica si super/division)
        $idAreaPost  = (int) ($post['id_area'] ?? 0);

        // asignado_a puede venir int o array
        $assignees   = $this->normalizeAssignees($post['asignado_a'] ?? []);

        $fechaInicioRaw = (string) ($post['fecha_inicio'] ?? '');
        $fechaFinRaw    = (string) ($post['fecha_fin'] ?? '');

        // Validaciones mínimas
        if ($titulo === '') {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }

        if ($idEstado <= 0) {
            return ['success' => false, 'error' => 'El estado es obligatorio.'];
        }

        // CREATE => fin obligatorio
        $dateCheck = $this->validateDates($fechaInicioRaw, $fechaFinRaw, true);
        if (!$dateCheck['ok']) {
            return ['success' => false, 'error' => $dateCheck['error'] ?? 'Fechas inválidas.'];
        }

        /** @var \DateTimeImmutable $startDt */
        $startDt = $dateCheck['startDt'];
        /** @var \DateTimeImmutable $endDt */
        $endDt   = $dateCheck['endDt'];

        // Determinar área final según scope
        $idAreaFinal = 0;

        if ($scope['mode'] === 'super') {
            // gerencia: libre
            $idAreaFinal = $idAreaPost;
        } elseif ($scope['mode'] === 'division') {
            // jefe de división: debe elegir área (dentro de su división)
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
        if ($scope['mode'] === 'self') {
            $assignees = [$currentUserId];
        }

        if (empty($assignees)) {
            return ['success' => false, 'error' => 'Debes seleccionar al menos un usuario para asignar.'];
        }

        // ✅ Validación de usuarios vs área (reusando método centralizado)
        $usuariosArea = $this->getUsersByArea($idAreaFinal);
        $idsArea = array_map(static fn($u) => (int) ($u['id_user'] ?? 0), $usuariosArea);

        // jefe división / super pueden autoasignarse aunque no pertenezcan al área
        $allowSelfOutsideArea = in_array($scope['mode'], ['division', 'super'], true);

        foreach ($assignees as $uid) {
            if ($allowSelfOutsideArea && $uid === $currentUserId) {
                continue;
            }
            if (!in_array((int) $uid, $idsArea, true)) {
                return ['success' => false, 'error' => 'Uno o más usuarios no pertenecen al área seleccionada.'];
            }
        }

        // Prioridad automática
        $idPrioridadAuto = $this->autoPriorityFromEnd($endDt);

        // Insert múltiple (una fila por usuario)
        $db = Database::connect();
        $db->transStart();

        try {
            $now = new \DateTimeImmutable('now', $this->tz());

            // MISMO created_at para todas las filas (sirve para fallback batch)
            $createdAt = $now->format('Y-m-d H:i:s');

            // batch_uid si existe columna
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

                // Guardar batch_uid solo si existe columna y valor
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
    // EVENTOS FULLCALENDAR
    // ==================================================
    public function getCalendarEvents(int $userId, string $scope): array
    {
        $db = Database::connect();

        $where = ($scope === 'assigned')
            ? 't.asignado_por = ?'
            : 't.asignado_a = ?';

        $sql = <<<SQL
SELECT
    t.id_tarea,
    t.titulo,
    t.descripcion,
    t.id_estado_tarea,
    p.nombre_prioridad,
    e.nombre_estado,
    t.fecha_inicio,
    t.fecha_fin,
    t.asignado_a,
    t.asignado_por,
    ua.nombres || ' ' || ua.apellidos AS asignado_a_nombre,
    up.nombres || ' ' || up.apellidos AS asignado_por_nombre,
    ar.nombre_area
FROM public.tareas t
JOIN public.prioridad p    ON p.id_prioridad = t.id_prioridad
JOIN public.estado_tarea e ON e.id_estado_tarea = t.id_estado_tarea
LEFT JOIN public."USER" ua ON ua.id_user = t.asignado_a
LEFT JOIN public."USER" up ON up.id_user = t.asignado_por
LEFT JOIN public.area ar   ON ar.id_area = t.id_area
WHERE {$where}
ORDER BY t.fecha_inicio DESC
SQL;

        $rows = $db->query($sql, [$userId])->getResultArray();

        $events = [];

        foreach ($rows as $r) {
            $events[] = [
                'id'    => (string) $r['id_tarea'],
                'title' => $r['titulo'],
                'start' => $r['fecha_inicio'],
                'end'   => $r['fecha_fin'],
                'extendedProps' => [
                    'descripcion'         => $r['descripcion'] ?? '',
                    'prioridad'           => $r['nombre_prioridad'],
                    'estado'              => $r['nombre_estado'],
                    'id_estado_tarea'     => (int) $r['id_estado_tarea'],
                    'area'                => $r['nombre_area'] ?? '',
                    'asignado_a'          => (int) $r['asignado_a'],
                    'asignado_a_nombre'   => $r['asignado_a_nombre'] ?? '',
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
                    'completed_at'    => $now->format('Y-m-d H:i:s'),
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
        $db = Database::connect();

        // 1) Cargar fila base
        $task = $this->tareaModel->find($idTarea);
        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        // 2) Autorización base
        $isAssignee = ((int) $task['asignado_a'] === $currentUserId);
        $isCreator  = ((int) $task['asignado_por'] === $currentUserId);
        $isSuper    = ($currentUserAreaId === 1);

        if (!$isAssignee && !$isCreator && !$isSuper) {
            return ['success' => false, 'error' => 'No autorizado.'];
        }

        // 3) Scope real
        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        // 4) Regla de edición (bloqueos)
        if (!$this->canEditTask($task, $currentUserId, $scope)) {
            return ['success' => false, 'error' => 'No tienes permisos para editar esta tarea.'];
        }

        // 5) Grupo batch
        $groupRows = $this->getBatchRowsForTask($task);

        // batch_uid consistente si existe
        $batchUid = $this->ensureBatchUidForGroup($groupRows);

        // 6) Asignados actuales (del grupo)
        $existingAssignees = [];
        foreach ($groupRows as $r) {
            $existingAssignees[] = (int) ($r['asignado_a'] ?? 0);
        }
        $existingAssignees = array_values(array_unique(array_filter($existingAssignees)));

        // 7) Asignados entrantes
        $incomingAssignees = $this->normalizeAssignees($data['asignado_a'] ?? []);

        /**
         * Solo CREADOR o GERENCIA pueden administrar asignados.
         */
        $canManageAssignees = ($isCreator || $isSuper);

        if (!$canManageAssignees) {
            // Seguridad: si alguien intenta postear otros ids, se ignoran
            $incomingAssignees = [$currentUserId];
        } else {
            if (empty($incomingAssignees)) {
                return ['success' => false, 'error' => 'Debes seleccionar al menos un usuario.'];
            }
        }

        // 8) Determinar área final según scope
        $idAreaPost  = (int) ($data['id_area'] ?? ($task['id_area'] ?? 0));
        $idAreaFinal = 0;

        if ($scope['mode'] === 'super') {
            $idAreaFinal = $idAreaPost;
        } elseif ($scope['mode'] === 'division') {
            $idAreaFinal = $idAreaPost;

            $divisionId = (int) ($scope['divisionId'] ?? 0);
            if ($divisionId <= 0) return ['success' => false, 'error' => 'No se pudo determinar la división del jefe.'];
            if ($idAreaFinal <= 0) return ['success' => false, 'error' => 'Debes seleccionar un área.'];

            if (method_exists($this->tareaModel, 'isAreaInDivision')) {
                if (!$this->tareaModel->isAreaInDivision($idAreaFinal, $divisionId)) {
                    return ['success' => false, 'error' => 'Área inválida para tu división.'];
                }
            }
        } else {
            // jefe de área: área fija
            $idAreaFinal = (int) ($scope['areaId'] ?? ($task['id_area'] ?? 0));
        }

        if ($idAreaFinal <= 0) {
            return ['success' => false, 'error' => 'Área inválida.'];
        }

        // 9) Validar usuarios vs área (solo si puede gestionar)
        if ($canManageAssignees) {
            $usuariosArea = $this->getUsersByArea($idAreaFinal);
            $idsArea      = array_map(static fn($u) => (int) ($u['id_user'] ?? 0), $usuariosArea);

            // jefe division / super puede autoasignarse aunque no pertenezca al área elegida
            $allowSelfOutsideArea = in_array($scope['mode'], ['division', 'super'], true);

            foreach ($incomingAssignees as $uid) {
                if ($allowSelfOutsideArea && $uid === $currentUserId) {
                    continue;
                }
                if (!in_array((int) $uid, $idsArea, true)) {
                    return ['success' => false, 'error' => 'Uno o más usuarios no pertenecen al área seleccionada.'];
                }
            }
        }

        // 10) Construir update común (campos compartidos por el batch)
        $commonUpdate = [];

        // Mantener área consistente si puede gestionar
        if ($canManageAssignees || $isSuper) {
            $commonUpdate['id_area'] = $idAreaFinal;
        }

        // Título global: solo creador/gerencia
        if (($canManageAssignees || $isSuper) && isset($data['titulo'])) {
            $commonUpdate['titulo'] = trim((string) $data['titulo']);
        }

        // Descripción: creador/gerencia
        if (($canManageAssignees || $isSuper) && array_key_exists('descripcion', $data)) {
            $desc = trim((string) $data['descripcion']);
            $commonUpdate['descripcion'] = ($desc !== '' ? $desc : null);
        }

        // Estado global: solo creador/gerencia
        $estadoPost = null;
        if (($canManageAssignees || $isSuper) && isset($data['id_estado_tarea'])) {
            $estadoPost = (int) $data['id_estado_tarea'];
        }

        // 11) Fechas: solo creador/gerencia
        $hasStart = array_key_exists('fecha_inicio', $data);
        $hasEnd   = array_key_exists('fecha_fin', $data);

        if (($canManageAssignees || $isSuper) && ($hasStart || $hasEnd)) {
            $incomingStartRaw = $hasStart ? (string) $data['fecha_inicio'] : (string) ($task['fecha_inicio'] ?? '');
            $incomingEndRaw   = $hasEnd   ? (string) $data['fecha_fin']    : (string) ($task['fecha_fin'] ?? '');

            if ($hasEnd && trim((string) $data['fecha_fin']) === '') {
                return ['success' => false, 'error' => 'La fecha final es obligatoria.'];
            }

            $incomingStartDt = $this->parseLocalDateTime($incomingStartRaw);
            $incomingEndDt   = $this->parseLocalDateTime($incomingEndRaw);

            if (!$incomingStartDt) return ['success' => false, 'error' => 'La fecha de inicio es obligatoria.'];
            if (!$incomingEndDt)   return ['success' => false, 'error' => 'La fecha final es obligatoria.'];

            // Detectar si NO cambió (para no bloquear tareas viejas)
            $taskStartDt = $this->parseLocalDateTime((string) ($task['fecha_inicio'] ?? ''));
            $taskEndDt   = $this->parseLocalDateTime((string) ($task['fecha_fin'] ?? ''));

            $startUnchanged = $this->sameMinute($incomingStartDt, $taskStartDt);
            $endUnchanged   = $this->sameMinute($incomingEndDt, $taskEndDt);

            $today = $this->todayKey();

            $skipPastStart = $startUnchanged && $taskStartDt && ($this->dayKey($taskStartDt) < $today);
            $skipPastEnd   = $endUnchanged   && $taskEndDt   && ($this->dayKey($taskEndDt) < $today);

            $dateCheck = $this->validateDates(
                $incomingStartRaw,
                $incomingEndRaw,
                true,
                $skipPastStart,
                $skipPastEnd
            );

            if (!$dateCheck['ok']) {
                return ['success' => false, 'error' => $dateCheck['error'] ?? 'Fechas inválidas.'];
            }

            /** @var \DateTimeImmutable $startDt */
            $startDt = $dateCheck['startDt'];
            /** @var \DateTimeImmutable $endDt */
            $endDt   = $dateCheck['endDt'];

            $commonUpdate['fecha_inicio'] = $this->toDbDateTime($startDt);
            $commonUpdate['fecha_fin']    = $this->toDbDateTime($endDt);

            // prioridad automática según fecha fin
            $commonUpdate['id_prioridad'] = $this->autoPriorityFromEnd($endDt, (int) ($task['id_prioridad'] ?? 0));
        }

        // 12) Cambios de asignados: keep / add / cancel
        $toKeep   = array_values(array_intersect($existingAssignees, $incomingAssignees));
        $toAdd    = array_values(array_diff($incomingAssignees, $existingAssignees));
        $toCancel = array_values(array_diff($existingAssignees, $incomingAssignees));

        if (!$canManageAssignees) {
            $toKeep   = [$currentUserId];
            $toAdd    = [];
            $toCancel = [];
        }

        // 13) Valores finales (para inserts)
        $tituloFinal = (string) ($commonUpdate['titulo'] ?? ($task['titulo'] ?? ''));
        $descFinal   = array_key_exists('descripcion', $commonUpdate)
            ? $commonUpdate['descripcion']
            : ($task['descripcion'] ?? null);

        $estadoFinal = (int) ($estadoPost ?? ($task['id_estado_tarea'] ?? 0));
        if ($estadoFinal <= 0) {
            $estadoFinal = (int) ($task['id_estado_tarea'] ?? 0);
        }

        $fechaIniFinal  = (string) ($commonUpdate['fecha_inicio'] ?? ($task['fecha_inicio'] ?? ''));
        $fechaFinFinal  = (string) ($commonUpdate['fecha_fin']    ?? ($task['fecha_fin']    ?? ''));
        $prioridadFinal = (int)    ($commonUpdate['id_prioridad'] ?? ($task['id_prioridad'] ?? 0));
        $areaFinal      = (int)    ($commonUpdate['id_area']      ?? ($task['id_area'] ?? $idAreaFinal));

        // 14) Transacción: update + inserts + cancelados
        $db->transStart();

        try {
            $now = new \DateTimeImmutable('now', $this->tz());
            $nowStr = $now->format('Y-m-d H:i:s');

            $table = $db->table('public.tareas');

            // --- A) Actualizar existentes (keep + cancel)
            foreach ($groupRows as $r) {
                $rowId    = (int) ($r['id_tarea'] ?? 0);
                $assignee = (int) ($r['asignado_a'] ?? 0);

                if ($rowId <= 0 || $assignee <= 0) continue;

                // Cancelar removidos
                if ($canManageAssignees && in_array($assignee, $toCancel, true)) {
                    $cancelUpdate = $commonUpdate;
                    $cancelUpdate['id_estado_tarea'] = $this->estadoCancelada;
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

                    // Estado global si se envió
                    if ($estadoPost !== null) {
                        $rowUpdate['id_estado_tarea'] = (int) $estadoPost;
                        if ((int) $estadoPost === $this->estadoRealizada) {
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
                        'fecha_inicio'    => $fechaIniFinal,
                        'fecha_fin'       => $fechaFinFinal,
                        'id_area'         => $areaFinal,
                        'asignado_a'      => (int) $uid,
                        'asignado_por'    => (int) ($task['asignado_por'] ?? $currentUserId),
                        'created_at'      => (string) ($task['created_at'] ?? $nowStr),
                    ];

                    if ($this->hasBatchUidColumn() && $batchUid) {
                        $insert['batch_uid'] = $batchUid;
                    }

                    // completed_at si entra como realizada/no realizada
                    if (in_array((int) $estadoFinal, [$this->estadoRealizada, $this->estadoNoRealizada], true)) {
                        $insert['completed_at'] = $nowStr;
                    } else {
                        $insert['completed_at'] = null;
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
            'batch_uid' => ($this->hasBatchUidColumn() ? (string) ($batchUid ?? '') : ''),
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

    // ==================================================
    // GESTIÓN (mis tareas / asignadas / equipo / revisión)
    // ==================================================
    public function getTasksForManagement(int $idUser, ?int $currentUserAreaId = null): array
    {
        // Contexto del usuario actual
        if ($currentUserAreaId === null) {
            $currentUserAreaId = (int) (session()->get('id_area') ?? 0);
        }

        // Scope real (super/division/area/self)
        $scope = $this->resolveAssignScope($idUser, $currentUserAreaId);

        // Usuarios de mi equipo
        $teamUserIds = $this->getTeamUserIdsFromScope($scope, $idUser);

        $db = Database::connect();

        // Armamos SELECT dinámico
        $hasReviewCols = $this->hasReviewFlowColumns();

        /**
         * Helper interno para armar SELECT con joins (evita duplicación).
         */
        $baseSelect = function () use ($db, $hasReviewCols) {
            $b = $db->table('public.tareas t');

            // Campos base
            $select = [
                't.id_tarea',
                't.titulo',
                't.descripcion',
                't.fecha_inicio',
                't.fecha_fin',
                't.id_estado_tarea',
                't.asignado_a',
                't.asignado_por',
                't.id_area',
                'p.nombre_prioridad',
                'e.nombre_estado',
                'ar.nombre_area',
                'ua.nombres || \' \' || ua.apellidos AS asignado_a_nombre',
                'up.nombres || \' \' || up.apellidos AS asignado_por_nombre',
                'ua.id_supervisor AS asignado_a_supervisor',
            ];

            // Campos de revisión (si existen)
            if ($hasReviewCols) {
                $select[] = 't.edit_count';
                $select[] = 't.review_requested_state';
                $select[] = 't.review_requested_at';
                $select[] = 'er.nombre_estado AS nombre_estado_solicitado';
            } else {
                // Fallback neutro para no romper vistas
                $select[] = '0 AS edit_count';
                $select[] = 'NULL::int AS review_requested_state';
                $select[] = 'NULL::timestamp AS review_requested_at';
                $select[] = 'NULL::text AS nombre_estado_solicitado';
            }

            $b->select($select, false);

            // Joins base
            $b->join('public.prioridad p', 'p.id_prioridad = t.id_prioridad');
            $b->join('public.estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea');
            $b->join('public.area ar', 'ar.id_area = t.id_area', 'left');
            $b->join('public."USER" ua', 'ua.id_user = t.asignado_a', 'left');
            $b->join('public."USER" up', 'up.id_user = t.asignado_por', 'left');

            // Join estado solicitado (solo si existen columnas)
            if ($hasReviewCols) {
                $b->join('public.estado_tarea er', 'er.id_estado_tarea = t.review_requested_state', 'left');
            }

            return $b;
        };

        // 1) Mis tareas
        $misTareas = $baseSelect()
            ->where('t.asignado_a', $idUser)
            ->orderBy('t.fecha_inicio', 'DESC')
            ->get()
            ->getResultArray();

        // 2) Asignadas por mí (excluye autoasignadas)
        $tareasAsignadas = $baseSelect()
            ->where('t.asignado_por', $idUser)
            ->where('t.asignado_a <>', $idUser)
            ->orderBy('t.fecha_inicio', 'DESC')
            ->get()
            ->getResultArray();

        // 3) Tareas de mi equipo (autoasignadas de ellos)
        $tareasEquipo = [];
        if (!empty($teamUserIds)) {
            $tareasEquipo = $baseSelect()
                ->whereIn('t.asignado_a', $teamUserIds)
                ->where('t.asignado_por = t.asignado_a', null, false)
                ->orderBy('t.fecha_inicio', 'DESC')
                ->get()
                ->getResultArray();
        }

        // 4) Pendientes de revisión (solo si existe flujo)
        $pendientesRevision = [];

        if ($hasReviewCols) {
            $pendientesBuilder = $baseSelect()
                ->where('t.id_estado_tarea', $this->estadoEnRevision);

            // Supervisor directo
            $pendientesBuilder->groupStart()
                ->where('ua.id_supervisor', $idUser);

            // Gerencia/jefaturas también pueden revisar según alcance
            if ($scope['mode'] === 'super') {
                $pendientesBuilder->orWhere('1=1', null, false);
            } elseif ($scope['mode'] === 'division') {
                $divisionId = (int) ($scope['divisionId'] ?? 0);
                if ($divisionId > 0) {
                    $pendientesBuilder->orWhere('ar.id_division', $divisionId);
                }
            } elseif ($scope['mode'] === 'area') {
                $areaId = (int) ($scope['areaId'] ?? 0);
                if ($areaId > 0) {
                    $pendientesBuilder->orWhere('t.id_area', $areaId);
                }
            }

            $pendientesBuilder->groupEnd();

            $pendientesRevision = $pendientesBuilder
                ->orderBy('t.review_requested_at', 'DESC')
                ->get()
                ->getResultArray();
        }

        return [
            'assignScope'        => $scope,
            'misTareas'          => $misTareas,
            'tareasAsignadas'    => $tareasAsignadas,
            'tareasEquipo'       => $tareasEquipo,
            'pendientesRevision' => $pendientesRevision,
            'hasReviewFlow'      => $hasReviewCols,
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
        $task = $this->tareaModel->find($idTarea);
        if (!$task) return null;

        $isAssignee = ((int) $task['asignado_a'] === $currentUserId);
        $isCreator  = ((int) $task['asignado_por'] === $currentUserId);
        $isSuper    = ((int) session()->get('id_area') === 1);

        if (!$isAssignee && !$isCreator && !$isSuper) return null;

        $groupRows = $this->getBatchRowsForTask($task);

        $ids = [];
        foreach ($groupRows as $r) {
            // Cancelado NO se preselecciona
            if ((int) ($r['id_estado_tarea'] ?? 0) === $this->estadoCancelada) {
                continue;
            }
            $ids[] = (int) ($r['asignado_a'] ?? 0);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        $task['asignado_a'] = $ids;

        if ($this->hasBatchUidColumn()) {
            $task['batch_uid'] = (string) ($task['batch_uid'] ?? '');
        }

        return $task;
    }

    // ==================================================
    // SATISFACCIÓN (jueves → miércoles)  ✅ (PARA TU VISTA)
    // ==================================================
    // (TODO LO DEMÁS QUEDA IGUAL A TU CÓDIGO ORIGINAL)
    // --------------------------------------------------
    // ⚠️ Desde aquí en adelante NO cambié tu lógica de satisfacción/revisión
    //     para no romper tu vista, solo mantuve el archivo completo.
    // --------------------------------------------------

    private function getBusinessWeekRange(): array
    {
        $now = new \DateTimeImmutable('now', $this->tz());

        // 1=Lunes ... 7=Domingo
        $dow = (int) $now->format('N');

        // JUEVES = 4
        $daysSinceThursday = ($dow >= 4) ? ($dow - 4) : ($dow + 3);

        // Inicio jueves 00:00
        $start = $now->modify("-{$daysSinceThursday} days")->setTime(0, 0, 0);

        // Fin EXCLUSIVO (jueves siguiente)
        $endExclusive = $start->modify('+7 days')->setTime(0, 0, 0);

        // Fin visible (miércoles)
        $endDisplay = $start->modify('+6 days')->setTime(0, 0, 0);

        return [
            'start'        => $start,
            'endExclusive' => $endExclusive,
            'inicioLabel'  => $start->format('Y-m-d'),
            'finLabel'     => $endDisplay->format('Y-m-d'),
        ];
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
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn($x) => $x > 0)));

        if (empty($userIds)) {
            return ['realizadas' => 0, 'no_realizadas' => 0, 'porcentaje' => 0.0];
        }

        $db = Database::connect();

        $b = $db->table('public.tareas t');

        $b->select("
            SUM(CASE WHEN t.id_estado_tarea = {$this->estadoRealizada} THEN 1 ELSE 0 END) AS realizadas,
            SUM(CASE WHEN t.id_estado_tarea = {$this->estadoNoRealizada} THEN 1 ELSE 0 END) AS no_realizadas
        ", false);

        $b->where('t.fecha_inicio >=', $start->format('Y-m-d H:i:s'));
        $b->where('t.fecha_inicio <',  $endExclusive->format('Y-m-d H:i:s'));

        $b->whereIn('t.id_estado_tarea', [$this->estadoRealizada, $this->estadoNoRealizada]);

        if ($areaId !== null && $areaId > 0) {
            $b->where('t.id_area', $areaId);
        }

        if ($divisionId !== null && $divisionId > 0) {
            $b->join('public.area ar', 'ar.id_area = t.id_area', 'left');
            $b->where('ar.id_division', $divisionId);
        }

        $b->groupStart()
            ->whereIn('t.asignado_a', $userIds)
            ->orWhereIn('t.asignado_por', $userIds)
            ->groupEnd();

        $row = $b->get()->getRowArray();

        $realizadas   = (int)($row['realizadas'] ?? 0);
        $noRealizadas = (int)($row['no_realizadas'] ?? 0);
        $total = $realizadas + $noRealizadas;

        $porcentaje = ($total > 0) ? round(($realizadas / $total) * 100, 2) : 0.0;

        return [
            'realizadas'    => $realizadas,
            'no_realizadas' => $noRealizadas,
            'porcentaje'    => $porcentaje,
        ];
    }

    private function buildHistorySeries(
        array $userIds,
        \DateTimeImmutable $currentWeekStart,
        ?int $divisionId = null,
        ?int $areaId = null
    ): array {
        $series = [];

        for ($i = 4; $i >= 1; $i--) {
            $wStart = $currentWeekStart->modify("-{$i} week")->setTime(0, 0, 0);
            $wEndEx = $wStart->modify('+7 days')->setTime(0, 0, 0);

            $data = $this->calcSatisfaccionChain($userIds, $wStart, $wEndEx, $divisionId, $areaId);

            $series[] = [
                'label' => $this->buildWeekLabel($wStart),
                'value' => (float) ($data['porcentaje'] ?? 0),
            ];
        }

        $curEndEx = $currentWeekStart->modify('+7 days')->setTime(0, 0, 0);
        $curData  = $this->calcSatisfaccionChain($userIds, $currentWeekStart, $curEndEx, $divisionId, $areaId);

        $series[] = [
            'label' => $this->buildWeekLabel($currentWeekStart),
            'value' => (float) ($curData['porcentaje'] ?? 0),
        ];

        return $series;
    }

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
                        AND t.fecha_inicio >= ?
                        AND t.fecha_inicio < ?
                        AND t.id_estado_tarea IN ({$this->estadoRealizada}, {$this->estadoNoRealizada})
                        AND (
                            t.asignado_a = u.id_user
                            OR t.asignado_por = u.id_user
                        )
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

                $pct = ($tot > 0) ? round(($real / $tot) * 100, 2) : 0.0;

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
                        AND t.fecha_inicio >= ?
                        AND t.fecha_inicio < ?
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

            $historyDivision = $this->buildHistorySeries($idsDivision, $start, $divisionId, null);
            $avgDivision4    = $this->getLast4WeeksAverage($idsDivision, $start, $divisionId, null);

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

                $historyArea = $this->buildHistorySeries($idsArea, $start, null, $areaId);
                $avgArea4    = $this->getLast4WeeksAverage($idsArea, $start, null, $areaId);

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

            $historyArea = $this->buildHistorySeries($idsArea, $start, null, $areaId);
            $avgArea4    = $this->getLast4WeeksAverage($idsArea, $start, null, $areaId);

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

        $historyPersonal = $this->buildHistorySeries([$idUser], $start, null, null);
        $avgPersonal4    = $this->getLast4WeeksAverage([$idUser], $start, null, null);

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

    // ==================================================
    // REGLA DE EDICIÓN
    // ==================================================
    private function canEditTask(array $task, int $currentUserId, array $scope): bool
    {
        $estado = (int) ($task['id_estado_tarea'] ?? 0);

        if (in_array($estado, [$this->estadoRealizada, $this->estadoNoRealizada], true)) {
            return false;
        }

        if (($scope['mode'] ?? 'self') === 'self') {
            return false;
        }

        if (($scope['mode'] ?? '') === 'super') {
            return true;
        }

        if ((int) ($task['asignado_por'] ?? 0) === $currentUserId) {
            return true;
        }

        if (($scope['mode'] ?? '') === 'area') {
            return (int) ($task['id_area'] ?? 0) === (int) ($scope['areaId'] ?? 0);
        }

        if (($scope['mode'] ?? '') === 'division') {
            $divisionId = (int) ($scope['divisionId'] ?? 0);
            $areaIdTask = (int) ($task['id_area'] ?? 0);

            if ($divisionId > 0 && $areaIdTask > 0 && method_exists($this->tareaModel, 'isAreaInDivision')) {
                return $this->tareaModel->isAreaInDivision($areaIdTask, $divisionId);
            }
        }

        return false;
    }

    // ==================================================
    // FLUJO DE REVISIÓN (OPCIONAL, SI EXISTEN COLUMNAS)
    // ==================================================
    public function requestOrSetEstado(
        int $taskId,
        int $requestedEstado,
        int $currentUserId,
        int $currentUserAreaId
    ): array {

        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        if (!in_array($requestedEstado, [$this->estadoRealizada, $this->estadoNoRealizada], true)) {
            return ['success' => false, 'error' => 'Estado inválido.'];
        }

        $task = $this->tareaModel->find($taskId);
        if (!$task) return ['success' => false, 'error' => 'Tarea no encontrada.'];

        $estadoActual = (int) ($task['id_estado_tarea'] ?? 0);

        if (in_array($estadoActual, [$this->estadoRealizada, $this->estadoNoRealizada, $this->estadoCancelada], true)) {
            return ['success' => false, 'error' => 'Esta tarea ya está cerrada y no se puede modificar.'];
        }

        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        if (($scope['mode'] ?? 'self') === 'self') {

            if ((int) ($task['asignado_a'] ?? 0) !== $currentUserId) {
                return ['success' => false, 'error' => 'No autorizado.'];
            }

            $editCount = (int) ($task['edit_count'] ?? 0);
            if ($editCount > $this->maxEditsForRealizada) {
                $db->table('public.tareas')
                    ->where('id_tarea', $taskId)
                    ->update([
                        'id_estado_tarea'        => $this->estadoNoRealizada,
                        'completed_at'           => null,
                        'review_requested_state' => null,
                        'review_requested_at'    => null,
                        'approved_by'            => null,
                        'approved_at'            => null,
                    ]);

                return ['success' => true, 'message' => 'La tarea tiene 3+ ediciones. Se marcó como No realizada.'];
            }

            $row = $db->query(
                'SELECT id_supervisor FROM public."USER" WHERE id_user = ? LIMIT 1',
                [$currentUserId]
            )->getRowArray();

            $supervisorId = (int) ($row['id_supervisor'] ?? 0);
            if ($supervisorId <= 0) {
                return ['success' => false, 'error' => 'No tienes supervisor asignado (id_supervisor).'];
            }

            $db->table('public.tareas')
                ->where('id_tarea', $taskId)
                ->update([
                    'id_estado_tarea'         => $this->estadoEnRevision,
                    'review_requested_state'  => $requestedEstado,
                    'review_requested_at'     => $now,
                    'approved_by'             => null,
                    'approved_at'             => null,
                ]);

            return ['success' => true, 'message' => 'Enviado a revisión de tu supervisor.'];
        }

        $asignadoA   = (int) ($task['asignado_a'] ?? 0);
        $asignadoPor = (int) ($task['asignado_por'] ?? 0);

        $isSuperUser  = (($scope['mode'] ?? '') === 'super');
        $isAsignador  = ($asignadoPor === $currentUserId);

        $rowSup = $db->query(
            'SELECT id_supervisor FROM public."USER" WHERE id_user = ? LIMIT 1',
            [$asignadoA]
        )->getRowArray();

        $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);
        $isDirectSupervisor   = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);

        $isBossAllowed = $this->canEditTask($task, $currentUserId, $scope);

        if (!$isSuperUser && !$isAsignador && !$isDirectSupervisor && !$isBossAllowed) {
            return ['success' => false, 'error' => 'No autorizado para cerrar esta tarea.'];
        }

        $editCount = (int) ($task['edit_count'] ?? 0);
        if ($editCount > $this->maxEditsForRealizada) {
            $requestedEstado = $this->estadoNoRealizada;
        }

        $payload = [
            'id_estado_tarea'        => $requestedEstado,
            'review_requested_state' => null,
            'review_requested_at'    => null,
            'approved_by'            => $currentUserId,
            'approved_at'            => $now,
            'completed_at'           => ($requestedEstado === $this->estadoRealizada) ? $now : null,
        ];

        $db->table('public.tareas')
            ->where('id_tarea', $taskId)
            ->update($payload);

        return ['success' => true, 'message' => 'Estado actualizado.'];
    }

    public function reviewBatch(
        array $taskIds,
        string $action,
        int $currentUserId,
        int $currentUserAreaId
    ): array {

        if (!$this->hasReviewFlowColumns()) {
            return $this->reviewColumnsMissingError();
        }

        $clean = [];
        foreach ($taskIds as $id) {
            $n = (int) $id;
            if ($n > 0) $clean[] = $n;
        }
        $clean = array_values(array_unique($clean));

        if (empty($clean)) {
            return ['success' => false, 'error' => 'No seleccionaste tareas.'];
        }

        if (!in_array($action, ['approve', 'reject'], true)) {
            return ['success' => false, 'error' => 'Acción inválida.'];
        }

        $scope = $this->resolveAssignScope($currentUserId, $currentUserAreaId);

        $db  = Database::connect();
        $now = (new \DateTimeImmutable('now', $this->tz()))->format('Y-m-d H:i:s');

        $db->transStart();

        try {
            foreach ($clean as $taskId) {
                $task = $this->tareaModel->find((int) $taskId);
                if (!$task) continue;

                if ((int) ($task['id_estado_tarea'] ?? 0) !== $this->estadoEnRevision) {
                    continue;
                }

                $asignadoA = (int) ($task['asignado_a'] ?? 0);

                $rowSup = $db->query(
                    'SELECT id_supervisor FROM public."USER" WHERE id_user = ? LIMIT 1',
                    [$asignadoA]
                )->getRowArray();

                $assignedSupervisorId = (int) ($rowSup['id_supervisor'] ?? 0);

                $isDirectSupervisor = ($assignedSupervisorId > 0 && $assignedSupervisorId === $currentUserId);
                $isSuperUser        = (($scope['mode'] ?? '') === 'super');
                $isBossAllowed      = $this->canEditTask($task, $currentUserId, $scope);

                if (!$isDirectSupervisor && !$isSuperUser && !$isBossAllowed) {
                    continue;
                }

                $editCount = (int) ($task['edit_count'] ?? 0);
                $forcedNoRealizada = ($editCount > $this->maxEditsForRealizada);

                $finalEstado = $this->estadoNoRealizada;

                if ($action === 'reject') {
                    $finalEstado = $this->estadoNoRealizada;
                } else {
                    $req = (int) ($task['review_requested_state'] ?? 0);
                    $finalEstado = in_array($req, [$this->estadoRealizada, $this->estadoNoRealizada], true)
                        ? $req
                        : $this->estadoNoRealizada;

                    if ($forcedNoRealizada) {
                        $finalEstado = $this->estadoNoRealizada;
                    }
                }

                $payload = [
                    'id_estado_tarea'        => $finalEstado,
                    'review_requested_state' => null,
                    'review_requested_at'    => null,
                    'approved_by'            => $currentUserId,
                    'approved_at'            => $now,
                    'completed_at'           => ($finalEstado === $this->estadoRealizada) ? $now : null,
                ];

                $db->table('public.tareas')
                    ->where('id_tarea', (int) $taskId)
                    ->update($payload);
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['success' => false, 'error' => 'Error procesando revisión.'];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'No se pudo completar la transacción.'];
        }

        return ['success' => true];
    }
}
