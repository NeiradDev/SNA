<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\BaseConnection;


class UsuarioModel extends Model
{
    protected ?array $lastDbError = null;

    /**
     * Cache en memoria (solo DURANTE esta request).
     * Útil para catálogos que se llaman varias veces en el mismo request.
     */
    private array $memoryCache = [];

    /** Reutiliza conexión. */
    private function db(): BaseConnection
    {
        return \Config\Database::connect();
    }

    /** Helper corto para queries parametrizadas. */
    private function fetchAll(string $sql, array $params = []): array
    {
        return $this->db()->query($sql, $params)->getResultArray();
    }

    /** Helper corto para un solo row. */
    private function fetchRow(string $sql, array $params = []): ?array
    {
        $row = $this->db()->query($sql, $params)->getRowArray();
        return $row ?: null;
    }

    /** Tabla USER (Postgres) centralizada para inserts/updates. */
    private function userTable()
    {
        return $this->db()->table('public."USER"');
    }

    /** Guarda último error de BD (si existe) o lo limpia. */
    private function setDbError(?array $err): void
    {
        $this->lastDbError = $err;
    }

    /** Último error DB (insert/update). */
    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    
    private function buildUserWithJoinsSql(string $whereSql = '', string $orderSql = '', bool $useLimit = false): string
    {
        /**
         * ✅ SELECT base con JOINS reutilizable:
         * - USER (u)
         * - agencias (ag)
         * - area (ar)
         * - cargo (ca)
         * - division (dv)  <-- NUEVO (sale desde ar.id_division)
         * - supervisor (sup)
         */
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

    -- Agencia
    ag.nombre_agencia,

    -- Área
    ar.nombre_area,
    ar.id_division,

    -- División (NUEVO)
    dv.nombre_division,

    -- Cargo
    ca.nombre_cargo,

    -- Supervisor (self join)
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

        // WHERE opcional
        if ($whereSql !== '') {
            $sql .= "\nWHERE " . $whereSql;
        }

        // ORDER opcional
        if ($orderSql !== '') {
            $sql .= "\nORDER BY " . $orderSql;
        }

        // LIMIT opcional
        if ($useLimit) {
            $sql .= "\nLIMIT ?";
        }

        return $sql;
    }

       public function getUserList(int $limit = 50): array
    {
        $sql = $this->buildUserWithJoinsSql(
            whereSql: '',                 // sin filtro
            orderSql: 'u.id_user DESC',   // mismo orden
            useLimit: true                // aplica LIMIT ?
        );

        return $this->fetchAll($sql, [$limit]);
    }

    
    public function getOrgChartUsersByArea(int $areaId, int $gerenciaAreaId = 1): array
    {
        $where = ($areaId === $gerenciaAreaId)
            ? 'u.id_area = ?'
            : '(u.id_area = ? OR u.id_area = ?)';

        $sql = <<<SQL
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

        $params = ($areaId === $gerenciaAreaId) ? [$areaId] : [$areaId, $gerenciaAreaId];

        return $this->fetchAll($sql, $params);
    }

   

    public function getAgencies(): array
    {
        // ✅ Evita ejecutar la misma consulta varias veces dentro del mismo request
        if (isset($this->memoryCache['agencies'])) {
            return $this->memoryCache['agencies'];
        }

        $this->memoryCache['agencies'] = $this->fetchAll(
            'SELECT id_agencias, nombre_agencia FROM public.agencias ORDER BY nombre_agencia ASC'
        );

        return $this->memoryCache['agencies'];
    }

    public function getAreas(): array
    {
        if (isset($this->memoryCache['areas'])) {
            return $this->memoryCache['areas'];
        }

        $this->memoryCache['areas'] = $this->fetchAll(
            'SELECT id_area, nombre_area FROM public.area ORDER BY nombre_area ASC'
        );

        return $this->memoryCache['areas'];
    }

   
    public function getCargosByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT c.id_cargo, c.nombre_cargo
FROM public.cargo c
WHERE c.id_area = ?
ORDER BY c.nombre_cargo ASC
SQL;

        return $this->fetchAll($sql, [$areaId]);
    }

  
    public function getSupervisorsByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.id_area,
    (u.nombres || ' ' || u.apellidos) AS nombre_completo,
    COALESCE(ca.nombre_cargo, '') AS nombre_cargo,
    CASE
        WHEN ca.nombre_cargo IS NULL OR ca.nombre_cargo = '' THEN (u.nombres || ' ' || u.apellidos)
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

    
    public function docExists(string $docNumber): bool
    {
        $docNumber = trim($docNumber);
        if ($docNumber === '') return false;

        $row = $this->fetchRow(
            'SELECT 1 FROM public."USER" WHERE cedula = ? LIMIT 1',
            [$docNumber]
        );

        return !empty($row);
    }

    public function docExistsForOtherUser(string $docNumber, int $userId): bool
    {
        $docNumber = trim($docNumber);
        if ($docNumber === '') return false;

        $row = $this->fetchRow(
            'SELECT 1 FROM public."USER" WHERE cedula = ? AND id_user <> ? LIMIT 1',
            [$docNumber, $userId]
        );

        return !empty($row);
    }

   
    public function insertUser(array $data): bool
    {
        try {
            $ok = $this->userTable()->insert($data);
            $this->setDbError($ok ? null : $this->db()->error());
            return (bool)$ok;
        } catch (\Throwable $e) {
            $this->setDbError(['code' => 0, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function getUserById(int $id): ?array
    {
        return $this->fetchRow(
            'SELECT * FROM public."USER" WHERE id_user = ? LIMIT 1',
            [$id]
        );
    }

    public function updateUser(int $id, array $data): bool
    {
        try {
            $ok = $this->userTable()
                ->where('id_user', $id)
                ->update($data);

            $this->setDbError($ok ? null : $this->db()->error());
            return (bool)$ok;
        } catch (\Throwable $e) {
            $this->setDbError(['code' => 0, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function getUserProfileForPlan(int $idUser): ?array
    {
        $sql = $this->buildUserWithJoinsSql(
            whereSql: 'u.id_user = ?',
            orderSql: '',       // no hace falta ORDER en LIMIT 1
            useLimit: false
        );

       
        $sql .= "\nLIMIT 1";

        return $this->fetchRow($sql, [$idUser]);
    }

   
    public function getUsersByArea(int $areaId): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    (u.nombres || ' ' || u.apellidos) AS nombre_completo,
    u.id_area,
    ar.nombre_area,
    u.id_cargo,
    ca.nombre_cargo
FROM public."USER" u
LEFT JOIN public.area ar  ON ar.id_area  = u.id_area
LEFT JOIN public.cargo ca ON ca.id_cargo = u.id_cargo
WHERE u.activo = TRUE
  AND u.id_area = ?
ORDER BY nombre_completo ASC
SQL;

        return $this->fetchAll($sql, [$areaId]);
    }
}