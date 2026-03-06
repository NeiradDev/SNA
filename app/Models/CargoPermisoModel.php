<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

/**
 * =========================================================
 * Modelo: CargoPermisoModel
 * =========================================================
 * Maneja la tabla pivote public.cargo_permiso
 * =========================================================
 */
class CargoPermisoModel extends Model
{
    protected $table      = 'cargo_permiso';
    protected $returnType = 'array';
    protected $allowedFields = [
        'id_cargo',
        'id_permiso',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * =========================================================
     * Devuelve los códigos de permisos para una lista de cargos
     * =========================================================
     */
    public function getPermissionCodesByCargoIds(array $cargoIds): array
    {
        $cargoIds = array_values(array_unique(array_map('intval', $cargoIds)));

        if (empty($cargoIds)) {
            return [];
        }

        $db = Database::connect();

        $rows = $db->table('cargo_permiso cp')
            ->select('p.codigo')
            ->join('permiso p', 'p.id_permiso = cp.id_permiso', 'inner')
            ->whereIn('cp.id_cargo', $cargoIds)
            ->where('p.activo', true)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            static fn(array $row): string => (string) ($row['codigo'] ?? ''),
            $rows
        )));
    }

    /**
     * =========================================================
     * Reemplaza todos los permisos de un cargo
     * =========================================================
     */
    public function replacePermissionsByCargo(int $idCargo, array $permissionIds): bool
    {
        $db = Database::connect();

        $idCargo = (int) $idCargo;
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        $db->transStart();

        $db->table($this->table)
            ->where('id_cargo', $idCargo)
            ->delete();

        if (!empty($permissionIds)) {
            $batch = [];

            foreach ($permissionIds as $idPermiso) {
                $batch[] = [
                    'id_cargo'   => $idCargo,
                    'id_permiso' => $idPermiso,
                ];
            }

            $db->table($this->table)->insertBatch($batch);
        }

        $db->transComplete();

        return $db->transStatus();
    }

    /**
     * =========================================================
     * Devuelve IDs de permisos asignados a un cargo
     * =========================================================
     */
    public function getPermissionIdsByCargo(int $idCargo): array
    {
        $rows = $this->where('id_cargo', $idCargo)->findAll();

        return array_map(
            static fn(array $row): int => (int) ($row['id_permiso'] ?? 0),
            $rows
        );
    }
}