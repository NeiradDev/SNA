<?php

namespace App\Services;

use App\Models\TareaModel;
use App\Models\UsuarioModel;
use Config\Database;

/**
 * TareaService
 *
 * Lógica de negocio central de TAREAS
 * - El Controller SOLO llama a este Service
 * - El Service valida reglas
 * - El Model ejecuta BD
 */
class TareaService
{
    private TareaModel $tareaModel;

    public function __construct(?TareaModel $tareaModel = null)
    {
        $this->tareaModel = $tareaModel ?? new TareaModel();
    }

    // ==================================================
    // CREAR TAREA
    // ==================================================
    public function createTaskFromPost(array $post, int $asignadoPor): array
    {
        $titulo        = trim((string)($post['titulo'] ?? ''));
        $descripcion   = trim((string)($post['descripcion'] ?? ''));
        $idPrioridad   = (int)($post['id_prioridad'] ?? 0);
        $idEstado      = (int)($post['id_estado_tarea'] ?? 0);
        $idAreaPost    = (int)($post['id_area'] ?? 0);
        $asignadoA     = (int)($post['asignado_a'] ?? 0);
        $fechaInicio   = trim((string)($post['fecha_inicio'] ?? ''));
        $fechaFin      = trim((string)($post['fecha_fin'] ?? ''));

        if ($titulo === '') {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }

        if ($idPrioridad <= 0 || $idEstado <= 0 || $asignadoA <= 0) {
            return ['success' => false, 'error' => 'Completa todos los campos obligatorios.'];
        }

        if ($fechaInicio === '') {
            return ['success' => false, 'error' => 'La fecha de inicio es obligatoria.'];
        }

        // Determinar área real
        $areaAsignador = (int)(session()->get('id_area') ?? 0);
        $esGerencia    = ($areaAsignador === 1);
        $idArea        = $esGerencia ? $idAreaPost : $areaAsignador;

        if ($idArea <= 0) {
            return ['success' => false, 'error' => 'Área inválida.'];
        }

        // Validar usuario pertenece al área
        $usuarios = $this->tareaModel->getAssignableUsersByArea($idArea);
        $ids      = array_map(fn($u) => (int)$u['id_user'], $usuarios);

        if (!in_array($asignadoA, $ids, true)) {
            return ['success' => false, 'error' => 'El usuario no pertenece al área seleccionada.'];
        }

        try {
            $this->tareaModel->insert([
                'titulo'          => $titulo,
                'descripcion'     => $descripcion ?: null,
                'id_prioridad'    => $idPrioridad,
                'id_estado_tarea' => $idEstado,
                'fecha_inicio'    => $fechaInicio,
                'fecha_fin'       => $fechaFin ?: null,
                'id_area'         => $idArea,
                'asignado_a'      => $asignadoA,
                'asignado_por'    => $asignadoPor,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error guardando la tarea.'];
        }

        return ['success' => true];
    }

    // ==================================================
    // EVENTOS PARA FULLCALENDAR
    // ==================================================
    public function getCalendarEvents(int $userId, string $scope): array
    {
        $db = Database::connect();

        $where = ($scope === 'assigned')
            ? 't.asignado_por = ?'
            : 't.asignado_a = ?';

        $sql = <<<SQL
SELECT
    t.id_tarea,
    t.titulo,
    t.descripcion,
    t.id_estado_tarea,
    p.nombre_prioridad,
    e.nombre_estado,
    t.fecha_inicio,
    t.fecha_fin,
    t.asignado_a,
    t.asignado_por,
    ua.nombres || ' ' || ua.apellidos AS asignado_a_nombre,
    up.nombres || ' ' || up.apellidos AS asignado_por_nombre,
    ar.nombre_area
FROM public.tareas t
JOIN public.prioridad p    ON p.id_prioridad = t.id_prioridad
JOIN public.estado_tarea e ON e.id_estado_tarea = t.id_estado_tarea
LEFT JOIN public."USER" ua ON ua.id_user = t.asignado_a
LEFT JOIN public."USER" up ON up.id_user = t.asignado_por
LEFT JOIN public.area ar   ON ar.id_area = t.id_area
WHERE {$where}
ORDER BY t.fecha_inicio DESC
SQL;

        $rows = $db->query($sql, [$userId])->getResultArray();
        $events = [];

        foreach ($rows as $r) {
            $events[] = [
                'id'    => (string)$r['id_tarea'],
                'title' => $r['titulo'],
                'start' => $r['fecha_inicio'],
                'end'   => $r['fecha_fin'],
                'extendedProps' => [
                    'descripcion'         => $r['descripcion'] ?? '',
                    'prioridad'           => $r['nombre_prioridad'],
                    'estado'              => $r['nombre_estado'],
                    'id_estado_tarea'     => (int)$r['id_estado_tarea'],
                    'area'                => $r['nombre_area'] ?? '',
                    'asignado_a'          => (int)$r['asignado_a'],
                    'asignado_a_nombre'   => $r['asignado_a_nombre'] ?? '',
                    'asignado_por_nombre' => $r['asignado_por_nombre'] ?? '',
                ],
            ];
        }

        return $events;
    }

    // ==================================================
    // MARCAR COMO COMPLETADA
    // ==================================================
    public function markDone(int $taskId, int $currentUserId): array
    {
        $task = $this->tareaModel->find($taskId);

        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        if ((int)$task['asignado_a'] !== $currentUserId) {
            return ['success' => false, 'error' => 'No autorizado.'];
        }

        try {
            $this->tareaModel->update($taskId, [
                'id_estado_tarea' => 3,
                'completed_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error actualizando la tarea.'];
        }

        return ['success' => true];
    }

    // ==================================================
    // ACTUALIZAR / REASIGNAR TAREA
    // ==================================================
    public function updateTask(
        int $idTarea,
        array $data,
        int $currentUserId,
        int $currentUserAreaId
    ): array {
        $task = $this->tareaModel->find($idTarea);

        if (!$task) {
            return ['success' => false, 'error' => 'Tarea no encontrada.'];
        }

        $esAsignado = ((int)$task['asignado_a'] === $currentUserId);
        $esCreador  = ((int)$task['asignado_por'] === $currentUserId);
        $esGerencia = ($currentUserAreaId === 1);

        if (!$esAsignado && !$esCreador && !$esGerencia) {
            return ['success' => false, 'error' => 'No autorizado.'];
        }

        $update = [];

        if (isset($data['id_estado_tarea'])) {
            $update['id_estado_tarea'] = (int)$data['id_estado_tarea'];
        }

        if (isset($data['fecha_inicio'])) {
            $update['fecha_inicio'] = $data['fecha_inicio'];
        }

        if (array_key_exists('fecha_fin', $data)) {
            $update['fecha_fin'] = $data['fecha_fin'] ?: null;
        }

        if (isset($data['asignado_a']) && ($esCreador || $esGerencia)) {
            $usuarios = $this->tareaModel->getAssignableUsersByArea((int)$task['id_area']);
            $ids      = array_map(fn($u) => (int)$u['id_user'], $usuarios);

            if (!in_array((int)$data['asignado_a'], $ids, true)) {
                return ['success' => false, 'error' => 'Usuario no válido para el área.'];
            }

            $update['asignado_a'] = (int)$data['asignado_a'];
        }

        if (empty($update)) {
            return ['success' => false, 'error' => 'No hay cambios para guardar.'];
        }

        try {
            $this->tareaModel->update($idTarea, $update);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error actualizando la tarea.'];
        }

        return ['success' => true];
    }

    // ==================================================
    // CONTEXTO ORGANIZACIONAL / CATÁLOGOS
    // ==================================================
    public function getDivisionByUser(int $idUser): ?array
    {
        return $this->tareaModel->getDivisionByUser($idUser);
    }

    public function getAreasByDivision(int $idDivision): array
    {
        return $this->tareaModel->getAreasByDivision($idDivision);
    }

    public function getUsersByArea(int $idArea): array
    {
        return $this->tareaModel->getAssignableUsersByArea($idArea);
        return $this->tareaModel->getUsersByArea($areaId);
    }

    public function getPrioridades(): array
    {
        return $this->tareaModel->getPrioridades();
    }

    public function getEstadosTarea(): array
    {
        return $this->tareaModel->getEstadosTarea();
    }
    // ==================================================
// GESTIÓN DE TAREAS (MIS TAREAS + ASIGNADAS POR MÍ)
// ==================================================
// ==================================================
// TAREAS PARA GESTIÓN (MIS TAREAS / ASIGNADAS POR MÍ)
// ==================================================
public function getTasksForManagement(int $idUser): array
{
    $db = Database::connect();

    $sql = <<<SQL
SELECT
    t.id_tarea,
    t.titulo,
    t.fecha_inicio,
    t.fecha_fin,
    t.asignado_a,
    t.asignado_por,

    p.nombre_prioridad,
    e.nombre_estado,
    ar.nombre_area,

    ua.nombres || ' ' || ua.apellidos AS asignado_a_nombre
FROM public.tareas t
JOIN public.prioridad p    ON p.id_prioridad = t.id_prioridad
JOIN public.estado_tarea e ON e.id_estado_tarea = t.id_estado_tarea
LEFT JOIN public.area ar   ON ar.id_area = t.id_area
LEFT JOIN public."USER" ua ON ua.id_user = t.asignado_a
WHERE t.asignado_a = ?
   OR t.asignado_por = ?
ORDER BY t.fecha_inicio DESC
SQL;

    $rows = $db->query($sql, [$idUser, $idUser])->getResultArray();

    $misTareas       = [];
    $tareasAsignadas = [];

    foreach ($rows as $r) {
        if ((int)$r['asignado_a'] === $idUser) {
            $misTareas[] = $r;
        }

        if ((int)$r['asignado_por'] === $idUser) {
            $tareasAsignadas[] = $r;
        }
    }

    return [
        'misTareas'       => $misTareas,
        'tareasAsignadas'=> $tareasAsignadas,
    ];
}

// ==================================================
// OBTENER TAREA PARA EDICIÓN
// ==================================================
public function getTaskById(int $idTarea, int $currentUserId): ?array
{
    $task = $this->tareaModel->find($idTarea);

    if (!$task) {
        return null;
    }

    $esAsignado = ((int)$task['asignado_a'] === $currentUserId);
    $esCreador  = ((int)$task['asignado_por'] === $currentUserId);
    $esGerencia = ((int)session()->get('id_area') === 1);

    if (!$esAsignado && !$esCreador && !$esGerencia) {
        return null;
    }

    return $task;
}

// ==================================================
// OBTENER TAREA PARA EDICIÓN
// ==================================================
public function getTaskForEdit(int $idTarea, int $currentUserId): ?array
{
    $task = $this->tareaModel->find($idTarea);

    if (!$task) {
        return null;
    }

    $esAsignado = ((int)$task['asignado_a'] === $currentUserId);
    $esCreador  = ((int)$task['asignado_por'] === $currentUserId);
    $esGerencia = ((int)session()->get('id_area') === 1);

    if (!$esAsignado && !$esCreador && !$esGerencia) {
        return null;
    }

    return $task;
}
// ==================================================
// PORCENTAJE DE SATISFACCIÓN (EN VIVO)
// ==================================================

public function getSatisfaccionActual(int $idUser): array
{
    $db = Database::connect();

    // ===== rango jueves → miércoles =====
    $hoy = new \DateTime();
    $diaSemana = (int)$hoy->format('N'); // 1=lunes ... 7=domingo

    $inicio = clone $hoy;
    $inicio->modify('-' . (($diaSemana >= 4 ? $diaSemana - 4 : $diaSemana + 3)) . ' days');
    $inicio->setTime(0, 0, 0);

    $fin = clone $inicio;
    $fin->modify('+6 days')->setTime(23, 59, 59);

    // ===== QUERY REAL =====
    $sql = <<<SQL
SELECT
    SUM(CASE WHEN id_estado_tarea = 3 THEN 1 ELSE 0 END) AS realizadas,
    SUM(CASE WHEN id_estado_tarea = 4 THEN 1 ELSE 0 END) AS no_realizadas
FROM public.tareas
WHERE
    (asignado_a = :u: OR asignado_por = :u:)
    AND fecha_inicio BETWEEN :ini: AND :fin:
    AND id_estado_tarea IN (3,4)
SQL;

    $row = $db->query($sql, [
        'u'   => $idUser,
        'ini' => $inicio->format('Y-m-d H:i:s'),
        'fin' => $fin->format('Y-m-d H:i:s'),
    ])->getRowArray();

    $realizadas   = (int)($row['realizadas'] ?? 0);
    $noRealizadas = (int)($row['no_realizadas'] ?? 0);
    $total        = $realizadas + $noRealizadas;

    $porcentaje = $total > 0
        ? round(($realizadas / $total) * 100, 2)
        : 0;

    return [
        'porcentaje'    => $porcentaje,
        'realizadas'    => $realizadas,
        'no_realizadas' => $noRealizadas,
        'inicio'        => $inicio->format('Y-m-d'),
        'fin'           => $fin->format('Y-m-d'),
    ];
}
public function plan()
{
    if (!session()->get('logged_in')) {
        return redirect()->to(site_url('login'));
    }

    $idUser = (int) session()->get('id_user');

    // MODELOS
    $usuarioModel = new UsuarioModel();

    // SERVICES
    $tareaService = new TareaService();

    return view('reporte/plan', [
        // 👇 perfil viene del UsuarioModel (CORRECTO)
        'perfil'       => $usuarioModel->getUserProfileForPlan($idUser),

        // 👇 porcentaje viene del TareaService (CORRECTO)
        'satisfaccion' => $tareaService->getSatisfaccionActual($idUser),

        'old'     => session()->getFlashdata('old') ?? [],
        'error'   => session()->getFlashdata('error'),
        'success' => session()->getFlashdata('success'),
    ]);
}

}
