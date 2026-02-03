<?php

namespace App\Controllers;

use App\Models\AgenciaModel;

class Agencias extends BaseController
{
    public function index()
    {
        // Crear instancia del modelo
        $model = new AgenciaModel();
        
        // Obtener todas las agencias
        $data['agencias'] = $model->findAll();
        
        // Cargar vista con datos
        return view('pages/agencias_views/agencias', $data);
    }
}