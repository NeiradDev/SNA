<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\DivisionModel;

class DivisionService
{
    public function __construct(private DivisionModel $divisionModel) {}

    public function validate(array $data): array
    {
        $name = trim((string)($data['nombre_division'] ?? ''));

        if ($name === '') return ['ok' => false, 'error' => 'El nombre de la división es obligatorio.'];
        if (mb_strlen($name) > 50) return ['ok' => false, 'error' => 'Máximo 50 caracteres.'];

        return ['ok' => true];
    }

    public function create(array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $ok = $this->divisionModel->insert(['nombre_division' => trim($data['nombre_division'])]);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo crear (puede estar duplicada).'];
    }

    public function update(int $id, array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $ok = $this->divisionModel->update($id, ['nombre_division' => trim($data['nombre_division'])]);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo actualizar.'];
    }
}
