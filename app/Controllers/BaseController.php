<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Services\MenuService;
use App\Services\AuthService;

abstract class BaseController extends Controller
{
    protected array $menuAllowed = [];

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    )
    {
        parent::initController($request, $response, $logger);

        if (session()->get('logged_in') === true) {

            // -------------------------------------------------------
            // ✅ 1) “AUTO-FIX” DEL NIVEL (por cargo_nombre de sesión)
            // -------------------------------------------------------
            $nivelActual = (string)(session()->get('nivel') ?? '');
            $cargoSesion = (string)(session()->get('cargo_nombre') ?? '');
            $areaSesion  = session()->get('id_area');
            $areaId      = ($areaSesion !== null) ? (int)$areaSesion : null;

            // Recalcula nivel basado en texto (no DB)
            $authService = new AuthService();
            $nivelReal   = $authService->resolveNivel($cargoSesion, $areaId);

            // Si cambia, actualizamos sesión (esto te arregla el N1 pegado)
            if ($nivelReal !== '' && $nivelReal !== $nivelActual) {
                session()->set('nivel', $nivelReal);
                $nivelActual = $nivelReal;
            }

            // -------------------------------------------------------
            // ✅ 2) Menú por nivel
            // -------------------------------------------------------
            if ($nivelActual !== '') {
                $menuService = new MenuService();
                $this->menuAllowed = $menuService->getMenuByNivel($nivelActual);

                // Variable global para vistas
                service('renderer')->setVar('menuAllowed', $this->menuAllowed);
            }
        }
    }
}
