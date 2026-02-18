<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Services\PlanBatallaService;
use App\Services\TareaService;

class Reporte extends BaseController
{
    // Servicio del plan de batalla (historico)
    private PlanBatallaService $service;

    // Modelo de usuario
    private UsuarioModel $usuarioModel;

    // Servicio de tareas (satisfacción)
    private TareaService $tareaService;

    public function __construct()
    {
        $this->service       = new PlanBatallaService();
        $this->usuarioModel  = new UsuarioModel();
        $this->tareaService  = new TareaService(); 
    }

    // --------------------------------------------------
    // Vista Plan de Batalla
    // --------------------------------------------------
    public function plan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');

        // Perfil del usuario
        $perfil = $this->service->getUserProfile($idUser);

        // ✅ SATISFACCIÓN SEMANAL (EN VIVO)
        $satisfaccion = $this->tareaService->getSatisfaccionActual($idUser);

        return view('reporte/plan', [
            'perfil'       => $perfil,
            'satisfaccion' => $satisfaccion, // 👈 se envía a la vista
        ]);
    }

    // --------------------------------------------------
    // Guardar Plan de Batalla (historico)
    // --------------------------------------------------
    public function storePlan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');
        $post   = (array) $this->request->getPost();

        $res = $this->service->savePlanToHistorico($idUser, $post);

        if (!$res['ok']) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $res['errors'] ?? [
                    'general' => 'No se pudo guardar el plan.',
                ]);
        }

        return redirect()
            ->to(site_url('reporte/plan'))
            ->with('success', 'Plan guardado correctamente.');
    }

    public function Completado()
    {
        $modelo = new UsuarioModel();
        $data['usuarios'] = $modelo->completado();

        return view('reporte/completado', $data);
    }
}
