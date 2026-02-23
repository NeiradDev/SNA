<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * OrgChartModel (PostgreSQL)
 *
 * ✅ Solo lectura (organigrama).
 * ✅ Ahora incluye: correo + telefono (para mostrar en el nodo).
 */
class OrgChartModel extends Model
{
    protected $table      = 'public."USER"';
    protected $primaryKey = 'id_user';
    protected $useTimestamps = false;

    public function getDivisionName(int $divisionId): ?string
    {
        $sql = 'SELECT nombre_division FROM public.division WHERE id_division = ? LIMIT 1';
        $row = $this->db->query($sql, [(int)$divisionId])->getRowArray();
        $name = $row['nombre_division'] ?? null;

        return $name ? (string)$name : null;
    }

    /**
     * getDivisionWithBoss()
     * - Trae división + id_jf_division + datos del jefe (nombre, correo, telefono)
     */
    public function getDivisionWithBoss(int $divisionId): ?array
    {
        $sql = <<<'SQL'
SELECT
    d.id_division,
    d.nombre_division,
    d.id_jf_division,

    u.id_user AS jefe_division_id,
    CASE
        WHEN u.id_user IS NULL THEN NULL
        ELSE (u.nombres || ' ' || u.apellidos)
    END AS jefe_division_nombre,
    u.correo   AS jefe_division_correo,
    u.telefono AS jefe_division_telefono

FROM public.division d
LEFT JOIN public."USER" u ON u.id_user = d.id_jf_division
WHERE d.id_division = ?
LIMIT 1
SQL;

        return $this->db->query($sql, [(int)$divisionId])->getRowArray() ?: null;
    }

    /**
     * getGerenciaUserByCargo()
     * - Root: primer usuario activo con id_cargo = 6 (por defecto)
     * - Incluye correo y telefono
     */
    public function getGerenciaUserByCargo(int $cargoGerenciaId = 16): ?array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.correo,
    u.telefono,
    u.id_cargo,
    c.nombre_cargo
FROM public."USER" u
LEFT JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND u.id_cargo = ?
ORDER BY u.id_user ASC
LIMIT 1
SQL;

        return $this->db->query($sql, [(int)$cargoGerenciaId])->getRowArray() ?: null;
    }

    /**
     * getUsersByDivisionLevel()
     * - Usuarios cuyo cargo pertenece directamente a la división: cargo.id_division = divisionId
     * - Incluye correo y telefono
     */
    public function getUsersByDivisionLevel(int $divisionId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.correo,
    u.telefono,
    u.id_cargo,
    c.nombre_cargo
FROM public."USER" u
JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND c.id_division = ?
ORDER BY (u.nombres || ' ' || u.apellidos) ASC
SQL;

        return $this->db->query($sql, [(int)$divisionId])->getResultArray();
    }

    /**
     * getAreasWithBossByDivision()
     * - Áreas de una división + su jefe (id_jf_area) y datos del jefe (nombre, correo, telefono)
     */
    public function getAreasWithBossByDivision(int $divisionId): array
    {
        $sql = <<<'SQL'
SELECT
    a.id_area,
    a.nombre_area,
    a.id_division,
    a.id_jf_area,

    u.id_user AS jefe_area_id,
    CASE
        WHEN u.id_user IS NULL THEN NULL
        ELSE (u.nombres || ' ' || u.apellidos)
    END AS jefe_area_nombre,
    u.correo   AS jefe_area_correo,
    u.telefono AS jefe_area_telefono

FROM public.area a
LEFT JOIN public."USER" u ON u.id_user = a.id_jf_area
WHERE a.id_division = ?
ORDER BY a.nombre_area ASC
SQL;

        return $this->db->query($sql, [(int)$divisionId])->getResultArray();
    }

    /**
     * getUsersByArea()
     * - Usuarios cuyo cargo pertenece al área: cargo.id_area = areaId
     * - Incluye correo y telefono
     */
    public function getUsersByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.correo,
    u.telefono,
    u.id_cargo,
    c.nombre_cargo
FROM public."USER" u
JOIN public.cargo c ON c.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND c.id_area = ?
ORDER BY (u.nombres || ' ' || u.apellidos) ASC
SQL;

        return $this->db->query($sql, [(int)$areaId])->getResultArray();
    }
}