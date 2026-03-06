<?php

declare(strict_types=1);

namespace App\Controllers\Mantenimiento;

use App\Controllers\BaseController;
use App\Models\PermisoModel;
use App\Models\CargoPermisoModel;
use Config\Database;

/**
 * =========================================================
 * Controlador: Mantenimiento\Permisos
 * =========================================================
 * Permite administrar visualmente los permisos por cargo.
 *
 * Soporta:
 * - Modo individual: un solo cargo
 * - Modo masivo: varios cargos al mismo tiempo
 * =========================================================
 */
class Permisos extends BaseController
{
    /**
     * =========================================================
     * Vista principal
     * =========================================================
     * URL:
     * /mantenimiento/permisos
     * /mantenimiento/permisos?cargo=1
     * =========================================================
     */
    public function index()
    {
        $db = Database::connect();

        // -----------------------------------------------------
        // 1) Cargo seleccionado desde GET
        // -----------------------------------------------------
        $selectedCargoId = (int) ($this->request->getGet('cargo') ?? 0);

        // -----------------------------------------------------
        // 2) Cargar cargos disponibles
        // -----------------------------------------------------
        $cargos = $db->query(
            '
            SELECT
                c.id_cargo,
                c.nombre_cargo,
                c.id_area,
                c.id_division,
                a.nombre_area,
                d.nombre_division
            FROM public.cargo c
            LEFT JOIN public.area a
                ON a.id_area = c.id_area
            LEFT JOIN public.division d
                ON d.id_division = c.id_division
            ORDER BY c.nombre_cargo ASC
            '
        )->getResultArray();

        // -----------------------------------------------------
        // 3) Cargar permisos activos
        // -----------------------------------------------------
        $permisoModel = new PermisoModel();

        $permissions = $permisoModel
            ->where('activo', true)
            ->orderBy('modulo', 'ASC')
            ->orderBy('nombre_permiso', 'ASC')
            ->findAll();

        // -----------------------------------------------------
        // 4) Agrupar permisos por módulo
        // -----------------------------------------------------
        $permissionsByModule = [];

        foreach ($permissions as $permission) {
            $module = (string) ($permission['modulo'] ?? 'general');

            if (!isset($permissionsByModule[$module])) {
                $permissionsByModule[$module] = [];
            }

            $permissionsByModule[$module][] = $permission;
        }

        // -----------------------------------------------------
        // 5) Cargar permisos ya asignados al cargo seleccionado
        // -----------------------------------------------------
        $assignedPermissionIds = [];

        if ($selectedCargoId > 0) {
            $cargoPermisoModel = new CargoPermisoModel();
            $assignedPermissionIds = $cargoPermisoModel->getPermissionIdsByCargo($selectedCargoId);
        }

        // -----------------------------------------------------
        // 6) Render de vista
        // -----------------------------------------------------
        return view('mantenimiento/permisos/index', [
            'cargos'                => $cargos,
            'selectedCargoId'       => $selectedCargoId,
            'permissionsByModule'   => $permissionsByModule,
            'assignedPermissionIds' => $assignedPermissionIds,
        ]);
    }

    /**
     * =========================================================
     * Guardar permisos
     * =========================================================
     * Soporta:
     * - Guardado individual
     * - Guardado masivo a varios cargos
     * =========================================================
     */
   public function save()
{
    $idCargo = (int) ($this->request->getPost('id_cargo') ?? 0);

    $permissionIds = $this->request->getPost('permission_ids');
    $permissionIds = is_array($permissionIds) ? $permissionIds : [];
    $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

    $targetCargoIds = $this->request->getPost('target_cargo_ids');
    $targetCargoIds = is_array($targetCargoIds) ? $targetCargoIds : [];
    $targetCargoIds = array_values(array_unique(array_map('intval', $targetCargoIds)));

    if (empty($targetCargoIds) && $idCargo > 0) {
        $targetCargoIds = [$idCargo];
    }

    if (empty($targetCargoIds)) {
        return redirect()->back()
            ->withInput()
            ->with('error', 'Debes seleccionar al menos un cargo.');
    }

    $cargoPermisoModel = new CargoPermisoModel();

    foreach ($targetCargoIds as $cargoIdItem) {
        $ok = $cargoPermisoModel->replacePermissionsByCargo((int) $cargoIdItem, $permissionIds);

        if (!$ok) {
            $redirectCargo = $idCargo > 0 ? $idCargo : (int) $targetCargoIds[0];

            return redirect()->to(site_url('mantenimiento/permisos?cargo=' . $redirectCargo))
                ->with('error', 'No se pudieron guardar los permisos para todos los cargos seleccionados.');
        }
    }

    $message = count($targetCargoIds) > 1
        ? 'Permisos actualizados correctamente para los cargos seleccionados.'
        : 'Permisos actualizados correctamente.';

    $redirectCargo = $idCargo > 0 ? $idCargo : (int) $targetCargoIds[0];

    return redirect()->to(site_url('mantenimiento/permisos?cargo=' . $redirectCargo))
        ->with('success', $message);
}
}