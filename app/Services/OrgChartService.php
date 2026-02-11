<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrgChartModel;

/**
 * OrgChartService
 *
 * ✅ Construye:
 * - Data de página (title + url json)
 * - Payload JSON {title, nodes} para la vista
 * - Nodos del organigrama para d3-org-chart
 *
 * ✅ Regla:
 * - ROOT = usuario con cargo 6 (Gerencia)
 * - Debajo: Jefe de División (division.id_jf_division)
 * - Luego: Jefes de Área (area.id_jf_area)
 * - Luego: Usuarios del área
 */
class OrgChartService
{
    private OrgChartModel $model;

    public function __construct()
    {
        // ✅ No tocamos tu CRUD, este model es solo lectura del organigrama
        $this->model = new OrgChartModel();
    }

    /**
     * Data para la vista HTML (orgchart.php)
     * - La vista recibe $dataUrl y opcionalmente $title (pero el JS toma el title del JSON)
     */
    public function getDivisionPageData(int $divisionId): array
    {
        $divisionId = (int) $divisionId;

        $divisionName = $this->model->getDivisionName($divisionId) ?? ('División #' . $divisionId);

        return [
            'title'      => 'Organigrama — ' . $divisionName,
            'dataUrl'    => base_url('orgchart/api/division/' . $divisionId),
            'divisionId' => $divisionId,
        ];
    }

    /**
     * ✅ Payload que tu vista espera:
     * { title: string, nodes: array }
     */
    public function getDivisionTreePayload(int $divisionId, int $gerenciaCargoId = 6): array
    {
        $divisionId = (int) $divisionId;

        $divisionName = $this->model->getDivisionName($divisionId) ?? ('División #' . $divisionId);

        return [
            'title' => 'Organigrama — ' . $divisionName,
            'nodes' => $this->getDivisionTreeData($divisionId, $gerenciaCargoId),
        ];
    }

    /**
     * Nodos para d3-org-chart
     * Devuelve: [{id, parentId, fullName, cargo, area}, ...]
     */
    public function getDivisionTreeData(int $divisionId, int $gerenciaCargoId = 6): array
    {
        $divisionId = (int) $divisionId;
        $gerenciaCargoId = (int) $gerenciaCargoId;

        // =========================================================
        // 1) ROOT: Gerencia (cargo 6)
        // =========================================================
        $gerencia = $this->model->getGerenciaUserByCargo($gerenciaCargoId);

        if (!$gerencia) {
            // ✅ Evita que el JS reviente si no existe Gerencia
            return [
                [
                    'id'       => 'root',
                    'parentId' => null,
                    'fullName' => 'Gerencia no asignada',
                    'cargo'    => 'Cargo 6 no encontrado',
                    'area'     => '',
                ],
            ];
        }

        $rootId = (int) ($gerencia['id_user'] ?? 0);

        $nodes = [];

        // ✅ Root
        $nodes[] = [
            'id'       => $rootId,
            'parentId' => null,
            'fullName' => trim((string)($gerencia['nombres'] ?? '') . ' ' . (string)($gerencia['apellidos'] ?? '')),
            'cargo'    => (string)($gerencia['nombre_cargo'] ?? 'Gerencia'),
            'area'     => '',
        ];

        // =========================================================
        // 2) División y jefe de división
        // =========================================================
        $division = $this->model->getDivisionWithBoss($divisionId);

        // ✅ Si no existe esa división, mostramos solo gerencia
        if (!$division) {
            return $nodes;
        }

        $divisionBossId = (int) ($division['id_jf_division'] ?? 0);

        // ✅ Si hay jefe de división y NO es el mismo root, colgarlo de gerencia
        if ($divisionBossId > 0 && $divisionBossId !== $rootId) {
            $nodes[] = [
                'id'       => $divisionBossId,
                'parentId' => $rootId,
                'fullName' => (string)($division['jefe_division_nombre'] ?? 'Jefe de División'),
                'cargo'    => 'Jefe de División — ' . (string)($division['nombre_division'] ?? ''),
                'area'     => '',
            ];
        }

        // ✅ Parent base para todo lo que cuelga de la división
        $divisionParentId = ($divisionBossId > 0 && $divisionBossId !== $rootId)
            ? $divisionBossId
            : $rootId;

        // =========================================================
        // 3) Usuarios “nivel división” (cargo.id_division = división)
        // =========================================================
        $divisionLevelUsers = $this->model->getUsersByDivisionLevel($divisionId);

        foreach ($divisionLevelUsers as $u) {
            $uid = (int)($u['id_user'] ?? 0);
            if ($uid <= 0) continue;

            // Evitar duplicar root y jefe
            if ($uid === $rootId) continue;
            if ($divisionBossId > 0 && $uid === $divisionBossId) continue;

            $nodes[] = [
                'id'       => $uid,
                'parentId' => $divisionParentId,
                'fullName' => trim((string)($u['nombres'] ?? '') . ' ' . (string)($u['apellidos'] ?? '')),
                'cargo'    => (string)($u['nombre_cargo'] ?? 'Cargo'),
                'area'     => '',
            ];
        }

        // =========================================================
        // 4) Áreas de la división + jefes de área + usuarios por área
        // =========================================================
        $areas = $this->model->getAreasWithBossByDivision($divisionId);

        foreach ($areas as $a) {
            $areaId = (int)($a['id_area'] ?? 0);
            if ($areaId <= 0) continue;

            $areaName = (string)($a['nombre_area'] ?? '');
            $areaBossId = (int)($a['id_jf_area'] ?? 0);

            // 4.1) Si hay jefe de área, colgarlo del jefe de división (o gerencia)
            $areaBossNodeId = null;

            if ($areaBossId > 0 && $areaBossId !== $rootId) {
                // Si el jefe de área coincide con el jefe de división, reusamos
                if ($divisionBossId > 0 && $areaBossId === $divisionBossId) {
                    $areaBossNodeId = $divisionBossId;
                } else {
                    $nodes[] = [
                        'id'       => $areaBossId,
                        'parentId' => $divisionParentId,
                        'fullName' => (string)($a['jefe_area_nombre'] ?? 'Jefe de Área'),
                        'cargo'    => 'Jefe de Área — ' . $areaName,
                        'area'     => $areaName,
                    ];
                    $areaBossNodeId = $areaBossId;
                }
            }

            // 4.2) Parent para usuarios del área
            $areaUsersParentId = $areaBossNodeId ?? $divisionParentId;

            // 4.3) Usuarios del área
            $areaUsers = $this->model->getUsersByArea($areaId);

            foreach ($areaUsers as $u) {
                $uid = (int)($u['id_user'] ?? 0);
                if ($uid <= 0) continue;

                // Evitar duplicados obvios
                if ($uid === $rootId) continue;
                if ($divisionBossId > 0 && $uid === $divisionBossId) continue;
                if ($areaBossId > 0 && $uid === $areaBossId) continue;

                $nodes[] = [
                    'id'       => $uid,
                    'parentId' => $areaUsersParentId,
                    'fullName' => trim((string)($u['nombres'] ?? '') . ' ' . (string)($u['apellidos'] ?? '')),
                    'cargo'    => (string)($u['nombre_cargo'] ?? 'Cargo'),
                    'area'     => $areaName,
                ];
            }
        }

        // =========================================================
        // 5) Quitar duplicados por id
        // =========================================================
        $unique = [];
        foreach ($nodes as $n) {
            $nid = (string)($n['id'] ?? '');
            if ($nid === '') continue;
            $unique[$nid] = $n;
        }

        return array_values($unique);
    }
}
