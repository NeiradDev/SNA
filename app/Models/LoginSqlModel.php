<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class LoginSqlModel extends Model
{
    public function findByCedula(string $cedula): ?array
    {
        $db = Database::connect();

        $sql = '
            SELECT
                u.id_user,
                u.nombres,
                u.apellidos,
                u.cedula,
                u.password,

                -- Convertimos boolean a 1/0 como ya lo venías usando
                (CASE WHEN u.activo THEN 1 ELSE 0 END) AS activo_int,

                -- ✅ En la base nueva NO existe u.id_area.
                -- El área viene desde el cargo (si el cargo es de área).
                c.id_area AS id_area,

                u.id_agencias,
                u.id_cargo,

                c.nombre_cargo
            FROM public."USER" u
            LEFT JOIN public.cargo c
                ON c.id_cargo = u.id_cargo
            WHERE u.cedula = ?
            LIMIT 1
        ';

        $user = $db->query($sql, [$cedula])->getRowArray();
        return $user ?: null;
    }
}
