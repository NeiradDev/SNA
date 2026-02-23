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

        // ✅ Compatible con tu CI4: getMethod() sin argumentos
        $method = strtoupper((string) $this->request->getMethod());
        if ($method !== 'POST') {
            return redirect()->to(site_url('perfil'));
        }

        $idUser = (int) session()->get('id_user');
        if ($idUser <= 0) {
            return redirect()->to(site_url('login'));
        }

        $usuarioModel = new UsuarioModel();

        $newEmail        = trim((string) $this->request->getPost('correo'));
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        $currentUser = $usuarioModel->select('id_user, correo, password')->find($idUser);
        if (!$currentUser) {
            return redirect()->to(site_url('perfil'))
                ->with('error', 'No se pudo cargar tu usuario.');
        }

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

            $currentEmail = (string) ($currentUser['correo'] ?? '');
            if ($newEmail !== $currentEmail) {
                if ($usuarioModel->emailExistsForOtherUser($newEmail, $idUser)) {
                    return redirect()->to(site_url('perfil'))
                        ->withInput()
                        ->with('error', 'Ese correo ya está registrado por otro usuario.');
                }

                $updateData['correo'] = $newEmail;
            }
        }

        // =========================================================
        // B) CONTRASEÑA
        // =========================================================
        // ✅ Solo cambiamos password si el usuario escribió nueva/confirmación
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

        if (empty($updateData)) {
            return redirect()->to(site_url('perfil'))
                ->with('info', 'No hay cambios para guardar.');
        }

        $ok = $usuarioModel->update($idUser, $updateData);

        if (!$ok) {
            return redirect()->to(site_url('perfil'))
                ->withInput()
                ->with('error', 'No se pudo guardar. Intenta nuevamente.');
        }

        if (isset($updateData['correo'])) {
            session()->set('correo', $updateData['correo']);
        }

        return redirect()->to(site_url('perfil'))
            ->with('success', 'Datos actualizados correctamente.');
    }
}
