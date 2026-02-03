<?php

namespace App\Services;

use App\Models\TareaModel;
use App\Models\UsuarioModel;

class TareaService
{
    private TareaModel $tareaModel;
    private UsuarioModel $usuarioModel;

    public function __construct(?TareaModel $tareaModel = null, ?UsuarioModel $usuarioModel = null)
    {
        $this->tareaModel   = $tareaModel ?? new TareaModel();
        $this->usuarioModel = $usuarioModel ?? new UsuarioModel();
    }

    public function createTaskFromPost(array $post, int $asignadoPor): array
    {
        $titulo      = trim((string)($post['titulo'] ?? ''));
        $descripcion = trim((string)($post['descripcion'] ?? ''));
        $prioridad   = trim((string)($post['prioridad'] ?? ''));
        $idAreaPost  = (int)($post['id_area'] ?? 0);
        $asignadoA   = (int)($post['asignado_a'] ?? 0);
        $inicio      = trim((string)($post['fecha_inicio'] ?? ''));
        $fin         = trim((string)($post['fecha_fin'] ?? ''));

        if ($titulo === '') return ['success' => false, 'error' => 'El título es obligatorio.'];
        if (!in_array($prioridad, ['Normal','Urgente'], true)) return ['success' => false, 'error' => 'Prioridad inválida.'];
        if ($asignadoA <= 0) return ['success' => false, 'error' => 'Selecciona un usuario.'];
        if ($inicio === '') return ['success' => false, 'error' => 'Fecha inicio es obligatoria.'];

        // ===== REGLA GERENCIA =====
        $areaAsignador = (int) (session()->get('id_area') ?? 0);
        $esGerencia = ($areaAsignador === 1);

        if (!$esGerencia) {
            // No gerencia: solo su área (ignora el post)
            $idArea = $areaAsignador;
        } else {
            // Gerencia: puede escoger cualquier área
            if ($idAreaPost <= 0) return ['success' => false, 'error' => 'Selecciona un área.'];
            $idArea = $idAreaPost;
        }

        // Validar que el usuario pertenezca al área permitida
        $users = $this->usuarioModel->getUsersByArea($idArea);
        $ids = array_map(fn($u) => (int)$u['id_user'], $users);

        if (!in_array($asignadoA, $ids, true)) {
            return ['success' => false, 'error' => 'El usuario seleccionado no pertenece al área permitida.'];
        }

        $data = [
            'titulo'        => $titulo,
            'descripcion'   => $descripcion !== '' ? $descripcion : null,
            'prioridad'     => $prioridad,
            'estado'        => 'Pendiente',
            'fecha_inicio'  => $inicio,
            'fecha_fin'     => $fin !== '' ? $fin : null,
            'id_area'       => $idArea,
            'asignado_a'    => $asignadoA,
            'asignado_por'  => $asignadoPor,
        ];

        try {
            $this->tareaModel->insert($data);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Error guardando tarea: ' . $e->getMessage()];
        }

        return ['success' => true];
    }

    /**
     * scope:
     *  - mine: tareas asignadas a mí
     *  - assigned: tareas asignadas por mí
     */
    public function getCalendarEvents(int $userId, string $scope): array
    {
        $db = \Config\Database::connect();
        $where = ($scope === 'assigned') ? 't.asignado_por = ?' : 't.asignado_a = ?';

        $sql = <<<SQL
SELECT
  t.id_tarea,
  t.titulo,
  t.descripcion,
  t.prioridad,
  t.estado,
  t.fecha_inicio,
  t.fecha_fin,
  t.asignado_a,
  t.asignado_por,
  (ua.nombres || ' ' || ua.apellidos) AS asignado_a_nombre,
  (up.nombres || ' ' || up.apellidos) AS asignado_por_nombre,
  ar.nombre_area
FROM public.tareas t
LEFT JOIN public."USER" ua ON ua.id_user = t.asignado_a
LEFT JOIN public."USER" up ON up.id_user = t.asignado_por
LEFT JOIN public.area ar   ON ar.id_area = t.id_area
WHERE {$where}
ORDER BY t.fecha_inicio DESC
SQL;

        $rows = $db->query($sql, [$userId])->getResultArray();

        $events = [];
        foreach ($rows as $r) {
            $titlePrefix = ($r['prioridad'] === 'Urgente') ? '🔥 ' : '';
            $doneSuffix  = ($r['estado'] === 'Hecha') ? ' ✅' : '';


            $events[] = [
                'id'    => (string)$r['id_tarea'],
                'title' => $titlePrefix . (string)$r['titulo'] . $doneSuffix,
                'start' => (string)$r['fecha_inicio'],
                'end'   => $r['fecha_fin'] ? (string)$r['fecha_fin'] : null,
                'extendedProps' => [
                    'descripcion'           => (string)($r['descripcion'] ?? ''),
                    'prioridad'             => (string)$r['prioridad'],
                    'estado'                => (string)$r['estado'],
                    'asignado_a'            => (int)$r['asignado_a'],
                    'asignado_por'          => (int)$r['asignado_por'],
                    'asignado_a_nombre'     => (string)($r['asignado_a_nombre'] ?? ''),
                    'asignado_por_nombre'   => (string)($r['asignado_por_nombre'] ?? ''),
                    'nombre_area'           => (string)($r['nombre_area'] ?? ''),
                ]
            ];
        }

        return $events;
    }

   public function markDone(int $taskId, int $currentUserId): array
{
    $task = $this->tareaModel->find($taskId);
    if (!$task) return ['success' => false, 'error' => 'Tarea no encontrada.'];

    // Solo el asignado puede marcar como hecha
    if ((int)$task['asignado_a'] !== $currentUserId) {
        return ['success' => false, 'error' => 'No autorizado para marcar esta tarea.'];
    }

    if (($task['estado'] ?? '') === 'Hecha') {
        return ['success' => true];
    }

    try {
        $this->tareaModel->update($taskId, [
            'estado'       => 'Hecha',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => 'Error actualizando tarea: ' . $e->getMessage()];
    }

    return ['success' => true];
}

}
