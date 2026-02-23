<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OrgChartService;
use App\Models\UsuarioModel;

class OrgChart extends BaseController
{
    private OrgChartService $service;

    public function __construct()
    {
        $this->service = new OrgChartService();
    }

    public function division(int $divisionId)
    {
        // ---------------------------------------------------------
        // 1) Data base para la vista (title, dataUrl, divisionId, etc.)
        // ---------------------------------------------------------
        $data = $this->service->getDivisionPageData($divisionId);

        // ---------------------------------------------------------
        // 2) Datos del usuario logueado (para mensaje de WhatsApp)
        //    - Se usan como "YO" en el mensaje prellenado
        // ---------------------------------------------------------
        $idUser = (int) session()->get('id_user');

        // Valores por defecto (desde sesión)
        $me = [
            'fullName' => trim((string) session()->get('nombres') . ' ' . (string) session()->get('apellidos')),
            'cargo'    => (string) (session()->get('nombre_cargo') ?? ''), // si existe en sesión
            'area'     => (string) (session()->get('nombre_area') ?? ''),  // si existe en sesión
        ];

        // Si no vienen cargo/area en sesión, los consultamos desde BD
        if ($idUser > 0 && ($me['fullName'] === '' || $me['cargo'] === '' || $me['area'] === '')) {

            $usuarioModel = new UsuarioModel();

            /**
             * OJO:
             * Este método ya lo vienes usando en Perfil y te devuelve:
             * nombres, apellidos, nombre_cargo, nombre_area, etc.
             */
            $profile = $usuarioModel->getUserProfileForPlan($idUser);

            if (is_array($profile)) {
                if ($me['fullName'] === '') {
                    $me['fullName'] = trim((string)($profile['nombres'] ?? '') . ' ' . (string)($profile['apellidos'] ?? ''));
                }

                if ($me['cargo'] === '') {
                    $me['cargo'] = (string)($profile['nombre_cargo'] ?? '');
                }

                if ($me['area'] === '') {
                    $me['area'] = (string)($profile['nombre_area'] ?? '');
                }
            }
        }

        // Guardamos en $data para que la vista lo use en el link de WhatsApp
        $data['me'] = $me;

        return view('orgchart/orgchart', $data);
    }

    public function divisionData(int $divisionId)
    {
        // OJO DEL CARGO DE GERENTE
        // ✅ Esto devuelve { title, nodes } como tu vista espera
        $payload = $this->service->getDivisionTreePayload($divisionId, 6);

        return $this->response->setJSON($payload);
    }
}