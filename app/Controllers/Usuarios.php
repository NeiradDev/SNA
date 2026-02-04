<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\UsuarioService;

class Usuarios extends BaseController
{
    private UsuarioService $service;

    public function __construct()
    {
        $this->service = service('usuarioService');
    }

    public function index()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        return view(
            'pages/usuario_views/Lista_usuario',
            ['usuarios' => $this->service->list($limit)]
        );
    }

    public function create()
    {
        return view(
            'pages/usuario_views/Crear_usuario',
            $this->service->getAuxData()
        );
    }

    public function store()
    {
        $result = $this->service->create($this->request->getPost());

        if (!$result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors']);
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
            $this->service->getAuxData() + ['usuario' => $user]
        );
    }

    public function update(int $id)
    {
        $result = $this->service->update($id, $this->request->getPost());

        if (!$result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors']);
        }

        return redirect()->to('usuarios')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    // API
    public function getCargosByArea()
    {
        $areaId = (int) $this->request->getGet('id_area');
        return $areaId > 0
            ? $this->response->setJSON($this->service->cargosByArea($areaId))
            : $this->response->setJSON([]);
    }

    public function getSupervisorsByArea()
    {
        $areaId = (int) $this->request->getGet('id_area');
        return $areaId > 0
            ? $this->response->setJSON($this->service->supervisorsByArea($areaId))
            : $this->response->setJSON([]);
    }
}

