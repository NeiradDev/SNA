<?php

namespace App\Controllers;

class Perfil extends BaseController
{
    public function index()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $data = [
            'nombres'      => session()->get('nombres'),
            'apellidos'    => session()->get('apellidos'),
            'cedula'       => session()->get('cedula'),
            'cargo_nombre' => session()->get('cargo_nombre'),
            'nivel'        => session()->get('nivel'),
            'id_area'      => session()->get('id_area'),
            'id_agencias'  => session()->get('id_agencias'),
        ];

        return view('perfil/index', $data);
    }
}
