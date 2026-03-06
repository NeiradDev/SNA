<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * =========================================================
 * Service: HistoricoPlanService
 * =========================================================
 * OBJETIVO:
 * - Resolver el "scope" (division/area/self) con prioridad:
 *      1) Jefe de División (division)
 *      2) Jefe de Área (area)
 *      3) Usuario normal (self)
 * - Listar semanas disponibles según el scope
 * - Listar usuarios disponibles según el scope
 * - Obtener el histórico (public.historico) por semana + usuario
 * - Obtener tareas de esa semana para el usuario consultado
 *
 * NOTA:
 * - semana (public.historico.semana) se maneja como "semana de negocio"
 *   basada en el miércoles de corte (tu regla guardada).
 * =========================================================
 */
class HistoricoPlanService
{
    /**
     * @var BaseConnection
     */
    private BaseConnection $db;

    public function __construct()
    {
        // Conexión DB CI4
        $this->db = Database::connect();
    }

    /**
     * =========================================================
     * Resolver alcance (scope) del usuario logueado
     * =========================================================
     * PRIORIDAD:
     * - Si el usuario es id_jf_division en alguna division -> division
     * - Si no, si es id_jf_area en alguna area -> area
     * - Si no -> self
     */
    public function resolveScope(int $currentUserId): array
    {
        // 1) ¿Es jefe de división? (PREVALECE)
        $division = $this->db->table('division')
            ->select('id_division, nombre_division')
            ->where('id_jf_division', $currentUserId)
            ->get()
            ->getRowArray();

        if (!empty($division)) {
            return [
                'scope'       => 'division',
                'id_division' => (int) $division['id_division'],
                'division'    => $division['nombre_division'] ?? '',
                'id_area'     => null,
                'area'        => null,
            ];
        }

        // 2) ¿Es jefe de área?
        $area = $this->db->table('area a')
            ->select('a.id_area, a.nombre_area, a.id_division, d.nombre_division')
            ->join('division d', 'd.id_division = a.id_division')
            ->where('a.id_jf_area', $currentUserId)
            ->get()
            ->getRowArray();

        if (!empty($area)) {
            return [
                'scope'       => 'area',
                'id_division' => (int) ($area['id_division'] ?? 0),
                'division'    => $area['nombre_division'] ?? '',
                'id_area'     => (int) $area['id_area'],
                'area'        => $area['nombre_area'] ?? '',
            ];
        }

        // 3) Usuario normal
        return [
            'scope'       => 'self',
            'id_division' => null,
            'division'    => null,
            'id_area'     => null,
            'area'        => null,
        ];
    }

    /**
     * =========================================================
     * Lista de usuarios accesibles según scope
     * =========================================================
     * Estrategia:
     * - Usamos historico como snapshot (tiene id_division, id_area por usuario)
     * - Evita complicación de inferir area por cargo/usuario_cargo
     *
     * ✅ FIX:
     * - NO usar "DISTINCT ..." dentro de select() porque CI lo escapa mal.
     * - Usar ->distinct() en su lugar.
     * =========================================================
     */
    public function getAccessibleUsers(array $scopeInfo, int $currentUserId): array
    {
        $builder = $this->db->table('historico h')
            ->distinct() // ✅ DISTINCT correcto (CI4)
            ->select('h.id_user, h.nombres, h.apellidos, h.cedula, h.id_division, h.id_area, h.area_nombre')
            ->orderBy('h.apellidos', 'ASC')
            ->orderBy('h.nombres', 'ASC');

        $scope = (string) ($scopeInfo['scope'] ?? 'self');

        if ($scope === 'division') {
            $builder->where('h.id_division', (int) ($scopeInfo['id_division'] ?? 0));
        } elseif ($scope === 'area') {
            $builder->where('h.id_area', (int) ($scopeInfo['id_area'] ?? 0));
        } else {
            $builder->where('h.id_user', $currentUserId);
        }

        $rows = $builder->get()->getResultArray();

        // Normaliza label para combo
        foreach ($rows as &$r) {
            $full = trim(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? ''));
            $ced  = (string) ($r['cedula'] ?? '');
            $r['label'] = $full . ($ced !== '' ? " ({$ced})" : '');
        }
        unset($r);

        return $rows;
    }

    /**
     * =========================================================
     * Semanas accesibles según scope y usuario seleccionado
     * =========================================================
     * ✅ FIX:
     * - Igual que arriba: usar ->distinct()
     * =========================================================
     */
    public function getAccessibleWeeks(array $scopeInfo, int $currentUserId, ?int $selectedUserId = null): array
    {
        $builder = $this->db->table('historico h')
            ->distinct() // ✅ DISTINCT correcto (CI4)
            ->select('h.semana')
            ->orderBy('h.semana', 'DESC');

        $scope = (string) ($scopeInfo['scope'] ?? 'self');

        if ($scope === 'division') {
            $builder->where('h.id_division', (int) ($scopeInfo['id_division'] ?? 0));
        } elseif ($scope === 'area') {
            $builder->where('h.id_area', (int) ($scopeInfo['id_area'] ?? 0));
        } else {
            $builder->where('h.id_user', $currentUserId);
        }

        // Si ya eligieron usuario, limitamos semanas a ese usuario
        if (!empty($selectedUserId)) {
            $builder->where('h.id_user', (int) $selectedUserId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * =========================================================
     * Obtener un registro de historico (semana + usuario)
     * =========================================================
     */
    public function getHistoricoRow(string $semana, int $userId): ?array
{
    // Traemos el histórico + nombre de división + nombre de área
    $row = $this->db->table('historico h')
        ->select('h.*, d.nombre_division, a.nombre_area')
        ->join('division d', 'd.id_division = h.id_division', 'left')
        ->join('area a', 'a.id_area = h.id_area', 'left')
        ->where('h.semana', $semana)
        ->where('h.id_user', $userId)
        ->get()
        ->getRowArray();

    if (!$row) {
        return null;
    }

    // Asegurar nombres (por si están NULL)
    $row['division_nombre'] = (string)($row['nombre_division'] ?? '');
    $row['area_nombre']     = (string)($row['nombre_area'] ?? ($row['area_nombre'] ?? ''));

    // Decodificar preguntas_json si viene
    $row['preguntas'] = [];

    $raw = (string)($row['preguntas_json'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $row['preguntas'] = $decoded;
        }
    }

    return $row;
}

    /**
     * =========================================================
     * Rango de fechas por semana de negocio
     * =========================================================
     * - semana = miércoles de corte (DATE)
     * - rango = semana 00:00:00 -> semana + 6 días 23:59:59
     */
    public function getWeekRange(string $semana): array
    {
        $start = date('Y-m-d 00:00:00', strtotime($semana));
        $end   = date('Y-m-d 23:59:59', strtotime($semana . ' +6 days'));

        return [$start, $end];
    }

    /**
     * =========================================================
     * Obtener tareas de la semana del usuario consultado
     * =========================================================
     * - "Tareas del usuario" = asignado_a = userId
     * - "Órdenes que asignó"  = asignado_por = userId AND asignado_a != userId
     *
     * También trae joins para nombre de estado y prioridad.
     * =========================================================
     */
    public function getTasksForWeek(string $semana, int $userId): array
    {
        [$start, $end] = $this->getWeekRange($semana);

        // -----------------------------
        // A) Tareas asignadas AL usuario
        // -----------------------------
        $assignedToMe = $this->db->table('tareas t')
            ->select('t.*, p.nombre_prioridad, e.nombre_estado, a.nombre_area')
            ->join('prioridad p', 'p.id_prioridad = t.id_prioridad')
            ->join('estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea')
            ->join('area a', 'a.id_area = t.id_area')
            ->where('t.asignado_a', $userId)
            ->groupStart()
                ->where('t.fecha_inicio >=', $start)
                ->where('t.fecha_inicio <=', $end)
            ->groupEnd()
            ->orderBy('t.fecha_inicio', 'ASC')
            ->get()
            ->getResultArray();

        // ----------------------------------------
        // B) Órdenes asignadas POR el usuario (juniors)
        // ----------------------------------------
        $assignedByMe = $this->db->table('tareas t')
            ->select('t.*, p.nombre_prioridad, e.nombre_estado, a.nombre_area, u.nombres AS asignado_a_nombres, u.apellidos AS asignado_a_apellidos')
            ->join('prioridad p', 'p.id_prioridad = t.id_prioridad')
            ->join('estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea')
            ->join('area a', 'a.id_area = t.id_area')
            ->join('"USER" u', 'u.id_user = t.asignado_a', 'left')
            ->where('t.asignado_por', $userId)
            ->where('t.asignado_a !=', $userId)
            ->groupStart()
                ->where('t.fecha_inicio >=', $start)
                ->where('t.fecha_inicio <=', $end)
            ->groupEnd()
            ->orderBy('t.fecha_inicio', 'ASC')
            ->get()
            ->getResultArray();

        // -----------------------------
        // C) Clasificación estilo Plan
        // -----------------------------
        $urgentes   = [];
        $pendientes = [];
        $ordenesMi  = $assignedToMe;

        foreach ($assignedToMe as $t) {
            // "Urgente": por nombre o por id 1
            $prioName = strtolower((string) ($t['nombre_prioridad'] ?? ''));
            $isUrg = ((int) ($t['id_prioridad'] ?? 0) === 1) || str_contains($prioName, 'urg');

            if ($isUrg) {
                $urgentes[] = $t;
            }

            // "Pendiente": si no está completada o no suena a completado
            $estadoName = strtolower((string) ($t['nombre_estado'] ?? ''));
            $isDone = !empty($t['completed_at']) || str_contains($estadoName, 'complet') || str_contains($estadoName, 'realiz');

            if (!$isDone) {
                $pendientes[] = $t;
            }
        }

        return [
            'start'          => $start,
            'end'            => $end,
            'urgentes'       => $urgentes,
            'pendientes'     => $pendientes,
            'ordenesMi'      => $ordenesMi,
            'ordenesJuniors' => $assignedByMe,
        ];
    }
}