<?php

namespace App\Controllers;

use App\Models\PlanBatallaModel;

class Home extends BaseController
{
    public function Home()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');

        $planModel = new PlanBatallaModel();

        // 🔹 Traemos las últimas 3 semanas
        $rows = $planModel->getUltimasSemanasSatisfaccion($idUser, 3);

        // 🔹 Ordenamos cronológicamente (viejo → nuevo)
        $rows = array_reverse($rows);

        // 🔹 Preparamos arrays para Chart.js
        $labels = [];
        $values = [];

        foreach ($rows as $r) {
            $labels[] = $r['semana'];
            $values[] = (float) $r['satisfaccion'];
        }

        return view('pages/home', [
            'chartLabels' => $labels,
            'chartValues' => $values,
            'weeksCount'  => count($rows),
            'bestWeek'    => !empty($values) ? max($values) : 0,
            'worstWeek'   => !empty($values) ? min($values) : 0,
            'avgWeek'     => !empty($values)
                ? round(array_sum($values) / count($values), 2)
                : 0,
        ]);
    }
}
