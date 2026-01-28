<?php

namespace App\Controllers;

use Config\Database;

class Auth extends BaseController
{
    // Muestra el formulario de login
    public function login()
    {
        // Si ya hay sesión iniciada, evita volver al login
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('home'));
        }

        // ✅ Vista: app/Views/auth/login_view.php
        return view('auth/login_view', [
            'error' => null,
            'old'   => [],
        ]);
    }

    // Procesa el envío del formulario de login
    public function attempt()
    {
        $cedula = (int) $this->request->getPost('cedula');
        $pass   = (string) $this->request->getPost('password');

        // Validación básica
        if ($cedula <= 0 || trim($pass) === '') {
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
            FROM public."user" u
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

        // Usuario inactivo
        if ((int)($user['activo_int'] ?? 0) !== 1) {
            return view('auth/login_view', [
                'error' => 'Usuario deshabilitado. Contacta al administrador.',
                'old'   => ['cedula' => $cedula],
            ]);
        }

        // Password incorrecto
        if (!password_verify($pass, (string)($user['password'] ?? ''))) {
            return view('auth/login_view', [
                'error' => 'Credenciales incorrectas.',
                'old'   => ['cedula' => $cedula],
            ]);
        }

        // Nivel por cargo
        $cargoNombre = (string)($user['nombre_cargo'] ?? '');
        $nivel       = $this->nivelPorCargo($cargoNombre);

        // Seguridad
        session()->regenerate(true);

        // Guardar sesión
        session()->set([
            'logged_in'    => true,
            'id_user'      => (int)$user['id_user'],
            'nombres'      => (string)$user['nombres'],
            'apellidos'    => (string)$user['apellidos'],
            'cedula'       => (int)$user['cedula'],
            'id_area'      => $user['id_area'] !== null ? (int)$user['id_area'] : null,
            'id_agencias'  => $user['id_agencias'] !== null ? (int)$user['id_agencias'] : null,
            'id_cargo'     => $user['id_cargo'] !== null ? (int)$user['id_cargo'] : null,
            'cargo_nombre' => $cargoNombre,
            'nivel'        => $nivel,
        ]);

        // ✅ Login OK => HOME
        return redirect()->to(site_url('home'));
    }

    // Cierra sesión
    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'));
    }

    /**
     * Mapea cargos -> nivel (N1/N2/N3/N4)
     */
    private function nivelPorCargo(string $cargo): string
    {
        $cargo = mb_strtolower(trim($cargo));

        $n1 = ['vendedores', 'callcenter', 'cobranzas'];
        $n2 = ['jefe de agencia', 'sup de call center', 'sup cobranzas'];
        $n3 = [];
        $n4 = ['gerente'];

        if (in_array($cargo, $n4, true)) return 'N4';
        if (in_array($cargo, $n3, true)) return 'N3';
        if (in_array($cargo, $n2, true)) return 'N2';
        if (in_array($cargo, $n1, true)) return 'N1';

        return 'N1';
    }
}
