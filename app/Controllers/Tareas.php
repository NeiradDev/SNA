<?php

namespace App\Controllers;

use App\Services\TareaService;
use App\Models\UsuarioModel;

class Tareas extends BaseController
{
    private TareaService $service;
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->service = new TareaService();
        $this->usuarioModel = new UsuarioModel();
    }

    private function requireLogin()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }
        return null;
    }

    public function calendario()
    {
        if ($r = $this->requireLogin()) return $r;

        return view('tareas/calendario', [
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    public function asignarForm()
    {
        if ($r = $this->requireLogin()) return $r;

        $areaAsignador = (int) (session()->get('id_area') ?? 0);
        $esGerencia = ($areaAsignador === 1);

        // Si es gerencia: ve todas las áreas
        // Si no: solo su área (para que no elija otras)
        $areas = $this->usuarioModel->getAreas();
        if (!$esGerencia) {
            $areas = array_values(array_filter($areas, fn($a) => (int)$a['id_area'] === $areaAsignador));
        }

        return view('tareas/asignar', [
            'areas'      => $areas,
            'esGerencia' => $esGerencia,
            'areaFija'   => $areaAsignador,
            'error'      => session()->getFlashdata('error'),
            'success'    => session()->getFlashdata('success'),
            'old'        => session()->getFlashdata('old') ?? [],
        ]);
    }

    public function asignarStore()
    {
        if ($r = $this->requireLogin()) return $r;

        $post = $this->request->getPost();
        $asignadoPor = (int) session()->get('id_user');

        $result = $this->service->createTaskFromPost($post, $asignadoPor);

        if (!$result['success']) {
            return redirect()->to(site_url('tareas/asignar'))
                ->with('error', $result['error'] ?? 'No se pudo asignar.')
                ->with('old', $post);
        }

        return redirect()->to(site_url('tareas/asignar'))
            ->with('success', 'Tarea asignada correctamente.');
    }

    // ===== API =====

    public function events()
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'No autorizado']);
        }

        $scope = (string)($this->request->getGet('scope') ?? 'mine');
        if (!in_array($scope, ['mine','assigned'], true)) $scope = 'mine';

        $idUser = (int) session()->get('id_user');
        $events = $this->service->getCalendarEvents($idUser, $scope);

        return $this->response->setJSON($events);
    }

    public function usersByArea(int $areaId)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'No autorizado']);
        }

        $areaAsignador = (int) (session()->get('id_area') ?? 0);
        $esGerencia = ($areaAsignador === 1);

        // Si NO es gerencia, forzamos el área a su área (ignoramos la pedida)
        if (!$esGerencia) {
            $areaId = $areaAsignador;
        }

        $users = $this->usuarioModel->getUsersByArea($areaId);

        $out = array_map(function($u) {
            return [
                'id_user' => (int)$u['id_user'],
                'label'   => (string)$u['nombre_completo'] . ' — ' . (string)($u['nombre_cargo'] ?? '')
            ];
        }, $users);

        return $this->response->setJSON($out);
    }

    public function marcarCumplida(int $idTarea)
    {
        if (!session()->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'error' => 'No autorizado']);
        }

        $idUser = (int) session()->get('id_user');
        $result = $this->service->markDone($idTarea, $idUser);

        return $this->response->setJSON($result);
    }
}
