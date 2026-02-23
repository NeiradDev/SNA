<?php

namespace App\Services;

class MenuService
{
    public function getMenuByNivel(string $nivel): array
    {
        return $this->permissions()[$nivel] ?? [];
    }

    private function permissions(): array
    {
        return [

            // 🔵 N5 - Mantenimiento (TODO)
            'N5' => [
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
            ],

            // 🟢 N4 - Gerente
            'N4' => [
                'reporte' => [
                    'historico',
                    'plan_batalla',
                    'completado',
                ],
                'agencias'      => true,
                'division'      => true,
                'planificacion' => true,
            ],

            // 🟡 N3 - Jefe División
            'N3' => [
                'reporte' => [
                    'historico',
                    'plan_batalla',
                    'completado',
                ],
                'agencias'      => true,
                'division'      => true,
                'planificacion' => true,
            ],

            // 🟠 N2 - Jefe Área
            'N2' => [
                'reporte' => [
                    'historico',
                    'plan_batalla',
                    'completado',
                ],
                'agencias'      => true,
                'division'      => true,
                'planificacion' => true,
            ],

            // 🔴 N1 - Usuario normal (AHORA VE DIVISIÓN)
            'N1' => [
                'reporte' => [
                    'historico',
                    'plan_batalla',
                ],
                'division'      => true,  
                'planificacion' => true,
            ],
        ];
    }
}
