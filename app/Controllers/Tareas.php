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

    /**
     * ==================================================
     * ✅ NUEVO: Verifica si la tarea ya está cerrada
     * - 3 = Realizada
     * - 4 = No realizada
     * - 5 = Cancelada
     * ==================================================
     */
    private function isClosedTask(array $tarea): bool
    {
        $estadoId = (int)($tarea['id_estado_tarea'] ?? 0);
        return in_array($estadoId, [3, 4, 5], true);
    }

    // ==================================================
    // CALENDARIO
    // ==================================================
    public function calendario()
    {
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
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $idUser = (int) session()->get('id_user');

        $division = $this->service->getDivisionByUser($idUser);
        if (!$division) {
            return redirect()->back()->with('error', 'No se pudo determinar la división del usuario.');
        }

        $assignScope = $this->service->getAssignScopeForUi(
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

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
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $post = $this->request->getPost();
        $asignadoPor = (int) session()->get('id_user');

        $result = $this->service->createTaskFromPost($post, $asignadoPor);

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
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $idUser = (int) session()->get('id_user');
        $idArea = (int) session()->get('id_area');

        $data = $this->service->getTasksForManagement($idUser, $idArea);

        return view('tareas/gestionar', [
            'assignScope'          => $data['assignScope'] ?? [],
            'misTareas'            => $data['misTareas'] ?? [],
            'misDiarias'           => $data['misDiarias'] ?? [
                'activas'  => [],
                'revision' => [],
                'cerradas' => [],
            ],
            'tareasAsignadas'      => $data['tareasAsignadas'] ?? [],
            'tareasEquipo'         => $data['tareasEquipo'] ?? [],
            'pendientesRevision'   => $data['pendientesRevision'] ?? [],
            'dueAlerts'            => $data['dueAlerts'] ?? [],
            'expiredUpdated'       => $data['expiredUpdated'] ?? 0,

            /**
             * ✅ NOTIFICACIONES DE DECISIÓN
             * Solo deben mostrarse las no vistas.
             * La lógica real de "solo no vistas" queda en el service.
             */
            'decisionNotifications' => $this->service->getDecisionNotifications($idUser),

            'error'                => session()->getFlashdata('error'),
            'success'              => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // EDITAR
    // ==================================================
    public function editar(int $idTarea)
    {
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $idUser = (int) session()->get('id_user');
        $idArea = (int) session()->get('id_area');

        $tarea = $this->service->getTaskById($idTarea, $idUser);
        if (!$tarea) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Tarea no encontrada o no autorizada.');
        }

        /**
         * ✅ NUEVO: BLOQUEO BACKEND DE TAREAS CERRADAS
         * Si ya está:
         * - Realizada
         * - No realizada
         * - Cancelada
         * no puede volver a entrar a editar
         */
        if ($this->isClosedTask($tarea)) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Esta actividad ya fue cerrada y no puede editarse.');
        }

        $division = $this->service->getDivisionByUser($idUser);
        if (!$division) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'No se pudo determinar la división del usuario.');
        }

        $assignScope = $this->service->getAssignScopeForUi($idUser, $idArea);
        $assignMode  = (string)($assignScope['mode'] ?? 'self');

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
        if ($r = $this->requireLogin()) {
            return $r;
        }

        $post = $this->request->getPost();

        $currentUserId   = (int) session()->get('id_user');
        $currentUserArea = (int) session()->get('id_area');

        $tareaActual = $this->service->getTaskById($idTarea, $currentUserId);

        if (!$tareaActual) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Tarea no encontrada o no autorizada.');
        }

        /**
         * ✅ NUEVO: BLOQUEO BACKEND DE ACTUALIZACIÓN
         * Aunque alguien manipule la URL o el HTML,
         * una tarea cerrada ya no se puede actualizar.
         */
        if ($this->isClosedTask($tareaActual)) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('error', 'Esta actividad ya fue cerrada y no puede modificarse.');
        }

        // ✅ Fuerza la fecha_inicio a la original
        if (isset($tareaActual['fecha_inicio'])) {
            $post['fecha_inicio'] = (string) $tareaActual['fecha_inicio'];
        }

        $reviewAction = trim((string) ($post['review_action'] ?? ''));
        $reviewReason = trim((string) ($post['review_reason'] ?? ''));

        if (in_array($reviewAction, ['date_change', 'cancel'], true)) {
            if ($reviewReason === '') {
                return redirect()->back()
                    ->with('error', 'Debes escribir un motivo para enviar la solicitud a revisión.')
                    ->with('old', $post);
            }

            $requestedEnd = null;

            if ($reviewAction === 'date_change') {
                $requestedEnd = trim((string) ($post['review_requested_fecha_fin'] ?? ''));

                if ($requestedEnd === '') {
                    return redirect()->back()
                        ->with('error', 'Debes seleccionar la nueva fecha fin para solicitar el cambio.')
                        ->with('old', $post);
                }
            }

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
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'error'   => 'No autorizado',
            ]);
        }

        $scope  = (string) ($this->request->getGet('scope') ?? 'mine');
        $idUser = (int) session()->get('id_user');
        $idArea = (int) session()->get('id_area');

        return $this->response->setJSON(
            $this->service->getCalendarEvents($idUser, $scope, $idArea)
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
                ->setJSON([
                    'success' => false,
                    'error'   => 'No autorizado',
                ]);
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
            'data' => $this->service->getSatisfaccionResumen($idUser),
        ]);
    }

    // ==================================================
    // Alias: /tareas/estado/{id}
    // ==================================================
    public function estado(int $idTarea)
    {
        return $this->cambiarEstado($idTarea);
    }

    /**
     * cambiarEstado($idTarea)
     *
     * ✅ SOLO 3 o 4
     * ✅ self => revisión
     * ✅ jefe/super => directo según service
     * ✅ soporta evidencia
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

        $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
        $reason = trim($reason);

        $hasEvidenceRaw = (string) ($this->request->getPost('has_evidence') ?? '0');
        $hasEvidence    = in_array(strtolower($hasEvidenceRaw), ['1', 'true', 'on', 'yes'], true);

        $evidenceUrl  = trim((string) ($this->request->getPost('evidence_url') ?? ''));
        $evidenceNote = trim((string) ($this->request->getPost('evidence_note') ?? ''));

        $result = $this->service->requestOrSetEstado(
            $idTarea,
            $estado,
            (int) session()->get('id_user'),
            (int) session()->get('id_area'),
            ($reason !== '' ? $reason : null),
            $hasEvidence,
            ($evidenceUrl !== '' ? $evidenceUrl : null),
            ($evidenceNote !== '' ? $evidenceNote : null)
        );

        return $this->response->setJSON([
            'success'  => (bool) ($result['success'] ?? false),
            'error'    => (string) ($result['error'] ?? ''),
            'message'  => (string) ($result['message'] ?? ''),
            'csrfHash' => csrf_hash(),
        ]);
    }

    // ==================================================
    // CANCELAR
    // ==================================================
    public function cancelar(int $idTarea)
    {
        if ($r = $this->requireLogin()) {
            return $r;
        }

        if (!$this->request->is('post')) {
            return redirect()->to(site_url('tareas/gestionar'));
        }

        $currentUserId   = (int)(session()->get('id_user') ?? 0);
        $currentUserArea = (int)(session()->get('id_area') ?? 0);

        $reason = (string)($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
        $reason = trim($reason);

        // ✅ NUEVO: alcance de cancelación
        // Valores esperados:
        // - single_today  => solo esta tarea
        // - all           => toda la serie repetitiva
        $cancelScope = (string)($this->request->getPost('cancel_scope') ?? 'single_today');
        $cancelScope = in_array($cancelScope, ['single_today', 'all'], true)
            ? $cancelScope
            : 'single_today';

        $result = $this->service->cancelTask(
            $idTarea,
            $currentUserId,
            $currentUserArea,
            ($reason !== '' ? $reason : null),
            $cancelScope
        );

        if (!empty($result['success'])) {
            return redirect()->to(site_url('tareas/gestionar'))
                ->with('success', (string)($result['message'] ?? 'Acción ejecutada correctamente.'));
        }

        return redirect()->to(site_url('tareas/gestionar'))
            ->with('error', (string)($result['error'] ?? 'No se pudo cancelar la tarea.'));
    }

    // ==================================================
    // ENDPOINT GENERAL 3/4/5
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
        // CANCELAR
        // ==================================================
        if ($estado === 5) {
            $reason = (string) ($this->request->getPost('review_reason') ?? $this->request->getPost('motivo') ?? '');
            $reason = trim($reason);

            // ✅ NUEVO: recibir alcance de cancelación
            // - single_today => solo esta tarea
            // - all          => esta + futuras de la repetitiva
            $cancelScope = (string) ($this->request->getPost('cancel_scope') ?? 'single_today');
            $cancelScope = in_array($cancelScope, ['single_today', 'all'], true)
                ? $cancelScope
                : 'single_today';

            $result = $this->service->cancelTask(
                $idTarea,
                (int) session()->get('id_user'),
                (int) session()->get('id_area'),
                ($reason !== '' ? $reason : null),
                $cancelScope
            );

            return $this->response->setJSON([
                'success'  => (bool) ($result['success'] ?? false),
                'error'    => (string) ($result['error'] ?? ''),
                'message'  => (string) ($result['message'] ?? ''),
                'csrfHash' => csrf_hash(),
            ]);
        }

        // ==================================================
        // REALIZADA / NO REALIZADA
        // ==================================================
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
    // REVISIÓN POR LOTE
    // ==================================================
    public function revisionBatch()
    {
        return $this->revisarLote();
    }

    public function revisarLote()
    {
        // ==================================================
        // 1) Validar sesión
        // ==================================================
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'           => false,
                'error'             => 'No autorizado',
                'message'           => '',
                'needs_time_update' => false,
                'expired_tasks'     => [],
                'csrfHash'          => csrf_hash(),
            ]);
        }

        // ==================================================
        // 2) Leer IDs enviados desde el frontend
        //    Puede venir como:
        //    - task_ids[]
        //    - ids[]
        // ==================================================
        $ids = (array) (
            $this->request->getPost('task_ids')
            ?? $this->request->getPost('ids')
            ?? []
        );

        // ==================================================
        // 3) Leer acción solicitada
        //    Valores esperados:
        //    - approve
        //    - cancel_request
        //    - force_not_done
        // ==================================================
        $action = (string) ($this->request->getPost('action') ?? '');

        // ==================================================
        // 4) Ejecutar service
        // ==================================================
        $result = $this->service->reviewBatch(
            $ids,
            $action,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // ==================================================
        // 5) Responder JSON
        //    IMPORTANTE:
        //    aquí devolvemos también:
        //    - needs_time_update
        //    - expired_tasks
        //
        //    Esto permite que el frontend NO recargue de una vez
        //    si la tarea quedó vencida al volver al estado anterior,
        //    y abra el modal para actualizar solo la hora.
        // ==================================================
        return $this->response->setJSON([
            'success'           => (bool) ($result['success'] ?? false),
            'error'             => (string) ($result['error'] ?? ''),
            'message'           => (string) ($result['message'] ?? ''),
            'needs_time_update' => (bool) ($result['needs_time_update'] ?? false),
            'expired_tasks'     => (array) ($result['expired_tasks'] ?? []),
            'csrfHash'          => csrf_hash(),
        ]);
    }
    /**
     * ==================================================
     * ✅ NUEVO: Marcar notificaciones como leídas
     * - Usa la tabla tarea_review_decision_log
     * - El service debe actualizar seen_by_requester = true
     * - Se llamará por AJAX desde la vista
     * ==================================================
     */
    public function markDecisionSeen()
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'count'    => 0,
                'csrfHash' => csrf_hash(),
            ]);
        }

        $idUser = (int) session()->get('id_user');
        $count  = (int) $this->service->markDecisionNotificationsAsSeen($idUser);

        return $this->response->setJSON([
            'success'  => true,
            'count'    => $count,
            'csrfHash' => csrf_hash(),
        ]);
    }
    // ==================================================
    // SUPERVISOR: CANCELAR SOLICITUD
    // - Devuelve la tarea al estado anterior
    // - Limpia la revisión pendiente
    // ==================================================
    public function cancelReviewRequest()
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

        $result = $this->service->supervisorReviewAction(
            $ids,
            'cancel_request',
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

    // ==================================================
    // SUPERVISOR: APROBAR COMO REALIZADA
    // - Marca la tarea o tareas como Realizada
    // ==================================================
    public function approveReviewAsDone()
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

        $result = $this->service->supervisorReviewAction(
            $ids,
            'approve_done',
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

    // ==================================================
    // SUPERVISOR: FORZAR COMO NO REALIZADA
    // - Marca sí o sí la tarea o tareas como No realizada
    // ==================================================
    public function forceReviewAsNotDone()
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

        $result = $this->service->supervisorReviewAction(
            $ids,
            'force_not_done',
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
    public function reviewUpdateTime(int $idTarea)
    {
        // ==================================================
        // 1) Validar sesión
        // ==================================================
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success'  => false,
                'error'    => 'No autorizado',
                'csrfHash' => csrf_hash(),
            ]);
        }

        // ==================================================
        // 2) Leer nueva hora desde POST
        // ==================================================
        $newTime = trim((string) ($this->request->getPost('new_time') ?? ''));

        // ==================================================
        // 3) Ejecutar service
        // ==================================================
        $result = $this->service->updateOnlyEndTime(
            $idTarea,
            $newTime,
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        // ==================================================
        // 4) Respuesta JSON
        // ==================================================
        return $this->response->setJSON([
            'success'       => (bool) ($result['success'] ?? false),
            'error'         => (string) ($result['error'] ?? ''),
            'message'       => (string) ($result['message'] ?? ''),
            'new_fecha_fin' => $result['new_fecha_fin'] ?? null,
            'csrfHash'      => csrf_hash(),
        ]);
    }
}
