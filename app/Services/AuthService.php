<?php

namespace App\Services;

use App\Models\LoginSqlModel;

class AuthService
{
    protected LoginSqlModel $userModel;

    public function __construct(?LoginSqlModel $LoginSqlModel = null)
    {
        $this->userModel = $LoginSqlModel ?? new LoginSqlModel();
    }

    public function authenticate(string $cedula, string $pass): array
    {
        $cedula = trim($cedula);
        $pass   = trim($pass);

        if ($cedula === '' || $pass === '') {
            return [
                'success' => false,
                'error'   => 'Completa cédula y password.',
            ];
        }

        // Solo números, permite 0 al inicio
        if (!ctype_digit($cedula)) {
            return [
                'success' => false,
                'error'   => 'La cédula debe contener solo números.',
            ];
        }

        // Si usas cédula ecuatoriana común (10 dígitos)
        if (strlen($cedula) !== 10) {
            return [
                'success' => false,
                'error'   => 'La cédula debe tener 10 dígitos.',
            ];
        }

        $user = $this->userModel->findByCedula($cedula);

        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Credenciales incorrectas.',
            ];
        }

        if (!$this->isUserActive($user)) {
            return [
                'success' => false,
                'error'   => 'Usuario deshabilitado. Contacta al administrador.',
            ];
        }

        if (!$this->verifyPassword($pass, (string)($user['password'] ?? ''))) {
            return [
                'success' => false,
                'error'   => 'Credenciales incorrectas.',
            ];
        }

        $cargoNombre = (string)($user['nombre_cargo'] ?? '');
        $nivel       = $this->nivelPorCargo($cargoNombre);

        return [
            'success' => true,
            'user'    => $user,
            'nivel'   => $nivel,
        ];
    }

    private function isUserActive(array $user): bool
    {
        return (int)($user['activo_int'] ?? 0) === 1;
    }

    private function verifyPassword(string $pass, string $hash): bool
    {
        return password_verify($pass, $hash);
    }

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
