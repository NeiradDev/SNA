<?php

declare(strict_types=1);

namespace App\Controllers\Mantenimiento;

use App\Controllers\BaseController;
use App\Models\AreaModel;
use App\Models\DivisionModel;
use App\Services\Mantenimiento\AreaService;

class Area extends BaseController
{
    /**
     * Modelos requeridos por este módulo.
     */
    private AreaModel $areaModel;
    private DivisionModel $divisionModel;

    /**
     * Service con reglas de negocio (validaciones, etc.).
     */
    private AreaService $service;

    public function __construct()
    {
        // -----------------------------
        // Inicialización de dependencias
        // -----------------------------
        $this->areaModel     = new AreaModel();
        $this->divisionModel = new DivisionModel();

        // Service inyectado con el modelo para mantener SOLID.
        $this->service       = new AreaService($this->areaModel);
    }

    /**
     * LISTA: muestra áreas con su división, usando plantilla reciclada.
     */
    public function index()
    {
        return view('pages/mantenimiento/areas/lista_areas', [
            // Config para plantilla genérica (crud_lista.php)
            'entityTitle' => 'Áreas',
            'createUrl'   => base_url('mantenimiento/areas/crear'),
            'actionsBase' => 'mantenimiento/areas',

            // Data de la lista
            'rows'        => $this->areaModel->listWithDivision(),

            // Columnas a mostrar (PK primero)
            'columns'     => [
                ['key' => 'id_area',        'label' => 'ID'],
                ['key' => 'nombre_division','label' => 'División'],
                ['key' => 'nombre_area',    'label' => 'Área'],
            ],
        ]);
    }

    /**
     * CREATE: formulario vacío para crear área.
     */
    public function create()
    {
        return view('pages/mantenimiento/areas/crear_areas', [
            // Config plantilla genérica (crud_form.php)
            'formTitle' => 'Nueva área',
            'actionUrl' => base_url('mantenimiento/areas/guardar'),
            'backUrl'   => base_url('mantenimiento/areas'),

            // Fila actual (vacía)
            'row'       => [],

            // Campos del formulario (select de divisiones + input nombre_area)
            'fields'    => $this->buildFormFields(),

            // Error (si no hay, null)
            'error'     => null,
        ]);
    }

    /**
     * STORE: guarda nueva área.
     */
    public function store()
    {
        $data = [
            'nombre_area' => (string) $this->request->getPost('nombre_area'),
            'id_division' => (int) $this->request->getPost('id_division'),

            // id_jf_area opcional (por ahora lo guardamos si viene; si no, NULL).
            'id_jf_area'  => $this->request->getPost('id_jf_area') ?: null,
        ];

        $res = $this->service->create($data);

        if (!$res['ok']) {
            return view('pages/mantenimiento/areas/crear_areas', [
                'formTitle' => 'Nueva área',
                'actionUrl' => base_url('mantenimiento/areas/guardar'),
                'backUrl'   => base_url('mantenimiento/areas'),
                'row'       => $data,
                'fields'    => $this->buildFormFields(),
                'error'     => $res['error'] ?? 'Error',
            ]);
        }

        return redirect()->to(base_url('mantenimiento/areas'));
    }

    /**
     * SHOW: ver detalle de área.
     */
    public function show(int $id)
    {
        $row = $this->areaModel->findWithDivision($id);

        if (!$row) {
            return redirect()->to(base_url('mantenimiento/areas'));
        }

        return view('pages/mantenimiento/areas/ver_areas', [
            'entityTitle' => 'Áreas',
            'row'         => $row,
            'labels'      => [
                'id_area'         => 'ID',
                'nombre_division' => 'División',
                'nombre_area'     => 'Área',
            ],
            'backUrl'     => base_url('mantenimiento/areas'),
            'editUrl'     => base_url('mantenimiento/areas/editar/' . $id),
        ]);
    }

    /**
     * EDIT: formulario con data para editar.
     */
    public function edit(int $id)
    {
        $row = $this->areaModel->findWithDivision($id);

        if (!$row) {
            return redirect()->to(base_url('mantenimiento/areas'));
        }

        return view('pages/mantenimiento/areas/editar_areas', [
            'formTitle' => 'Editar área',
            'actionUrl' => base_url('mantenimiento/areas/actualizar/' . $id),
            'backUrl'   => base_url('mantenimiento/areas'),
            'row'       => $row,
            'fields'    => $this->buildFormFields(),
            'error'     => null,
        ]);
    }

    /**
     * UPDATE: actualiza área.
     */
    public function update(int $id)
    {
        $data = [
            'nombre_area' => (string) $this->request->getPost('nombre_area'),
            'id_division' => (int) $this->request->getPost('id_division'),
            'id_jf_area'  => $this->request->getPost('id_jf_area') ?: null,
        ];

        $res = $this->service->update($id, $data);

        if (!$res['ok']) {
            $row = $this->areaModel->findWithDivision($id) ?? [];

            return view('pages/mantenimiento/areas/editar_areas', [
                'formTitle' => 'Editar área',
                'actionUrl' => base_url('mantenimiento/areas/actualizar/' . $id),
                'backUrl'   => base_url('mantenimiento/areas'),
                'row'       => array_merge($row, $data),
                'fields'    => $this->buildFormFields(),
                'error'     => $res['error'] ?? 'Error',
            ]);
        }

        return redirect()->to(base_url('mantenimiento/areas'));
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Construye fields para la plantilla reciclada (crud_form.php).
     * - Select de división
     * - Input nombre_area
     */
    private function buildFormFields(): array
    {
        // Opciones de divisiones
        $divs = $this->divisionModel->listAll();

        $divisionOptions = array_map(static function (array $d): array {
            return [
                'value' => $d['id_division'],
                'label' => $d['nombre_division'],
            ];
        }, $divs);

        return [
            [
                'name'     => 'id_division',
                'label'    => 'División',
                'type'     => 'select',
                'required' => true,
                'options'  => $divisionOptions,
            ],
            [
                'name'     => 'nombre_area',
                'label'    => 'Nombre del área',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }
}
