<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\MenuService;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected array $menuAllowed = [];
    protected array $permissions = [];

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);

        if (session()->get('logged_in') === true) {
            $authService = new AuthService();
            $menuService = new MenuService();

            $nivelActual = (string) (session()->get('nivel') ?? '');
            $cargoSesion = (string) (session()->get('cargo_nombre') ?? '');
            $areaSesion  = session()->get('id_area');
            $areaId      = ($areaSesion !== null) ? (int) $areaSesion : null;

            $nivelReal = $authService->resolveNivel($cargoSesion, $areaId);

            if ($nivelReal !== '' && $nivelReal !== $nivelActual) {
                session()->set('nivel', $nivelReal);
            }

            $idUser  = (int) (session()->get('id_user') ?? 0);
            $idCargo = (int) (session()->get('id_cargo') ?? 0);

            // =====================================================
            // SUPERADMIN TEMPORAL: id_cargo = 1
            // =====================================================
            if ($idCargo === 1) {
                $this->permissions = [
                    'home.ver',
                    'perfil.ver',

                    'usuarios.ver',
                    'usuarios.crear',
                    'usuarios.editar',

                    'agencias.ver',

                    'division.ver',
                    'division.crear',

                    'areas.ver',

                    'cargos.ver',
                    'cargos.crear',
                    'cargos.editar',
                    'cargos.eliminar',

                    'tareas.calendario',
                    'tareas.asignar',
                    'tareas.gestionar',
                    'tareas.satisfaccion',

                    'reporte.horario_plan',
                    'reporte.plan',
                    'reporte.historico',
                    'reporte.completado',

                    'mantenimiento.divisiones.ver',
                    'mantenimiento.divisiones.crear',
                    'mantenimiento.divisiones.editar',

                    'mantenimiento.areas.ver',
                    'mantenimiento.areas.crear',
                    'mantenimiento.areas.editar',

                    'mantenimiento.cargos.ver',
                    'mantenimiento.cargos.crear',
                    'mantenimiento.cargos.editar',

                    'orgchart.ver',
                ];

                $this->menuAllowed = $menuService->getFullMenu();
            } else {
                if ($idUser > 0) {
                    $permissionService = new PermissionService();
                    $this->permissions = $permissionService->getUserPermissions($idUser);
                    $this->menuAllowed = $menuService->getMenuByPermissions($this->permissions);
                }
            }

            session()->set('permissions', $this->permissions);

            service('renderer')->setVar('menuAllowed', $this->menuAllowed);
            service('renderer')->setVar('permissions', $this->permissions);
        }
    }
}