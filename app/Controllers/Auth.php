<?php

namespace App\Controllers;

use App\Services\AuthService;

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
        // IMPORTANTE: como string para conservar 0 inicial
        $cedula = (string) $this->request->getPost('cedula');
        $pass   = (string) $this->request->getPost('password');

        $authService = new AuthService();
        $result = $authService->authenticate($cedula, $pass);

        if (($result['success'] ?? false) === false) {
            return view('auth/login_view', [
                'error' => $result['error'] ?? 'No se pudo iniciar sesión.',
                'old'   => $this->request->getPost(),
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
            'cedula'       => (string) $user['cedula'], // conserva 0 inicial
            'id_area'      => $user['id_area'] !== null ? (int) $user['id_area'] : null,
            'id_agencias'  => $user['id_agencias'] !== null ? (int) $user['id_agencias'] : null,
            'id_cargo'     => $user['id_cargo'] !== null ? (int) $user['id_cargo'] : null,
            'cargo_nombre' => (string) ($user['nombre_cargo'] ?? ''),
            'nivel'        => (string) $nivel,
        ]);

        return redirect()->to(site_url('home'));
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'));
    }
}
