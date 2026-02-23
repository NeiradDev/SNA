<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

/**
 * TareaModel
 *
 * MODELO ALINEADO A TU ESQUEMA PostgreSQL
 * Tabla principal: public.tareas
 *
 * Centraliza:
 * - Contexto organizacional (división / áreas / usuarios)
 * - Catálogos (prioridad / estado)
 * - Helpers de jefaturas (division.id_jf_division, area.id_jf_area)
 * - Obtención de usuarios asignables (área / división)
 *
 * IMPORTANTE:
 * - Se mantiene compatibilidad con métodos existentes.
 * - Se evita duplicar métodos para no causar "Cannot redeclare method".
 * - ✅ Se agregan allowedFields para soportar el flujo de revisión:
 *   edit_count, review_requested_state, review_requested_at, approved_by, approved_at.
 */
class TareaModel extends Model
{
    // --------------------------------------------------
    // Configuración base del Model
    // --------------------------------------------------

    /**
     * Tabla real en Postgres.
     * - Usamos esquema public para evitar conflictos.
     */
    protected $table      = 'public.tareas';

    /**
     * PK de la tabla tareas.
     */
    protected $primaryKey = 'id_tarea';

    /**
     * ✅ Campos permitidos para INSERT/UPDATE usando Model->insert/update.
     *
     * OJO:
     * - Si un campo NO está aquí, CI4 lo ignora silenciosamente en update().
     * - Por eso, para que el flujo "En revisión" funcione, deben estar:
     *   edit_count, review_requested_state, review_requested_at, approved_by, approved_at.
     */
    protected $allowedFields = [
        // ---------------------------
        // Campos base de la tarea
        // ---------------------------
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
        'created_at',

        // ---------------------------
        // BATCH (multi-asignación)
        // ---------------------------
        'batch_uid',

        // ---------------------------
        // ✅ FLUJO DE REVISIÓN / CONTROL
        // ---------------------------
        'edit_count',
        'review_requested_state',
        'review_requested_at',
        'approved_by',
        'approved_at',
    ];

    /**
     * Manejo timestamps manual (tu lógica ya usa created_at directo).
     */
    protected $useTimestamps = false;

    // ==================================================
    // CONTEXTO ORGANIZACIONAL
    // ==================================================

    /**
     * getDivisionByUser()
     *
     * Regla de prevalencia:
     * 1) Si es JEFE DE DIVISIÓN (division.id_jf_division = id_user) -> ESA división manda.
     * 2) Si no, intentar por MULTI-CARGO: usuario_cargo -> cargo -> division (directo) o area->division
     * 3) Fallback legacy: USER.id_cargo -> cargo -> area -> division (o cargo.id_division)
     *
     * Retorna:
     * - ['id_division' => int, 'nombre_division' => string] o null
     */
    public function getDivisionByUser(int $idUser): ?array
    {
        // --------------------------------------------------
        // 1) PREVALENCIA: JEFE DE DIVISIÓN
        // --------------------------------------------------
        $bossDivision = $this->db->table('public.division d')
            ->select('d.id_division, d.nombre_division')
            ->where('d.id_jf_division', $idUser)
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!empty($bossDivision)) {
            return $bossDivision;
        }

        // --------------------------------------------------
        // 2) MULTI-CARGO: usuario_cargo -> cargo -> division / area->division
        // --------------------------------------------------
        $sqlMultiCargo = <<<SQL
SELECT
    COALESCE(d_direct.id_division, d_area.id_division)         AS id_division,
    COALESCE(d_direct.nombre_division, d_area.nombre_division) AS nombre_division
FROM public.usuario_cargo uc
JOIN public.cargo c ON c.id_cargo = uc.id_cargo
LEFT JOIN public.division d_direct ON d_direct.id_division = c.id_division
LEFT JOIN public.area a            ON a.id_area           = c.id_area
LEFT JOIN public.division d_area   ON d_area.id_division  = a.id_division
WHERE uc.id_user = ?
ORDER BY
    CASE
        WHEN c.id_division IS NOT NULL THEN 0
        ELSE 1
    END
LIMIT 1
SQL;

        try {
            $row = $this->db->query($sqlMultiCargo, [$idUser])->getRowArray();
            if (!empty($row) && !empty($row['id_division'])) {
                return $row;
            }
        } catch (\Throwable $e) {
            // Si usuario_cargo no existe o falla, no rompemos: seguimos al legacy.
        }

        // --------------------------------------------------
        // 3) FALLBACK LEGACY: USER.id_cargo -> cargo -> (area->division o cargo->division)
        // --------------------------------------------------
        return $this->db->table('public."USER" u')
            ->select('d.id_division, d.nombre_division')
            ->join('public.cargo c', 'c.id_cargo = u.id_cargo', 'left')
            ->join('public.area a',  'a.id_area = c.id_area', 'left')
            ->join('public.division d', 'd.id_division = COALESCE(a.id_division, c.id_division)', 'left', false)
            ->where('u.id_user', $idUser)
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    /**
     * getAreasByDivision()
     *
     * Retorna áreas de una división:
     * - id_area, nombre_area
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

    // ==================================================
    // CATÁLOGOS
    // ==================================================

    /**
     * Catálogo prioridades:
     * - id_prioridad, nombre_prioridad
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
     * Catálogo estados:
     * - id_estado_tarea, nombre_estado
     */
    public function getEstadosTarea(): array
    {
        return $this->db->table('public.estado_tarea')
            ->select('id_estado_tarea, nombre_estado')
            ->orderBy('id_estado_tarea', 'ASC')
            ->get()
            ->getResultArray();
    }

    // ==================================================
    // SATISFACCIÓN
    // ==================================================

    public function calcularSatisfaccionSemana(int $idUser, string $inicio, string $fin): array
    {
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
            'realizadas'    => (int) ($row['realizadas'] ?? 0),
            'no_realizadas' => (int) ($row['no_realizadas'] ?? 0),
        ];
    }

    // ==================================================
    // USUARIOS ASIGNABLES
    // ==================================================

    public function getUsersByArea(int $areaId): array
    {
        // ---------------------------
        // 1) MULTI-CARGO
        // ---------------------------
        $sqlMulti = <<<SQL
SELECT DISTINCT
    u.id_user,
    TRIM(u.nombres || ' ' || u.apellidos) AS label
FROM public."USER" u
JOIN public.usuario_cargo uc ON uc.id_user = u.id_user
JOIN public.cargo c          ON c.id_cargo = uc.id_cargo
WHERE c.id_area = ?
  AND u.activo = true
ORDER BY label
SQL;

        try {
            $rows = $this->db->query($sqlMulti, [$areaId])->getResultArray();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // si falla, seguimos al legacy
        }

        // ---------------------------
        // 2) LEGACY: USER.id_cargo
        // ---------------------------
        $sqlLegacy = <<<SQL
SELECT
    u.id_user,
    TRIM(u.nombres || ' ' || u.apellidos) AS label
FROM public."USER" u
JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE c.id_area = ?
  AND u.activo = true
ORDER BY u.nombres, u.apellidos
SQL;

        return $this->db->query($sqlLegacy, [$areaId])->getResultArray();
    }

    public function getUsersByDivision(int $idDivision): array
    {
        // ---------------------------
        // 1) MULTI-CARGO
        // ---------------------------
        $sql = <<<SQL
SELECT DISTINCT
    u.id_user,
    TRIM(u.nombres || ' ' || u.apellidos) AS label
FROM public."USER" u
JOIN public.usuario_cargo uc ON uc.id_user = u.id_user
JOIN public.cargo c          ON c.id_cargo = uc.id_cargo
LEFT JOIN public.area a      ON a.id_area  = c.id_area
WHERE u.activo = true
  AND (
        c.id_division = ?
        OR a.id_division = ?
      )
ORDER BY label
SQL;

        try {
            $rows = $this->db->query($sql, [$idDivision, $idDivision])->getResultArray();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // si falla, hacemos fallback legacy
        }

        // ---------------------------
        // 2) LEGACY
        // ---------------------------
        $sqlLegacy = <<<SQL
SELECT DISTINCT
    u.id_user,
    TRIM(u.nombres || ' ' || u.apellidos) AS label
FROM public."USER" u
JOIN public.cargo c     ON c.id_cargo = u.id_cargo
LEFT JOIN public.area a ON a.id_area  = c.id_area
WHERE u.activo = true
  AND a.id_division = ?
ORDER BY label
SQL;

        return $this->db->query($sqlLegacy, [$idDivision])->getResultArray();
    }

    // ==================================================
    // REGLAS DE ASIGNACIÓN (JEFATURAS)
    // ==================================================

    public function getChiefDivisionId(int $userId): ?int
    {
        $row = $this->db->table('public.division')
            ->select('id_division')
            ->where('id_jf_division', $userId)
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row ? (int) $row['id_division'] : null;
    }

    public function getChiefAreaId(int $userId): ?int
    {
        $row = $this->db->table('public.area')
            ->select('id_area')
            ->where('id_jf_area', $userId)
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row ? (int) $row['id_area'] : null;
    }

    // ALIAS compatibilidad
    public function isDivisionBoss(int $idUser): ?int
    {
        return $this->getChiefDivisionId($idUser);
    }

    public function isAreaBoss(int $idUser): ?int
    {
        return $this->getChiefAreaId($idUser);
    }

    public function getAreaById(int $areaId): ?array
    {
        return $this->db->table('public.area')
            ->select('id_area, nombre_area, id_division')
            ->where('id_area', $areaId)
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    public function getAreaIdByUser(int $userId): ?int
    {
        // ---------------------------
        // 1) MULTI-CARGO
        // ---------------------------
        $sqlMulti = <<<SQL
SELECT c.id_area
FROM public.usuario_cargo uc
JOIN public.cargo c ON c.id_cargo = uc.id_cargo
WHERE uc.id_user = ?
  AND c.id_area IS NOT NULL
LIMIT 1
SQL;

        try {
            $row = $this->db->query($sqlMulti, [$userId])->getRowArray();
            if (!empty($row) && !empty($row['id_area'])) {
                return (int) $row['id_area'];
            }
        } catch (\Throwable $e) {
            // si falla, seguimos al legacy
        }

        // ---------------------------
        // 2) LEGACY
        // ---------------------------
        $sqlLegacy = <<<SQL
SELECT c.id_area
FROM public."USER" u
JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE u.id_user = ?
LIMIT 1
SQL;

        $row = $this->db->query($sqlLegacy, [$userId])->getRowArray();

        return (!empty($row) && !empty($row['id_area']))
            ? (int) $row['id_area']
            : null;
    }

    public function isAreaInDivision(int $areaId, int $divisionId): bool
    {
        $row = $this->db->table('public.area')
            ->select('id_area')
            ->where('id_area', $areaId)
            ->where('id_division', $divisionId)
            ->limit(1)
            ->get()
            ->getRowArray();

        return !empty($row);
    }

    // Alias compatibilidad
    public function areaBelongsToDivision(int $idArea, int $idDivision): bool
    {
        return $this->isAreaInDivision($idArea, $idDivision);
    }

    /**
     * Usuarios para combos:
     * - Usuario actual primero
     * - Si es el mismo usuario: "(Autoasignación)"
     */
    public function getUsersByAreaForDropdown(int $areaId, int $currentUserId): array
    {
        // ---------------------------
        // 1) MULTI-CARGO
        // ---------------------------
        $sqlMulti = <<<SQL
SELECT DISTINCT
    u.id_user,
    (
        TRIM(u.nombres || ' ' || u.apellidos)
        || CASE WHEN u.id_user = ? THEN ' (Autoasignación)' ELSE '' END
    ) AS label
FROM public."USER" u
JOIN public.usuario_cargo uc ON uc.id_user = u.id_user
JOIN public.cargo c          ON c.id_cargo = uc.id_cargo
WHERE c.id_area = ?
  AND u.activo = true
ORDER BY
    CASE WHEN u.id_user = ? THEN 0 ELSE 1 END,
    label
SQL;

        try {
            $rows = $this->db->query($sqlMulti, [$currentUserId, $areaId, $currentUserId])->getResultArray();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // si falla, seguimos legacy
        }

        // ---------------------------
        // 2) LEGACY
        // ---------------------------
        $sqlLegacy = <<<SQL
SELECT
    u.id_user,
    (
        TRIM(u.nombres || ' ' || u.apellidos)
        || CASE WHEN u.id_user = ? THEN ' (Autoasignación)' ELSE '' END
    ) AS label
FROM public."USER" u
JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE c.id_area = ?
  AND u.activo = true
ORDER BY
    CASE WHEN u.id_user = ? THEN 0 ELSE 1 END,
    u.nombres, u.apellidos
SQL;

        return $this->db->query($sqlLegacy, [$currentUserId, $areaId, $currentUserId])->getResultArray();
    }

 public function getChiefDivisionInfo(int $userId): ?array
    {
        $db = Database::connect();

        $row = $db->query(
            'SELECT id_division, nombre_division
             FROM public.division
             WHERE id_jf_division = ?
             LIMIT 1',
            [$userId]
        )->getRowArray();

        return $row ?: null;
    }

    /**
     * Devuelve todas las áreas donde el usuario es jefe (id_jf_area).
     * Puede ser 0, 1 o varias áreas.
     */
    public function getChiefAreasInfo(int $userId): array
    {
        $db = Database::connect();

        return $db->query(
            'SELECT id_area, nombre_area, id_division
             FROM public.area
             WHERE id_jf_area = ?
             ORDER BY nombre_area ASC',
            [$userId]
        )->getResultArray();
    }

    /**
     * Devuelve IDs de usuarios que pertenecen a una división.
     * ✅ Incluye usuarios con cargo por área (area.id_division = X)
     * ✅ Incluye usuarios con cargo directo por división (cargo.id_division = X)
     */
    public function getUserIdsByDivision(int $divisionId): array
    {
        $db = Database::connect();

        $rows = $db->query(
            'SELECT DISTINCT u.id_user
             FROM public."USER" u
             JOIN public.cargo c ON c.id_cargo = u.id_cargo
             LEFT JOIN public.area a ON a.id_area = c.id_area
             WHERE u.activo = true
               AND (
                    a.id_division = ?
                    OR c.id_division = ?
               )',
            [$divisionId, $divisionId]
        )->getResultArray();

        $ids = [];
        foreach ($rows as $r) {
            $id = (int)($r['id_user'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Devuelve IDs de usuarios que pertenecen a un área (por cargo.id_area).
     */
    public function getUserIdsByArea(int $areaId): array
    {
        $db = Database::connect();

        $rows = $db->query(
            'SELECT DISTINCT u.id_user
             FROM public."USER" u
             JOIN public.cargo c ON c.id_cargo = u.id_cargo
             WHERE u.activo = true
               AND c.id_area = ?',
            [$areaId]
        )->getResultArray();

        $ids = [];
        foreach ($rows as $r) {
            $id = (int)($r['id_user'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }


}

