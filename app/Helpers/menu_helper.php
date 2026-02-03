<?php

use App\Models\UsuarioModel;

/**
 * Extiende el submenu "Áreas" con áreas desde BD,
 * sin borrar lo fijo y sin duplicar URLs.
 */
function menu_build_items(array $menuItems): array
{
    helper('text'); // url_title()

    $model = new UsuarioModel();
    $areas = $model->getAreas(); // ya existe en tu UsuarioModel

    foreach ($menuItems as &$item) {
        if (($item['title'] ?? '') !== 'Áreas' || empty($item['sub']) || !is_array($item['sub'])) {
            continue;
        }

        $subs = $item['sub'];

        // Evitar duplicados por URL
        $existingUrls = array_flip(array_map(fn($s) => $s['url'] ?? '', $subs));

        foreach ($areas as $a) {
            $id   = (int)($a['id_area'] ?? 0);
            $name = (string)($a['nombre_area'] ?? 'Área');

            // slug automático desde nombre_area
            $slug = url_title($name, '-', true);

            // Caso especial: Sistemas (ID_AREA=3) se verá como TICs y ruta areas/tics
            if ($id === 3) {
                $slug = 'tics';
                $name = 'TICs';
            }

            $url = 'areas/' . $slug;

            if (isset($existingUrls[$url])) {
                continue;
            }

            $subs[] = ['title' => $name, 'url' => $url];
        }

        $item['sub'] = $subs;
        break;
    }
    unset($item);

    return $menuItems;
}
