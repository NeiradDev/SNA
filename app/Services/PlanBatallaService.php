<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanBatallaModel;
use App\Models\UsuarioModel;
use Config\Database;

/**
 * Service: PlanBatallaService
 *
 * ✅ Semana de negocio: JUEVES → MIÉRCOLES
 *
 * CLAVE:
 * - En historico, el campo "semana" representa el "miércoles de corte" (fin de la semana)
 * - Ajuste solicitado:
 *   ✅ Si HOY es MIÉRCOLES, "semana" debe ser ESTE miércoles (hoy),
 *      NO el miércoles anterior.
 */
class PlanBatallaService
{
    private PlanBatallaModel $planModel;
    private UsuarioModel $usuarioModel;
    private $db;

    public function __construct()
    {
        $this->planModel    = new PlanBatallaModel();
        $this->usuarioModel = new UsuarioModel();
        $this->db           = Database::connect();
    }

    /* =====================================================
       PERFIL
    ===================================================== */
    public function getUserProfile(int $idUser): ?array
    {
        return $this->usuarioModel->getUserProfileForPlan($idUser);
    }

    /* =====================================================
       GUARDAR PLAN (HISTORICO + EXTRAS)
    ===================================================== */
    public function savePlanToHistorico(int $idUser, array $post): array
    {
        $profile = $this->getUserProfile($idUser);

        if (!$profile) {
            return [
                'ok' => false,
                'errors' => ['general' => 'No se encontró el perfil del usuario.'],
            ];
        }

        $data = $this->buildHistoricoData($profile, $post);

        if (empty($data['semana'])) {
            return [
                'ok' => false,
                'errors' => ['general' => 'No se pudo calcular la semana.'],
            ];
        }

        // 1) Guardar historico (UPSERT)
        $ok = $this->planModel->upsertHistorico($data);

        if (!$ok) {
            return [
                'ok' => false,
                'errors' => ['general' => 'Error guardando historico.'],
            ];
        }

        // 2) Guardar extras semanales (si aplica)
        $this->saveExtrasSemana($idUser, $post);

        return ['ok' => true];
    }

    /* =====================================================
       ✅ MIÉRCOLES DE CORTE (FIN DE SEMANA)
       Semana de negocio: JUEVES → MIÉRCOLES
       Regla:
       - Lunes/Martes/Miércoles => el corte es "este miércoles"
       - Jueves/Viernes/Sábado/Domingo => el corte es "próximo miércoles"
    ===================================================== */
    private function currentWeekStart(): string
    {
        $tz = new \DateTimeZone('America/Guayaquil');
        $dt = new \DateTime('now', $tz);
        $dt->setTime(0, 0, 0);

        // 1=Lunes ... 7=Domingo
        $dayOfWeek = (int) $dt->format('N');

        if ($dayOfWeek <= 3) {
            // ✅ Lun/Mar/Mié => el corte es ESTE miércoles
            $dt->modify('wednesday this week');
        } else {
            // ✅ Jue/Vie/Sáb/Dom => el corte es el PRÓXIMO miércoles
            $dt->modify('next wednesday');
        }

        return $dt->format('Y-m-d');
    }

    /* =====================================================
       RANGO JUEVES → MIÉRCOLES (de la semana actual)
       - inicio: jueves 00:00:00
       - fin: miércoles 23:59:59
       - semana: miércoles de corte (Y-m-d)
    ===================================================== */
    public function getCurrentWeekRange(): array
    {
        $tz     = new \DateTimeZone('America/Guayaquil');
        $semana = new \DateTime($this->currentWeekStart(), $tz);

        // Inicio = jueves (6 días antes del miércoles de corte)
        $inicio = clone $semana;
        $inicio->modify('-6 days');

        // Fin visible = miércoles (el mismo día de corte)
        $fin = clone $semana;

        return [
            'inicio' => $inicio->format('Y-m-d 00:00:00'),
            'fin'    => $fin->format('Y-m-d 23:59:59'),
            'semana' => $semana->format('Y-m-d'),
        ];
    }

    /* =====================================================
       TAREAS DE LA SEMANA (MÍAS O ASIGNADAS)
    ===================================================== */
    public function getTareasSemana(int $idUser): array
    {
        $range = $this->getCurrentWeekRange();

        $sql = "
            SELECT *
            FROM public.tareas
            WHERE fecha_inicio BETWEEN :ini: AND :fin:
              AND (asignado_a = :u: OR asignado_por = :u:)
        ";

        return $this->db->query($sql, [
            'ini' => $range['inicio'],
            'fin' => $range['fin'],
            'u'   => $idUser
        ])->getResultArray();
    }

    /* =====================================================
       JUNIORS
    ===================================================== */
    public function hasJuniors(int $idUser): bool
    {
        return $this->db->table('public."USER"')
            ->where('id_supervisor', $idUser)
            ->countAllResults() > 0;
    }

    /* =====================================================
       ORDENES QUE DEBO CUMPLIR
    ===================================================== */
    public function getOrdenesParaMi(int $idUser): array
    {
        $range = $this->getCurrentWeekRange();

        return $this->db->table('public.tareas')
            ->where('asignado_a', $idUser)
            ->where('fecha_inicio >=', $range['inicio'])
            ->where('fecha_inicio <=', $range['fin'])
            ->get()
            ->getResultArray();
    }

    /* =====================================================
       ORDENES QUE DEBEN REALIZAR MIS JUNIORS
    ===================================================== */
    public function getOrdenesJuniors(int $idUser): array
    {
        $range = $this->getCurrentWeekRange();

        return $this->db->table('public.tareas')
            ->where('asignado_por', $idUser)
            ->where('asignado_a !=', $idUser)
            ->where('fecha_inicio >=', $range['inicio'])
            ->where('fecha_inicio <=', $range['fin'])
            ->get()
            ->getResultArray();
    }

    /* =====================================================
       ACTIVIDADES URGENTES (PRIORIDAD 4)
    ===================================================== */
    public function getUrgentes(int $idUser): array
    {
        $range = $this->getCurrentWeekRange();

        return $this->db->table('public.tareas')
            ->where('asignado_a', $idUser)
            ->where('id_prioridad', 4)
            ->where('fecha_inicio >=', $range['inicio'])
            ->where('fecha_inicio <=', $range['fin'])
            ->get()
            ->getResultArray();
    }

    /* =====================================================
       ACTIVIDADES PENDIENTES (NO REALIZADAS)
    ===================================================== */
    public function getPendientes(int $idUser): array
    {
        $range = $this->getCurrentWeekRange();

        return $this->db->table('public.tareas')
            ->where('asignado_a', $idUser)
            ->where('id_estado_tarea !=', 3)
            ->where('fecha_inicio >=', $range['inicio'])
            ->where('fecha_inicio <=', $range['fin'])
            ->get()
            ->getResultArray();
    }

    /* =====================================================
       EXTRAS: CUOTAS Y OBJETIVO SEMANAL
       (si tu tabla cuotas_semana existe)
    ===================================================== */
    private function saveExtrasSemana(int $idUser, array $post): void
    {
        $semanaCorte = $this->currentWeekStart();

        $cuota    = trim((string)($post['cuota_descripcion'] ?? ''));
        $objetivo = trim((string)($post['objetivo_estrategico'] ?? ''));

        $this->db->table('cuotas_semana')->replace([
            'id_user'              => $idUser,
            'semana_inicio'        => $semanaCorte,
            'descripcion'          => $cuota,
            'objetivo_estrategico' => $objetivo
        ]);
    }

    public function getExtrasSemana(int $idUser): array
    {
        $semanaCorte = $this->currentWeekStart();

        $row = $this->db->table('cuotas_semana')
            ->where('id_user', $idUser)
            ->where('semana_inicio', $semanaCorte)
            ->get()
            ->getRowArray();

        return [
            'cuota' => [
                'descripcion' => $row['descripcion'] ?? ''
            ],
            'objetivo' => [
                'descripcion' => $row['objetivo_estrategico'] ?? ''
            ]
        ];
    }

    /* =====================================================
       BUILD HISTORICO
    ===================================================== */
    private function buildPreguntasJson(array $post): string
    {
        return json_encode($post['preguntas'] ?? [], JSON_UNESCAPED_UNICODE);
    }

    private function buildHistoricoData(array $profile, array $post): array
    {
        return [
            // ✅ "semana" = miércoles de corte corregido
            'semana'         => $this->currentWeekStart(),
            'estado'         => null,

            'id_user'        => (int)($profile['id_user'] ?? 0),
            'id_agencias'    => $profile['id_agencias'] ?? null,
            'id_division'    => $profile['id_division'] ?? null,
            'id_area'        => $profile['id_area'] ?? null,
            'id_cargo'       => $profile['id_cargo'] ?? null,
            'id_supervisor'  => $profile['id_supervisor'] ?? null,

            'nombres'        => $profile['nombres'] ?? '',
            'apellidos'      => $profile['apellidos'] ?? '',
            'cedula'         => $profile['cedula'] ?? '',

            'area_nombre'    => $profile['nombre_area'] ?? '',
            'cargo_nombre'   => $profile['nombre_cargo'] ?? '',
            'jefe_inmediato' => $profile['supervisor_nombre'] ?? '',

            'condicion'      => $post['condicion'] ?? '',
            'preguntas_json' => $this->buildPreguntasJson($post),
            'satisfaccion'   => (float)($post['satisfaccion'] ?? 0),
        ];
    }
}
