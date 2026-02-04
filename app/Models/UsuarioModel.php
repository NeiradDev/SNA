<?php
declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    // --- Tablas ---
    private const TBL_USER     = 'public."USER"';
    private const TBL_AGENCIA  = 'public.agencias';
    private const TBL_AREA     = 'public.area';
    private const TBL_DIVISION = 'public.division';
    private const TBL_CARGO    = 'public.cargo';

    // --- Último error DB ---
    protected ?array $lastDbError = null;

    // --- Cache por request ---
    private array $memoryCache = [];

    // --------------------------------------------------
    // Helpers SQL
    // --------------------------------------------------
    private function fetchAll(string $sql, array $params = []): array
    {
        return $this->db->query($sql, $params)->getResultArray();
    }

    private function fetchRow(string $sql, array $params = []): ?array
    {
        return $this->db->query($sql, $params)->getRowArray() ?: null;
    }

    private function userTable()
    {
        return $this->db->table(self::TBL_USER);
    }

    private function setDbError(?array $error): void
    {
        $this->lastDbError = $error;
    }

    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    // --------------------------------------------------
    // SQL base: usuario + joins
    // --------------------------------------------------
    private function buildUserWithJoinsSql(
        ?string $where = null,
        ?string $orderBy = null,
        ?int $limit = null
    ): string {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.cedula,
    (u.activo::int) AS activo,
    u.id_agencias,
    u.id_area,
    u.id_cargo,
    u.id_supervisor,
    ag.nombre_agencia,
    ar.nombre_area,
    ar.id_division,
    dv.nombre_division,
    ca.nombre_cargo,
    CASE
        WHEN sup.id_user IS NULL THEN NULL
        ELSE (sup.nombres || ' ' || sup.apellidos)
    END AS supervisor_nombre
FROM public."USER" u
LEFT JOIN public.agencias ag ON ag.id_agencias = u.id_agencias
LEFT JOIN public.area ar     ON ar.id_area     = u.id_area
LEFT JOIN public.division dv ON dv.id_division = ar.id_division
LEFT JOIN public.cargo ca    ON ca.id_cargo    = u.id_cargo
LEFT JOIN public."USER" sup  ON sup.id_user    = u.id_supervisor
SQL;

        if ($where)   $sql .= "\nWHERE {$where}";
        if ($orderBy) $sql .= "\nORDER BY {$orderBy}";
        if ($limit !== null) $sql .= "\nLIMIT {$limit}";

        return $sql;
    }

    // --------------------------------------------------
    // Catálogos (cache por request)
    // --------------------------------------------------
    private function cachedQuery(string $key, string $sql): array
    {
        return $this->memoryCache[$key]
            ??= $this->fetchAll($sql);
    }

    public function getAgencies(): array
    {
        return $this->cachedQuery(
            'agencies',
            'SELECT id_agencias, nombre_agencia FROM public.agencias ORDER BY nombre_agencia ASC'
        );
    }

    public function getDivision(): array
    {
        return $this->cachedQuery(
            'division',
            'SELECT id_division, nombre_division FROM public.division ORDER BY nombre_division ASC'
        );
    }

    public function getAreas(): array
    {
        return $this->cachedQuery(
            'areas',
            'SELECT id_area, nombre_area FROM public.area ORDER BY nombre_area ASC'
        );
    }

    // --------------------------------------------------
    // Usuario
    // --------------------------------------------------
    public function getUserList(int $limit = 50): array
    {
        return $this->fetchAll(
            $this->buildUserWithJoinsSql(null, 'u.id_user DESC', $limit)
        );
    }

    public function getUserById(int $id): ?array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TBL_USER . ' WHERE id_user = ? LIMIT 1',
            [$id]
        );
    }

    // --------------------------------------------------
    // Organigrama / relaciones
    // --------------------------------------------------
    public function getOrgChartUsersByArea(int $areaId, int $gerenciaAreaId = 1): array
    {
        $isGerencia = $areaId === $gerenciaAreaId;

        $where  = $isGerencia
            ? 'u.id_area = ?'
            : '(u.id_area = ? OR u.id_area = ?)';

        $params = $isGerencia
            ? [$areaId]
            : [$areaId, $gerenciaAreaId];

        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.id_area,
    u.id_cargo,
    u.id_supervisor,
    ar.nombre_area,
    ca.nombre_cargo
FROM public."USER" u
LEFT JOIN public.area ar  ON ar.id_area  = u.id_area
LEFT JOIN public.cargo ca ON ca.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND {$where}
ORDER BY u.id_area ASC, u.id_user ASC
SQL;

        return $this->fetchAll($sql, $params);
    }

    // Cargo DEPENDE de Área (regla mantenida)
    public function getCargosByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT id_cargo, nombre_cargo
FROM public.cargo
WHERE id_area = ?
ORDER BY nombre_cargo ASC
SQL;
        return $this->fetchAll($sql, [$areaId]);
    }

    public function getSupervisorsByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    CASE
        WHEN ca.nombre_cargo IS NULL OR ca.nombre_cargo = ''
            THEN (u.nombres || ' ' || u.apellidos)
        ELSE (u.nombres || ' ' || u.apellidos || ' — ' || ca.nombre_cargo)
    END AS supervisor_label
FROM public."USER" u
LEFT JOIN public.cargo ca ON ca.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND (u.id_area = ? OR u.id_area = 1)
ORDER BY
  CASE WHEN u.id_area = 1 THEN 0 ELSE 1 END,
  supervisor_label ASC
SQL;

        return $this->fetchAll($sql, [$areaId]);
    }

    // --------------------------------------------------
    // CRUD
    // --------------------------------------------------
    public function insertUser(array $data): bool
    {
        try {
            $ok = $this->userTable()->insert($data);
            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['code' => 0, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function updateUser(int $id, array $data): bool
    {
        try {
            $ok = $this->userTable()->where('id_user', $id)->update($data);
            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['code' => 0, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function getUserProfileForPlan(int $idUser): ?array
    {
        return $this->fetchRow(
            $this->buildUserWithJoinsSql('u.id_user = ?', null, 1),
            [$idUser]
        );
    }

    public function getUsersByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    (u.nombres || ' ' || u.apellidos) AS nombre_completo,
    ca.nombre_cargo
FROM public."USER" u
LEFT JOIN public.cargo ca ON ca.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND u.id_area = ?
ORDER BY nombre_completo ASC
SQL;

        return $this->fetchAll($sql, [$areaId]);
    }
}