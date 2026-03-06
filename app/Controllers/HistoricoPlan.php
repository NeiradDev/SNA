<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\HistoricoPlanService;

/**
 * =========================================================
 * Controller: HistoricoPlan
 * =========================================================
 * Rutas:
 *  - GET  reporte/historico-plan
 *  - GET  reporte/historico-plan/pdf
 *
 * Query params:
 *  - semana=YYYY-MM-DD
 *  - user=ID
 * =========================================================
 */
class HistoricoPlan extends BaseController
{
    private HistoricoPlanService $service;

    public function __construct()
    {
        $this->service = new HistoricoPlanService();
    }

    /**
     * =========================================================
     * Vista principal
     * =========================================================
     */
    public function index()
    {
        // -----------------------------
        // 1) Usuario logueado
        // -----------------------------
        $currentUserId = (int)(session()->get('id_user') ?? 0);

        if ($currentUserId <= 0) {
            // Si por alguna razón no hay sesión válida
            return redirect()->to(base_url('login'));
        }

        // -----------------------------
        // 2) Resolver scope
        // -----------------------------
        $scopeInfo = $this->service->resolveScope($currentUserId);

        // -----------------------------
        // 3) Usuario seleccionado (si no viene, default según scope)
        // -----------------------------
        $selectedUserId = (int)($this->request->getGet('user') ?? 0);

        if ($selectedUserId <= 0) {
            // Default:
            // - division/area: el mismo usuario logueado
            // - self: él mismo
            $selectedUserId = $currentUserId;
        }

        // -----------------------------
        // 4) Usuarios accesibles
        // -----------------------------
        $users = $this->service->getAccessibleUsers($scopeInfo, $currentUserId);

        // Seguridad: si el usuario seleccionado no está en la lista, forzar al primero permitido
        $allowedIds = array_map(fn($u) => (int)$u['id_user'], $users);
        if (!in_array($selectedUserId, $allowedIds, true)) {
            $selectedUserId = !empty($allowedIds) ? (int)$allowedIds[0] : $currentUserId;
        }

        // -----------------------------
        // 5) Semanas accesibles (ya filtradas por el usuario seleccionado)
        // -----------------------------
        $weeks = $this->service->getAccessibleWeeks($scopeInfo, $currentUserId, $selectedUserId);

        // Semana seleccionada (si no viene, usa la más reciente)
        $selectedWeek = (string)($this->request->getGet('semana') ?? '');
        if ($selectedWeek === '' && !empty($weeks)) {
            $selectedWeek = (string)$weeks[0]['semana'];
        }

        // -----------------------------
        // 6) Obtener historico + tareas (si hay semana)
        // -----------------------------
        $historico = null;
        $tasksPack = null;

        if ($selectedWeek !== '' && $selectedUserId > 0) {
            $historico = $this->service->getHistoricoRow($selectedWeek, $selectedUserId);
            $tasksPack = $this->service->getTasksForWeek($selectedWeek, $selectedUserId);
        }

        // -----------------------------
        // 7) Enviar a la vista
        // -----------------------------
        return view('reporte/historico_plan', [
            'scopeInfo'      => $scopeInfo,
            'users'          => $users,
            'weeks'          => $weeks,
            'selectedUserId' => $selectedUserId,
            'selectedWeek'   => $selectedWeek,
            'historico'      => $historico,
            'tasksPack'      => $tasksPack,
        ]);
    }

    /**
     * =========================================================
     * PDF del histórico consultado
     * =========================================================
     * Usa Dompdf si está instalado.
     * Ruta:
     *  /reporte/historico-plan/pdf?semana=YYYY-MM-DD&user=ID
     * =========================================================
     */
    public function pdf()
    {
        $currentUserId = (int)(session()->get('id_user') ?? 0);
        if ($currentUserId <= 0) {
            return redirect()->to(base_url('login'));
        }

        $scopeInfo = $this->service->resolveScope($currentUserId);

        $selectedUserId = (int)($this->request->getGet('user') ?? 0);
        $selectedWeek   = (string)($this->request->getGet('semana') ?? '');

        if ($selectedUserId <= 0 || $selectedWeek === '') {
            return redirect()->to(base_url('reporte/historico-plan'));
        }

        // Validación de acceso: el user debe estar dentro del alcance
        $users = $this->service->getAccessibleUsers($scopeInfo, $currentUserId);
        $allowedIds = array_map(fn($u) => (int)$u['id_user'], $users);

        if (!in_array($selectedUserId, $allowedIds, true)) {
            return redirect()->to(base_url('reporte/historico-plan'));
        }

        $historico = $this->service->getHistoricoRow($selectedWeek, $selectedUserId);
        $tasksPack = $this->service->getTasksForWeek($selectedWeek, $selectedUserId);

        if (!$historico) {
            return redirect()->to(base_url('reporte/historico-plan'));
        }

        // Render HTML del PDF
        $html = view('reporte/historico_plan_pdf', [
            'scopeInfo'      => $scopeInfo,
            'selectedWeek'   => $selectedWeek,
            'historico'      => $historico,
            'tasksPack'      => $tasksPack,
        ]);

        // ---------------------------------------------------------
        // Dompdf (recomendado)
        // composer require dompdf/dompdf
        // ---------------------------------------------------------
        if (!class_exists(\Dompdf\Dompdf::class)) {
            // Si no está dompdf, devolvemos el HTML (para imprimir en navegador)
            return $this->response
                ->setHeader('Content-Type', 'text/html; charset=utf-8')
                ->setBody($html);
        }

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true, // permite cargar logos/css remotos si se usan
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'historico_plan_' . $selectedWeek . '_user_' . $selectedUserId . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="'.$filename.'"')
            ->setBody($dompdf->output());
    }
}