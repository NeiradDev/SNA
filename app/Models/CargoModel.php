<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tabla: public.cargo
 * Campos: id_cargo, nombre_cargo, id_area NULL, id_division NULL
 * Regla: XOR (solo uno de los dos)
 */
class CargoModel extends Model
{
    protected $table      = 'public.cargo';
    protected $primaryKey = 'id_cargo';

    protected $allowedFields = ['nombre_cargo', 'id_area', 'id_division'];
    protected $useTimestamps = false;

    public function listWithScope(): array
{
    $sql = '
        SELECT
            c.id_cargo,
            c.nombre_cargo,
            c.id_area,
            a.nombre_area,
            c.id_division,

            -- ✅ División resuelta:
            -- Si cargo.id_division existe -> esa
            -- Si no, toma la división del área
            d.id_division,
            d.nombre_division,

            CASE
                WHEN c.id_area IS NOT NULL THEN \'AREA\'
                WHEN c.id_division IS NOT NULL THEN \'DIVISION\'
                ELSE \'N/A\'
            END AS scope

        FROM public.cargo c
        LEFT JOIN public.area a
            ON a.id_area = c.id_area

        -- ✅ aquí está la clave:
        LEFT JOIN public.division d
            ON d.id_division = COALESCE(c.id_division, a.id_division)

        ORDER BY
          scope ASC,
          COALESCE(d.nombre_division, \'\') ASC,
          COALESCE(a.nombre_area, \'\') ASC,
          c.nombre_cargo ASC
    ';

    return $this->db->query($sql)->getResultArray();
}


    public function findWithScope(int $id): ?array
{
    $sql = '
        SELECT
            c.*,
            a.nombre_area,
            d.nombre_division

        FROM public.cargo c
        LEFT JOIN public.area a
            ON a.id_area = c.id_area

        LEFT JOIN public.division d
            ON d.id_division = COALESCE(c.id_division, a.id_division)

        WHERE c.id_cargo = ?
        LIMIT 1
    ';

    return $this->db->query($sql, [$id])->getRowArray() ?: null;
}

}
