<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\UsuarioService;


class Usuarios extends BaseController
{
    /**
     * Servicio de usuarios (inyectado desde Config\Services).
     */
    private UsuarioService $service;

    public function __construct()
    {
        // ✅ Mantienes tu forma actual
        $this->service = service('usuarioService');
    }

    // =========================================================
    // VISTAS (HTML)
    // =========================================================

    public function index()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        return view('pages/usuario_views/Lista_usuario', [
            'usuarios' => $this->service->list($limit),
        ]);
    }

    public function create()
    {
        return view(
            'pages/usuario_views/Crear_usuario',
            $this->service->getAuxDataForCreate()
        );
    }

    public function store()
    {
        $post = $this->request->getPost();

        $result = $this->service->create($post);

        if (empty($result['ok'])) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors'] ?? ['general' => 'No se pudo registrar el usuario.']);
        }

        return redirect()->to('usuarios')
            ->with('success', 'Usuario registrado correctamente.');
    }

    public function edit(int $id)
    {
        $user = $this->service->getUser($id);

        if (!$user) {
            return redirect()->to('usuarios')
                ->with('errors', ['general' => 'Usuario no encontrado.']);
        }

        return view(
            'pages/usuario_views/Editar_usuario',
            $this->service->getAuxDataForEdit($id) + ['usuario' => $user]
        );
    }

    public function update(int $id)
    {
        $post = $this->request->getPost();

        $result = $this->service->update($id, $post);

        if (empty($result['ok'])) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors'] ?? ['general' => 'No se pudo actualizar el usuario.']);
        }

        return redirect()->to('usuarios')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    // =========================================================
    // ENDPOINTS INTERNOS (JSON) PARA COMBOS
    // =========================================================

    public function getAreasByDivision()
    {
        $divisionId = (int) $this->request->getGet('id_division');

        if ($divisionId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON(
            $this->service->areasByDivision($divisionId)
        );
    }

    public function getCargosByArea()
    {
        $areaId = (int) $this->request->getGet('id_area');

        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON(
            $this->service->cargosByArea($areaId)
        );
    }

    public function getCargosByDivision()
    {
        $divisionId = (int) $this->request->getGet('id_division');

        if ($divisionId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON(
            $this->service->cargosByDivision($divisionId)
        );
    }

    /**
     * ✅ getSupervisorsByArea()
     *
     * GET params:
     * - id_area (requerido)
     * - exclude_id (opcional) -> para editar, excluir al mismo usuario
     * - keep_id (opcional)    -> para editar, mantener supervisor actual en la lista
     * - id_cargo_gerencia (opcional) -> fallback final si no hay jefe área/división
     *
     * Retorna (según tu Model nuevo):
     * - [{id_user, supervisor_label, reason?}, ...]
     *
     * ✅ Esto arregla tu problema:
     * - Si el área tiene id_jf_area, ese debe aparecer como supervisor (primero).
     * - Si no, cae al jefe de división (division.id_jf_division).
     * - Si tampoco, (opcional) cae a gerencia por cargo.
     */
    public function getSupervisorsByArea()
    {
        // 1) Requerido
        $areaId = (int) $this->request->getGet('id_area');
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        // 2) Opcionales (para EDITAR)
        $excludeId = (int) ($this->request->getGet('exclude_id') ?? 0);
        if ($excludeId < 0) $excludeId = 0;

        $keepId = (int) ($this->request->getGet('keep_id') ?? 0);
        if ($keepId < 0) $keepId = 0;

        // 3) Gerencia (fallback final opcional)
        $gerenciaCargoId = (int) ($this->request->getGet('id_cargo_gerencia') ?? 0);
        if ($gerenciaCargoId < 0) $gerenciaCargoId = 0;

        // 4) Llamada al service (nuevo contrato)
        return $this->response->setJSON(
            $this->service->supervisorsByArea(
                $areaId,
                $excludeId,
                $keepId,
                $gerenciaCargoId
            )
        );
    }

    public function getGerenciaUser()
    {
        $gerenciaCargoId = (int) ($this->request->getGet('id_cargo_gerencia') ?? 6);
        if ($gerenciaCargoId <= 0) $gerenciaCargoId = 6;

        return $this->response->setJSON(
            $this->service->getGerenciaUser($gerenciaCargoId)
        );
    }

    public function getDivisionBossByDivision()
    {
        $divisionId = (int) $this->request->getGet('id_division');

        if ($divisionId <= 0) {
            return $this->response->setJSON(['ok' => false, 'boss' => null]);
        }

        return $this->response->setJSON(
            $this->service->getDivisionBossByDivision($divisionId)
        );
    }
}
