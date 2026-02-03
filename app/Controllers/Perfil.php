<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class Perfil extends BaseController
{
    public function index()
    {
        /**
         * 1) Validamos que exista sesión iniciada
         */
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        /**
         * 2) Tomamos el ID del usuario desde sesión
         *    (Asegúrate de que en tu login guardas 'id_user')
         */
        $idUser = (int) session()->get('id_user');

        if ($idUser <= 0) {
            return redirect()->to(site_url('login'));
        }

        /**
         * 3) Llamamos al modelo y traemos el perfil completo desde BD
         */
        $usuarioModel = new UsuarioModel();
        $profile = $usuarioModel->getUserProfileForPlan($idUser);

        /**
         * 4) Armamos el data para la vista
         *    - "nivel" lo sigues tomando de sesión si no está en BD
         */
        $data = $profile ?? [];
        $data['nivel'] = session()->get('nivel');

        /**
         * 5) Retornamos la vista con los datos ya listos:
         *    nombre_area, nombre_cargo, nombre_division, nombre_agencia, etc.
         */
        return view('perfil/index', $data);
    }
}
