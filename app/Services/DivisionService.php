<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivisionModel;

/**
 * DivisionService
 *
 * ✅ Objetivo:
 * - Preparar la data para la vista de "cards":
 *   - divisiones desde BD
 *   - jefe de división (id_jf_division -> nombre usuario)
 *   - áreas SOLO de esa división
 *   - imagen por división (diferente / dinámica)
 */
class DivisionService
{
    private DivisionModel $divisionModel;

    public function __construct(DivisionModel $divisionModel)
    {
        $this->divisionModel = $divisionModel;
    }

    /**
     * getCardsData()
     * - Devuelve una lista lista para la vista:
     *   [
     *     [
     *       'id_division' => 1,
     *       'nombre' => 'T.I',
     *       'jefe' => 'Juan Pérez',
     *       'areas' => ['Soporte', 'Desarrollo'],
     *       'img' => 'https://....'
     *     ],
     *   ]
     */
    public function getCardsData(): array
    {
        // 1) Traer divisiones con jefe
        $divs = $this->divisionModel->listWithBossName();

        // 2) Armar cards
        $cards = [];

        foreach ($divs as $d) {
            // ✅ Casts seguros
            $divisionId   = (int) ($d['id_division'] ?? 0);
            $divisionName = (string) ($d['nombre_division'] ?? '');
            $bossName     = (string) ($d['jefe_division_nombre'] ?? 'No asignado');

            // 3) Traer áreas SOLO de esa división
            $areasRows = $this->divisionModel->listAreasByDivision($divisionId);

            // Convertimos a array plano de nombres
            $areas = [];
            foreach ($areasRows as $ar) {
                $areas[] = (string) ($ar['nombre_area'] ?? '');
            }

            // 4) Imagen distinta por división:
            //    - Si tienes una imagen específica, la puedes mapear aquí.
            //    - Si no, usamos un "seed" por id_division para que SIEMPRE sea diferente.
            $img = $this->resolveDivisionImage($divisionId, $divisionName);

            // 5) Empaquetar
            $cards[] = [
                'id_division' => $divisionId,
                'nombre'      => $divisionName,
                'jefe'        => $bossName,
                'areas'       => $areas,
                'img'         => $img,
            ];
        }

        return $cards;
    }

    /**
     * resolveDivisionImage()
     * - Puedes personalizar imágenes por división.
     * - Si no hay match, usa un placeholder "distinto" por id.
     */
   private function resolveDivisionImage(int $divisionId, string $divisionName): string
{
    $mapById = [
        1 => 'https://tse2.mm.bing.net/th/id/OIP.NZU4T5k2rw4FSg2IWGesJQHaEK?rs=1&pid=ImgDetMain&o=7&rm=3', //Administrador
        2 => 'https://tugimnasiacerebral.com/sites/default/files/inline-images/tecnologia-de-la-informacion-y-educacion_1.jpg',// TICS
        3 => 'https://tse1.mm.bing.net/th/id/OIP.zb465SiHhbrebdK8BiXUHgHaEd?rs=1&pid=ImgDetMain&o=7&rm=3',//Desarrollo Orgizacional
        4=> 'https://thelogisticsworld.com/wp-content/uploads/2023/03/red-de-distribucion-tienda-comercio-minorista.jpg',//Comercial Retail
        5=> 'https://tse3.mm.bing.net/th/id/OIP.xNlv0xQS5PFTMJBM3YC8OAAAAA?rs=1&pid=ImgDetMain&o=7&rm=3',//Call Center
        6=> 'https://tse1.mm.bing.net/th/id/OIP.eA1INq92_qy09U5GsanWiwHaHO?rs=1&pid=ImgDetMain&o=7&rm=3',//Marketing
        7=> 'https://tse4.mm.bing.net/th/id/OIP.93-lKioDKz1COwUjn-exIgHaHR?rs=1&pid=ImgDetMain&o=7&rm=3',//Riesgo
        8=> 'https://tse3.mm.bing.net/th/id/OIP.wWcyMAKfPk--YpyCfQd3SwHaEK?rs=1&pid=ImgDetMain&o=7&rm=3',//Cobranzas
        9=> 'https://tse4.mm.bing.net/th/id/OIP.q2zsxfc1ANrc1Un0iMb1xAHaFn?rs=1&pid=ImgDetMain&o=7&rm=3',//Contabilidad
        10=> 'https://tse1.mm.bing.net/th/id/OIP.t4iGuF210NPO8BQ6J_U85QHaHa?rs=1&pid=ImgDetMain&o=7&rm=3',//Operaciones
        11=> 'https://tse1.mm.bing.net/th/id/OIP.9dja_kwavbqe3p8kc63u_AHaE7?rs=1&pid=ImgDetMain&o=7&rm=3',//Nuevos Negocios
        12=> 'https://tse3.mm.bing.net/th/id/OIP.X0yUeujrcouPRx88ekzzYQHaFl?rs=1&pid=ImgDetMain&o=7&rm=3',//Direccion General
        
    ];

    if (isset($mapById[$divisionId])) {
        return $mapById[$divisionId];
    }

    // default si no está mapeada
    return 'https://picsum.photos/seed/division-' . $divisionId . '/900/500';
}

}
