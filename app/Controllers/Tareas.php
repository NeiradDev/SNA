<?php

namespace App\Controllers;

use App\Services\TareaService;

class Tareas extends BaseController
{
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
        if ($r = $this->requireLogin()) return $r;

        return view('tareas/calendario');
    }

    // ==================================================
    // FORM CREAR
    // ==================================================
    public function asignarForm()
    {
        if ($r = $this->requireLogin()) return $r;

        $idUser   = (int) session()->get('id_user');
        $division = $this->service->getDivisionByUser($idUser);

        if (!$division) {
            return redirect()->back()
                ->with('error', 'No se pudo determinar la división del usuario.');
        }

        return view('tareas/asignar', [
            'tarea'           => null,
            'divisionUsuario' => $division,
            'areasDivision'   => $this->service->getAreasByDivision((int)$division['id_division']),
            'prioridades'     => $this->service->getPrioridades(),
            'estados'         => $this->service->getEstadosTarea(),
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
        if ($r = $this->requireLogin()) return $r;

        $post        = $this->request->getPost();
        $asignadoPor = (int) session()->get('id_user');

        $result = $this->service->createTaskFromPost($post, $asignadoPor);

        if (!$result['success']) {
            return redirect()->to(site_url('tareas/asignar'))
                ->with('error', $result['error'])
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
        if ($r = $this->requireLogin()) return $r;

        $idUser = (int) session()->get('id_user');

        $data = $this->service->getTasksForManagement($idUser);

        return view('tareas/gestionar', [
            'misTareas'       => $data['misTareas'],
            'tareasAsignadas' => $data['tareasAsignadas'],
            'error'           => session()->getFlashdata('error'),
            'success'         => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // EDITAR
    // ==================================================
    public function editar(int $idTarea)
{
    if ($r = $this->requireLogin()) return $r;

    $idUser = (int) session()->get('id_user');

    $tarea = $this->service->getTaskById($idTarea, $idUser);

    if (!$tarea) {
        return redirect()->to(site_url('tareas/gestionar'))
            ->with('error', 'Tarea no encontrada o no autorizada.');
    }

    $division = $this->service->getDivisionByUser($idUser);

    return view('tareas/asignar', [
        'divisionUsuario' => $division,
        'areasDivision'   => $this->service->getAreasByDivision((int)$division['id_division']),
        'prioridades'     => $this->service->getPrioridades(),
        'estados'         => $this->service->getEstadosTarea(),
        'tarea'           => $tarea, // 🔥 CLAVE
        'old'             => [],
        'error'           => session()->getFlashdata('error'),
        'success'         => session()->getFlashdata('success'),
    ]);
}


    // ==================================================
    // ACTUALIZAR / REASIGNAR
    // ==================================================
    public function actualizar(int $idTarea)
    {
        if ($r = $this->requireLogin()) return $r;

        $result = $this->service->updateTask(
            $idTarea,
            $this->request->getPost(),
            (int) session()->get('id_user'),
            (int) session()->get('id_area')
        );

        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['error'])
                ->with('old', $this->request->getPost());
        }

        return redirect()->to(site_url('tareas/gestionar'))
            ->with('success', 'Tarea actualizada correctamente.');
    }

    // ==================================================
    // API: EVENTOS
    // ==================================================
    public function events()
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401);
        }

        $scope  = $this->request->getGet('scope') ?? 'mine';
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
    return $this->response->setJSON(
        $this->service->getUsersByArea($areaId)
    );
}



    // ==================================================
    // API: MARCAR REALIZADA
    // ==================================================
    public function marcarCumplida(int $idTarea)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No autorizado'
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
        if ($r = $this->requireLogin()) return $r;

        $idUser = (int) session()->get('id_user');

        return view('tareas/satisfaccion', [
            'data' => $this->service->getSatisfaccionActual($idUser),
        ]);
    }
}
