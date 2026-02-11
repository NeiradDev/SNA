<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\DivisionModel;
use App\Services\DivisionService;

/**
 * Controller: Division
 *
 * ✅ NO afecta Usuarios ni nada del otro módulo.
 * ✅ Solo prepara data para mostrar cards dinámicos.
 */
class Division extends BaseController
{
    public function index()
    {
        // 1) Instanciamos model
        $divisionModel = new DivisionModel();

        // 2) Instanciamos service
        $divisionService = new DivisionService($divisionModel);

        // 3) Data lista para vista
        $divisiones = $divisionService->getCardsData();

        // 4) Render view
        return view('pages/division_views/division', [
            'divisiones' => $divisiones,
        ]);
    }
}
