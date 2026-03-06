<?php

namespace App\Services;

class MenuService
{
    /**
     * =========================================================
     * Menú completo
     * =========================================================
     */
    public function getFullMenu(): array
    {
        return [
            'reporte' => [
                'horario_plan',
                'historico',
                'plan_batalla',
                'completado',
            ],
            'agencias'      => true,
            'division'      => true,
            'planificacion' => true,
            'usuarios'      => true,
            'mantenimiento' => true,
        ];
    }

    /**
     * =========================================================
     * Menú por permisos
     * =========================================================
     */
    public function getMenuByPermissions(array $permissions): array
    {
        $menu = [];

        // -----------------------------------------------------
        // REPORTE
        // -----------------------------------------------------
        $reportItems = [];

        if ($this->has($permissions, 'reporte.horario_plan')) {
            $reportItems[] = 'horario_plan';
        }

        if ($this->has($permissions, 'reporte.historico')) {
            $reportItems[] = 'historico';
        }

        if ($this->has($permissions, 'reporte.plan')) {
            $reportItems[] = 'plan_batalla';
        }

        if ($this->has($permissions, 'reporte.completado')) {
            $reportItems[] = 'completado';
        }

        if (!empty($reportItems)) {
            $menu['reporte'] = $reportItems;
        }

        // -----------------------------------------------------
        // AGENCIAS
        // -----------------------------------------------------
        if ($this->has($permissions, 'agencias.ver')) {
            $menu['agencias'] = true;
        }

        // -----------------------------------------------------
        // DIVISIÓN
        // IMPORTANTE:
        // también se activa con orgchart.ver
        // para mostrar la misma pestaña "División"
        // -----------------------------------------------------
        if (
            $this->has($permissions, 'division.ver') ||
            $this->has($permissions, 'orgchart.ver')
        ) {
            $menu['division'] = true;
        }

        // -----------------------------------------------------
        // USUARIOS
        // -----------------------------------------------------
        if ($this->has($permissions, 'usuarios.ver')) {
            $menu['usuarios'] = true;
        }

        // -----------------------------------------------------
        // PLANIFICACIÓN
        // -----------------------------------------------------
        if ($this->has($permissions, 'tareas.asignar')) {
            $menu['planificacion'] = true;
        }

        // -----------------------------------------------------
        // MANTENIMIENTO
        // -----------------------------------------------------
        if (
            $this->has($permissions, 'mantenimiento.divisiones.ver') ||
            $this->has($permissions, 'mantenimiento.areas.ver') ||
            $this->has($permissions, 'mantenimiento.cargos.ver') ||
            $this->has($permissions, 'mantenimiento.permisos.ver') ||
            $this->has($permissions, 'mantenimiento.permisos.editar')
        ) {
            $menu['mantenimiento'] = true;
        }

        return $menu;
    }

    /**
     * =========================================================
     * Helper
     * =========================================================
     */
    private function has(array $permissions, string $code): bool
    {
        return in_array($code, $permissions, true);
    }
}