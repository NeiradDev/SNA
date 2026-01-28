<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Usuarios extends BaseController
{
    /**
     * Muestra la lista de usuarios.
     * - Lee el parámetro GET "limit" (por defecto 50)
     * - Consulta al model y envía el array $usuarios a la vista
     */
    public function index()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        $usuarioModel = new UsuarioModel();

        $data = [
            'usuarios' => $usuarioModel->getUserList($limit),
        ];

        return view('pages/Lista_usuario', $data);
    }

    /**
     * Muestra el formulario de creación.
     * - Carga agencias y áreas para poblar los combobox
     * - NO rompe la lógica del JS: el cambio de área sigue cargando cargos y supervisores por API
     */
    public function create()
    {
        $usuarioModel = new UsuarioModel();

        $data = [
            'agencias' => $usuarioModel->getAgencies(),
            'areas'    => $usuarioModel->getAreas(),
        ];

        return view('pages/Crear_usuario', $data);
    }

    /**
     * Guarda un usuario.
     *
     * Flujo:
     * 1) Validación general del formulario (campos obligatorios, longitudes, selects válidos)
     * 2) Validación específica del documento:
     *    - CÉDULA: solo números y máximo 10
     *    - PASAPORTE/CI/NU: alfanumérico y máximo 15
     * 3) Verificación de duplicado del documento en BD
     * 4) Insert en BD y manejo de errores (duplicado, tipo de dato incorrecto, etc.)
     *
     * Importante:
     * - Esto NO afecta los endpoints del combobox (getCargosByArea / getSupervisorsByArea).
     * - Los mensajes se devuelven vía flashdata('errors') para mostrarlos en tu modal.
     */
    public function store()
    {
        $usuarioModel = new UsuarioModel();

        // ============================================================
        // 0) Lectura de inputs del documento
        // ============================================================
        // doc_type: "CEDULA" o "PASAPORTE"
        // cedula: número/identificador (puede ser alfanumérico si es pasaporte)
        $docType   = (string) ($this->request->getPost('doc_type') ?? 'CEDULA');
        $docNumber = trim((string) ($this->request->getPost('cedula') ?? ''));

        // ============================================================
        // 1) Validación base (reglas generales de formulario)
        // ============================================================
        $rules = [
            'nombres'     => 'required|min_length[2]|max_length[32]',
            'apellidos'   => 'required|min_length[2]|max_length[32]',
            'cedula'      => 'required|max_length[15]',
            'doc_type'    => 'required|in_list[CEDULA,PASAPORTE]',
            'password'    => 'required|min_length[6]',
            'id_agencias' => 'required|is_natural_no_zero',
            'id_area'     => 'required|is_natural_no_zero',
            'id_cargo'    => 'required|is_natural_no_zero',
        ];

        // Mensajes en español (los métodos y variables se mantienen en inglés básico)
        $messages = [
            'nombres' => [
                'required'   => 'El campo Nombres es obligatorio.',
                'min_length' => 'El campo Nombres debe tener al menos 2 caracteres.',
                'max_length' => 'El campo Nombres no debe exceder 32 caracteres.',
            ],
            'apellidos' => [
                'required'   => 'El campo Apellidos es obligatorio.',
                'min_length' => 'El campo Apellidos debe tener al menos 2 caracteres.',
                'max_length' => 'El campo Apellidos no debe exceder 32 caracteres.',
            ],
            'cedula' => [
                'required'   => 'El número de documento es obligatorio.',
                'max_length' => 'El número de documento no debe exceder 15 caracteres.',
            ],
            'doc_type' => [
                'required' => 'Debe seleccionar el tipo de documento.',
                'in_list'  => 'Tipo de documento inválido.',
            ],
            'password' => [
                'required'   => 'La Contraseña es obligatoria.',
                'min_length' => 'La Contraseña debe tener al menos 6 caracteres.',
            ],
            'id_agencias' => [
                'required'           => 'Debe seleccionar una Agencia.',
                'is_natural_no_zero' => 'Debe seleccionar una Agencia válida.',
            ],
            'id_area' => [
                'required'           => 'Debe seleccionar un Área.',
                'is_natural_no_zero' => 'Debe seleccionar un Área válida.',
            ],
            'id_cargo' => [
                'required'           => 'Debe seleccionar un Cargo.',
                'is_natural_no_zero' => 'Debe seleccionar un Cargo válido.',
            ],
        ];

        // Si falla la validación base, regresamos al form con errores (para el modal)
        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // ============================================================
        // 2) Validación específica del documento (reglas por tipo)
        // ============================================================
        if ($docType === 'CEDULA') {
            // Cédula: solo números, máximo 10
            if (!ctype_digit($docNumber)) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'La cédula debe contener solo números.']);
            }

            if (strlen($docNumber) > 10) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'La cédula debe tener máximo 10 dígitos.']);
            }
        } else {
            // Pasaporte/CI/NU: alfanumérico, máximo 15
            if (!preg_match('/^[a-zA-Z0-9]+$/', $docNumber)) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'El pasaporte/CI/NU solo debe contener letras y números.']);
            }

            if (strlen($docNumber) > 15) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'El pasaporte/CI/NU debe tener máximo 15 caracteres.']);
            }
        }

        // ============================================================
        // 3) Validación de duplicado (antes de insertar)
        // ============================================================
        // Esto evita la mayoría de choques con UNIQUE, pero igual se controla en el insert.
        if ($usuarioModel->docExists($docNumber)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', ['cedula' => 'El número de documento ya está registrado.']);
        }

        // ============================================================
        // 4) Construcción del payload para insertar
        // ============================================================
        // Los combobox NO se afectan:
        // - id_agencias, id_area, id_cargo se envían tal cual
        // - supervisor es opcional (0 => null)
        $isActive     = $this->request->getPost('activo') ? true : false;
        $agencyId     = (int) $this->request->getPost('id_agencias');
        $areaId       = (int) $this->request->getPost('id_area');
        $cargoId      = (int) $this->request->getPost('id_cargo');
        $supervisorId = (int) ($this->request->getPost('id_supervisor') ?? 0);

        $data = [
            'nombres'   => (string) $this->request->getPost('nombres'),
            'apellidos' => (string) $this->request->getPost('apellidos'),

            // Guardamos el documento como string (CEDULA o PASAPORTE)
            // Requisito: la columna cedula en BD debe ser VARCHAR para permitir letras
            'cedula'    => $docNumber,

            'password'  => password_hash((string) $this->request->getPost('password'), PASSWORD_BCRYPT),

            'id_agencias' => $agencyId,
            'id_area'     => $areaId,
            'id_cargo'    => $cargoId,

            'id_supervisor' => $supervisorId > 0 ? $supervisorId : null,
            'activo'        => $isActive,
        ];

        // ============================================================
        // 5) Inserción y manejo de errores de base de datos
        // ============================================================
        // - $ok se inicializa para evitar "Undefined variable"
        // - try/catch cubre casos donde la conexión arroja excepción
        // - si el insert devuelve false sin excepción, leemos getLastDbError()
        $ok = false;

        try {
            $ok = $usuarioModel->insertUser($data);
        } catch (DatabaseException $e) {
            $msg = $e->getMessage();

            // Duplicado por UNIQUE (PostgreSQL: 23505)
            if (
                str_contains($msg, '23505') ||
                str_contains($msg, 'llave duplicada') ||
                str_contains($msg, 'duplicate key') ||
                str_contains($msg, 'USER_cedula_key')
            ) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'El número de documento ya está registrado.']);
            }

            // Cualquier otro error de BD
            return redirect()->back()
                ->withInput()
                ->with('errors', ['general' => 'No se pudo registrar el usuario. Intente nuevamente.']);
        }

        // Si no hubo excepción pero el insert falló, revisamos el error guardado en el Model
        if (!$ok) {
            $err  = $usuarioModel->getLastDbError();
            $msg  = $err['message'] ?? '';
            $code = (string) ($err['code'] ?? '');

            // Duplicado (por si no se lanzó excepción)
            if (
                $code === '23505' ||
                str_contains($msg, '23505') ||
                str_contains($msg, 'llave duplicada') ||
                str_contains($msg, 'duplicate key') ||
                str_contains($msg, 'USER_cedula_key')
            ) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'El número de documento ya está registrado.']);
            }

            // Caso típico cuando cedula sigue siendo integer y llega texto (pasaporte)
            if (str_contains($msg, 'invalid input syntax for type integer')) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['cedula' => 'La base aún tiene el documento como numérico. Cambie la columna a VARCHAR para permitir pasaporte.']);
            }

            // Mensaje general (fallback)
            return redirect()->back()
                ->withInput()
                ->with('errors', ['general' => 'No se pudo registrar el usuario. Revise los datos y vuelva a intentar.']);
        }

        // Todo OK: redirigimos a la lista con mensaje de éxito (modal en Lista_usuario.php)
        return redirect()->to(base_url('usuarios'))
            ->with('success', 'Usuario registrado correctamente.');
    }

    /**
     * Endpoint JSON para el combobox de cargos (filtrado por área).
     * NO se modifica por las validaciones del store().
     * GET: /usuarios/api/cargos?id_area=#
     */
    public function getCargosByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);

        // Si no hay área, devolvemos lista vacía
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        $usuarioModel = new UsuarioModel();
        return $this->response->setJSON($usuarioModel->getCargosByArea($areaId));
    }

    /**
     * Endpoint JSON para el combobox de supervisores (filtrado por área).
     * Regla del model: trae supervisores del área + siempre incluye área 1.
     * GET: /usuarios/api/supervisores?id_area=#
     */
    public function getSupervisorsByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);

        // Si no hay área, devolvemos lista vacía
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        $usuarioModel = new UsuarioModel();
        return $this->response->setJSON($usuarioModel->getSupervisorsByArea($areaId));
    }
}
