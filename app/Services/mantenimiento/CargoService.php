<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\CargoModel;

class CargoService
{
    public function __construct(private CargoModel $cargoModel) {}

    public function validate(array $data): array
    {
        $nameRaw = trim((string)($data['nombre_cargo'] ?? ''));
        $name = mb_strtolower($nameRaw);

        if ($nameRaw === '') return ['ok' => false, 'error' => 'El nombre del cargo es obligatorio.'];

        $hasArea = !empty($data['id_area']);
        $hasDivision = !empty($data['id_division']);

        // XOR
        if ($hasArea === $hasDivision) {
            return ['ok' => false, 'error' => 'El cargo debe pertenecer a un Área o a una División (no ambos).'];
        }

        // Excepciones por nombre
        if ((str_contains($name, 'jefe de división') || str_contains($name, 'jefe de division')) && !$hasDivision) {
            return ['ok' => false, 'error' => '“Jefe de División” debe pertenecer a una División.'];
        }
        if ((str_contains($name, 'jefe de área') || str_contains($name, 'jefe de area')) && !$hasArea) {
            return ['ok' => false, 'error' => '“Jefe de Área” debe pertenecer a un Área.'];
        }

        return ['ok' => true];
    }

    public function create(array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $payload = [
            'nombre_cargo' => trim((string)$data['nombre_cargo']),
            'id_area'      => !empty($data['id_area']) ? (int)$data['id_area'] : null,
            'id_division'  => !empty($data['id_division']) ? (int)$data['id_division'] : null,
        ];

        // Limpieza XOR
        if ($payload['id_area'] !== null) $payload['id_division'] = null;
        else $payload['id_area'] = null;

        $ok = $this->cargoModel->insert($payload);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo crear el cargo.'];
    }

    public function update(int $id, array $data): array
    {
        $check = $this->validate($data);
        if (!$check['ok']) return $check;

        $payload = [
            'nombre_cargo' => trim((string)$data['nombre_cargo']),
            'id_area'      => !empty($data['id_area']) ? (int)$data['id_area'] : null,
            'id_division'  => !empty($data['id_division']) ? (int)$data['id_division'] : null,
        ];

        if ($payload['id_area'] !== null) $payload['id_division'] = null;
        else $payload['id_area'] = null;

        $ok = $this->cargoModel->update($id, $payload);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo actualizar el cargo.'];
    }
}
