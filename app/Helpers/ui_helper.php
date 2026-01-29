<?php

if (!function_exists('badge_estado')) {
    function badge_estado($activo): string
    {
        return !empty($activo)
            ? '<span class="badge bg-success-subtle text-success px-3">Activo</span>'
            : '<span class="badge bg-danger-subtle text-danger px-3">Inactivo</span>';
    }
}

if (!function_exists('btn_outline_edit')) {
    function btn_outline_edit(string $url, string $label = 'Editar'): string
    {
        return '<a href="' . esc($url) . '" class="btn btn-sm btn-outline-dark border-0"><i class="bi bi-pencil-square"></i> ' . esc($label) . '</a>';
    }
}

if (!function_exists('form_error_bs')) {
    /**
     * Renderiza el error de validación en formato Bootstrap 5
     * @param array $errors Array de errores (session('errors') o validator->getErrors())
     * @param string $field Campo
     */
    function form_error_bs(?array $errors, string $field): string
    {
        if (!$errors || empty($errors[$field])) return '';
        return '<div class="invalid-feedback d-block">'. esc($errors[$field]) .'</div>';
    }
}