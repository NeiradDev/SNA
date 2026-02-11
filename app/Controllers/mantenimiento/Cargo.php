<?php

declare(strict_types=1);

namespace App\Controllers\Mantenimiento;

use App\Controllers\BaseController;
use App\Models\CargoModel;
use App\Models\AreaModel;
use App\Models\DivisionModel;
use App\Services\Mantenimiento\CargoService;

class Cargo extends BaseController
{
    /**
     * Modelos usados por este CRUD.
     */
    private CargoModel $cargoModel;
    private AreaModel $areaModel;
    private DivisionModel $divisionModel;

    /**
     * Service con reglas de negocio (XOR, excepciones jefe de área/división, etc.).
     */
    private CargoService $service;

    public function __construct()
    {
        // -----------------------------
        // Inicialización de dependencias
        // -----------------------------
        $this->cargoModel    = new CargoModel();
        $this->areaModel     = new AreaModel();
        $this->divisionModel = new DivisionModel();

        // Inyectamos el modelo al service para que el controller quede delgado.
        $this->service       = new CargoService($this->cargoModel);
    }

    /**
     * LISTA: muestra todos los cargos con su alcance (AREA/DIVISION),
     * nombre de área y/o nombre de división, usando plantilla reciclada.
     */
    public function index()
    {
        return view('pages/mantenimiento/cargos/lista_cargos', [
            // Config para plantilla genérica (crud_lista.php)
            'entityTitle' => 'Cargos',
            'createUrl'   => base_url('mantenimiento/cargos/crear'),
            'actionsBase' => 'mantenimiento/cargos',

            // Data de la lista
            'rows'        => $this->cargoModel->listWithScope(),

            // Columnas a mostrar (en el orden deseado)
            'columns'     => [
                ['key' => 'id_cargo',        'label' => 'ID'],
                ['key' => 'nombre_cargo',    'label' => 'Cargo'],
                ['key' => 'scope',           'label' => 'Alcance'],
                ['key' => 'nombre_division', 'label' => 'División'],
                ['key' => 'nombre_area',     'label' => 'Área'],
            ],
        ]);
    }

    /**
     * CREATE: formulario vacío para crear cargo.
     */
    public function create()
    {
        return view('pages/mantenimiento/cargos/crear_cargos', [
            // Config plantilla genérica (crud_form.php)
            'formTitle' => 'Nuevo cargo',
            'actionUrl' => base_url('mantenimiento/cargos/guardar'),
            'backUrl'   => base_url('mantenimiento/cargos'),

            // Fila actual (vacía)
            'row'       => [],

            // Campos del formulario
            'fields'    => $this->buildFormFields(),

            // Error (si no hay, null)
            'error'     => null,

            // Script opcional (XOR + reglas por nombre)
            'extraScript' => $this->buildCargoXorScript(),
        ]);
    }

    /**
     * STORE: guarda el cargo (sin delete).
     */
    public function store()
    {
        // -----------------------------
        // Tomamos los datos del POST
        // -----------------------------
        $data = [
            'nombre_cargo' => (string) $this->request->getPost('nombre_cargo'),
            'id_area'      => $this->request->getPost('id_area') ?: null,
            'id_division'  => $this->request->getPost('id_division') ?: null,
        ];

        // -----------------------------
        // Creamos usando el service
        // -----------------------------
        $res = $this->service->create($data);

        // Si falla, re-mostramos el formulario con error.
        if (!$res['ok']) {
            return view('pages/mantenimiento/cargos/crear_cargos', [
                'formTitle'    => 'Nuevo cargo',
                'actionUrl'    => base_url('mantenimiento/cargos/guardar'),
                'backUrl'      => base_url('mantenimiento/cargos'),
                'row'          => $data,
                'fields'       => $this->buildFormFields(),
                'error'        => $res['error'] ?? 'Error',
                'extraScript'  => $this->buildCargoXorScript(),
            ]);
        }

        // Si todo ok, redirigimos a la lista.
        return redirect()->to(base_url('mantenimiento/cargos'));
    }

    /**
     * SHOW: ver detalle del cargo.
     */
    public function show(int $id)
    {
        $row = $this->cargoModel->findWithScope($id);

        if (!$row) {
            return redirect()->to(base_url('mantenimiento/cargos'));
        }

        return view('pages/mantenimiento/cargos/ver_cargos', [
            'entityTitle' => 'Cargos',
            'row'         => $row,
            'labels'      => [
                'id_cargo'        => 'ID',
                'nombre_cargo'    => 'Cargo',
                'nombre_division' => 'División',
                'nombre_area'     => 'Área',
            ],
            'backUrl'     => base_url('mantenimiento/cargos'),
            'editUrl'     => base_url('mantenimiento/cargos/editar/' . $id),
        ]);
    }

    /**
     * EDIT: formulario con data.
     */
    public function edit(int $id)
    {
        $row = $this->cargoModel->findWithScope($id);

        if (!$row) {
            return redirect()->to(base_url('mantenimiento/cargos'));
        }

        return view('pages/mantenimiento/cargos/editar_cargos', [
            'formTitle'    => 'Editar cargo',
            'actionUrl'    => base_url('mantenimiento/cargos/actualizar/' . $id),
            'backUrl'      => base_url('mantenimiento/cargos'),
            'row'          => $row,
            'fields'       => $this->buildFormFields(),
            'error'        => null,
            'extraScript'  => $this->buildCargoXorScript(),
        ]);
    }

    /**
     * UPDATE: actualiza el cargo.
     */
    public function update(int $id)
    {
        $data = [
            'nombre_cargo' => (string) $this->request->getPost('nombre_cargo'),
            'id_area'      => $this->request->getPost('id_area') ?: null,
            'id_division'  => $this->request->getPost('id_division') ?: null,
        ];

        $res = $this->service->update($id, $data);

        if (!$res['ok']) {
            $row = $this->cargoModel->findWithScope($id) ?? [];

            return view('pages/mantenimiento/cargos/editar_cargos', [
                'formTitle'    => 'Editar cargo',
                'actionUrl'    => base_url('mantenimiento/cargos/actualizar/' . $id),
                'backUrl'      => base_url('mantenimiento/cargos'),
                'row'          => array_merge($row, $data),
                'fields'       => $this->buildFormFields(),
                'error'        => $res['error'] ?? 'Error',
                'extraScript'  => $this->buildCargoXorScript(),
            ]);
        }

        return redirect()->to(base_url('mantenimiento/cargos'));
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Construye el arreglo fields para la plantilla reciclada (crud_form.php).
     * Aquí armamos selects con options para áreas y divisiones.
     */
   private function buildFormFields(): array
{
    // ✅ Áreas con división (ideal: listWithDivision)
    $areas = method_exists($this->areaModel, 'listWithDivision')
        ? $this->areaModel->listWithDivision()
        : $this->areaModel->findAll(); // fallback

    $areaOptions = array_map(static function (array $a): array {
        // Si viene de listWithDivision: $a['id_division'] existe
        // Si viene de findAll: depende de tu tabla, igual podría existir.
        $divisionId = $a['id_division'] ?? '';

        return [
            'value' => $a['id_area'],
            'label' => $a['nombre_area'],
            // ✅ data attribute para JS
            'data'  => [
                'division' => $divisionId,
            ],
        ];
    }, $areas);

    // Divisiones igual
    $divs = $this->divisionModel->listAll();
    $divOptions = array_map(static function (array $d): array {
        return [
            'value' => $d['id_division'],
            'label' => $d['nombre_division'],
        ];
    }, $divs);

    return [
        [
            'name'     => 'nombre_cargo',
            'label'    => 'Nombre del cargo',
            'type'     => 'text',
            'required' => true,
            'id'       => 'nombreCargo',
            'help'     => 'Regla: el cargo debe pertenecer a un Área o a una División (no ambos).',
        ],
        [
            'name'     => 'id_area',
            'label'    => 'Área (si aplica)',
            'type'     => 'select',
            'required' => false,
            'id'       => 'selectArea',
            'options'  => $areaOptions,
        ],
        [
            'name'     => 'id_division',
            'label'    => 'División (si aplica)',
            'type'     => 'select',
            'required' => false,
            'id'       => 'selectDivision',
            'options'  => $divOptions,
        ],
    ];
}


    /**
     * Script opcional para mejorar UX:
     * - XOR: si eliges Área, limpia División y viceversa.
     * - Excepciones por nombre: "jefe de división" obliga división; "jefe de área" obliga área.
     */
    private function buildCargoXorScript(): string
{
    return <<<HTML
<script>
(function() {
  const nameInput = document.getElementById('nombreCargo');
  const areaSel   = document.getElementById('selectArea');
  const divSel    = document.getElementById('selectDivision');

  if (!areaSel || !divSel) return;

  function normalize(text) {
    return (text || '').toLowerCase().trim();
  }

  //Lee data-division del área seleccionada
  function getSelectedAreaDivisionId() {
    const opt = areaSel.options[areaSel.selectedIndex];
    if (!opt) return '';
    return opt.getAttribute('data-division') || '';
  }

  //Si hay área, setea división y bloquea el select de división
  function applyAreaAutoDivision() {
    if (!areaSel.value) {
      divSel.disabled = false; // si no hay área, división puede ser manual
      return;
    }

    const divId = getSelectedAreaDivisionId();
    if (divId) {
      divSel.value = divId;
    }

    // Evita inconsistencias: la división viene del área
    divSel.disabled = true;
  }

  //XOR clásico (si eligen división manual, limpia área)
  function enforceXor() {
    if (areaSel.value) {
      // Área manda (división autocalculada)
      applyAreaAutoDivision();
      return;
    }

    if (divSel.value) {
      areaSel.value = '';
      divSel.disabled = false;
    }
  }

  //Excepciones por nombre: jefe de división / jefe de área
  function applyNameRules() {
    if (!nameInput) return;

    const name = normalize(nameInput.value);
    const isBossDivision = name.includes('jefe de división') || name.includes('jefe de division');
    const isBossArea     = name.includes('jefe de área')     || name.includes('jefe de area');

    if (isBossDivision) {
      // Debe ser por división
      areaSel.value = '';
      areaSel.disabled = true;
      divSel.disabled = false;
    } else if (isBossArea) {
      // Debe ser por área (y división se autocalcula)
      divSel.value = '';
      divSel.disabled = true;
      areaSel.disabled = false;
    } else {
      areaSel.disabled = false;
      // división se bloquea solo si hay área seleccionada
      divSel.disabled = !!areaSel.value;
    }
  }

  // Eventos
  areaSel.addEventListener('change', function() {
    applyNameRules();
    applyAreaAutoDivision();
  });

  divSel.addEventListener('change', function() {
    applyNameRules();
    enforceXor();
  });

  if (nameInput) {
    nameInput.addEventListener('input', function() {
      applyNameRules();
      applyAreaAutoDivision();
    });
  }

  // init
  applyNameRules();
  applyAreaAutoDivision();
})();
</script>
HTML;
}

}
