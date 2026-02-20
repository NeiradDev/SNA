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
            return ['success' => false, 'error' => 'Completa cédula y password.'];
        }

        if (!ctype_digit($cedula)) {
            return ['success' => false, 'error' => 'La cédula debe contener solo números.'];
        }

        if (strlen($cedula) !== 10) {
            return ['success' => false, 'error' => 'La cédula debe tener 10 dígitos.'];
        }

        $user = $this->userModel->findByCedula($cedula);

        if (!$user) {
            return ['success' => false, 'error' => 'Credenciales incorrectas.'];
        }

        if (!$this->isUserActive($user)) {
            return ['success' => false, 'error' => 'Usuario deshabilitado. Contacta al administrador.'];
        }

        if (!$this->verifyPassword($pass, (string)($user['password'] ?? ''))) {
            return ['success' => false, 'error' => 'Credenciales incorrectas.'];
        }

        $cargoNombre = (string)($user['nombre_cargo'] ?? '');
        $areaId      = isset($user['id_area']) ? (int)$user['id_area'] : null;

        // ✅ NIVEL FINAL (robusto)
        $nivel = $this->resolveNivel($cargoNombre, $areaId);

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

    /**
     * ✅ Método público para poder recalcular nivel desde BaseController
     * sin tocar DB (solo texto).
     */
    public function resolveNivel(string $cargoNombre, ?int $areaId = null): string
    {
        $cargo = $this->normalizeText($cargoNombre);

        // -------------------------
        // N5 (máximo)
        // -------------------------
        // Administrador SIEMPRE debe ser N5
        if ($cargo !== '' && str_contains($cargo, 'admin')) return 'N5';
        if ($cargo !== '' && str_contains($cargo, 'administrador')) return 'N5';
        if ($cargo !== '' && str_contains($cargo, 'mantenimiento')) return 'N5';

        // (Opcional si quieres forzar por ID de área)
        // if ($areaId === 1) return 'N5';

        // -------------------------
        // N4
        // -------------------------
        if ($cargo !== '' && str_contains($cargo, 'gerente')) return 'N4';
        if ($cargo !== '' && str_contains($cargo, 'subgerente')) return 'N4';

        // -------------------------
        // N3
        // -------------------------
        if ($cargo !== '' && str_contains($cargo, 'jefe de division')) return 'N3';
        if ($cargo !== '' && str_contains($cargo, 'division')) return 'N3';

        // -------------------------
        // N2
        // -------------------------
        if ($cargo !== '' && str_contains($cargo, 'jefe de area')) return 'N2';
        if ($cargo !== '' && str_contains($cargo, 'area')) return 'N2';

        return 'N1';
    }

    /**
     * Normaliza:
     * - minúsculas
     * - sin tildes
     * - sin depender de iconv (evita false en Windows)
     */
    private function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));

        // reemplazo tildes/ñ (simple y seguro)
        $text = str_replace(
            ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'],
            ['a','e','i','o','u','n','a','e','i','o','u','n'],
            $text
        );

        return $text;
    }
}
