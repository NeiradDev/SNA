<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlanBatallaModel;

class Home extends BaseController
{
    public function home()
    {
        // ---------------------------------------------------------
        // Seguridad
        // ---------------------------------------------------------
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        // ---------------------------------------------------------
        // Sesión
        // ---------------------------------------------------------
        $idUser    = (int) (session()->get('id_user') ?? 0);
        $nombres   = (string) (session()->get('nombres') ?? '');
        $apellidos = (string) (session()->get('apellidos') ?? '');

        $userFullName = trim($nombres . ' ' . $apellidos);
        if ($userFullName === '') {
            $userFullName = 'Usuario';
        }

        $cargoName = (string) (session()->get('nombre_cargo')
            ?? session()->get('cargo_nombre')
            ?? session()->get('cargo')
            ?? '');

        $areaName = (string) (session()->get('nombre_area')
            ?? session()->get('area_nombre')
            ?? '');

        // ✅ Si tienes id_division en sesión (modo normal)
        $sessionDivisionId = (int) (session()->get('id_division') ?? 0);

        // ---------------------------------------------------------
        // Filtro fechas (GET)
        // ---------------------------------------------------------
        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');

        $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

        if (!$isDate($to)) {
            $to = date('Y-m-d');
        }
        if (!$isDate($from)) {
            $from = date('Y-m-d', strtotime($to . ' -84 days'));
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // ---------------------------------------------------------
        // Filtro de división (GET) -> SOLO gerencia
        // ---------------------------------------------------------
        $selectedDivisionId = (int) ($this->request->getGet('division_id') ?? 0);

        // ---------------------------------------------------------
        // Model
        // ---------------------------------------------------------
        $planModel = new PlanBatallaModel();

        // ---------------------------------------------------------
        // Detectar modo gerencia/admin (por keywords)
        // ---------------------------------------------------------
        $haystack = mb_strtolower(trim($cargoName . ' ' . $areaName));

        $gerenciaKeywords = [
            'gerencia',
            'gerente',
            'asistente de gerencia',
            'presidencia',
            'dirección general',
            'direccion general',
            'administrador',
            'directorio',
            'ceo',
        ];

        $isGerencia = false;
        foreach ($gerenciaKeywords as $k) {
            if ($k !== '' && str_contains($haystack, $k)) {
                $isGerencia = true;
                break;
            }
        }

        // ---------------------------------------------------------
        // ✅ Cargar todas las divisiones (siempre que sea gerencia)
        // y construir mapa id => nombre para IMPRESIÓN (sin historico)
        // ---------------------------------------------------------
        $allDivisions    = [];
        $divisionNameMap = [];

        if ($isGerencia) {
            $allDivisions = $planModel->getAllDivisions();

            foreach ($allDivisions as $d) {
                $did = (int) ($d['id_division'] ?? 0);
                if ($did <= 0) continue;

                $divisionNameMap[$did] = (string) ($d['nombre_division'] ?? '');
            }
        }

        // ---------------------------------------------------------
        // ✅ Nombre de división seleccionada (MATCH directo por ID)
        // ---------------------------------------------------------
        $selectedDivisionName = '';
        if ($selectedDivisionId > 0) {
            $selectedDivisionName = $planModel->getDivisionNameById($selectedDivisionId);
        }

        // ---------------------------------------------------------
        // MODO GERENCIA
        // ---------------------------------------------------------
        if ($isGerencia) {
            $rows = $planModel->getSatisfaccionDivisionesPorRango($from, $to);

            $series = [];
            foreach ($rows as $r) {
                $divId  = (int) ($r['id_division'] ?? 0);
                $semana = (string) ($r['semana'] ?? '');
                $val    = (float) ($r['satisfaccion_avg'] ?? 0);

                if ($divId <= 0 || $semana === '') continue;

                if (!isset($series[$divId])) {
                    $series[$divId] = ['labels' => [], 'values' => []];
                }

                $series[$divId]['labels'][] = $semana;
                $series[$divId]['values'][] = $val;
            }

            $divisionCards = [];
            foreach ($allDivisions as $d) {
                $divId = (int) ($d['id_division'] ?? 0);
                if ($divId <= 0) continue;

                $divName = (string) ($d['nombre_division'] ?? ('División #' . $divId));

                $labels = $series[$divId]['labels'] ?? [];
                $values = $series[$divId]['values'] ?? [];
                $weeksCount = count($values);

                $divisionCards[] = [
                    'divisionId'   => $divId,
                    'divisionName' => $divName, // ✅ esto es lo correcto para imprimir por card
                    'chartLabels'  => $labels,
                    'chartValues'  => $values,
                    'weeksCount'   => $weeksCount,
                    'bestWeek'     => $weeksCount ? max($values) : 0,
                    'worstWeek'    => $weeksCount ? min($values) : 0,
                    'avgWeek'      => $weeksCount ? round(array_sum($values) / $weeksCount, 2) : 0,
                ];
            }

            usort($divisionCards, function ($a, $b) {
                $aHas = ((int)($a['weeksCount'] ?? 0)) > 0;
                $bHas = ((int)($b['weeksCount'] ?? 0)) > 0;

                if ($aHas !== $bHas) return $aHas ? -1 : 1;

                if ($aHas && $bHas) {
                    $cmp = ((float)($b['avgWeek'] ?? 0) <=> (float)($a['avgWeek'] ?? 0));
                    if ($cmp !== 0) return $cmp;
                }

                return strcmp((string)($a['divisionName'] ?? ''), (string)($b['divisionName'] ?? ''));
            });

            if ($selectedDivisionId > 0) {
                $divisionCards = array_values(array_filter($divisionCards, function ($c) use ($selectedDivisionId) {
                    return (int)($c['divisionId'] ?? 0) === $selectedDivisionId;
                }));
            }

            return view('pages/home', [
                'isGerencia'           => true,
                'cargoName'            => $cargoName,
                'areaName'             => $areaName,

                'userFullName'         => $userFullName,

                'filterFrom'           => $from,
                'filterTo'             => $to,

                'allDivisions'         => $allDivisions,
                'divisionNameMap'      => $divisionNameMap, // ✅ NUEVO: para imprimir por match sin historico

                'selectedDivisionId'   => $selectedDivisionId,
                'selectedDivisionName' => $selectedDivisionName, // ✅ match directo

                'divisionCards'        => $divisionCards,
            ]);
        }

        // ---------------------------------------------------------
        // MODO NORMAL (usuario)
        // ---------------------------------------------------------
        $rows = $planModel->getSatisfaccionUsuarioPorRango($idUser, $from, $to);

        if (empty($rows)) {
            $rows = $planModel->getUltimasSemanasSatisfaccion($idUser, 3);
            $rows = array_reverse($rows);
        }

        $labels = [];
        $values = [];
        foreach ($rows as $r) {
            $labels[] = (string) ($r['semana'] ?? '');
            $values[] = (float) ($r['satisfaccion'] ?? 0);
        }

        $weeksCount = count($values);

        // ✅ Para normal: nombre división por match directo desde session id_division
        $normalDivisionName = '';
        if ($sessionDivisionId > 0) {
            $normalDivisionName = $planModel->getDivisionNameById($sessionDivisionId);
        }

        return view('pages/home', [
            'isGerencia'           => false,
            'userFullName'         => $userFullName,

            'selectedDivisionId'   => $sessionDivisionId,
            'selectedDivisionName' => $normalDivisionName,

            'filterFrom'           => $from,
            'filterTo'             => $to,

            'chartLabels'          => $labels,
            'chartValues'          => $values,
            'weeksCount'           => $weeksCount,
            'bestWeek'             => $weeksCount ? max($values) : 0,
            'worstWeek'            => $weeksCount ? min($values) : 0,
            'avgWeek'              => $weeksCount ? round(array_sum($values) / $weeksCount, 2) : 0,
        ]);
    }
}