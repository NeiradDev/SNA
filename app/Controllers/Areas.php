<?php

namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Services\OrgChartService;

class Areas extends BaseController
{
    // Vista: /areas/{slug}
    public function view(string $slug)
    {

        return view('areas/orgchart', [
            'dataUrl' => base_url('areas/orgchart-data/' . $slug),
        ]);
    }

    // JSON: /areas/orgchart-data/{slug}
    public function data(string $slug)
    {
        $service = new OrgChartService(new UsuarioModel());
        return $this->response->setJSON($service->getBySlug($slug));

        // llamado de vista del formulario
        return view('pages/areas_views/areas');

    }
}
