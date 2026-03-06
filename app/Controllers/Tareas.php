<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TareaService;

class Tareas extends BaseController
{
    /**
     * Servicio central de tareas
     */
    private TareaService $service;

    public function __construct()
    {
        $this->service = new TareaService();
    }

    // ==================================================
    // SEGURIDAD
    // ==================================================
    private function requireLogin()
    {
        // ✅ Si no hay sesión activa, redirige al login
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }
        return null;
    }

    // ==================================================
    // CALENDARIO
    // ==================================================
    public function calendario()
    {
        // ✅ Seguridad
        if ($r = $this->requireLogin()) {
            return $r;
        }

        return view('tareas/calendario');
    }

    // ==================================================
    // FORM CREAR
    // ==================================================
    public function asignarForm()
    {
        // ✅ Seguridad
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // ✅ Usuario logueado
        $idUser = (int) session()->get('id_user');

        // ✅ Determinar división del usuario (para mostrarla en form)
        $division = $this->service->getDivisionByUser($idUser);
        if (!$division) {
            return redirect()->back()->with('error', 'No se pudo determinar la división del usuario.');
        }

        // ✅ Scope UI (super/division/area/self)
        $assignScope = $this->service->getAssignScopeForUi(
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // ✅ Render vista
        return view('tareas/asignar', [
            'tarea'           => null,
            'divisionUsuario' => $division,
            'areasDivision'   => $this->service->getAreasByDivision((int) $division['id_division']),
            'prioridades'     => $this->service->getPrioridades(),
            'estados'         => $this->service->getEstadosTarea(),
            'assignScope'     => $assignScope,
            'old'             => session()->getFlashdata('old') ?? [],
            'error'           => session()->getFlashdata('error'),
            'success'         => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // STORE CREAR
    // ==================================================
    public function asignarStore()
    {
        // ✅ Seguridad
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // ✅ POST del form
        $post = $this->request->getPost();

        // ✅ Quien asigna (auditoría)
        $asignadoPor = (int) session()->get('id_user');

        // ✅ Crea tarea(s)
        $result = $this->service->createTaskFromPost($post, $asignadoPor);

        // ✅ Errores
        if (!($result['success'] ?? false)) {
            return redirect()->to(site_url('tareas/asignar'))
                ->with('error', (string)($result['error'] ?? 'No se pudo guardar la tarea.'))
                ->with('old', $post);
        }

        return redirect()->to(site_url('tareas/gestionar'))
            ->with('success', 'Tarea asignada correctamente.');
    }

    // ==================================================
    // GESTIONAR
    // ==================================================
   public function gestionar()
{
    // ✅ Seguridad
    if ($r = $this->requireLogin()) {
        return $r;
    }

    $idUser = (int) session()->get('id_user');
    $idArea = (int) session()->get('id_area');

    // ✅ Trae data agrupada (mis tareas / asignadas / equipo / pendientes revisión)
    $data = $this->service->getTasksForManagement($idUser, $idArea);

    return view('tareas/gestionar', [
        'assignScope'        => $data['assignScope'] ?? [],
        'misTareas'          => $data['misTareas'] ?? [],

        // ✅ NUEVO: Mis tareas diarias (repetidas de HOY)
        'misDiarias'         => $data['misDiarias'] ?? ['activas'=>[],'revision'=>[],'cerradas'=>[]],

        'tareasAsignadas'    => $data['tareasAsignadas'] ?? [],
        'tareasEquipo'       => $data['tareasEquipo'] ?? [],
        'pendientesRevision' => $data['pendientesRevision'] ?? [],
        'dueAlerts'          => $data['dueAlerts'] ?? [],
        'expiredUpdated'     => $data['expiredUpdated'] ?? 0,            'decisionNotifications' => $this->service->getDecisionNotifications($idUser),

        'error'              => session()->getFlashdata('error'),
        'success'            => session()->getFlashdata('success'),
    ]);
}

    // ==================================================
    // EDITAR
    // ==================================================
    public function editar(int $idTarea)
{
    // ✅ Seguridad
    if ($r = $this->requireLogin()) {
        return $r;
    }

    $idUser = (int) session()->get('id_user');
    $idArea = (int) session()->get('id_area');

    // ✅ Carga tarea validando permisos
    $tarea = $this->service->getTaskById($idTarea, $idUser);
    if (!$tarea) {
        return redirect()->to(site_url('tareas/gestionar'))
            ->with('error', 'Tarea no encontrada o no autorizada.');
    }

    // ✅ Determinar división del usuario
    $division = $this->service->getDivisionByUser($idUser);
    if (!$division) {
        return redirect()->to(site_url('tareas/gestionar'))
            ->with('error', 'No se pudo determinar la división del usuario.');
    }

    // ✅ Scope UI
    $assignScope = $this->service->getAssignScopeForUi($idUser, $idArea);

    // ✅ NUEVO (simple y seguro):
    // En la vista: si mode === 'self' => NO editar fecha fin, solo solicitar cambio
    $assignMode = (string)($assignScope['mode'] ?? 'self');

    return view('tareas/asignar', [
        'divisionUsuario' => $division,
        'areasDivision'   => $this->service->getAreasByDivision((int) $division['id_division']),
        'prioridades'     => $this->service->getPrioridades(),
        'estados'         => $this->service->getEstadosTarea(),
        'tarea'           => $tarea,
        'assignScope'     => $assignScope,
        'assignMode'      => $assignMode, 
        'old'             => session()->getFlashdata('old') ?? [],
        'error'           => session()->getFlashdata('error'),
        'success'         => session()->getFlashdata('success'),
    ]);
}

    // ==================================================
    // ACTUALIZAR
    // ==================================================
    public function actualizar(int $idTarea)
    {
        // ✅ Seguridad
        if ($r = $this->requireLogin()) {
            return $r;
        }

        // ✅ POST del form
        $post = $this->request->getPost();

        $currentUserId   = (int) session()->get('id_user');
        $currentUserArea = (int) session()->get('id_area');

        // ==================================================
        // ✅ (SEGURIDAD EXTRA) BLOQUEAR CAMBIO DE FECHA INICIO
        // - Aunque la vista lo bloquea, aquí lo reforzamos.
        // - Cargamos la tarea para tener la fecha_inicio real.
        // ==================================================
        $tareaActual = $this->service->getTaskById($idTarea, $currentUserId);

        if (!$tareaActual) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Tarea no encontrada o no autorizada.');
        }

        // ✅ Fuerza la fecha_inicio a la original (si alguien manipula HTML)
        if (isset($tareaActual['fecha_inicio'])) {
            $post['fecha_inicio'] = (string) $tareaActual['fecha_inicio'];
        }

        // ==================================================
        // ✅ Si viene solicitud de revisión (cambio fecha / cancelación)
        // ==================================================
        $reviewAction = trim((string) ($post['review_action'] ?? ''));
        $reviewReason = trim((string) ($post['review_reason'] ?? ''));

        if (in_array($reviewAction, ['date_change', 'cancel'], true)) {

            // ✅ Motivo obligatorio
            if ($reviewReason === '') {
                return redirect()->back()
                    ->with('error', 'Debes escribir un motivo para enviar la solicitud a revisión.')
                    ->with('old', $post);
            }

            $requestedEnd = null;

            // ==================================================
            // ✅ CAMBIO IMPORTANTE:
            // - La vista manda el "nuevo fin solicitado" en:
            //   review_requested_fecha_fin
            // - NO debemos tomarlo desde fecha_fin para evitar mezclar
            // ==================================================
            if ($reviewAction === 'date_change') {
                $requestedEnd = trim((string) ($post['review_requested_fecha_fin'] ?? ''));

                if ($requestedEnd === '') {
                    return redirect()->back()
                        ->with('error', 'Debes seleccionar la nueva fecha fin para solicitar el cambio.')
                        ->with('old', $post);
                }
            }

            // ✅ Enviar solicitud al supervisor (service decide el supervisor y guarda en pendientes)
            $result = $this->service->requestReviewChange(
                $idTarea,
                $reviewAction,
                $requestedEnd,
                $reviewReason,
                $currentUserId,
                $currentUserArea
            );

            if (!($result['success'] ?? false)) {
                return redirect()->back()
                    ->with('error', (string) ($result['error'] ?? 'No se pudo enviar a revisión.'))
                    ->with('old', $post);
            }

            return redirect()->to(site_url('tareas/gestionar'))
                ->with('success', (string) ($result['message'] ?? 'Solicitud enviada a revisión.'));
        }

        // ==================================================
        // ✅ Flujo normal (jefes/super pueden guardar directo)
        // ==================================================
        $result = $this->service->updateTask(
            $idTarea,
            $post,
            $currentUserId,
            $currentUserArea
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()
                ->with('error', (string)($result['error'] ?? 'No se pudo actualizar la tarea.'))
                ->with('old', $post);
        }

        $flashMsg = (string) ($result['message'] ?? 'Tarea actualizada correctamente.');

        return redirect()->to(site_url('tareas/gestionar'))
            ->with('success', $flashMsg);
    }

    // ==================================================
    // API: EVENTOS (FullCalendar)
    // ==================================================
    public function events()
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401);
        }

        $scope  = (string) ($this->request->getGet('scope') ?? 'mine');
        $idUser = (int) session()->get('id_user');

        return $this->response->setJSON(
            $this->service->getCalendarEvents($idUser, $scope)
        );
    }

    // ==================================================
    // API: USUARIOS POR ÁREA
    // ==================================================
    public function usersByArea(int $areaId)
    {
        if (!session()->get('logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'No autorizado']);
        }

        return $this->response->setJSON(
            $this->service->getAssignableUsersByArea(
                $areaId,
                (int) session()->get('id_user'),
                (int) session()->get('id_area')
            )
        );
    }

    // ==================================================
    // API: MARCAR REALIZADA (compatibilidad)
    // ==================================================
    public function marcarCumplida(int $idTarea)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No autorizado',
            ]);
        }

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
    public function satisfaccion()
    {
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $idUser = (int) session()->get('id_user');

        return view('tareas/satisfaccion', [
            'data' => $this->service->getSatisfaccionResumen($idUser)
        ]);
    }

    // ==================================================
    // ✅ Alias: /tareas/estado/{id}
    // ==================================================
    public function estado(int $idTarea)
    {
        return $this->cambiarEstado($idTarea);
    }

    /**
     * cambiarEstado($idTarea)
     *
     * ✅ Permite SOLO 3 o 4 (Realizada / No realizada)
     * ✅ Si el usuario es self, va a revisión.
     * ✅ Si es jefe/super, cierra directo (depende del Service).
     *
     * ✅ Puede recibir motivo opcional:
     * - POST: review_reason o motivo
     */
    public function cambiarEstado(int $idTarea)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        $estado = (int) (
            $this->request->getPost('id_estado_tarea')
            ?? $this->request->getPost('estado')
            ?? 0
        );

        if (!in_array($estado, [3, 4], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success'  => false,
                'error'    => 'Estado inválido. Solo se permite 3 (Realizada) o 4 (No realizada).',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // ✅ Motivo opcional
        $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
        $reason = trim($reason);

        $result = $this->service->requestOrSetEstado(
            $idTarea,
            $estado,
            (int) session()->get('id_user'),
            (int) session()->get('id_area'),
            ($reason !== '' ? $reason : null)
        );

        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'message'  => (string) ($result['message'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }

    // ==================================================
    // ✅ Cancelar
    // - usuario asignado => solicita revisión
    // - supervisor directo / super => puede aplicar directo
    // ==================================================
    public function cancelar(int $idTarea)
    {
        // ✅ Seguridad
        if ($r = $this->requireLogin()) {
            return $r;
        }

        if (!$this->request->is('post')) {
            return redirect()->to(site_url('tareas/gestionar'));
        }

        // ✅ Usuario actual
        $currentUserId   = (int) (session()->get('id_user') ?? 0);
        $currentUserArea = (int) (session()->get('id_area') ?? 0);

        // ✅ Motivo:
        // - si es usuario asignado/self, el Service lo exigirá
        // - si es supervisor/super, puede ir vacío y cancelar directo
        $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
        $reason = trim($reason);

        // ✅ IMPORTANTE:
        // centralizamos toda la lógica en cancelTask()
        $result = $this->service->cancelTask(
            $idTarea,
            $currentUserId,
            $currentUserArea,
            ($reason !== '' ? $reason : null)
        );

        if (!empty($result['success'])) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('success', (string) ($result['message'] ?? 'Acción ejecutada correctamente.'));
        }

        return redirect()->to(site_url('tareas/gestionar'))
            ->with('error', (string) ($result['error'] ?? 'No se pudo cancelar la tarea.'));
    }

    // ==================================================
    // ✅ Endpoint general 3/4/5 (si lo usas por AJAX)
    // - 5 => self solicita revisión / supervisor cancela directo
    // - 3/4 => requestOrSetEstado
    // ==================================================
    public function cambiarEstadoGeneral(int $idTarea)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        $estado = (int) (
            $this->request->getPost('id_estado_tarea')
            ?? $this->request->getPost('estado')
            ?? 0
        );

        if (!in_array($estado, [3, 4, 5], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success'  => false,
                'error'    => 'Estado inválido. Se permite 3 (Realizada), 4 (No realizada) o 5 (Cancelada).',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // ==================================================
        // ✅ 5 = Cancelada
        // - self/asignado => a revisión con motivo obligatorio
        // - supervisor/super => cancelación directa
        // ==================================================
        if ($estado === 5) {
            $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
            $reason = trim($reason);

            $result = $this->service->cancelTask(
                $idTarea,
                (int) session()->get('id_user'),
                (int) session()->get('id_area'),
                ($reason !== '' ? $reason : null)
            );

            return $this->response->setJSON([
                'success'  => (bool) ($result['success'] ?? false),
                'error'    => (string) ($result['error'] ?? ''),
                'message'  => (string) ($result['message'] ?? ''),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
        $reason = trim($reason);

        $result = $this->service->requestOrSetEstado(
            $idTarea,
            $estado,
            (int) session()->get('id_user'),
            (int) session()->get('id_area'),
            ($reason !== '' ? $reason : null)
        );

        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'message'  => (string) ($result['message'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }

    // ==================================================
    // ✅ Revisión por lote
    // ==================================================
    public function revisionBatch()
    {
        return $this->revisarLote();
    }

    public function revisarLote()
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        $ids = (array) (
            $this->request->getPost('task_ids')
            ?? $this->request->getPost('ids')
            ?? []
        );

        $action = (string) ($this->request->getPost('action') ?? '');

        $result = $this->service->reviewBatch(
            $ids,
            $action,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'message'  => (string) ($result['message'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }
/**
 * Marca como vistas las notificaciones de decisión del usuario solicitante.
 * Se llama vía AJAX cuando el modal se muestra o se cierra.
 */
public function markDecisionSeen()
{
    if ($r = $this->requireLogin()) {
        return $r;
    }

    $idUser = (int) session()->get('id_user');
    $count  = $this->service->markDecisionNotificationsAsSeen($idUser);

    return $this->response->setJSON([
        'success' => true,
        'count'   => $count,
        'csrfHash'=> csrf_hash(),
    ]);
}

}
