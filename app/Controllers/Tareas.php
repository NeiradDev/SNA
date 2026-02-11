<?php

namespace App\Controllers;

use App\Services\TareaService;

/**
 * Controller: Tareas
 *
 * ❌ No lógica de negocio
 * ✅ Orquesta vistas y endpoints
 */
class Tareas extends BaseController
{
    private TareaService $service;

    public function __construct()
    {
        $this->service = new TareaService();
    }

    // ==================================================
    // Seguridad básica
    // ==================================================
    private function requireLogin()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }
        return null;
    }

    // ==================================================
    // Vista: calendario
    // ==================================================
    public function calendario()
    {
        if ($r = $this->requireLogin()) return $r;

        return view('tareas/calendario');
    }

    // ==================================================
    // Vista: asignar tarea (CREAR)
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
            'tarea'            => null, // 👈 IMPORTANTE
            'divisionUsuario'  => $division,
            'areasDivision'    => $this->service->getAreasByDivision((int)$division['id_division']),
            'prioridades'      => $this->service->getPrioridades(),
            'estados'          => $this->service->getEstadosTarea(),
            'old'              => session()->getFlashdata('old') ?? [],
            'error'            => session()->getFlashdata('error'),
            'success'          => session()->getFlashdata('success'),
        ]);
    }

    // ==================================================
    // POST: crear tarea
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
    // Vista: listado para editar / reasignar
    // ==================================================
   public function gestionar()
{
    if ($r = $this->requireLogin()) return $r;

    $idUser = (int) session()->get('id_user');

    $data = $this->service->getTasksForManagement($idUser);

    return view('tareas/gestionar', [
        'misTareas'        => $data['misTareas'],
        'tareasAsignadas' => $data['tareasAsignadas'],
        'error'            => session()->getFlashdata('error'),
        'success'          => session()->getFlashdata('success'),
    ]);
}

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
        'tarea'           => $tarea, 
        'old'             => [],
        'error'           => session()->getFlashdata('error'),
        'success'         => session()->getFlashdata('success'),
    ]);
}

//public function actualizar(int $idTarea)
//{
  //  if ($r = $this->requireLogin()) return $r;

    //$result = $this->service->updateTask(
      //  $idTarea,
       // $this->request->getPost(),
       // (int) session('id_user')
    //);

    //if (!$result['success']) {
      //  return redirect()->back()->with('error', $result['error']);
    
    //  }

    //return redirect()->to('tareas/gestionar')
      //  ->with('success', 'Tarea actualizada correctamente');
//}

    // ==================================================
    // API: eventos calendario
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
    // API: usuarios por área
    // ==================================================
    public function usersByArea(int $areaId)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401);
        }

        return $this->response->setJSON(
            $this->service->getUsersByArea($areaId)
            
        );
    }

    // ==================================================
    // API: marcar cumplida
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
 public function satisfaccion()
{
    if ($r = $this->requireLogin()) return $r;

    $idUser = (int) session()->get('id_user');

    return view('tareas/satisfaccion', [
        'data' => $this->service->getSatisfaccionActual($idUser),
    ]);
}

}
