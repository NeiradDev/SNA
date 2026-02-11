<?php

declare(strict_types=1);

namespace App\Controllers\Mantenimiento;

use App\Controllers\BaseController;
use App\Models\DivisionModel;
use App\Services\Mantenimiento\DivisionService;

class Division extends BaseController
{
    private DivisionModel $model;
    private DivisionService $service;

    public function __construct()
    {
        $this->model = new DivisionModel();
        $this->service = new DivisionService($this->model);
    }

    public function index()
    {
        return view('pages/mantenimiento/divisiones/lista_divisiones', [
            // Config para plantilla reutilizable
            'entityTitle' => 'Divisiones',
            'createUrl'   => base_url('mantenimiento/divisiones/crear'),
            'rows'        => $this->model->listAll(),
            'columns'     => [
                ['key' => 'id_division', 'label' => 'ID'],
                ['key' => 'nombre_division', 'label' => 'División'],
            ],
            'actionsBase' => 'mantenimiento/divisiones',
        ]);
    }

    public function create()
    {
        return view('pages/mantenimiento/divisiones/crear_divisiones', [
            'entityTitle' => 'Divisiones',
            'formTitle'   => 'Nueva división',
            'actionUrl'   => base_url('mantenimiento/divisiones/guardar'),
            'fields'      => [
                ['name' => 'nombre_division', 'label' => 'Nombre de la división', 'type' => 'text', 'required' => true],
            ],
            'row'         => null,
            'error'       => null,
        ]);
    }

    public function store()
    {
        $data = ['nombre_division' => (string)$this->request->getPost('nombre_division')];
        $res = $this->service->create($data);

        if (!$res['ok']) {
            return view('pages/mantenimiento/divisiones/crear_divisiones', [
                'entityTitle' => 'Divisiones',
                'formTitle'   => 'Nueva división',
                'actionUrl'   => base_url('mantenimiento/divisiones/guardar'),
                'fields'      => [
                    ['name' => 'nombre_division', 'label' => 'Nombre de la división', 'type' => 'text', 'required' => true],
                ],
                'row'         => $data,
                'error'       => $res['error'] ?? 'Error',
            ]);
        }

        return redirect()->to(base_url('mantenimiento/divisiones'));
    }

    public function show(int $id)
    {
        $row = $this->model->find($id);
        if (!$row) return redirect()->to(base_url('mantenimiento/divisiones'));

        return view('pages/mantenimiento/divisiones/ver_divisiones', [
            'entityTitle' => 'Divisiones',
            'row'         => $row,
            'labels'      => [
                'id_division'     => 'ID',
                'nombre_division' => 'División',
            ],
            'backUrl'     => base_url('mantenimiento/divisiones'),
            'editUrl'     => base_url('mantenimiento/divisiones/editar/' . $id),
        ]);
    }

    public function edit(int $id)
    {
        $row = $this->model->find($id);
        if (!$row) return redirect()->to(base_url('mantenimiento/divisiones'));

        return view('pages/mantenimiento/divisiones/editar_divisiones', [
            'entityTitle' => 'Divisiones',
            'formTitle'   => 'Editar división',
            'actionUrl'   => base_url('mantenimiento/divisiones/actualizar/' . $id),
            'fields'      => [
                ['name' => 'nombre_division', 'label' => 'Nombre de la división', 'type' => 'text', 'required' => true],
            ],
            'row'         => $row,
            'error'       => null,
        ]);
    }

    public function update(int $id)
    {
        $data = ['nombre_division' => (string)$this->request->getPost('nombre_division')];
        $res = $this->service->update($id, $data);

        if (!$res['ok']) {
            $row = $this->model->find($id) ?? $data;

            return view('pages/mantenimiento/divisiones/editar_divisiones', [
                'entityTitle' => 'Divisiones',
                'formTitle'   => 'Editar división',
                'actionUrl'   => base_url('mantenimiento/divisiones/actualizar/' . $id),
                'fields'      => [
                    ['name' => 'nombre_division', 'label' => 'Nombre de la división', 'type' => 'text', 'required' => true],
                ],
                'row'         => array_merge($row, $data),
                'error'       => $res['error'] ?? 'Error',
            ]);
        }

        return redirect()->to(base_url('mantenimiento/divisiones'));
    }
}
