<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TareaService;

/**
 * Controller: Tareas
 *
 * ✅ Objetivo:
 * - Mantener tus pantallas/flujo actual (calendario, asignar, gestionar, editar, actualizar)
 * - Exponer endpoints JSON para FullCalendar, usersByArea y cambios de estado
 * - Implementar el flujo de revisión (estado 6 "En revisión") SIN romper lo existente
 *
 * ⚠️ Importante:
 * - Este controller NO duplica validaciones (eso vive en TareaService)
 * - Mantiene alias /tareas/estado/{id} y /tareas/revision-batch para compatibilidad con tu JS
 */
class Tareas extends BaseController
{
    /**
     * Servicio central de tareas (validaciones, permisos, reglas, batch, revisión, etc.)
     */
    private TareaService $service;

    /**
     * Constructor
     * - Instancia el Service una sola vez por request.
     */
    public function __construct()
    {
        $this->service = new TareaService();
    }

    // ==================================================
    // SEGURIDAD
    // ==================================================

    /**
     * requireLogin()
     *
     * ✅ Requiere login para acceder a pantallas (views).
     * - Si NO está logueado => redirige a /login
     * - Si está logueado => retorna null (continúa normal)
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|null
     */
    private function requireLogin()
    {
        // Si no existe flag de sesión de login, bloqueamos acceso
        if (!session()->get('logged_in')) {
            // Redirección estándar a tu login
            return redirect()->to(site_url('login'));
        }

        // Si está logueado, devolvemos null para seguir flujo normal
        return null;
    }

    // ==================================================
    // CALENDARIO
    // ==================================================

    /**
     * calendario()
     *
     * ✅ Muestra la vista del calendario (FullCalendar).
     */
    public function calendario()
    {
        // Protección de pantalla (view)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // Render de la vista
        return view('tareas/calendario');
    }

    // ==================================================
    // FORM CREAR
    // ==================================================

    /**
     * asignarForm()
     *
     * ✅ Formulario para crear/asignar una tarea.
     * Pasa a la vista:
     * - división del usuario
     * - áreas de la división
     * - catálogos (prioridad/estado)
     * - assignScope (super/division/area/self) para bloquear/preseleccionar en UI
     */
    public function asignarForm()
    {
        // Protección de pantalla (view)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // Usuario actual desde sesión
        $idUser = (int) session()->get('id_user');

        // División del usuario (según tu lógica del Model a través del Service)
        $division = $this->service->getDivisionByUser($idUser);

        // Si no podemos determinar división, no podemos cargar combos de áreas
        if (!$division) {
            return redirect()->back()
                ->with('error', 'No se pudo determinar la división del usuario.');
        }

        // ✅ Scope real para UI:
        // - super (gerencia id_area=1)
        // - division (jefe división)
        // - area (jefe área)
        // - self (normal)
        $assignScope = $this->service->getAssignScopeForUi(
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // Render de la vista de asignación (modo crear: tarea null)
        return view('tareas/asignar', [
            // null => la vista sabe que es "crear"
            'tarea'           => null,

            // Contexto de división/áreas
            'divisionUsuario' => $division,
            'areasDivision'   => $this->service->getAreasByDivision((int) $division['id_division']),

            // Catálogos
            'prioridades'     => $this->service->getPrioridades(),
            'estados'         => $this->service->getEstadosTarea(),

            // Scope UI
            'assignScope'     => $assignScope,

            // Flashdata para repintar form
            'old'             => session()->getFlashdata('old') ?? [],
            'error'           => session()->getFlashdata('error'),
            'success'         => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // STORE CREAR (sin validaciones duplicadas)
    // ==================================================

    /**
     * asignarStore()
     *
     * ✅ Guarda una tarea nueva.
     * - Validación de fechas/permisos/asignados/batch => Service
     * - Aquí solo se enruta el resultado y se mantiene el comportamiento existente
     */
    public function asignarStore()
    {
        // Protección de pantalla (post de vista)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // POST completo del formulario
        $post = $this->request->getPost();

        // Usuario que asigna (creador)
        $asignadoPor = (int) session()->get('id_user');

        // Crear usando el Service (multi-asignación incluida)
        $result = $this->service->createTaskFromPost($post, $asignadoPor);

        // Si el Service devuelve error, volvemos al form con old() + error
        if (!($result['success'] ?? false)) {
            return redirect()->to(site_url('tareas/asignar'))
                ->with('error', (string)($result['error'] ?? 'No se pudo guardar la tarea.'))
                ->with('old', $post);
        }

        // Ok => redirige a gestionar (como tu flujo actual)
        return redirect()->to(site_url('tareas/gestionar'))
            ->with('success', 'Tarea asignada correctamente.');
    }

    // ==================================================
    // GESTIONAR
    // ==================================================

    /**
     * gestionar()
     *
     * ✅ Pantalla para ver:
     * - Mis tareas (asignado_a)
     * - Tareas asignadas por mí (asignado_por)
     * - Tareas del equipo (según scope)
     * - Pendientes de revisión (si existe flujo en DB)
     */
    public function gestionar()
    {
        // Protección de pantalla (view)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // Usuario actual
        $idUser = (int) session()->get('id_user');

        // Área del usuario actual (para scope)
        $idArea = (int) session()->get('id_area');

        // Todo el armado de datos lo hace el Service
        $data = $this->service->getTasksForManagement($idUser, $idArea);

        // Render de la vista
        return view('tareas/gestionar', [
            'assignScope'        => $data['assignScope'] ?? [],
            'misTareas'          => $data['misTareas'] ?? [],
            'tareasAsignadas'    => $data['tareasAsignadas'] ?? [],
            'tareasEquipo'       => $data['tareasEquipo'] ?? [],
            'pendientesRevision' => $data['pendientesRevision'] ?? [],
            'error'              => session()->getFlashdata('error'),
            'success'            => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // EDITAR
    // ==================================================

    /**
     * editar($idTarea)
     *
     * ✅ Carga el formulario en modo edición.
     * - getTaskById() ya trae asignado_a como array (multi) si aplica batch
     * - Pasa assignScope para que la vista respete bloqueos/preselección
     */
    public function editar(int $idTarea)
    {
        // Protección de pantalla (view)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // Usuario actual
        $idUser = (int) session()->get('id_user');

        // Cargar tarea (con permisos y batch)
        $tarea = $this->service->getTaskById($idTarea, $idUser);

        // Si no existe o no tiene permiso
        if (!$tarea) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Tarea no encontrada o no autorizada.');
        }

        // División del usuario (para combos)
        $division = $this->service->getDivisionByUser($idUser);

        if (!$division) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'No se pudo determinar la división del usuario.');
        }

        // Scope real para UI
        $assignScope = $this->service->getAssignScopeForUi(
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // Render de la misma vista "asignar" pero en modo editar (tarea != null)
        return view('tareas/asignar', [
            'divisionUsuario' => $division,
            'areasDivision'   => $this->service->getAreasByDivision((int) $division['id_division']),
            'prioridades'     => $this->service->getPrioridades(),
            'estados'         => $this->service->getEstadosTarea(),

            // Tarea existente => modo editar
            'tarea'           => $tarea,

            // Scope UI
            'assignScope'     => $assignScope,

            // Flashdata
            'old'             => session()->getFlashdata('old') ?? [],
            'error'           => session()->getFlashdata('error'),
            'success'         => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // ACTUALIZAR (sin validaciones duplicadas)
    // ==================================================

    /**
     * actualizar($idTarea)
     *
     * ✅ Actualiza una tarea.
     * - Validación de permisos, batch, cancelados, fechas, prioridad => Service
     */
    public function actualizar(int $idTarea)
    {
        // Protección de pantalla (post de vista)
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // POST del formulario
        $post = $this->request->getPost();

        // Actualizar mediante el Service
        $result = $this->service->updateTask(
            $idTarea,
            $post,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // Error => volver atrás con old + error
        if (!($result['success'] ?? false)) {
            return redirect()->back()
                ->with('error', (string)($result['error'] ?? 'No se pudo actualizar la tarea.'))
                ->with('old', $post);
        }

        // Ok => volver a gestionar
        return redirect()->to(site_url('tareas/gestionar'))
            ->with('success', 'Tarea actualizada correctamente.');
    }

    // ==================================================
    // API: EVENTOS (FullCalendar)
    // ==================================================

    /**
     * events()
     *
     * ✅ Endpoint JSON para FullCalendar.
     * QueryString:
     * - scope=mine     => tareas donde asignado_a = yo
     * - scope=assigned => tareas donde asignado_por = yo
     */
    public function events()
    {
        // Endpoint JSON: si no está logueado => 401
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401);
        }

        // scope (mine | assigned)
        $scope = (string) ($this->request->getGet('scope') ?? 'mine');

        // usuario actual
        $idUser = (int) session()->get('id_user');

        // Respuesta JSON con eventos
        return $this->response->setJSON(
            $this->service->getCalendarEvents($idUser, $scope)
        );
    }

    // ==================================================
    // API: USUARIOS POR ÁREA (SEGURO)
    // ==================================================

    /**
     * usersByArea($areaId)
     *
     * ✅ Endpoint seguro:
     * - super    : puede ver usuarios del área solicitada (+ autoasignación garantizada)
     * - division : solo si el área pertenece a su división (+ autoasignación garantizada)
     * - area     : fuerza a su área
     * - self     : solo él mismo (autoasignación)
     */
    public function usersByArea(int $areaId)
    {
        // Endpoint JSON: si no está logueado => 401
        if (!session()->get('logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'No autorizado']);
        }

        // Retornamos la lista EXACTA que el Service permite (sin exponer extras)
        return $this->response->setJSON(
            $this->service->getAssignableUsersByArea(
                $areaId,
                (int) session()->get('id_user'),
                (int) session()->get('id_area')
            )
        );
    }

    // ==================================================
    // API: MARCAR REALIZADA (compatibilidad si lo usas)
    // ==================================================

    /**
     * marcarCumplida($idTarea)
     *
     * ✅ Endpoint histórico/simple (NO es flujo de revisión).
     * - Mantener por compatibilidad si lo usas en el calendario o en otra vista.
     * - Solo puede marcar el asignado_a.
     */
    public function marcarCumplida(int $idTarea)
    {
        // Endpoint JSON: si no está logueado => error JSON (manteniendo tu estilo)
        if (!session()->get('logged_in')) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No autorizado',
            ]);
        }

        // Ejecuta marcado directo (estado realizada) en el Service
        return $this->response->setJSON(
            $this->service->markDone(
                $idTarea,
                (int) session()->get('id_user')
            )
        );
    }

    // ==================================================
    // SATISFACCIÓN
    // ==================================================

    /**
     * satisfaccion()
     *
     * ✅ Muestra satisfacción semanal (Miércoles a Miércoles)
     * - data: porcentaje, realizadas, no_realizadas, inicio, fin
     */
  public function satisfaccion()
{
    if ($r = $this->requireLogin()) return $r;

    $idUser = (int) session()->get('id_user');

    return view('tareas/satisfaccion', [
        'data' => $this->service->getSatisfaccionResumen($idUser)
    ]);
}




    // ==================================================
    // ✅ ENDPOINTS PARA: Estado + Revisión por lote
    // (alineado con tu vista gestionar.php)
    // ==================================================

    /**
     * estado($idTarea)
     *
     * ✅ ALIAS: /tareas/estado/{id}
     * - Mantiene compatibilidad con tu JS actual (POST /tareas/estado/{id})
     * - Reutiliza cambiarEstado() para NO duplicar lógica
     */
    public function estado(int $idTarea)
    {
        // Alias directo (no cambiar nada del resto)
        return $this->cambiarEstado($idTarea);
    }

    /**
     * cambiarEstado($idTarea)
     *
     * ✅ Cambia estado (Realizada/No realizada) con flujo:
     * - Si usuario normal (scope self): envía a revisión (estado 6) + review_requested_state
     * - Si jefe/asignador/supervisor/gerencia: aplica directo
     *
     * ✅ Acepta POST:
     * - id_estado_tarea  (como manda tu JS actual)
     * - estado           (compatibilidad)
     *
     * ✅ Respuesta:
     * - success, error, message, csrfHash
     */
    public function cambiarEstado(int $idTarea)
    {
        // Endpoint JSON: si no está logueado => 401 + csrfHash (para que tu JS pueda refrescar token)
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // 1) Leer estado desde POST:
        // - Primero id_estado_tarea (tu JS actual)
        // - Si no existe, usar estado (compatibilidad)
        $estado = (int) (
            $this->request->getPost('id_estado_tarea')
            ?? $this->request->getPost('estado')
            ?? 0
        );

        // 2) Validar: solo 3 o 4 aquí (Realizada / No realizada)
        if (!in_array($estado, [3, 4], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success'  => false,
                'error'    => 'Estado inválido. Solo se permite 3 (Realizada) o 4 (No realizada).',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // 3) Ejecutar lógica del flujo en el Service
        $result = $this->service->requestOrSetEstado(
            $idTarea,
            $estado,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // 4) Responder JSON consistente + csrfHash para tu JS
        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'message'  => (string) ($result['message'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }

    /**
     * revisionBatch()
     *
     * ✅ ALIAS: /tareas/revision-batch
     * - Mantiene compatibilidad con rutas/JS que apunten a este endpoint
     * - Reutiliza revisarLote() para NO duplicar lógica
     */
    public function revisionBatch()
    {
        // Alias directo
        return $this->revisarLote();
    }

    /**
     * revisarLote()
     *
     * ✅ Revisión masiva:
     * - action: approve | reject
     * - ids: array de id_tarea
     *
     * ✅ Acepta POST:
     * - task_ids[] (recomendado)
     * - ids[]      (compatibilidad)
     *
     * ✅ Respuesta:
     * - success, error, csrfHash
     */
    public function revisarLote()
    {
        // Endpoint JSON: si no está logueado => 401 + csrfHash
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // 1) IDs: soporta ambos nombres (task_ids / ids)
        $ids = (array) (
            $this->request->getPost('task_ids')
            ?? $this->request->getPost('ids')
            ?? []
        );

        // 2) Acción: approve | reject
        $action = (string) ($this->request->getPost('action') ?? '');

        // 3) Procesar en el Service (valida permisos + aplica cambios)
        $result = $this->service->reviewBatch(
            $ids,
            $action,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // 4) Respuesta JSON + csrfHash
        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }
}
