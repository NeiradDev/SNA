<?php

namespace App\Controllers;

use App\Services\PlanBatallaService;
use App\Models\UsuarioModel;

class Reporte extends BaseController
{
    private PlanBatallaService $service;
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->service = new PlanBatallaService();
        $this->usuarioModel = new UsuarioModel();
    }

    public function plan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');
        $perfil = $this->usuarioModel->getUserProfileForPlan($idUser);

        if (!$perfil) {
            return redirect()->to(site_url('home'))
                ->with('error', 'No se pudo cargar el perfil del usuario.');
        }

        return view('reporte/plan', [
            'perfil'  => $perfil,
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
            'old'     => session()->getFlashdata('old') ?? [],
        ]);
    }

    public function storePlan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $post = $this->request->getPost();
        $result = $this->service->createFromUserSessionAndPost($post);

        if (!$result['success']) {
            return redirect()->to(site_url('reporte/plan'))
                ->with('error', $result['error'] ?? 'No se pudo guardar.')
                ->with('old', $post);
        }

        return redirect()->to(site_url('reporte/plan'))
            ->with('success', 'Plan de Batalla guardado correctamente.');
    }
}
