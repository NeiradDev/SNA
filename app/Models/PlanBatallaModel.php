<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PlanBatallaModel extends Model
{
    // Tabla destino del snapshot semanal
    protected $table      = 'public.historico';
    protected $primaryKey = 'id_historico';

    // Campos permitidos en historico
    protected $allowedFields = [
        'semana',
        'estado',
        'id_user',
        'id_agencias',
        'id_division',
        'id_area',
        'id_cargo',
        'id_supervisor',
        'nombres',
        'apellidos',
        'cedula',
        'area_nombre',
        'cargo_nombre',
        'jefe_inmediato',
        'condicion',
        'satisfaccion',
        'preguntas_json',
        'created_at',
    ];

    // Inserta o actualiza el snapshot semanal por (semana, id_user)
    public function upsertHistorico(array $data): bool
    {
        $sql = <<<'SQL'
INSERT INTO public.historico
(
    semana,
    estado,
    id_user,
    id_agencias,
    id_division,
    id_area,
    id_cargo,
    id_supervisor,
    nombres,
    apellidos,
    cedula,
    area_nombre,
    cargo_nombre,
    jefe_inmediato,
    condicion,
    preguntas_json,
    satisfaccion
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)
ON CONFLICT (semana, id_user) DO UPDATE SET
    estado         = EXCLUDED.estado,
    id_agencias    = EXCLUDED.id_agencias,
    id_division    = EXCLUDED.id_division,
    id_area        = EXCLUDED.id_area,
    id_cargo       = EXCLUDED.id_cargo,
    id_supervisor  = EXCLUDED.id_supervisor,
    nombres        = EXCLUDED.nombres,
    apellidos      = EXCLUDED.apellidos,
    cedula         = EXCLUDED.cedula,
    area_nombre    = EXCLUDED.area_nombre,
    cargo_nombre   = EXCLUDED.cargo_nombre,
    jefe_inmediato = EXCLUDED.jefe_inmediato,
    condicion      = EXCLUDED.condicion,
    preguntas_json = EXCLUDED.preguntas_json,
    satisfaccion   = EXCLUDED.satisfaccion
SQL;


        $params = [
            $data['semana'] ?? null,
            $data['estado'] ?? null,
            $data['id_user'] ?? null,
            $data['id_agencias'] ?? null,
            $data['id_division'] ?? null,
            $data['id_area'] ?? null,
            $data['id_cargo'] ?? null,
            $data['id_supervisor'] ?? null,
            $data['nombres'] ?? '',
            $data['apellidos'] ?? '',
            $data['cedula'] ?? '',
            $data['area_nombre'] ?? null,
            $data['cargo_nombre'] ?? null,
            $data['jefe_inmediato'] ?? '',
            $data['condicion'] ?? '',
            $data['preguntas_json'] ?? '[]',
            (float) ($data['satisfaccion'] ?? 0)
        ];

        try {
            return (bool) $this->db->query($sql, $params);
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function getUltimasSemanasSatisfaccion(int $idUser, int $limit = 3): array
{
    $sql = <<<SQL
SELECT 
    semana,
    satisfaccion
FROM public.historico
WHERE id_user = ?
  AND satisfaccion IS NOT NULL
ORDER BY semana DESC
LIMIT ?
SQL;

    return $this->db->query($sql, [$idUser, $limit])->getResultArray();
}
public function completado()
{
    $db = \Config\Database::connect();

    $sql = "
        SELECT u.*
        FROM \"USER\" u
        WHERE u.activo = true
        AND NOT EXISTS (
            SELECT 1
            FROM historico h
            WHERE h.id_user = u.id_user
            AND h.semana = date_trunc('week', CURRENT_DATE) + interval '2 days'
        )
    ";

    return $db->query($sql)->getResult();
}
}
