<?php

if (!function_exists('is_active_url')) {
    /**
     * Retorna true si la URL actual comienza con el prefijo dado.
     * Ej: is_active_url('/usuarios') será true en /usuarios, /usuarios/editar/1, etc.
     */
    function is_active_url(string $prefix): bool
    {
        $current = '/' . trim(uri_string(), '/'); // "" -> "/"
        $prefix  = '/' . trim($prefix, '/');
        return $prefix === '/' ? ($current === '/') : str_starts_with($current, $prefix);
    }
}

if (!function_exists('menu_has_active_child')) {
    /**
     * Revisa si algún hijo (sub ítem) está activo para abrir el collapse.
     *
     * @param array $sub
     */
    function menu_has_active_child(array $sub): bool
    {
        foreach ($sub as $child) {
            if (!empty($child['url']) && is_active_url($child['url'])) {
                return true;
            }
        }
        return false;
    }
}