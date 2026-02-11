<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OrgChartService;

class OrgChart extends BaseController
{
    private OrgChartService $service;

    public function __construct()
    {
        $this->service = new OrgChartService();
    }

    public function division(int $divisionId)
    {
        $data = $this->service->getDivisionPageData($divisionId);
         return view('orgchart/orgchart', $data);

    }

    public function divisionData(int $divisionId)
    {
        //OJO DEL CARGO DE GERENTE
        // ✅ Esto devuelve { title, nodes } como tu vista espera
        $payload = $this->service->getDivisionTreePayload($divisionId, 6);

        return $this->response->setJSON($payload);
    }
}
