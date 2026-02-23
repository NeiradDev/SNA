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

    // =========================================================
    // Inserta o actualiza el snapshot semanal por (semana, id_user)
    // =========================================================
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
            (float) ($data['satisfaccion'] ?? 0),
        ];

        try {
            return (bool) $this->db->query($sql, $params);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================
    // Fallback: últimas semanas de satisfacción del usuario
    // =========================================================
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

    // =========================================================
    // NUEVO: Satisfacción semanal del USUARIO por rango
    // =========================================================
    public function getSatisfaccionUsuarioPorRango(int $idUser, string $from, string $to): array
    {
        $sql = <<<SQL
SELECT
    semana,
    satisfaccion
FROM public.historico
WHERE id_user = ?
  AND semana BETWEEN ? AND ?
  AND satisfaccion IS NOT NULL
ORDER BY semana ASC
SQL;

        return $this->db->query($sql, [$idUser, $from, $to])->getResultArray();
    }

    // =========================================================
    // NUEVO: Traer TODAS las divisiones (dropdown gerencia)
    // =========================================================
    public function getAllDivisions(): array
    {
        $sql = <<<SQL
SELECT id_division, nombre_division
FROM public.division
ORDER BY nombre_division ASC
SQL;

        return $this->db->query($sql)->getResultArray();
    }

    // =========================================================
    // NUEVO: Satisfacción semanal PROMEDIO por DIVISIÓN (rango)
    // =========================================================
    public function getSatisfaccionDivisionesPorRango(string $from, string $to): array
    {
        $sql = <<<SQL
SELECT
    h.id_division,
    d.nombre_division,
    h.semana,
    ROUND(AVG(h.satisfaccion)::numeric, 2) AS satisfaccion_avg
FROM public.historico h
JOIN public.division d ON d.id_division = h.id_division
WHERE h.id_division IS NOT NULL
  AND h.semana BETWEEN ? AND ?
  AND h.satisfaccion IS NOT NULL
GROUP BY h.id_division, d.nombre_division, h.semana
ORDER BY d.nombre_division ASC, h.semana ASC
SQL;

        return $this->db->query($sql, [$from, $to])->getResultArray();
    }

    // =========================================================
    // ✅ (Se mantiene) Nombre de división del usuario dentro del rango
    // =========================================================
    public function getDivisionNameByUserRange(int $idUser, string $from, string $to): string
    {
        $sql = <<<SQL
SELECT d.nombre_division
FROM public.historico h
JOIN public.division d ON d.id_division = h.id_division
WHERE h.id_user = ?
  AND h.id_division IS NOT NULL
  AND h.semana BETWEEN ? AND ?
ORDER BY h.semana DESC
LIMIT 1
SQL;

        $row = $this->db->query($sql, [$idUser, $from, $to])->getRowArray();
        return trim((string)($row['nombre_division'] ?? ''));
    }

    // =========================================================
    // ✅ (Se mantiene) Nombre de división del usuario (último histórico)
    // =========================================================
    public function getDivisionNameByUserLatest(int $idUser): string
    {
        $sql = <<<SQL
SELECT d.nombre_division
FROM public.historico h
JOIN public.division d ON d.id_division = h.id_division
WHERE h.id_user = ?
  AND h.id_division IS NOT NULL
ORDER BY h.semana DESC
LIMIT 1
SQL;

        $row = $this->db->query($sql, [$idUser])->getRowArray();
        return trim((string)($row['nombre_division'] ?? ''));
    }

    // =========================================================
    // ✅ NUEVO (EL QUE NECESITAS AHORA):
    // Match directo con tabla division: id_division -> nombre_division
    // =========================================================
    public function getDivisionNameById(int $divisionId): string
    {
        // Si no seleccionaron división (0 = Todas), devolvemos vacío
        if ($divisionId <= 0) {
            return '';
        }

        $sql = <<<SQL
SELECT nombre_division
FROM public.division
WHERE id_division = ?
LIMIT 1
SQL;

        try {
            $row = $this->db->query($sql, [$divisionId])->getRowArray();
            return trim((string)($row['nombre_division'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }
}

// =====================================================================
// OJO: Esta clase es otra cosa (cuotas/objetivos)
// =====================================================================
class PlanBatallaExtraModel extends Model
{
    protected $table = '';
    protected $allowedFields = ['id_user','semana','descripcion'];

    public function saveCuota(int $idUser, string $semana, string $desc): bool
    {
        return (bool) $this->db->query(
            "INSERT INTO public.cuotas_semana (id_user, semana, descripcion)
             VALUES (?, ?, ?)
             ON CONFLICT (id_user, semana)
             DO UPDATE SET descripcion = EXCLUDED.descripcion",
            [$idUser, $semana, $desc]
        );
    }

    public function saveObjetivo(int $idUser, string $semana, string $desc): bool
    {
        return (bool) $this->db->query(
            "INSERT INTO public.objetivos_semana (id_user, semana, descripcion)
             VALUES (?, ?, ?)
             ON CONFLICT (id_user, semana)
             DO UPDATE SET descripcion = EXCLUDED.descripcion",
            [$idUser, $semana, $desc]
        );
    }

    public function getCuota(int $idUser, string $semana): ?array
    {
        return $this->db->query(
            "SELECT * FROM public.cuotas_semana WHERE id_user = ? AND semana = ?",
            [$idUser, $semana]
        )->getRowArray();
    }

    public function getObjetivo(int $idUser, string $semana): ?array
    {
        return $this->db->query(
            "SELECT * FROM public.objetivos_semana WHERE id_user = ? AND semana = ?",
            [$idUser, $semana]
        )->getRowArray();
    }
}