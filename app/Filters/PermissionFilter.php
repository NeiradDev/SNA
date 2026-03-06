<?php

namespace App\Filters;

use App\Services\PermissionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * =========================================================
 * Filtro: PermissionFilter
 * =========================================================
 * Uso en rutas:
 *
 * ['filter' => 'permission:usuarios.ver']
 * ['filter' => 'permission:usuarios.ver,usuarios.crear']
 *
 * Regla:
 * - Si el usuario tiene AL MENOS UNO de los permisos indicados -> entra
 * - Si no tiene ninguno -> se bloquea
 *
 * EXCEPCIÓN TEMPORAL:
 * - Si el usuario tiene id_cargo = 1, entra a todo
 * =========================================================
 */
class PermissionFilter implements FilterInterface
{
    /**
     * =========================================================
     * Ejecuta validación ANTES de entrar a la ruta
     * =========================================================
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // -----------------------------------------------------
        // 1) Validar sesión iniciada
        // -----------------------------------------------------
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        // -----------------------------------------------------
        // 2) SUPERADMIN TEMPORAL
        //    Si id_cargo = 1, permitir acceso total
        // -----------------------------------------------------
        $idCargo = (int) (session()->get('id_cargo') ?? 0);

        if ($idCargo === 1) {
            return;
        }

        // -----------------------------------------------------
        // 3) Validar usuario en sesión
        // -----------------------------------------------------
        $idUser = (int) (session()->get('id_user') ?? 0);

        if ($idUser <= 0) {
            return redirect()->to(site_url('login'));
        }

        // -----------------------------------------------------
        // 4) Leer permisos requeridos desde la ruta
        // -----------------------------------------------------
        // Ejemplo:
        // 'permission:usuarios.ver'
        // 'permission:usuarios.ver,usuarios.crear'
        $requiredPermissions = array_filter(array_map(
            static fn($value) => trim((string) $value),
            is_array($arguments) ? $arguments : []
        ));

        // Si la ruta usa el filtro pero no manda permisos, bloquear
        if (empty($requiredPermissions)) {
            return redirect()->to(site_url('home'))
                ->with('error', 'No se definieron permisos para esta ruta.');
        }

        // -----------------------------------------------------
        // 5) Validar permisos reales del usuario
        // -----------------------------------------------------
        $permissionService = new PermissionService();

        $hasAccess = $permissionService->userHasAnyPermission($idUser, $requiredPermissions);

        if (!$hasAccess) {
            return redirect()->to(site_url('home'))
                ->with('error', 'No tienes permisos para acceder a esta sección.');
        }

        // Si tiene permiso, simplemente deja continuar
        return;
    }

    /**
     * =========================================================
     * Lógica posterior a la respuesta
     * =========================================================
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se requiere lógica after por ahora
    }
}