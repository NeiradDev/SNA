<?php

namespace App\Services;

use App\Models\CargoPermisoModel;
use App\Models\UsuarioCargoModel;
use Config\Database;

/**
 * =========================================================
 * Servicio: PermissionService
 * =========================================================
 * Calcula permisos reales del usuario:
 * - cargo principal desde USER.id_cargo
 * - cargos adicionales desde usuario_cargo
 * =========================================================
 */
class PermissionService
{
    protected CargoPermisoModel $cargoPermisoModel;
    protected UsuarioCargoModel $usuarioCargoModel;

    public function __construct()
    {
        $this->cargoPermisoModel = new CargoPermisoModel();
        $this->usuarioCargoModel = new UsuarioCargoModel();
    }

    /**
     * =========================================================
     * Devuelve todos los cargos del usuario
     * =========================================================
     */
    public function getUserCargoIds(int $idUser): array
    {
        $db = Database::connect();

        $cargoIds = [];

        // -----------------------------------------------------
        // 1) Cargo principal desde USER.id_cargo
        // -----------------------------------------------------
        $user = $db->table('"USER"')
            ->select('id_cargo')
            ->where('id_user', $idUser)
            ->get()
            ->getRowArray();

        if (!empty($user['id_cargo'])) {
            $cargoIds[] = (int) $user['id_cargo'];
        }

        // -----------------------------------------------------
        // 2) Cargos adicionales desde usuario_cargo
        // -----------------------------------------------------
        $extraCargoIds = $this->usuarioCargoModel->getCargoIdsByUser($idUser);

        $cargoIds = array_merge($cargoIds, $extraCargoIds);

        // -----------------------------------------------------
        // 3) Limpiar duplicados y ceros
        // -----------------------------------------------------
        $cargoIds = array_filter(array_unique(array_map('intval', $cargoIds)));

        return array_values($cargoIds);
    }

    /**
     * =========================================================
     * Devuelve todos los permisos del usuario
     * =========================================================
     */
    public function getUserPermissions(int $idUser): array
    {
        $cargoIds = $this->getUserCargoIds($idUser);

        if (empty($cargoIds)) {
            return [];
        }

        return $this->cargoPermisoModel->getPermissionCodesByCargoIds($cargoIds);
    }

    /**
     * =========================================================
     * Valida si usuario tiene un permiso específico
     * =========================================================
     */
    public function userHasPermission(int $idUser, string $permissionCode): bool
    {
        $permissions = $this->getUserPermissions($idUser);

        return in_array($permissionCode, $permissions, true);
    }

    /**
     * =========================================================
     * Valida si usuario tiene al menos uno de varios permisos
     * =========================================================
     */
    public function userHasAnyPermission(int $idUser, array $permissionCodes): bool
    {
        $permissions = $this->getUserPermissions($idUser);

        foreach ($permissionCodes as $code) {
            if (in_array((string) $code, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}