<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class Perfil extends BaseController
{
    public function index()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');
        if ($idUser <= 0) {
            return redirect()->to(site_url('login'));
        }

        $usuarioModel = new UsuarioModel();
        $profile = $usuarioModel->getUserProfileForPlan($idUser);

        $data = $profile ?? [];
        $data['nivel'] = session()->get('nivel');

        return view('perfil/index', $data);
    }

    public function updateCredentials()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        // =========================================================
        // Validar que la petición sea POST
        // =========================================================
        $method = strtoupper((string) $this->request->getMethod());
        if ($method !== 'POST') {
            return redirect()->to(site_url('perfil'));
        }

        // =========================================================
        // Obtener usuario logueado
        // =========================================================
        $idUser = (int) session()->get('id_user');
        if ($idUser <= 0) {
            return redirect()->to(site_url('login'));
        }

        $usuarioModel = new UsuarioModel();

        // =========================================================
        // Datos recibidos del formulario
        // =========================================================
        $newEmail        = trim((string) $this->request->getPost('correo'));
        $newPhone        = trim((string) $this->request->getPost('telefono'));
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        // =========================================================
        // Cargar datos actuales del usuario
        // =========================================================
        $currentUser = $usuarioModel->select('id_user, correo, telefono, password')->find($idUser);

        if (!$currentUser) {
            return redirect()->to(site_url('perfil'))
                ->with('error', 'No se pudo cargar tu usuario.');
        }

        // =========================================================
        // Array de datos a actualizar
        // =========================================================
        $updateData = [];

        // =========================================================
        // A) CORREO
        // =========================================================
        if ($newEmail !== '') {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'El correo no tiene un formato válido.');
            }

            $currentEmail = trim((string) ($currentUser['correo'] ?? ''));

            if ($newEmail !== $currentEmail) {
                if ($usuarioModel->emailExistsForOtherUser($newEmail, $idUser)) {
                    return redirect()->to(site_url('perfil'))
                        ->withInput()
                        ->with('error', 'Ese correo ya está registrado por otro usuario.');
                }

                $updateData['correo'] = $newEmail;
            }
        } else {
            // Permitir vaciar correo
            $currentEmail = trim((string) ($currentUser['correo'] ?? ''));
            if ($currentEmail !== '') {
                $updateData['correo'] = null;
            }
        }

        // =========================================================
        // B) TELÉFONO
        // =========================================================
        // Reglas:
        // - Debe ser celular ecuatoriano con código +593
        // - Se acepta con espacios, guiones o paréntesis
        // - Se guarda normalizado como +5939XXXXXXXX
        if ($newPhone !== '') {
            // -----------------------------------------------------
            // Guardamos una copia del valor original escrito
            // -----------------------------------------------------
            $rawPhone = $newPhone;

            // -----------------------------------------------------
            // Normalizar:
            // quitar espacios, guiones y paréntesis
            // Ejemplo:
            // +593 99 123 4567 -> +593991234567
            // -----------------------------------------------------
            $normalizedPhone = preg_replace('/[\s\-\(\)]+/', '', $rawPhone);

            // -----------------------------------------------------
            // Validar formato:
            // +593 seguido de 9 dígitos
            // iniciando en 9
            // Ejemplo válido: +593991234567
            // -----------------------------------------------------
            if (!preg_match('/^\+5939\d{8}$/', $normalizedPhone)) {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'El número de teléfono debe ser un celular ecuatoriano válido con código +593. Ejemplo: +593991234567');
            }

            // -----------------------------------------------------
            // Comparar contra el valor actual para evitar updates
            // innecesarios
            // -----------------------------------------------------
            $currentPhone = trim((string) ($currentUser['telefono'] ?? ''));

            if ($normalizedPhone !== $currentPhone) {
                $updateData['telefono'] = $normalizedPhone;
            }
        } else {
            // Permitir vaciar teléfono
            $currentPhone = trim((string) ($currentUser['telefono'] ?? ''));
            if ($currentPhone !== '') {
                $updateData['telefono'] = null;
            }
        }

        // =========================================================
        // C) CONTRASEÑA
        // =========================================================
        // Solo se cambia si el usuario escribió nueva contraseña
        // o confirmación
        $wantsPasswordChange = (trim($newPassword) !== '' || trim($confirmPassword) !== '');

        if ($wantsPasswordChange) {
            if (trim($currentPassword) === '') {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'Para cambiar la contraseña, ingresa tu contraseña actual.');
            }

            if (trim($newPassword) === '' || trim($confirmPassword) === '') {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'Ingresa y confirma la nueva contraseña.');
            }

            if ($newPassword !== $confirmPassword) {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'La nueva contraseña y la confirmación no coinciden.');
            }

            if (strlen($newPassword) < 6) {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
            }

            $hash = (string) ($currentUser['password'] ?? '');

            if ($hash === '' || !password_verify($currentPassword, $hash)) {
                return redirect()->to(site_url('perfil'))
                    ->withInput()
                    ->with('error', 'La contraseña actual es incorrecta.');
            }

            $updateData['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        // =========================================================
        // Si no hay cambios
        // =========================================================
        if (empty($updateData)) {
            return redirect()->to(site_url('perfil'))
                ->with('info', 'No hay cambios para guardar.');
        }

        // =========================================================
        // Guardar cambios del perfil
        // =========================================================
        $ok = $usuarioModel->update($idUser, $updateData);

        if (!$ok) {
            return redirect()->to(site_url('perfil'))
                ->withInput()
                ->with('error', 'No se pudo guardar. Intenta nuevamente.');
        }

        // =========================================================
        // Actualizar datos de sesión si cambiaron
        // =========================================================
        if (array_key_exists('correo', $updateData)) {
            session()->set('correo', $updateData['correo']);
        }

        if (array_key_exists('telefono', $updateData)) {
            session()->set('telefono', $updateData['telefono']);
        }

        return redirect()->to(site_url('perfil'))
            ->with('success', 'Datos actualizados correctamente.');
    }
}