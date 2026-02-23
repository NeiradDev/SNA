<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrgChartModel;

/**
 * OrgChartService
 *
 * ✅ Reglas:
 * - Se muestran correo y teléfono en nodos.
 * - Si un usuario es Jefe de División y también Jefe de Área:
 *   ✅ se crea también el nodo "Jefe de Área" (bloquecito)
 *   ✅ y además puede aparecer como usuario normal SOLO en ese caso
 *   ✅ el usuario normal lleva "extra" para que se entienda
 * - Si un usuario es SOLO Jefe de Área:
 *   ✅ NO se repite como usuario normal dentro de su área
 */
class OrgChartService
{
    private OrgChartModel $model;

    public function __construct()
    {
        $this->model = new OrgChartModel();
    }

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
     * Devuelve nodos para d3-org-chart:
     * [{ id, parentId, fullName, cargo, area, email, phone, extra }, ...]
     */
    public function getDivisionTreeData(int $divisionId, int $gerenciaCargoId = 6): array
    {
        $divisionId = (int) $divisionId;
        $gerenciaCargoId = (int) $gerenciaCargoId;

        // =========================
        // 1) ROOT: Gerencia
        // =========================
        $gerencia = $this->model->getGerenciaUserByCargo($gerenciaCargoId);

        if (!$gerencia) {
            return [
                [
                    'id'       => 'root',
                    'parentId' => null,
                    'fullName' => 'Gerencia no asignada',
                    'cargo'    => 'Cargo 6 no encontrado',
                    'area'     => '',
                    'email'    => '',
                    'phone'    => '',
                    'extra'    => '',
                ],
            ];
        }

        $rootUserId = (int) ($gerencia['id_user'] ?? 0);
        $rootId = 'u-' . $rootUserId;

        $nodes = [];

        $nodes[] = [
            'id'       => $rootId,
            'parentId' => null,
            'fullName' => trim((string)($gerencia['nombres'] ?? '') . ' ' . (string)($gerencia['apellidos'] ?? '')),
            'cargo'    => (string)($gerencia['nombre_cargo'] ?? 'Gerencia'),
            'area'     => '',
            'email'    => (string)($gerencia['correo'] ?? ''),
            'phone'    => (string)($gerencia['telefono'] ?? ''),
            'extra'    => '',
        ];

        // =========================
        // 2) División + Jefe División
        // =========================
        $division = $this->model->getDivisionWithBoss($divisionId);
        if (!$division) {
            return $nodes;
        }

        $divisionBossUserId = (int) ($division['id_jf_division'] ?? 0);

        // Parent para colgar “nivel división”
        $divisionParentId = $rootId;

        // Nodo jefe de división (si existe)
        if ($divisionBossUserId > 0) {
            $divisionBossId = 'u-' . $divisionBossUserId;

            // Si el jefe de división es root, no duplicamos nodo
            if ($divisionBossId !== $rootId) {
                $nodes[] = [
                    'id'       => $divisionBossId,
                    'parentId' => $rootId,
                    'fullName' => (string)($division['jefe_division_nombre'] ?? 'Jefe de División'),
                    'cargo'    => 'Jefe de División — ' . (string)($division['nombre_division'] ?? ''),
                    'area'     => '',
                    'email'    => (string)($division['jefe_division_correo'] ?? ''),
                    'phone'    => (string)($division['jefe_division_telefono'] ?? ''),
                    'extra'    => '',
                ];
                $divisionParentId = $divisionBossId;
            }
        }

        // =========================
        // 3) Usuarios “nivel división”
        // =========================
        $divisionLevelUsers = $this->model->getUsersByDivisionLevel($divisionId);

        foreach ($divisionLevelUsers as $u) {
            $uid = (int)($u['id_user'] ?? 0);
            if ($uid <= 0) continue;

            $nid = 'u-' . $uid;

            // Evitar repetir root y jefe de división como usuario normal
            if ($nid === $rootId) continue;
            if ($divisionBossUserId > 0 && $nid === ('u-' . $divisionBossUserId)) continue;

            $nodes[] = [
                'id'       => $nid,
                'parentId' => $divisionParentId,
                'fullName' => trim((string)($u['nombres'] ?? '') . ' ' . (string)($u['apellidos'] ?? '')),
                'cargo'    => (string)($u['nombre_cargo'] ?? 'Cargo'),
                'area'     => '',
                'email'    => (string)($u['correo'] ?? ''),
                'phone'    => (string)($u['telefono'] ?? ''),
                'extra'    => '',
            ];
        }

        // =========================
        // 4) Áreas + jefes de área + usuarios
        // =========================
        $areas = $this->model->getAreasWithBossByDivision($divisionId);

        foreach ($areas as $a) {
            $areaId = (int)($a['id_area'] ?? 0);
            if ($areaId <= 0) continue;

            $areaName = (string)($a['nombre_area'] ?? '');
            $areaBossUserId = (int)($a['id_jf_area'] ?? 0);

            // Parent base si no hay jefe de área
            $areaUsersParentId = $divisionParentId;

            // Si hay jefe de área:
            // ✅ SIEMPRE creamos el “bloquecito”, aunque sea el mismo que jefe de división
            if ($areaBossUserId > 0) {
                $areaBossMainId = 'u-' . $areaBossUserId;

                // ID único para el nodo "Jefe de Área"
                $areaBossNodeId = $areaBossMainId . '-area-' . $areaId;

                $nodes[] = [
                    'id'       => $areaBossNodeId,
                    'parentId' => $divisionParentId,
                    'fullName' => (string)($a['jefe_area_nombre'] ?? 'Jefe de Área'),
                    'cargo'    => 'Jefe de Área — ' . $areaName,
                    'area'     => $areaName,
                    'email'    => (string)($a['jefe_area_correo'] ?? ''),
                    'phone'    => (string)($a['jefe_area_telefono'] ?? ''),
                    'extra'    => '',
                ];

                // Usuarios del área cuelgan del “bloquecito”
                $areaUsersParentId = $areaBossNodeId;
            }

            // Usuarios del área
            $areaUsers = $this->model->getUsersByArea($areaId);

            foreach ($areaUsers as $u) {
                $uid = (int)($u['id_user'] ?? 0);
                if ($uid <= 0) continue;

                $nid = 'u-' . $uid;

                // Nunca repetir root como usuario normal
                if ($nid === $rootId) continue;

                // ✅ Regla exacta:
                // - Si es jefe de división y jefe de área -> SE PERMITE repetir (solo ese caso)
                // - Si es solo jefe de área -> NO se repite como usuario normal
                // - Si es solo jefe de división -> NO se repite como usuario normal
                $isDivisionBoss = ($divisionBossUserId > 0 && $uid === $divisionBossUserId);
                $isAreaBoss     = ($areaBossUserId > 0 && $uid === $areaBossUserId);

                if ($isDivisionBoss && !$isAreaBoss) continue;
                if ($isAreaBoss && !$isDivisionBoss) continue;

                // ✅ Si se repite porque tiene ambos roles, marcamos "extra"
                $extra = '';
                if ($isDivisionBoss && $isAreaBoss) {
                    $extra = 'También: Usuario del área (por cargo)';
                }

                $nodes[] = [
                    'id'       => $nid,
                    'parentId' => $areaUsersParentId,
                    'fullName' => trim((string)($u['nombres'] ?? '') . ' ' . (string)($u['apellidos'] ?? '')),
                    'cargo'    => (string)($u['nombre_cargo'] ?? 'Cargo'),
                    'area'     => $areaName,
                    'email'    => (string)($u['correo'] ?? ''),
                    'phone'    => (string)($u['telefono'] ?? ''),
                    'extra'    => $extra,
                ];
            }
        }

        // =========================
        // 5) Deduplicar por id exacto
        // =========================
        $unique = [];
        foreach ($nodes as $n) {
            $nid = (string)($n['id'] ?? '');
            if ($nid === '') continue;
            $unique[$nid] = $n;
        }

        return array_values($unique);
    }
}