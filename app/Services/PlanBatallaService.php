<?php

namespace App\Services;

use App\Models\PlanBatallaModel;
use App\Models\UsuarioModel;

class PlanBatallaService
{
    private PlanBatallaModel $planModel;
    private UsuarioModel $usuarioModel;

    public function __construct(?PlanBatallaModel $planModel = null, ?UsuarioModel $usuarioModel = null)
    {
        $this->planModel = $planModel ?? new PlanBatallaModel();
        $this->usuarioModel = $usuarioModel ?? new UsuarioModel();
    }

    public function createFromUserSessionAndPost(array $post): array
    {
        $idUser = (int) (session()->get('id_user') ?? 0);
        if ($idUser <= 0) {
            return ['success' => false, 'error' => 'Sesión inválida.'];
        }

        // Perfil completo desde BD (área/cargo/supervisor)
        $perfil = $this->usuarioModel->getUserProfileForPlan($idUser);
        if (!$perfil) {
            return ['success' => false, 'error' => 'No se pudo cargar el perfil del usuario.'];
        }

        $cond = trim((string)($post['condicion'] ?? ''));
        $allowed = ['Afluencia', 'Normal', 'Emergencia', 'Peligro'];
        if (!in_array($cond, $allowed, true)) {
            return ['success' => false, 'error' => 'Selecciona una condición válida.'];
        }

        // Preguntas obligatorias
        $preguntas = $post['preguntas'] ?? null;
        if (!is_array($preguntas) || count($preguntas) === 0) {
            return ['success' => false, 'error' => 'Debes responder todas las preguntas.'];
        }

        $clean = [];
        $i = 1;
        foreach ($preguntas as $p) {
            $q = trim((string)($p['q'] ?? ''));
            $a = trim((string)($p['a'] ?? ''));

            if ($q === '') {
                return ['success' => false, 'error' => 'Error interno: falta el texto de una pregunta.'];
            }
            if ($a === '') {
                return ['success' => false, 'error' => "La respuesta #{$i} es obligatoria."];
            }

            $clean[] = ['q' => $q, 'a' => $a];
            $i++;
        }

        $areaNombre = trim((string)($perfil['nombre_area'] ?? ''));
        $cargoNombre = trim((string)($perfil['nombre_cargo'] ?? ''));
        $jefeNombre  = trim((string)($perfil['supervisor_nombre'] ?? ''));

        $data = [
            'id_user'        => (int)$perfil['id_user'],
            'cedula'         => (string)$perfil['cedula'],
            'nombres'        => (string)$perfil['nombres'],
            'apellidos'      => (string)$perfil['apellidos'],

            'id_area'        => $perfil['id_area'] !== null ? (int)$perfil['id_area'] : null,
            'area_nombre'    => $areaNombre !== '' ? $areaNombre : 'N/D',

            'id_cargo'       => $perfil['id_cargo'] !== null ? (int)$perfil['id_cargo'] : null,
            'cargo_nombre'   => $cargoNombre !== '' ? $cargoNombre : 'N/D',

            // Jefe inmediato por id_supervisor (si no hay => N/D)
            'id_supervisor'  => $perfil['id_supervisor'] !== null ? (int)$perfil['id_supervisor'] : null,
            'jefe_inmediato' => $jefeNombre !== '' ? $jefeNombre : 'N/D',

            'condicion'      => $cond,
            'preguntas_json' => json_encode($clean, JSON_UNESCAPED_UNICODE),
        ];

        try {
            $this->planModel->insert($data);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error guardando en BD: ' . $e->getMessage()];
        }

        return ['success' => true];
    }
}
