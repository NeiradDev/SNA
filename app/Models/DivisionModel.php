<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * DivisionModel
 *
 * Tabla: public.division
 * Campos esperados:
 * - id_division (PK)
 * - nombre_division
 * - id_jf_division (FK a public."USER".id_user)  ✅ según tu SQL base del chat
 */
class DivisionModel extends Model
{
    // =========================================================
    // CONFIG BASE
    // =========================================================
    protected $table      = 'public.division';
    protected $primaryKey = 'id_division';

    protected $allowedFields = [
        'nombre_division',
        'id_jf_division', // ✅ lo incluimos porque tu schema ya lo usa
    ];

    protected $useTimestamps = false;

    // =========================================================
    // LISTADO SIMPLE (lo mantengo por compatibilidad)
    // =========================================================
    public function listAll(): array
    {
        // ✅ No cambia tu funcionalidad anterior
        return $this->orderBy('nombre_division', 'ASC')->findAll();
    }

    // =========================================================
    // ✅ NUEVO: Divisiones + Jefe (nombre) por id_jf_division
    // =========================================================
    public function listWithBossName(): array
    {
        // ✅ Nota:
        // - LEFT JOIN para que si no tiene jefe, igual salga la división
        // - u.activo opcional: no filtro, porque si el jefe está inactivo igual
        //   puede ser el jefe "registrado" en la tabla. Si quieres filtrar luego, lo hacemos.
        $sql = <<<'SQL'
SELECT
    d.id_division,
    d.nombre_division,
    d.id_jf_division,
    CASE
        WHEN u.id_user IS NULL THEN 'No asignado'
        ELSE (u.nombres || ' ' || u.apellidos)
    END AS jefe_division_nombre
FROM public.division d
LEFT JOIN public."USER" u ON u.id_user = d.id_jf_division
ORDER BY d.nombre_division ASC
SQL;

        return $this->db->query($sql)->getResultArray();
    }

    // =========================================================
    // ✅ NUEVO: Áreas por división (solo las correspondientes)
    // =========================================================
    public function listAreasByDivision(int $divisionId): array
    {
        // ✅ Seguridad simple
        if ($divisionId <= 0) return [];

        $sql = <<<'SQL'
SELECT
    a.id_area,
    a.nombre_area
FROM public.area a
WHERE a.id_division = ?
ORDER BY a.nombre_area ASC
SQL;

        return $this->db->query($sql, [$divisionId])->getResultArray();
    }
}
