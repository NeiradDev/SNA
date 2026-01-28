<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * ============================================================
 * Controller: Usuarios
 *
 * Responsabilidad:
 * - Renderizar vistas de usuarios (lista, crear, editar)
 * - Procesar formularios (store: crear, update: editar)
 * - Exponer endpoints JSON para combos dependientes (cargos/supervisores por área)
 *
 * Notas del proyecto:
 * - Rutas en español (usuarios/nuevo, usuarios/editar/1, etc.)
 * - Métodos/variables en inglés básico (index, create, store, edit, update)
 * - Mensajes visibles en español
 * ============================================================
 */
class Usuarios extends BaseController
{
    /**
     * ============================================================
     * index()
     * Muestra la lista de usuarios.
     *
     * Flujo:
     * - Lee el parámetro GET "limit" (por defecto 50)
     * - Pide al Model el listado con joins (agencia/área/cargo/supervisor)
     * - Envía los datos a la vista pages/Lista_usuario
     * ============================================================
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
     * ============================================================
     * create()
     * Muestra el formulario de creación.
     *
     * Flujo:
     * - Carga agencias y áreas para los combobox del formulario
     * - La carga de cargos y supervisores se hace por JS (endpoints JSON)
     * ============================================================
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
     * ============================================================
     * store()
     * Crea un usuario.
     *
     * Flujo:
     * 1) Lee documento (doc_type + cedula)
     * 2) Valida campos base (requeridos, longitudes, selects)
     * 3) Valida documento según tipo:
     *    - CEDULA: solo números, max 10
     *    - PASAPORTE: alfanumérico, max 15
     * 4) Valida duplicidad de documento antes del insert
     * 5) Inserta en BD
     * 6) Si falla:
     *    - Duplicado (23505) => mensaje específico
     *    - Columna cedula es integer y llega texto => mensaje específico
     *    - Otros => mensaje general
     *
     * Importante:
     * - NO afecta el combobox dinámico (cargos/supervisores) porque esos
     *   endpoints son métodos separados (getCargosByArea, getSupervisorsByArea)
     * ============================================================
     */
    public function store()
    {
        $usuarioModel = new UsuarioModel();

        // ------------------------------------------------------------
        // 0) Documento (tipo + número)
        // ------------------------------------------------------------
        $docType   = (string) ($this->request->getPost('doc_type') ?? 'CEDULA'); // CEDULA | PASAPORTE
        $docNumber = trim((string) ($this->request->getPost('cedula') ?? ''));

        // ------------------------------------------------------------
        // 1) Validación base del formulario
        // ------------------------------------------------------------
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

        // Si falla la validación, retorna al formulario con errores para el modal
        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // ------------------------------------------------------------
        // 2) Validación del documento según tipo
        // ------------------------------------------------------------
        if ($docType === 'CEDULA') {
            if (!ctype_digit($docNumber)) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'La cédula debe contener solo números.',
                ]);
            }
            if (strlen($docNumber) > 10) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'La cédula debe tener máximo 10 dígitos.',
                ]);
            }
        } else {
            if (!preg_match('/^[a-zA-Z0-9]+$/', $docNumber)) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El pasaporte/CI/NU solo debe contener letras y números.',
                ]);
            }
            if (strlen($docNumber) > 15) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El pasaporte/CI/NU debe tener máximo 15 caracteres.',
                ]);
            }
        }

        // ------------------------------------------------------------
        // 3) Validación de duplicidad (antes del insert)
        // ------------------------------------------------------------
        if ($usuarioModel->docExists($docNumber)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', ['cedula' => 'El número de documento ya está registrado.']);
        }

        // ------------------------------------------------------------
        // 4) Armar data para insertar
        // ------------------------------------------------------------
        $isActive     = $this->request->getPost('activo') ? true : false;
        $agencyId     = (int) $this->request->getPost('id_agencias');
        $areaId       = (int) $this->request->getPost('id_area');
        $cargoId      = (int) $this->request->getPost('id_cargo');
        $supervisorId = (int) ($this->request->getPost('id_supervisor') ?? 0);

        $data = [
            'nombres'   => (string) $this->request->getPost('nombres'),
            'apellidos' => (string) $this->request->getPost('apellidos'),
            'cedula'    => $docNumber,
            'password'  => password_hash((string) $this->request->getPost('password'), PASSWORD_BCRYPT),

            'id_agencias'   => $agencyId,
            'id_area'       => $areaId,
            'id_cargo'      => $cargoId,
            'id_supervisor' => $supervisorId > 0 ? $supervisorId : null,

            'activo' => $isActive,
        ];

        // ------------------------------------------------------------
        // 5) Insert + manejo de errores de BD
        // ------------------------------------------------------------
        $ok = false;

        try {
            $ok = $usuarioModel->insertUser($data);
        } catch (DatabaseException $e) {
            // Si la BD lanza excepción, revisamos si es duplicado y respondemos
            $msg = $e->getMessage();

            if (
                str_contains($msg, '23505') ||
                str_contains($msg, 'llave duplicada') ||
                str_contains($msg, 'duplicate key') ||
                str_contains($msg, 'USER_cedula_key')
            ) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El número de documento ya está registrado.',
                ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('errors', ['general' => 'No se pudo registrar el usuario. Intente nuevamente.']);
        }

        // Si no hubo excepción pero el insert falló, usamos el error guardado por el Model
        if (!$ok) {
            $err  = $usuarioModel->getLastDbError();
            $msg  = $err['message'] ?? '';
            $code = (string) ($err['code'] ?? '');

            // Duplicado por UNIQUE
            if (
                $code === '23505' ||
                str_contains($msg, '23505') ||
                str_contains($msg, 'llave duplicada') ||
                str_contains($msg, 'duplicate key') ||
                str_contains($msg, 'USER_cedula_key')
            ) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El número de documento ya está registrado.',
                ]);
            }

            // Si cedula sigue siendo integer y mandas texto (pasaporte)
            if (str_contains($msg, 'invalid input syntax for type integer')) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'La base aún tiene el documento como numérico. Cambie la columna a VARCHAR para permitir pasaporte.',
                ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('errors', ['general' => 'No se pudo registrar el usuario. Revise los datos y vuelva a intentar.']);
        }

        // Todo OK: vuelve a la lista con modal de éxito
        return redirect()->to(base_url('usuarios'))
            ->with('success', 'Usuario registrado correctamente.');
    }

    /**
     * ============================================================
     * edit()
     * Muestra el formulario de edición.
     *
     * - Busca el usuario por ID
     * - Si no existe, vuelve a la lista con error
     * - Carga agencias y áreas para el formulario
     * ============================================================
     */
    public function edit(int $id)
    {
        $usuarioModel = new UsuarioModel();

        $user = $usuarioModel->getUserById($id);
        if (empty($user)) {
            return redirect()->to(base_url('usuarios'))
                ->with('errors', ['general' => 'Usuario no encontrado.']);
        }

        $data = [
            'usuario'  => $user,
            'agencias' => $usuarioModel->getAgencies(),
            'areas'    => $usuarioModel->getAreas(),
        ];

        return view('pages/Editar_usuario', $data);
    }

    /**
     * ============================================================
     * update()
     * Actualiza un usuario existente.
     *
     * Diferencias vs store():
     * - Password es opcional: solo se actualiza si escriben una nueva
     * - Validación de duplicado excluye al usuario actual:
     *   docExistsForOtherUser(documento, id)
     * ============================================================
     */
    public function update(int $id)
    {
        $usuarioModel = new UsuarioModel();

        // Documento
        $docType   = (string) ($this->request->getPost('doc_type') ?? 'CEDULA');
        $docNumber = trim((string) ($this->request->getPost('cedula') ?? ''));

        // Validación base (password opcional)
        $rules = [
            'nombres'     => 'required|min_length[2]|max_length[32]',
            'apellidos'   => 'required|min_length[2]|max_length[32]',
            'cedula'      => 'required|max_length[15]',
            'doc_type'    => 'required|in_list[CEDULA,PASAPORTE]',
            'id_agencias' => 'required|is_natural_no_zero',
            'id_area'     => 'required|is_natural_no_zero',
            'id_cargo'    => 'required|is_natural_no_zero',
            'password'    => 'permit_empty|min_length[6]',
        ];

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

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Validación documento por tipo
        if ($docType === 'CEDULA') {
            if (!ctype_digit($docNumber)) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'La cédula debe contener solo números.',
                ]);
            }
            if (strlen($docNumber) > 10) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'La cédula debe tener máximo 10 dígitos.',
                ]);
            }
        } else {
            if (!preg_match('/^[a-zA-Z0-9]+$/', $docNumber)) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El pasaporte/CI/NU solo debe contener letras y números.',
                ]);
            }
            if (strlen($docNumber) > 15) {
                return redirect()->back()->withInput()->with('errors', [
                    'cedula' => 'El pasaporte/CI/NU debe tener máximo 15 caracteres.',
                ]);
            }
        }

        // Duplicado excluyendo este usuario
        if ($usuarioModel->docExistsForOtherUser($docNumber, $id)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', ['cedula' => 'El número de documento ya está registrado en otro usuario.']);
        }

        // Armar data
        $isActive     = $this->request->getPost('activo') ? true : false;
        $agencyId     = (int) $this->request->getPost('id_agencias');
        $areaId       = (int) $this->request->getPost('id_area');
        $cargoId      = (int) $this->request->getPost('id_cargo');
        $supervisorId = (int) ($this->request->getPost('id_supervisor') ?? 0);

        $data = [
            'nombres'       => (string) $this->request->getPost('nombres'),
            'apellidos'     => (string) $this->request->getPost('apellidos'),
            'cedula'        => $docNumber,
            'id_agencias'   => $agencyId,
            'id_area'       => $areaId,
            'id_cargo'      => $cargoId,
            'id_supervisor' => $supervisorId > 0 ? $supervisorId : null,
            'activo'        => $isActive,
        ];

        // Password opcional: solo se actualiza si el usuario escribió una nueva
        $password = (string) ($this->request->getPost('password') ?? '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        // Ejecutar update
        $ok = $usuarioModel->updateUser($id, $data);

        if (!$ok) {
            $err = $usuarioModel->getLastDbError();
            $msg = $err['message'] ?? '';

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

            return redirect()->back()
                ->withInput()
                ->with('errors', ['general' => 'No se pudo actualizar el usuario. Intente nuevamente.']);
        }

        return redirect()->to(base_url('usuarios'))
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * ============================================================
     * getCargosByArea()
     * Endpoint JSON para llenar el combo "Cargo".
     *
     * GET: /usuarios/api/cargos?id_area=#
     * - Devuelve [] si id_area no es válido
     * - Devuelve [{id_cargo, nombre_cargo}, ...] si todo OK
     * ============================================================
     */
    public function getCargosByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        $usuarioModel = new UsuarioModel();
        return $this->response->setJSON($usuarioModel->getCargosByArea($areaId));
    }

    /**
     * ============================================================
     * getSupervisorsByArea()
     * Endpoint JSON para llenar el combo "Supervisor".
     *
     * GET: /usuarios/api/supervisores?id_area=#
     * - Devuelve [] si id_area no es válido
     * - Devuelve supervisores del área + (id_area=1) gerencia
     * ============================================================
     */
    public function getSupervisorsByArea()
    {
        $areaId = (int) ($this->request->getGet('id_area') ?? 0);
        if ($areaId <= 0) {
            return $this->response->setJSON([]);
        }

        $usuarioModel = new UsuarioModel();
        return $this->response->setJSON($usuarioModel->getSupervisorsByArea($areaId));
    }
}
