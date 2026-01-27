<?php

namespace App\Controllers;

// El nombre de la clase debe ser igual al nombre del archivo
class Users_crt extends BaseController
{
    public function index()
    {
        // Por ahora simulamos datos, luego vendrán del Modelo
        $data['usuarios'] = []; 
        return view('pages/user_list', $data);
    }
    public function crear()
    {
        return view('pages/users_create');
    }

    public function store()
    {
        $datos = $this->request->getPost();
        print_r($datos); // Para probar que los datos llegan
    }
}