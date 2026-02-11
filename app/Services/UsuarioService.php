<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsuarioModel;
use CodeIgniter\Validation\ValidationInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\BaseConnection;

class UsuarioService
{
    private const DEFAULT_GERENCIA_CARGO_ID = 6;

    public function __construct(
        private UsuarioModel $usuarioModel,
        private ValidationInterface $validator
    ) {}

    // -----------------------------
    // Listas / Aux
    // -----------------------------

    public function list(int $limit = 50): array
    {
        return $this->usuarioModel->getUserList($limit);
    }

    public function getAuxDataForCreate(): array
    {
        return $this->auxData();
    }

    public function getAuxDataForEdit(int $userId): array
    {
        // $userId se conserva por compatibilidad (por si luego lo usas).
        return $this->auxData();
    }

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

    public function supervisorsByArea(
        int $areaId,
        int $excludeUserId = 0,
        int $keepUserId = 0,
        int $gerenciaCargoId = 0
    ): array {
        // Fallback si tu model aún no trae el método nuevo.
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

    private function save(int $id, array $input, bool $isUpdate): array
    {
        // 1) Validación (rules + doc + duplicado + jefaturas)
        $v = $this->validateAll($input, $isUpdate, $id);
        if (!$v['ok']) return $v;

        $docNumber = (string) $v['doc'];

        // 2) Transacción (manejo centralizado)
        return $this->inTransaction(function (BaseConnection $db) use ($id, $input, $isUpdate, $docNumber) {

            if ($isUpdate) {
                // UPDATE: supervisor final se resuelve antes (igual que tu flujo original)
                $finalSupervisorId = $this->resolveSupervisorId($input, $id);

                $data = $this->buildUserData($input, $docNumber, true, $finalSupervisorId);

                if (!$this->usuarioModel->updateUser($id, $data)) {
                    return $this->fail($this->mapDbModelError($this->usuarioModel->getLastDbError()));
                }

                // Jefaturas + cargos
                $this->applyBossAssignmentsOrFail($input, $id);
                $this->replaceUserCargosOrFail($input, $id);

                return ['ok' => true];
            }

            // CREATE
            $data = $this->buildUserData($input, $docNumber, false, null);

            if (!$this->usuarioModel->insertUser($data)) {
                return $this->fail($this->mapDbModelError($this->usuarioModel->getLastDbError()));
            }

            $newUserId = (int) $this->usuarioModel->getLastInsertId();

            // Mantengo el orden exacto que tenías:
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

    private function inTransaction(callable $work, string $genericError): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $res = $work($db);

            // Si el work devolvió ok:false, hacemos rollback aquí.
            if (!is_array($res) || empty($res['ok'])) {
                $db->transRollback();
                return is_array($res) ? $res : $this->fail(['general' => $genericError]);
            }

            $db->transCommit();
            return $res;
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

    private function validateAll(array $input, bool $isUpdate, int $id): array
    {
        [$rules, $messages] = $this->rules($isUpdate);

        if (!$this->validator->setRules($rules, $messages)->run($input)) {
            return $this->fail($this->validator->getErrors());
        }

        $docType   = (string) ($input['doc_type'] ?? 'CEDULA');
        $docNumber = trim((string) ($input['cedula'] ?? ''));

        if ($err = $this->validateDocumento($docType, $docNumber)) {
            return $this->fail($err);
        }

        $dup = $isUpdate
            ? $this->usuarioModel->docExistsForOtherUser($docNumber, $id)
            : $this->usuarioModel->docExists($docNumber);

        if ($dup) {
            return $this->fail(['cedula' => 'El número de documento ya está registrado.']);
        }

        if ($err = $this->validateBossSelections($input)) {
            return $this->fail($err);
        }

        return ['ok' => true, 'doc' => $docNumber];
    }

    // =========================================================
    // Rules (compacto)
    // =========================================================

    private function rules(bool $isUpdate): array
    {
        $rules = [
            'nombres'     => 'required|min_length[2]|max_length[64]',
            'apellidos'   => 'required|min_length[2]|max_length[64]',
            'cedula'      => 'required|max_length[20]',
            'doc_type'    => 'required|in_list[CEDULA,PASAPORTE]',
            'id_agencias' => 'required|is_natural_no_zero',

            'id_cargo'    => 'required|is_natural_no_zero',
            'id_supervisor' => 'permit_empty|is_natural',

            'is_division_boss' => 'permit_empty',
            'is_area_boss'     => 'permit_empty',

            'id_division' => 'permit_empty|is_natural_no_zero',
            'id_area'     => 'permit_empty|is_natural_no_zero',

            'id_cargo_gerencia' => 'permit_empty|is_natural_no_zero',
            'id_cargo_secondary' => 'permit_empty|is_natural_no_zero',
        ];

        $rules['password'] = $isUpdate ? 'permit_empty|min_length[6]' : 'required|min_length[6]';

        $messages = [
            'id_division' => ['is_natural_no_zero' => 'Debe seleccionar una división.'],
            'id_area'     => ['is_natural_no_zero' => 'Debe seleccionar un área.'],
            'id_cargo'    => ['required' => 'Debe seleccionar un cargo.'],
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
    // Build data
    // =========================================================

    private function buildUserData(array $input, string $docNumber, bool $isUpdate, ?int $forcedSupervisorId): array
    {
        $agencyId = (int) ($input['id_agencias'] ?? 0);
        $cargoId  = (int) ($input['id_cargo'] ?? 0);

        $supervisorId = $forcedSupervisorId;
        if ($supervisorId === null) {
            $manual = (int) ($input['id_supervisor'] ?? 0);
            $supervisorId = $manual > 0 ? $manual : null;
        }

        $data = [
            'nombres'       => (string) ($input['nombres'] ?? ''),
            'apellidos'     => (string) ($input['apellidos'] ?? ''),
            'cedula'        => $docNumber,
            'id_agencias'   => $agencyId > 0 ? $agencyId : null,
            'id_cargo'      => $cargoId > 0 ? $cargoId : null,
            'id_supervisor' => ($supervisorId && $supervisorId > 0) ? $supervisorId : null,
            'activo'        => !empty($input['activo']),
        ];

        $password = (string) ($input['password'] ?? '');
        if (!$isUpdate || $password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        return $data;
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
    // Errores DB
    // =========================================================

    private function mapDbException(DatabaseException $e): array
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'duplicate key') !== false) {
            return ['cedula' => 'El número de documento ya está registrado.'];
        }
        return ['general' => 'No se pudo registrar el usuario. Intente nuevamente.'];
    }

    private function mapDbModelError(?array $err): array
    {
        $msg = (string) ($err['message'] ?? '');
        if (stripos($msg, 'duplicate key') !== false) {
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
