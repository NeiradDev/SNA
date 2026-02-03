<?php

namespace App\Services;

use App\Models\UsuarioModel;
use Config\OrgChartRules;

class OrgChartService
{
    public function __construct(private UsuarioModel $usuarioModel) {}

    /**
     * Retorna: slug, title, nodes[] (para d3-org-chart)
     * - Incluye siempre la gerencia arriba (configurable)
     * - El área solicitada cuelga debajo de la gerencia
     */
    public function getBySlug(string $slug): array
    {
        helper('text');

        [$areaId, $areaName] = $this->resolveAreaBySlug($slug);
        if (!$areaId) {
            return ['slug' => $slug, 'title' => 'Organigrama', 'nodes' => []];
        }

        // ✅ Gerencia configurable (por defecto 1)
        $gerenciaAreaId = OrgChartRules::GERENCIA_AREA_ID;

        // 1) Traer usuarios del área solicitada + gerencia
        //    IMPORTANTE: necesitas que tu UsuarioModel soporte el 2do parámetro
        //    getOrgChartUsersByArea(int $areaId, int $gerenciaAreaId)
        $rows  = $this->usuarioModel->getOrgChartUsersByArea($areaId, $gerenciaAreaId);

        // 2) Nodos en formato d3-org-chart
        $nodes = $this->toNodes($rows);

        // 3) Evita parentId inválidos (si supervisor no está en este set, lo vuelve null)
        $nodes = $this->sanitizeParentsInsideArea($nodes);

        // 4) Asegura que Gerencia esté arriba:
        //    - Si el área solicitada no es gerencia, cuelga raíces del área debajo del root de gerencia (si hay uno único)
        $nodes = $this->attachAreaRootsToGerencia($nodes, $areaId, $gerenciaAreaId);

        // 5) Reglas especiales por área (si existen): niveles / etc.
        $rules = OrgChartRules::BY_AREA_ID[$areaId] ?? null;

        $title = 'Organigrama - ' . ($areaName ?: 'Área');

        if ($rules) {
            $nodes = $this->applyRules($nodes, $rules);
            $title = $rules['title'] ?? $title;
        }

        // Orden opcional (solo visual)
        usort($nodes, fn($a, $b) => ($a['level'] <=> $b['level']) ?: ($a['id'] <=> $b['id']));

        return [
            'slug'  => $slug,
            'title' => $title,
            'nodes' => $nodes,
        ];
    }

    /**
     * Resuelve ID_AREA por slug sin cambiar BD:
     * - tics => 5
     * - resto => url_title(nombre_area) == slug
     */
    private function resolveAreaBySlug(string $slug): array
    {
        if ($slug === 'tics') {
            return [5, 'TICs (Sistemas)'];
        }

        $areas = $this->usuarioModel->getAreas();
        foreach ($areas as $a) {
            $id   = (int)($a['id_area'] ?? 0);
            $name = (string)($a['nombre_area'] ?? '');

            if ($id && url_title($name, '-', true) === $slug) {
                return [$id, $name];
            }
        }

        return [0, ''];
    }

    /**
     * Convierte filas SQL a nodos.
     * Se agrega areaId para poder identificar nodos de gerencia.
     */
    private function toNodes(array $rows): array
    {
        $nodes = [];

        foreach ($rows as $r) {
            $nodes[] = [
                'id'       => (int)$r['id_user'],
                'parentId' => $r['id_supervisor'] !== null ? (int)$r['id_supervisor'] : null,
                'fullName' => trim(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? '')),
                'cargo'    => $r['nombre_cargo'] ?? null,
                'cargoId'  => isset($r['id_cargo']) ? (int)$r['id_cargo'] : null,
                'area'     => $r['nombre_area'] ?? null,
                'areaId'   => isset($r['id_area']) ? (int)$r['id_area'] : null,
                'level'    => 99, // default; se ajusta por reglas si aplica
            ];
        }

        return $nodes;
    }

    /**
     * Si parentId apunta a alguien fuera del set, se vuelve null.
     * Evita romper el render.
     */
    private function sanitizeParentsInsideArea(array $nodes): array
    {
        $ids = array_flip(array_map(fn($n) => $n['id'], $nodes));

        foreach ($nodes as &$n) {
            if ($n['parentId'] !== null && !isset($ids[$n['parentId']])) {
                $n['parentId'] = null;
            }
        }
        unset($n);

        return $nodes;
    }

    /**
     * Gerencia siempre arriba:
     * - Busca ROOTS de gerencia (areaId=gerenciaAreaId y parentId=NULL)
     * - Si hay EXACTAMENTE 1 root, cuelga cualquier root del área solicitada debajo de ese root
     * - Si hay 0 o más de 1 root, NO inventa enlaces
     */
    private function attachAreaRootsToGerencia(array $nodes, int $areaId, int $gerenciaAreaId): array
    {
        // Si el área solicitada ES gerencia, no hacemos nada
        if ($areaId === $gerenciaAreaId) {
            return $nodes;
        }

        $gerenciaRoots = array_values(array_map(
            fn($n) => $n['id'],
            array_filter($nodes, fn($n) => (int)($n['areaId'] ?? 0) === $gerenciaAreaId && $n['parentId'] === null)
        ));

        // Solo si hay un root claro en gerencia
        if (count($gerenciaRoots) !== 1) {
            return $nodes;
        }

        $rootId = $gerenciaRoots[0];

        foreach ($nodes as &$n) {
            $isGerencia = ((int)($n['areaId'] ?? 0) === $gerenciaAreaId);

            // Si no es gerencia y quedó como root, lo cuelgo del root de gerencia
            if (!$isGerencia && $n['parentId'] === null) {
                $n['parentId'] = $rootId;
            }
        }
        unset($n);

        return $nodes;
    }

    /**
     * Reglas del área:
     * - Asigna niveles por cargo para ordenar (visual)
     * - Si gerente_cargo_id > 0, puede colgar raíces al gerente del área (solo si hay 1)
     *   (En tu caso TICs lo dejamos en 0 porque el top lo da Gerencia)
     */
    private function applyRules(array $nodes, array $rules): array
    {
        $gerenteCargoId = (int)($rules['gerente_cargo_id'] ?? 0);
        $levelsMap      = (array)($rules['levels_by_cargo_id'] ?? []);

        // 1) Niveles (orden visual)
        foreach ($nodes as &$n) {
            $cid = (int)($n['cargoId'] ?? 0);
            $n['level'] = $levelsMap[$cid] ?? $n['level'];
        }
        unset($n);

        // 2) Si no hay gerente definido en el área, no forzamos enlaces
        if ($gerenteCargoId <= 0) {
            return $nodes;
        }

        // 3) Buscar "gerentes" del área por cargo
        $gerenteIds = array_values(array_map(
            fn($n) => $n['id'],
            array_filter($nodes, fn($n) => (int)($n['cargoId'] ?? 0) === $gerenteCargoId)
        ));

        // 4) Si hay 1 gerente, cuelga raíces que NO sean gerente debajo de él
        foreach ($nodes as &$n) {
            $isGerente = ((int)($n['cargoId'] ?? 0) === $gerenteCargoId);

            if ($n['parentId'] === null && !$isGerente) {
                if (count($gerenteIds) === 1) {
                    $n['parentId'] = $gerenteIds[0];
                }
            }
        }
        unset($n);

        return $nodes;
    }
}
