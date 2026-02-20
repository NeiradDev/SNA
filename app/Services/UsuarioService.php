<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsuarioModel;
use CodeIgniter\Validation\ValidationInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\BaseConnection;

/**
 * UsuarioService
 *
 * ✅ Ajuste solicitado:
 * - Guardar correo y teléfono del usuario.
 *
 * ✅ Qué se hizo:
 * 1) Se agregan reglas de validación para "correo" y "telefono".
 * 2) Se normaliza:
 *    - correo: trim + strtolower
 *    - telefono: trim + solo números, +, espacios y guiones (y máximo 20)
 * 3) Se agregan al buildUserData() para que el Model inserte/actualice.
 * 4) Se mejora el mapeo de errores de BD para detectar duplicado de correo.
 */
class UsuarioService
{
    /**
     * ID de cargo usado como fallback para “Gerencia”.
     * (Tu lógica actual lo usa para resolver supervisor automático).
     */
    private const DEFAULT_GERENCIA_CARGO_ID = 6;

    public function __construct(
        private UsuarioModel $usuarioModel,
        private ValidationInterface $validator
    ) {}

    // -----------------------------
    // Listas / Aux
    // -----------------------------

    /**
     * list()
     * - Retorna un listado de usuarios con joins (según tu model).
     */
    public function list(int $limit = 50): array
    {
        return $this->usuarioModel->getUserList($limit);
    }

    /**
     * getAuxDataForCreate()
     * - Datos auxiliares para vista crear usuario.
     */
    public function getAuxDataForCreate(): array
    {
        return $this->auxData();
    }

    /**
     * getAuxDataForEdit()
     * - Datos auxiliares para vista editar usuario.
     * - $userId se conserva por compatibilidad.
     */
    public function getAuxDataForEdit(int $userId): array
    {
        return $this->auxData();
    }

    /**
     * getUser()
     * - Trae usuario con joins (según tu model).
     */
    public function getUser(int $id): ?array
    {
        $u = $this->usuarioModel->getUserWithJoinsById($id);
        return $u ?: null;
    }

    // -----------------------------
    // Combos
    // -----------------------------

    public function areasByDivision(int $divisionId): array
    {
        return $this->usuarioModel->getAreasByDivision($divisionId);
    }

    public function cargosByArea(int $areaId): array
    {
        return $this->usuarioModel->getCargosByArea($areaId);
    }

    public function cargosByDivision(int $divisionId): array
    {
        return $this->usuarioModel->getCargosByDivision($divisionId);
    }

    /**
     * supervisorsByArea()
     * - Mantiene tu compatibilidad.
     * - Si existe getPreferredSupervisorsForArea() lo usa; si no, fallback.
     */
    public function supervisorsByArea(
        int $areaId,
        int $excludeUserId = 0,
        int $keepUserId = 0,
        int $gerenciaCargoId = 0
    ): array {
        if (!method_exists($this->usuarioModel, 'getPreferredSupervisorsForArea')) {
            return $this->usuarioModel->getSupervisorsByAreaOnly($areaId, $excludeUserId);
        }

        return $this->usuarioModel->getPreferredSupervisorsForArea(
            $areaId,
            $excludeUserId,
            $keepUserId,
            $gerenciaCargoId
        );
    }

    public function getGerenciaUser(int $gerenciaCargoId): array
    {
        $u = $this->usuarioModel->getUserByCargoId($gerenciaCargoId);

        return [
            'ok' => (bool) $u,
            'gerenciaCargoId' => $gerenciaCargoId,
            'user' => $u,
        ];
    }

    public function getDivisionBossByDivision(int $divisionId): array
    {
        $boss = $this->usuarioModel->getDivisionBossByDivision($divisionId);
        return ['ok' => (bool) $boss, 'boss' => $boss];
    }

    // -----------------------------
    // Create / Update (API)
    // -----------------------------

    public function create(array $input): array
    {
        return $this->save(0, $input, false);
    }

    public function update(int $id, array $input): array
    {
        return $this->save($id, $input, true);
    }

    // =========================================================
    // Core Save (reduce duplicación)
    // =========================================================

    /**
     * save()
     * - Core para create/update.
     * - Mantiene tu orden:
     *   CREATE:
     *   1) insertUser
     *   2) assign boss
     *   3) resolve supervisor + update puntual
     *   4) replace cargos
     *
     *   UPDATE:
     *   1) resolve supervisor
     *   2) updateUser
     *   3) assign boss
     *   4) replace cargos
     */
    private function save(int $id, array $input, bool $isUpdate): array
    {
        // ---------------------------------------------------------
        // 1) Validación total (rules + doc + duplicados + jefaturas)
        // ---------------------------------------------------------
        $v = $this->validateAll($input, $isUpdate, $id);
        if (!$v['ok']) return $v;

        // Doc ya validado y limpiado
        $docNumber = (string) $v['doc'];

        // ---------------------------------------------------------
        // 2) Transacción centralizada
        // ---------------------------------------------------------
        return $this->inTransaction(function (BaseConnection $db) use ($id, $input, $isUpdate, $docNumber) {

            // =====================================================
            // UPDATE
            // =====================================================
            if ($isUpdate) {
                // Supervisor final se resuelve ANTES (tu flujo original)
                $finalSupervisorId = $this->resolveSupervisorId($input, $id);

                // Construye data incluyendo correo/teléfono
                $data = $this->buildUserData($input, $docNumber, true, $finalSupervisorId);

                // Actualiza usuario
                if (!$this->usuarioModel->updateUser($id, $data)) {
                    return $this->fail($this->mapDbModelError($this->usuarioModel->getLastDbError()));
                }

                // Asignaciones de jefatura (puede lanzar RuntimeException)
                $this->applyBossAssignmentsOrFail($input, $id);

                // Reemplazo de cargos (puede lanzar RuntimeException)
                $this->replaceUserCargosOrFail($input, $id);

                return ['ok' => true];
            }

            // =====================================================
            // CREATE
            // =====================================================
            $data = $this->buildUserData($input, $docNumber, false, null);

            if (!$this->usuarioModel->insertUser($data)) {
                return $this->fail($this->mapDbModelError($this->usuarioModel->getLastDbError()));
            }

            $newUserId = (int) $this->usuarioModel->getLastInsertId();

            // 1) asignar jefaturas
            $this->applyBossAssignmentsOrFail($input, $newUserId);

            // 2) supervisor final y update puntual
            $finalSupervisorId = $this->resolveSupervisorId($input, $newUserId);

            $okSup = $this->usuarioModel->updateUser($newUserId, [
                'id_supervisor' => ($finalSupervisorId && $finalSupervisorId > 0) ? $finalSupervisorId : null,
            ]);

            if (!$okSup) {
                return $this->fail(['id_supervisor' => 'No se pudo asignar el supervisor final.']);
            }

            // 3) cargos
            $this->replaceUserCargosOrFail($input, $newUserId);

            return ['ok' => true];

        }, $isUpdate ? 'Error al actualizar el usuario.' : 'Error inesperado al registrar el usuario.');
    }

    // =========================================================
    // Transaction wrapper (menos líneas repetidas)
    // =========================================================

    /**
     * inTransaction()
     * - Abre transacción, ejecuta $work, commit/rollback.
     * - Mejora: si hay RuntimeException (por jefaturas/cargos),
     *   devolvemos el mensaje específico.
     */
    private function inTransaction(callable $work, string $genericError): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $res = $work($db);

            // Si work devolvió ok:false => rollback aquí
            if (!is_array($res) || empty($res['ok'])) {
                $db->transRollback();
                return is_array($res) ? $res : $this->fail(['general' => $genericError]);
            }

            $db->transCommit();
            return $res;

        } catch (\RuntimeException $e) {
            // ✅ Mensaje explícito (más útil para UI)
            $db->transRollback();
            return $this->fail(['general' => $e->getMessage()]);

        } catch (DatabaseException $e) {
            $db->transRollback();
            return $this->fail($this->mapDbException($e));

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->fail(['general' => $genericError]);
        }
    }

    private function fail(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors];
    }

    // =========================================================
    // Validación total (compacta)
    // =========================================================

    /**
     * validateAll()
     * - Corre rules del validator
     * - Valida documento según tipo
     * - Valida duplicado de documento
     * - Valida coherencia de jefaturas
     *
     * ✅ Nota sobre correo duplicado:
     * - Si tu model NO tiene métodos "emailExists", confiamos en el índice unique
     *   y lo capturamos con mapDbException/mapDbModelError.
     */
    private function validateAll(array $input, bool $isUpdate, int $id): array
    {
        // 1) Reglas de validator (incluye correo y teléfono)
        [$rules, $messages] = $this->rules($isUpdate);

        if (!$this->validator->setRules($rules, $messages)->run($input)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2) Documento
        $docType   = (string) ($input['doc_type'] ?? 'CEDULA');
        $docNumber = trim((string) ($input['cedula'] ?? ''));

        if ($err = $this->validateDocumento($docType, $docNumber)) {
            return $this->fail($err);
        }

        // 3) Duplicado documento (tu lógica original)
        $dup = $isUpdate
            ? $this->usuarioModel->docExistsForOtherUser($docNumber, $id)
            : $this->usuarioModel->docExists($docNumber);

        if ($dup) {
            return $this->fail(['cedula' => 'El número de documento ya está registrado.']);
        }

        // 4) Validación de jefaturas/cargos coherentes
        if ($err = $this->validateBossSelections($input)) {
            return $this->fail($err);
        }

        // 5) OK
        return ['ok' => true, 'doc' => $docNumber];
    }

    // =========================================================
    // Rules (compacto)
    // =========================================================

    /**
     * rules()
     * - Se agregan "correo" y "telefono".
     */
    private function rules(bool $isUpdate): array
    {
        $rules = [
            'nombres'     => 'required|min_length[2]|max_length[64]',
            'apellidos'   => 'required|min_length[2]|max_length[64]',
            'cedula'      => 'required|max_length[20]',
            'doc_type'    => 'required|in_list[CEDULA,PASAPORTE]',
            'id_agencias' => 'required|is_natural_no_zero',

            'id_cargo'      => 'required|is_natural_no_zero',
            'id_supervisor' => 'permit_empty|is_natural',

            'is_division_boss' => 'permit_empty',
            'is_area_boss'     => 'permit_empty',

            'id_division' => 'permit_empty|is_natural_no_zero',
            'id_area'     => 'permit_empty|is_natural_no_zero',

            'id_cargo_gerencia'   => 'permit_empty|is_natural_no_zero',
            'id_cargo_secondary'  => 'permit_empty|is_natural_no_zero',

            // ✅ NUEVOS CAMPOS
            // - correo opcional, pero si viene, debe ser email válido
            'correo'   => 'permit_empty|valid_email|max_length[120]',
            // - telefono opcional, longitud máxima 20
            'telefono' => 'permit_empty|max_length[20]',
        ];

        // Password: obligatorio en create, opcional en update
        $rules['password'] = $isUpdate ? 'permit_empty|min_length[6]' : 'required|min_length[6]';

        $messages = [
            'id_division' => ['is_natural_no_zero' => 'Debe seleccionar una división.'],
            'id_area'     => ['is_natural_no_zero' => 'Debe seleccionar un área.'],
            'id_cargo'    => ['required' => 'Debe seleccionar un cargo.'],

            // ✅ Mensajes para los nuevos campos
            'correo' => [
                'valid_email' => 'El correo no tiene un formato válido.',
                'max_length'  => 'El correo no puede superar 120 caracteres.',
            ],
            'telefono' => [
                'max_length'  => 'El teléfono no puede superar 20 caracteres.',
            ],
        ];

        return [$rules, $messages];
    }

    // =========================================================
    // Validación jefaturas (misma lógica, menos ruido)
    // =========================================================

    private function validateBossSelections(array $input): ?array
    {
        $isDivisionBoss = !empty($input['is_division_boss']);
        $isAreaBoss     = !empty($input['is_area_boss']);

        $divisionId = (int) ($input['id_division'] ?? 0);
        $areaId     = (int) ($input['id_area'] ?? 0);

        $primaryCargoId   = (int) ($input['id_cargo'] ?? 0);
        $secondaryCargoId = (int) ($input['id_cargo_secondary'] ?? 0);

        if ($isDivisionBoss && $divisionId <= 0) {
            return ['id_division' => 'Debe seleccionar la división para Jefe de División.'];
        }

        if ($isAreaBoss) {
            if ($divisionId <= 0) return ['id_division' => 'Debe seleccionar la división para Jefe de Área.'];
            if ($areaId <= 0)     return ['id_area' => 'Debe seleccionar el área para Jefe de Área.'];

            if (!$this->usuarioModel->areaBelongsToDivision($areaId, $divisionId)) {
                return ['id_area' => 'El área seleccionada no pertenece a la división elegida.'];
            }
        }

        if ($isDivisionBoss && !$this->usuarioModel->cargoBelongsToDivision($primaryCargoId, $divisionId)) {
            return ['id_cargo' => 'El cargo principal no corresponde a la división seleccionada.'];
        }

        if ($isAreaBoss && !$isDivisionBoss && !$this->usuarioModel->cargoBelongsToArea($primaryCargoId, $areaId)) {
            return ['id_cargo' => 'El cargo seleccionado no corresponde al área seleccionada.'];
        }

        if ($isDivisionBoss && $isAreaBoss) {
            if ($secondaryCargoId <= 0) {
                return ['id_cargo_secondary' => 'Debe seleccionar el cargo secundario (por área) cuando tiene ambos roles.'];
            }
            if ($secondaryCargoId === $primaryCargoId) {
                return ['id_cargo_secondary' => 'El cargo secundario no puede ser igual al cargo principal.'];
            }
            if (!$this->usuarioModel->cargoBelongsToArea($secondaryCargoId, $areaId)) {
                return ['id_cargo_secondary' => 'El cargo secundario no corresponde al área seleccionada.'];
            }
        }

        return null;
    }

    // =========================================================
    // Supervisor final (misma lógica)
    // =========================================================

    private function resolveSupervisorId(array $input, int $userId): ?int
    {
        $isDivisionBoss = !empty($input['is_division_boss']);
        $isAreaBoss     = !empty($input['is_area_boss']);

        $divisionId = (int) ($input['id_division'] ?? 0);

        $gerenciaCargoId = (int) ($input['id_cargo_gerencia'] ?? self::DEFAULT_GERENCIA_CARGO_ID);
        if ($gerenciaCargoId <= 0) $gerenciaCargoId = self::DEFAULT_GERENCIA_CARGO_ID;

        // Jefe división => supervisor = gerencia
        if ($isDivisionBoss) {
            $gerenciaUser = $this->usuarioModel->getUserByCargoId($gerenciaCargoId);
            $supId = (int) ($gerenciaUser['id_user'] ?? 0);
            return ($supId > 0 && $supId !== $userId) ? $supId : null;
        }

        // Jefe área (no división) => supervisor = jefe división
        if ($isAreaBoss && !$isDivisionBoss && $divisionId > 0) {
            $bossRow = $this->usuarioModel->getDivisionBossByDivision($divisionId);
            $bossId  = (int) ($bossRow['id_jf_division'] ?? 0);
            return ($bossId > 0 && $bossId !== $userId) ? $bossId : null;
        }

        // Usuario normal => manual
        $manual = (int) ($input['id_supervisor'] ?? 0);
        return $manual > 0 ? $manual : null;
    }

    // =========================================================
    // Asignación jefaturas
    // =========================================================

    private function applyBossAssignmentsOrFail(array $input, int $userId): void
    {
        $isDivisionBoss = !empty($input['is_division_boss']);
        $isAreaBoss     = !empty($input['is_area_boss']);

        $divisionId = (int) ($input['id_division'] ?? 0);
        $areaId     = (int) ($input['id_area'] ?? 0);

        if ($isDivisionBoss && !$this->usuarioModel->assignDivisionBoss($divisionId, $userId)) {
            throw new \RuntimeException('No se pudo asignar el Jefe de División.');
        }

        if ($isAreaBoss && !$this->usuarioModel->assignAreaBoss($areaId, $userId)) {
            throw new \RuntimeException('No se pudo asignar el Jefe de Área.');
        }
    }

    // =========================================================
    // usuario_cargo
    // =========================================================

    private function replaceUserCargosOrFail(array $input, int $userId): void
    {
        if (!method_exists($this->usuarioModel, 'replaceUserCargos')) {
            return;
        }

        $primary   = (int) ($input['id_cargo'] ?? 0);
        $secondary = (int) ($input['id_cargo_secondary'] ?? 0);

        $cargoIds = [$primary];
        if ($secondary > 0 && $secondary !== $primary) $cargoIds[] = $secondary;

        if (!$this->usuarioModel->replaceUserCargos($userId, $cargoIds)) {
            throw new \RuntimeException('No se pudieron guardar los cargos del usuario.');
        }
    }

    // =========================================================
    // Build data (✅ ahora incluye correo y teléfono)
    // =========================================================

    /**
     * buildUserData()
     * - Crea el array final para insert/update.
     * - ✅ Se agregan:
     *   - correo (normalizado)
     *   - telefono (sanitizado)
     */
    private function buildUserData(array $input, string $docNumber, bool $isUpdate, ?int $forcedSupervisorId): array
    {
        // ---------------------------------------------
        // IDs básicos
        // ---------------------------------------------
        $agencyId = (int) ($input['id_agencias'] ?? 0);
        $cargoId  = (int) ($input['id_cargo'] ?? 0);

        // ---------------------------------------------
        // Supervisor final:
        // - Si forced viene definido, se usa ese.
        // - Si no, se usa manual (si aplica).
        // ---------------------------------------------
        $supervisorId = $forcedSupervisorId;
        if ($supervisorId === null) {
            $manual = (int) ($input['id_supervisor'] ?? 0);
            $supervisorId = $manual > 0 ? $manual : null;
        }

        // ---------------------------------------------
        // ✅ Normalización de correo/teléfono
        // ---------------------------------------------
        $email = $this->normalizeEmail((string)($input['correo'] ?? ''));
        $phone = $this->sanitizePhone((string)($input['telefono'] ?? ''));

        // ---------------------------------------------
        // Data base (incluye correo y teléfono)
        // ---------------------------------------------
        $data = [
            'nombres'       => trim((string) ($input['nombres'] ?? '')),
            'apellidos'     => trim((string) ($input['apellidos'] ?? '')),
            'cedula'        => $docNumber,

            'id_agencias'   => $agencyId > 0 ? $agencyId : null,
            'id_cargo'      => $cargoId > 0 ? $cargoId : null,
            'id_supervisor' => ($supervisorId && $supervisorId > 0) ? $supervisorId : null,
            'activo'        => !empty($input['activo']),

            // ✅ NUEVOS
            'correo'        => $email,  // null si viene vacío
            'telefono'      => $phone,  // null si viene vacío
        ];

        // ---------------------------------------------
        // Password
        // - En create siempre se setea.
        // - En update solo si viene algo.
        // ---------------------------------------------
        $password = (string) ($input['password'] ?? '');
        if (!$isUpdate || $password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        return $data;
    }

    // =========================================================
    // Helpers de normalización (correo/teléfono)
    // =========================================================

    /**
     * normalizeEmail()
     * - trim
     * - lowercase
     * - retorna null si viene vacío
     */
    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '') return null;

        // Minúsculas (evita duplicados por mayúsculas)
        $email = mb_strtolower($email);

        // Respeta límite de tu columna (120)
        if (strlen($email) > 120) {
            $email = substr($email, 0, 120);
        }

        return $email;
    }

    /**
     * sanitizePhone()
     * - trim
     * - permite solo: números, +, espacios y guiones
     * - retorna null si viene vacío
     */
    private function sanitizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        if ($phone === '') return null;

        // Quita cualquier cosa que NO sea número, +, espacio o guión
        $phone = preg_replace('/[^0-9+\-\s]/', '', $phone) ?? '';

        $phone = trim($phone);

        // Respeta límite de tu columna (20)
        if (strlen($phone) > 20) {
            $phone = substr($phone, 0, 20);
        }

        return $phone !== '' ? $phone : null;
    }

    // =========================================================
    // Documento
    // =========================================================

    private function validateDocumento(string $docType, string $docNumber): ?array
    {
        if ($docType === 'CEDULA') {
            return preg_match('/^\d{10}$/', $docNumber)
                ? null
                : ['cedula' => 'Cédula inválida. Debe contener 10 dígitos.'];
        }

        return (strlen($docNumber) >= 5) ? null : ['cedula' => 'Documento inválido.'];
    }

    // =========================================================
    // Errores DB (✅ mejora: detectar duplicado por correo)
    // =========================================================

    /**
     * mapDbException()
     * - Captura DatabaseException de CI4.
     * - Si detecta duplicado:
     *   - Si es de correo => error en "correo"
     *   - Si es de cedula => error en "cedula"
     */
    private function mapDbException(DatabaseException $e): array
    {
        $msg = $e->getMessage();

        // Duplicado
        if (stripos($msg, 'duplicate key') !== false || stripos($msg, 'unique') !== false) {

            // ✅ Si el mensaje menciona "correo" o el índice "ux_user_correo"
            if (stripos($msg, 'ux_user_correo') !== false || stripos($msg, 'correo') !== false) {
                return ['correo' => 'El correo ya está registrado.'];
            }

            // Por defecto, tu caso clásico: cédula duplicada
            return ['cedula' => 'El número de documento ya está registrado.'];
        }

        return ['general' => 'No se pudo registrar el usuario. Intente nuevamente.'];
    }

    /**
     * mapDbModelError()
     * - Igual que mapDbException pero usando el error capturado por tu model.
     */
    private function mapDbModelError(?array $err): array
    {
        $msg = (string) ($err['message'] ?? '');

        if (stripos($msg, 'duplicate key') !== false || stripos($msg, 'unique') !== false) {

            // ✅ Duplicado correo
            if (stripos($msg, 'ux_user_correo') !== false || stripos($msg, 'correo') !== false) {
                return ['correo' => 'El correo ya está registrado.'];
            }

            // Duplicado documento
            return ['cedula' => 'El número de documento ya está registrado.'];
        }

        return ['general' => 'No se pudo completar la operación.'];
    }

    // =========================================================
    // Aux data (sin duplicación create/edit)
    // =========================================================

    private function auxData(): array
    {
        $cid = self::DEFAULT_GERENCIA_CARGO_ID;

        return [
            'agencias' => $this->usuarioModel->getAgencies(),
            'division' => $this->usuarioModel->getDivision(),
            'areas'    => [],

            'gerenciaCargoIdDefault' => $cid,
            'gerenciaUser'           => $this->usuarioModel->getUserByCargoId($cid),
        ];
    }
}
