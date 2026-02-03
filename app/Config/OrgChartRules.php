<?php

namespace Config;

/**
 * ============================================================
 * OrgChartRules
 * ============================================================
 * Aquí defines REGLAS ESPECIALES por ID_AREA.
 *
 * ✅ Si NO pones un área aquí:
 *    - El organigrama igual funciona
 *    - Se arma solo con: id_supervisor (parentId)
 *
 * ✅ Si un área TIENE reglas diferentes (ej. cargo raíz distinto,
 *    niveles para ordenar, etc.), la agregas aquí.
 *
 * ------------------------------------------------------------
 * ¿Cómo agregar una nueva área?
 * ------------------------------------------------------------
 * 1) Identifica el ID del área en tabla "area" (id_area)
 * 2) Define cuál ID_CARGO es el "Gerente" (cargo raíz) de esa área
 * 3) (Opcional) Define niveles para ordenar visualmente (levels_by_cargo_id)
 *
 * Ejemplo:
 *  2 => [  // Comercial
 *    'title' => 'Organigrama - Comercial',
 *    'gerente_cargo_id' => 10,
 *    'levels_by_cargo_id' => [
 *      10 => 1, // Gerente Comercial
 *      11 => 2, // Jefe Comercial
 *      12 => 3, // Supervisor
 *      13 => 4, // Asistente
 *    ],
 *  ],
 * ============================================================
 */
class OrgChartRules
{
    /**
     * ✅ Área de Gerencia (siempre arriba del organigrama)
     * Si en tu BD Gerencia no es 1, cambia aquí el valor.
     */
    public const GERENCIA_AREA_ID = 1;

    public const BY_AREA_ID = [

        /**
         * ✅ TICs / Sistemas (id_area = 5)
         * - Tu jerarquía interna se ordena por cargo:
         *   10 Jefe de División → 11 Supervisor → 12 Asistente
         * - El "top" REAL lo da GERENCIA_AREA_ID, no TICs.
         */
        5 => [
            'title' => 'Organigrama - TICs (Sistemas)',

            /**
             * Cargo raíz dentro del área:
             * - Como el jefe máximo viene de Gerencia, aquí lo dejamos en 0
             *   para NO forzar "gerentes" dentro de TICs.
             */
            'gerente_cargo_id' => 0,

            /**
             * Niveles por cargo (solo orden visual)
             */
            'levels_by_cargo_id' => [
                10 => 2, // Jefe de División
                11 => 3, // Supervisor
                12 => 4, // Asistente
            ],
        ],

        // ======================================================
        // ✅ AQUÍ AGREGA OTRAS ÁREAS SI NECESITAN NIVELES/TÍTULO
        // ======================================================

        // 2 => [
        //     'title' => 'Organigrama - Comercial',
        //     'gerente_cargo_id' => 0, // top lo da GERENCIA
        //     'levels_by_cargo_id' => [
        //         20 => 2,
        //         21 => 3,
        //         22 => 4,
        //     ],
        // ],
    ];
}