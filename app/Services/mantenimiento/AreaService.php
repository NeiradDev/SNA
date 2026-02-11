<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\AreaModel;

class AreaService
{
    public function __construct(private AreaModel $areaModel) {}

    public function validate(array $data): array
    {
        $name = trim((string)($data['nombre_area'] ?? ''));
        $divisionId = (int)($data['id_division'] ?? 0);

        if ($name === '') return ['ok' => false, 'error' => 'El nombre del área es obligatorio.'];
        if ($divisionId <= 0) return ['ok' => false, 'error' => 'Debes seleccionar una división.'];

        return ['ok' => true];
    }

    public function create(array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $payload = [
            'nombre_area' => trim((string)$data['nombre_area']),
            'id_division' => (int)$data['id_division'],
            'id_jf_area'  => !empty($data['id_jf_area']) ? (int)$data['id_jf_area'] : null,
        ];

        $ok = $this->areaModel->insert($payload);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo crear el área.'];
    }

    public function update(int $id, array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $payload = [
            'nombre_area' => trim((string)$data['nombre_area']),
            'id_division' => (int)$data['id_division'],
            'id_jf_area'  => !empty($data['id_jf_area']) ? (int)$data['id_jf_area'] : null,
        ];

        $ok = $this->areaModel->update($id, $payload);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo actualizar el área.'];
    }
}
