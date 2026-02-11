<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * PlanBatallaModel
 * Persistencia del Plan de Batalla semanal en tabla historico.
 */
class PlanBatallaModel extends Model
{
    // Config del modelo (tabla real donde se guarda el plan)
    protected $table      = 'public.historico';
    protected $primaryKey = 'id_historico';

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
        'preguntas_json',
        'created_at',
    ];

    // Guarda el último error de BD para depuración/flashdata
    protected ?array $lastDbError = null;

    // Devuelve el último error de BD capturado
    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    // Setea el último error de BD capturado
    private function setDbError(?array $err): void
    {
        $this->lastDbError = $err;
    }

    /**
     * upsertWeekly()
     * Inserta/actualiza el registro semanal por usuario (uq: semana + id_user).
     */
    public function upsertWeekly(array $data): bool
    {
        $semana = (string) ($data['semana'] ?? '');
        $idUser = (int) ($data['id_user'] ?? 0);

        if ($semana === '' || $idUser <= 0) {
            $this->setDbError(['message' => 'Datos incompletos: semana/id_user']);
            return false;
        }

        $sql = <<<'SQL'
INSERT INTO public.historico
(
    semana, estado, id_user,
    id_agencias, id_division, id_area, id_cargo, id_supervisor,
    nombres, apellidos, cedula,
    area_nombre, cargo_nombre, jefe_inmediato,
    condicion, preguntas_json
)
VALUES
(
    ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?,
    ?, ?, ?,
    ?, ?
)
ON CONFLICT (semana, id_user)
DO UPDATE SET
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
    created_at     = now()
SQL;

        $params = [
            $data['semana'],
            $data['estado'] ?? null,
            $data['id_user'],

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
            $data['preguntas_json'] ?? null,
        ];

        try {
            $ok = (bool) $this->db->query($sql, $params);
            $this->setDbError($ok ? null : $this->db->error());
            return $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }
}
