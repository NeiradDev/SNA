<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database; 

/**
 * TareaModel
 *
 * MODELO 100% ALINEADO AL ESQUEMA SQL
 * Tabla: public.tareas
 *
 * Centraliza:
 * - Contexto organizacional (división / áreas / usuarios)
 * - Reglas de asignación
 */
class TareaModel extends Model
{
    // --------------------------------------------------
    // Configuración base
    // --------------------------------------------------
    protected $table      = 'public.tareas';
    protected $primaryKey = 'id_tarea';

    protected $allowedFields = [
        'titulo',
        'descripcion',
        'id_prioridad',
        'id_estado_tarea',
        'fecha_inicio',
        'fecha_fin',
        'completed_at',
        'id_area',
        'asignado_a',
        'asignado_por',
        'tipo_actividad',
        'created_at'
    ];

    protected $useTimestamps = false;

    // ==================================================
    // CONTEXTO ORGANIZACIONAL
    // ==================================================

    /**
     * Obtiene la división a la que pertenece un usuario
     * Flujo REAL:
     * USER → cargo → area → division
     */
    public function getDivisionByUser(int $idUser): ?array
    {
        return $this->db->table('public."USER" u')
            ->select('d.id_division, d.nombre_division')
            ->join('public.cargo c', 'c.id_cargo = u.id_cargo', 'left')
            ->join('public.area a', 'a.id_area = c.id_area', 'left')
            ->join('public.division d', 'd.id_division = a.id_division', 'left')
            ->where('u.id_user', $idUser)
            ->get()
            ->getRowArray();
    }

    /**
     * Obtiene áreas de una división
     */
    public function getAreasByDivision(int $idDivision): array
    {
        return $this->db->table('public.area')
            ->select('id_area, nombre_area')
            ->where('id_division', $idDivision)
            ->orderBy('nombre_area', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Usuarios activos asignables de un área
     */
  
    public function getPrioridades(): array
{
    return $this->db->table('public.prioridad')
        ->select('id_prioridad, nombre_prioridad')
        ->orderBy('id_prioridad', 'ASC')
        ->get()
        ->getResultArray();
}

/**
 * Estados de tarea
 */
public function getEstadosTarea(): array
{
    return $this->db->table('public.estado_tarea')
        ->select('id_estado_tarea, nombre_estado')
        ->orderBy('id_estado_tarea', 'ASC')
        ->get()
        ->getResultArray();
}

public function calcularSatisfaccionSemana(
    int $idUser,
    string $inicio,
    string $fin
): array {
    $db = Database::connect();

    $sql = <<<SQL
SELECT
    SUM(CASE WHEN id_estado_tarea = 3 THEN 1 ELSE 0 END) AS realizadas,
    SUM(CASE WHEN id_estado_tarea = 4 THEN 1 ELSE 0 END) AS no_realizadas
FROM public.tareas
WHERE
(
    asignado_a = ?
    OR asignado_por = ?
)
AND id_estado_tarea IN (3,4)
AND (
    fecha_inicio BETWEEN ? AND ?
    OR completed_at BETWEEN ? AND ?
)
SQL;

    $row = $db->query($sql, [
        $idUser,
        $idUser,
        $inicio,
        $fin,
        $inicio,
        $fin,
    ])->getRowArray();

    return [
        'realizadas'    => (int)($row['realizadas'] ?? 0),
        'no_realizadas' => (int)($row['no_realizadas'] ?? 0),
    ];
}
public function getUsersByArea(int $areaId): array
{
    $sql = "
        SELECT 
            u.id_user,
            TRIM(u.nombres || ' ' || u.apellidos) AS label
        FROM public.\"USER\" u
        INNER JOIN public.cargo c ON c.id_cargo = u.id_cargo
        WHERE c.id_area = ?
          AND u.activo = true
        ORDER BY u.nombres, u.apellidos
    ";

    return $this->db->query($sql, [$areaId])->getResultArray();
}


}