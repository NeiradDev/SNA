<?php

namespace App\Services;

use App\Models\UsuarioModel;
use CodeIgniter\Validation\ValidationInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;

class UsuarioService
{
    private UsuarioModel $usuarioModel;
    private ValidationInterface $validator;

    public function __construct(UsuarioModel $usuarioModel, ValidationInterface $validator)
    {
        $this->usuarioModel = $usuarioModel;
        $this->validator    = $validator;
    }

    // --------------------------
    // Lecturas auxiliares
    // --------------------------
    public function list(int $limit = 50): array
    {
        return $this->usuarioModel->getUserList($limit);
    }

    public function getAuxData(): array
    {
        return [
            'agencias' => $this->usuarioModel->getAgencies(),
            'areas'    => $this->usuarioModel->getAreas(),
        ];
    }

    public function getUser(int $id): ?array
    {
        $u = $this->usuarioModel->getUserById($id);
        return $u ?: null;
    }

    public function cargosByArea(int $areaId): array
    {
        return $this->usuarioModel->getCargosByArea($areaId);
    }

    public function supervisorsByArea(int $areaId): array
    {
        return $this->usuarioModel->getSupervisorsByArea($areaId);
    }

    // --------------------------
    // Crear
    // --------------------------
    public function create(array $input): array
    {
        $docType   = (string) ($input['doc_type'] ?? 'CEDULA');
        $docNumber = trim((string) ($input['cedula'] ?? ''));

        [$rules, $messages] = $this->rules(false);

        if (!$this->validator->setRules($rules, $messages)->run($input)) {
            return ['ok' => false, 'errors' => $this->validator->getErrors()];
        }

        if ($err = $this->validateDocumento($docType, $docNumber)) {
            return ['ok' => false, 'errors' => $err];
        }

        if ($this->usuarioModel->docExists($docNumber)) {
            return ['ok' => false, 'errors' => ['cedula' => 'El número de documento ya está registrado.']];
        }

        $data = $this->buildData($input, $docNumber, false);

        try {
            $ok = $this->usuarioModel->insertUser($data);
        } catch (DatabaseException $e) {
            return ['ok' => false, 'errors' => $this->mapDbException($e)];
        }

        if (!$ok) {
            return ['ok' => false, 'errors' => $this->mapDbModelError($this->usuarioModel->getLastDbError())];
        }

        return ['ok' => true];
    }

    // --------------------------
    // Actualizar
    // --------------------------
    public function update(int $id, array $input): array
    {
        $docType   = (string) ($input['doc_type'] ?? 'CEDULA');
        $docNumber = trim((string) ($input['cedula'] ?? ''));

        [$rules, $messages] = $this->rules(true);

        if (!$this->validator->setRules($rules, $messages)->run($input)) {
            return ['ok' => false, 'errors' => $this->validator->getErrors()];
        }

        if ($err = $this->validateDocumento($docType, $docNumber)) {
            return ['ok' => false, 'errors' => $err];
        }

        if ($this->usuarioModel->docExistsForOtherUser($docNumber, $id)) {
            return ['ok' => false, 'errors' => ['cedula' => 'El número de documento ya está registrado en otro usuario.']];
        }

        $data = $this->buildData($input, $docNumber, true);

        $ok = $this->usuarioModel->updateUser($id, $data);
        if (!$ok) {
            return ['ok' => false, 'errors' => $this->mapDbModelError($this->usuarioModel->getLastDbError())];
        }

        return ['ok' => true];
    }

    // --------------------------
    // Reglas y mensajes
    // --------------------------
    private function rules(bool $isUpdate): array
    {
        $rules = [
            'nombres'     => 'required|min_length[2]|max_length[32]',
            'apellidos'   => 'required|min_length[2]|max_length[32]',
            'cedula'      => 'required|max_length[15]',
            'doc_type'    => 'required|in_list[CEDULA,PASAPORTE]',
            'id_agencias' => 'required|is_natural_no_zero',
            'id_area'     => 'required|is_natural_no_zero',
            'id_cargo'    => 'required|is_natural_no_zero',
        ];

        if ($isUpdate) {
            $rules['password'] = 'permit_empty|min_length[6]';
        } else {
            $rules['password'] = 'required|min_length[6]';
        }

        $messages = [
            'nombres' => [
                'required'   => 'El campo Nombres es obligatorio.',
                'min_length' => 'El campo Nombres debe tener al menos 2 caracteres.',
                'max_length' => 'El campo Nombres no debe exceder 32 caracteres.',
            ],
            'apellidos' => [
                'required'   => 'El campo Apellidos es obligatorio.',
                'min_length' => 'El campo Apellidos debe tener al menos 2 caracteres.',
                'max_length' => 'El campo Apellidos no debe exceder 32 caracteres.',
            ],
            'cedula' => [
                'required'   => 'El número de documento es obligatorio.',
                'max_length' => 'El número de documento no debe exceder 15 caracteres.',
            ],
            'doc_type' => [
                'required' => 'Debe seleccionar el tipo de documento.',
                'in_list'  => 'Tipo de documento inválido.',
            ],
            'password' => $isUpdate
                ? ['min_length' => 'La Contraseña debe tener al menos 6 caracteres.']
                : [
                    'required'   => 'La Contraseña es obligatoria.',
                    'min_length' => 'La Contraseña debe tener al menos 6 caracteres.',
                ],
            'id_agencias' => [
                'required'           => 'Debe seleccionar una Agencia.',
                'is_natural_no_zero' => 'Debe seleccionar una Agencia válida.',
            ],
            'id_area' => [
                'required'           => 'Debe seleccionar un Área.',
                'is_natural_no_zero' => 'Debe seleccionar un Área válida.',
            ],
            'id_cargo' => [
                'required'           => 'Debe seleccionar un Cargo.',
                'is_natural_no_zero' => 'Debe seleccionar un Cargo válido.',
            ],
        ];

        return [$rules, $messages];
    }

    // --------------------------
    // Validación específica de documento
    // --------------------------
    private function validateDocumento(string $docType, string $docNumber): ?array
    {
        if ($docType === 'CEDULA') {
            if ($docNumber === '' || !ctype_digit($docNumber)) {
                return ['cedula' => 'La cédula debe contener solo números.'];
            }
            if (strlen($docNumber) > 10) {
                return ['cedula' => 'La cédula debe tener máximo 10 dígitos.'];
            }
        } else {
            if ($docNumber === '' || !preg_match('/^[a-zA-Z0-9]+$/', $docNumber)) {
                return ['cedula' => 'El pasaporte/CI/NU solo debe contener letras y números.'];
            }
            if (strlen($docNumber) > 15) {
                return ['cedula' => 'El pasaporte/CI/NU debe tener máximo 15 caracteres.'];
            }
        }
        return null;
    }

    // --------------------------
    // Builder de datos con nombres de columnas intactos
    // --------------------------
    private function buildData(array $input, string $docNumber, bool $isUpdate): array
    {
        $isActive     = !empty($input['activo']);
        $agencyId     = (int) ($input['id_agencias'] ?? 0);
        $areaId       = (int) ($input['id_area'] ?? 0);
        $cargoId      = (int) ($input['id_cargo'] ?? 0);
        $supervisorId = (int) ($input['id_supervisor'] ?? 0);

        $data = [
            'nombres'       => (string) ($input['nombres'] ?? ''),
            'apellidos'     => (string) ($input['apellidos'] ?? ''),
            'cedula'        => $docNumber,
            'id_agencias'   => $agencyId,
            'id_area'       => $areaId,
            'id_cargo'      => $cargoId,
            'id_supervisor' => $supervisorId > 0 ? $supervisorId : null,
            'activo'        => $isActive,
        ];

        $password = (string) ($input['password'] ?? '');
        if (!$isUpdate || $password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        return $data;
    }

    // --------------------------
    // Mapeo de errores de BD (PostgreSQL)
    // --------------------------
    private function mapDbException(DatabaseException $e): array
    {
        $msg = $e->getMessage();
        if ($this->isDuplicateKeyMessage($msg)) {
            return ['cedula' => 'El número de documento ya está registrado.'];
        }
        return ['general' => 'No se pudo registrar el usuario. Intente nuevamente.'];
    }

    private function mapDbModelError(array $err): array
    {
        $msg  = $err['message'] ?? '';
        $code = (string) ($err['code'] ?? '');

        if ($code === '23505' || $this->isDuplicateKeyMessage($msg)) {
            return ['cedula' => 'El número de documento ya está registrado.'];
        }

        if (stripos($msg, 'invalid input syntax for type integer') !== false) {
            return ['cedula' => 'La base aún tiene el documento como numérico. Cambie la columna a VARCHAR para permitir pasaporte.'];
        }

        return ['general' => 'No se pudo procesar la operación. Revise los datos y vuelva a intentar.'];
    }

    private function isDuplicateKeyMessage(string $msg): bool
    {
        $lower = strtolower($msg);
        return str_contains($msg, '23505')
            || str_contains($lower, 'duplicate key')
            || str_contains($lower, 'llave duplicada')
            || str_contains($msg, 'USER_cedula_key');
    }
}
