<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\UsuarioService;

class Usuarios extends BaseController
{
    private UsuarioService $service;

    public function __construct()
    {
        // Opción 5: Service Locator
        $this->service = service('usuarioService');
    }

    public function index()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        $data = [
            'usuarios' => $this->service->list($limit),
        ];

        return view('pages/usuario_views/Lista_usuario', $data);
    }

    public function create()
    {
        return view('pages/usuario_views/Crear_usuario', $this->service->getAuxData());
    }

    public function store()
    {
        $result = $this->service->create($this->request->getPost());

        if (!$result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors']);
        }

        return redirect()->to(base_url('usuarios'))
            ->with('success', 'Usuario registrado correctamente.');
    }

    public function edit(int $id)
    {
        $user = $this->service->getUser($id);
        if (empty($user)) {
            return redirect()->to(base_url('usuarios'))
                ->with('errors', ['general' => 'Usuario no encontrado.']);
        }

        $data = $this->service->getAuxData() + ['usuario' => $user];

        return view('pages/usuario_views/Editar_usuario', $data);
    }

    public function update(int $id)
    {
        $result = $this->service->update($id, $this->request->getPost());

        if (!$result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors']);
        }

        return redirect()->to(base_url('usuarios'))
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function getCargosByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON($this->service->cargosByArea($areaId));
    }

    public function getSupervisorsByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON($this->service->supervisorsByArea($areaId));
    }
    public function getDivision()
    {
        $divisionId = (int) ($this->request->getGet('id_divison') ?? 0);
        if ($divisionId <= 0) {
            return $this->response->setJSON([]);
        }

        return $this->response->setJSON($this->service->supervisorsByArea($divisionId));
    }
}
