<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tabla: public.area
 * Campos: id_area, nombre_area, id_division, id_jf_area
 */
class AreaModel extends Model
{
    protected $table      = 'public.area';
    protected $primaryKey = 'id_area';

    protected $allowedFields = ['nombre_area', 'id_division', 'id_jf_area'];
    protected $useTimestamps = false;

    public function listWithDivision(): array
    {
        $sql = '
            SELECT
                a.id_area,
                a.nombre_area,
                a.id_division,
                d.nombre_division,
                a.id_jf_area
            FROM public.area a
            JOIN public.division d ON d.id_division = a.id_division
            ORDER BY d.nombre_division ASC, a.nombre_area ASC
        ';
        return $this->db->query($sql)->getResultArray();
    }

    public function findWithDivision(int $id): ?array
    {
        $sql = '
            SELECT
                a.*,
                d.nombre_division
            FROM public.area a
            JOIN public.division d ON d.id_division = a.id_division
            WHERE a.id_area = ?
            LIMIT 1
        ';
        return $this->db->query($sql, [$id])->getRowArray() ?: null;
    }
}
