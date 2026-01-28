<?php

namespace App\Controllers;

use App\Services\AuthService;

use Config\Database;


class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('home'));
        }

        return view('auth/login_view', [
            'error' => null,
            'old'   => [],
        ]);
    }

    public function attempt()
    {
        $cedula = (string) $this->request->getPost('cedula');
        $pass   = (string) $this->request->getPost('password');

        $authService = new AuthService();
        $result = $authService->authenticate($cedula, $pass);

        if ($result['success'] === false) {
            return view('auth/login_view', [
                'error' => 'Completa cédula y password.',
                'old'   => $this->request->getPost(),
            ]);
        }

        $db = Database::connect();

        $sql = '
            SELECT
                u.id_user,
                u.nombres,
                u.apellidos,
                u.cedula,
                u.password,
                (CASE WHEN u.activo THEN 1 ELSE 0 END) AS activo_int,
                u.id_area,
                u.id_agencias,
                u.id_cargo,
                c.nombre_cargo
            FROM public."USER" u
            LEFT JOIN public.cargo c ON c.id_cargo = u.id_cargo
            WHERE u.cedula = ?
            LIMIT 1
        ';

        $user = $db->query($sql, [$cedula])->getRowArray();

        // Usuario no existe
        if (!$user) {
            return view('auth/login_view', [
                'error' => 'Credenciales incorrectas.',
                'old'   => ['cedula' => $cedula],
            ]);
        }

        $user  = $result['user'];
        $nivel = $result['nivel'];

        session()->regenerate(true);

        session()->set([
            'logged_in'    => true,
            'id_user'      => (int) $user['id_user'],
            'nombres'      => (string) $user['nombres'],
            'apellidos'    => (string) $user['apellidos'],
            'cedula'       => (string) $user['cedula'],
            'id_area'      => $user['id_area'] !== null ? (int) $user['id_area'] : null,
            'id_agencias'  => $user['id_agencias'] !== null ? (int) $user['id_agencias'] : null,
            'id_cargo'     => $user['id_cargo'] !== null ? (int) $user['id_cargo'] : null,
            'cargo_nombre' => (string) ($user['nombre_cargo'] ?? ''),
            'nivel'        => $nivel,
        ]);

        return redirect()->to(site_url('home'));
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'));
    }
}