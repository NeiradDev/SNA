<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanBatallaModel;
use App\Models\UsuarioModel;

class PlanBatallaService
{
    // Modelo que guarda el snapshot semanal en historico
    private PlanBatallaModel $planModel;

    // Modelo para traer el perfil del usuario con joins
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->planModel    = new PlanBatallaModel();
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Obtiene el perfil del usuario con joins
     */
    public function getUserProfile(int $idUser): ?array
    {
        return $this->usuarioModel->getUserProfileForPlan($idUser);
    }

    /**
     * Guarda el Plan de Batalla en public.historico
     */
    public function savePlanToHistorico(int $idUser, array $post): array
    {
        $profile = $this->getUserProfile($idUser);

        if (!$profile) {
            return [
                'ok'     => false,
                'errors' => ['general' => 'No se encontró el perfil del usuario.'],
            ];
        }

        $data = $this->buildHistoricoData($profile, $post);

        if (empty($data['semana'])) {
            return [
                'ok'     => false,
                'errors' => ['general' => 'No se pudo calcular la semana de corte.'],
            ];
        }

        $ok = $this->planModel->upsertHistorico($data);

       if (!$ok) {
    return [
        'ok'     => false,
        'errors' => [
            'general' => 'Error guardando el Plan de Batalla en la base de datos.',
        ],
    ];
}


        return ['ok' => true];
    }

    /**
     * Regla de negocio:
     * Semana SIEMPRE corta el miércoles
     * Jueves a martes pertenecen al miércoles anterior
     */
    private function currentWeekStart(): string
    {
        $tz = new \DateTimeZone('America/Guayaquil');
        $dt = new \DateTime('now', $tz);
        $dt->setTime(0, 0, 0);

        // ISO-8601: 1=Lunes ... 7=Domingo
        $dayOfWeek = (int) $dt->format('N');

        if ($dayOfWeek >= 4) {
            // Jueves (4) a Domingo (7)
            $dt->modify('wednesday this week');
        } else {
            // Lunes (1) a Miércoles (3)
            $dt->modify('wednesday last week');
        }

        return $dt->format('Y-m-d');
    }

    /**
     * Convierte preguntas del POST a JSON limpio
     */
    private function buildPreguntasJson(array $post): string
    {
        $rows  = [];
        $items = $post['preguntas'] ?? [];

        foreach ($items as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));

            if ($q === '' && $a === '') {
                continue;
            }

            $rows[] = [
                'q' => $q,
                'a' => $a,
            ];
        }

        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Arma el payload alineado a la tabla public.historico
     */
    private function buildHistoricoData(array $profile, array $post): array
    {
        return [
            // =========================
            // CLAVE DE CORTE SEMANAL
            // =========================
            'semana' => $this->currentWeekStart(),

            // Campo libre
            'estado' => null,

            // =========================
            // RELACIONES
            // =========================
            'id_user'       => (int) ($profile['id_user'] ?? 0),
            'id_agencias'   => !empty($profile['id_agencias']) ? (int) $profile['id_agencias'] : null,
            'id_division'   => !empty($profile['id_division']) ? (int) $profile['id_division'] : null,
            'id_area'       => !empty($profile['id_area']) ? (int) $profile['id_area'] : null,
            'id_cargo'      => !empty($profile['id_cargo']) ? (int) $profile['id_cargo'] : null,
            'id_supervisor' => !empty($profile['id_supervisor']) ? (int) $profile['id_supervisor'] : null,

            // =========================
            // SNAPSHOT PERSONAL
            // =========================
            'nombres'   => (string) ($profile['nombres'] ?? ''),
            'apellidos' => (string) ($profile['apellidos'] ?? ''),
            'cedula'    => (string) ($profile['cedula'] ?? ''),

            // =========================
            // SNAPSHOT ORGANIZACIONAL
            // =========================
            'area_nombre'    => (string) ($profile['nombre_area'] ?? 'N/D'),
            'cargo_nombre'   => (string) ($profile['nombre_cargo'] ?? 'N/D'),
            'jefe_inmediato' => (string) ($profile['supervisor_nombre'] ?? 'Sin superior'),

            // =========================
            // DATOS DEL FORMULARIO
            // =========================
            'condicion'     => (string) ($post['condicion'] ?? ''),
            'preguntas_json'=> $this->buildPreguntasJson($post),
            'satisfaccion'  => (float) ($post['satisfaccion'] ?? 0), 
        ];
    }
    
}
