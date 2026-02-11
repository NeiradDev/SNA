<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    // Config base del model
    protected $table      = 'public."USER"';
    protected $primaryKey = 'id_user';

    // Columnas permitidas para insert/update
    protected $allowedFields = [
        'nombres',
        'apellidos',
        'cedula',
        'password',
        'activo',
        'id_agencias',
        'id_cargo',
        'id_supervisor',
        'created_at',
        'updated_at',
    ];

    // Nombres de tablas
    private const TBL_USER          = 'public."USER"';
    private const TBL_AGENCIA       = 'public.agencias';
    private const TBL_AREA          = 'public.area';
    private const TBL_DIVISION      = 'public.division';
    private const TBL_CARGO         = 'public.cargo';
    private const TBL_USUARIO_CARGO = 'public.usuario_cargo';

    // Último error de BD y cache simple en memoria
    protected ?array $lastDbError = null;
    private array $memoryCache = [];

    // Ejecuta una consulta y devuelve resultArray
    private function fetchAll(string $sql, array $params = []): array
    {
        return $this->db->query($sql, $params)->getResultArray();
    }

    // Ejecuta una consulta y devuelve rowArray o null
    private function fetchRow(string $sql, array $params = []): ?array
    {
        return $this->db->query($sql, $params)->getRowArray() ?: null;
    }

    // Retorna el builder de la tabla USER
    private function userTable()
    {
        return $this->db->table(self::TBL_USER);
    }

    // Guarda el último error detectado
    private function setDbError(?array $error): void
    {
        $this->lastDbError = $error;
    }

    // Devuelve el último error de BD
    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    // Devuelve el último ID insertado
    public function getLastInsertId(): int
    {
        return (int) $this->db->insertID();
    }

    // Lista áreas con su división para combos/menú
    public function getAreas(): array
    {
        $sql = <<<'SQL'
SELECT
    a.id_area,
    a.nombre_area,
    a.id_division,
    d.nombre_division
FROM public.area a
JOIN public.division d ON d.id_division = a.id_division
ORDER BY d.nombre_division ASC, a.nombre_area ASC
SQL;

        return $this->fetchAll($sql);
    }

    // Construye SQL base de usuario con joins reutilizable
    private function buildUserWithJoinsSql(?string $where = null, ?string $orderBy = null, ?int $limit = null): string
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.cedula,
    (u.activo::int) AS activo,
    u.id_agencias,
    u.id_cargo,
    u.id_supervisor,
    ag.nombre_agencia,
    ca.nombre_cargo,

    ar.id_area,
    ar.nombre_area,

    COALESCE(ca.id_division, ar.id_division) AS id_division,
    dv.nombre_division,

    CASE
        WHEN sup.id_user IS NULL THEN NULL
        ELSE (sup.nombres || ' ' || sup.apellidos)
    END AS supervisor_nombre
FROM public."USER" u
LEFT JOIN public.agencias ag ON ag.id_agencias = u.id_agencias
LEFT JOIN public.cargo ca    ON ca.id_cargo    = u.id_cargo
LEFT JOIN public.area ar     ON ar.id_area     = ca.id_area
LEFT JOIN public.division dv ON dv.id_division = COALESCE(ca.id_division, ar.id_division)
LEFT JOIN public."USER" sup  ON sup.id_user    = u.id_supervisor
SQL;

        if ($where)   $sql .= "\nWHERE {$where}";
        if ($orderBy) $sql .= "\nORDER BY {$orderBy}";
        if ($limit !== null) $sql .= "\nLIMIT {$limit}";

        return $sql;
    }

    // Cachea resultados de catálogos por key
    private function cachedQuery(string $key, string $sql): array
    {
        return $this->memoryCache[$key] ??= $this->fetchAll($sql);
    }

    // Devuelve catálogo de agencias
    public function getAgencies(): array
    {
        return $this->cachedQuery(
            'agencies',
            'SELECT id_agencias, nombre_agencia FROM public.agencias ORDER BY nombre_agencia ASC'
        );
    }

    // Devuelve catálogo de divisiones
    public function getDivision(): array
    {
        return $this->cachedQuery(
            'division',
            'SELECT id_division, nombre_division FROM public.division ORDER BY nombre_division ASC'
        );
    }

    // Lista usuarios con joins para vista de listado
    public function getUserList(int $limit = 50): array
    {
        return $this->fetchAll(
            $this->buildUserWithJoinsSql(null, 'u.id_user DESC', $limit)
        );
    }

    // Devuelve perfil completo (con joins) para el usuario logueado
    public function getUserProfileForPlan(int $idUser): ?array
    {
        $sql = $this->buildUserWithJoinsSql('u.id_user = ?', null, 1);
        return $this->fetchRow($sql, [$idUser]);
    }

    // Devuelve el usuario por ID sin joins
    public function getUserById(int $id): ?array
    {
        return $this->fetchRow(
            'SELECT * FROM ' . self::TBL_USER . ' WHERE id_user = ? LIMIT 1',
            [$id]
        );
    }

    // Devuelve un usuario por ID con joins completos
    public function getUserWithJoinsById(int $id): ?array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.cedula,
    (u.activo::int) AS activo,
    u.id_agencias,
    u.id_cargo,
    u.id_supervisor,

    ag.nombre_agencia,
    ca.nombre_cargo,

    ar.id_area,
    ar.nombre_area,

    COALESCE(ca.id_division, ar.id_division) AS id_division,
    dv.nombre_division,

    CASE
        WHEN sup.id_user IS NULL THEN NULL
        ELSE (sup.nombres || ' ' || sup.apellidos)
    END AS supervisor_nombre
FROM public."USER" u
LEFT JOIN public.agencias ag ON ag.id_agencias = u.id_agencias
LEFT JOIN public.cargo ca    ON ca.id_cargo    = u.id_cargo
LEFT JOIN public.area ar     ON ar.id_area     = ca.id_area
LEFT JOIN public.division dv ON dv.id_division = COALESCE(ca.id_division, ar.id_division)
LEFT JOIN public."USER" sup  ON sup.id_user    = u.id_supervisor
WHERE u.id_user = ?
LIMIT 1
SQL;

        return $this->fetchRow($sql, [$id]);
    }

    // Verifica si existe un documento
    public function docExists(string $docNumber): bool
    {
        $row = $this->fetchRow('SELECT 1 FROM public."USER" WHERE cedula = ? LIMIT 1', [$docNumber]);
        return (bool) $row;
    }

    // Verifica documento para evitar duplicado en update
    public function docExistsForOtherUser(string $docNumber, int $userId): bool
    {
        $row = $this->fetchRow('SELECT 1 FROM public."USER" WHERE cedula = ? AND id_user <> ? LIMIT 1', [$docNumber, $userId]);
        return (bool) $row;
    }

    // Devuelve áreas filtradas por división
    public function getAreasByDivision(int $divisionId): array
    {
        $sql = <<<'SQL'
SELECT id_area, nombre_area
FROM public.area
WHERE id_division = ?
ORDER BY nombre_area ASC
SQL;
        return $this->fetchAll($sql, [$divisionId]);
    }

    // Devuelve cargos filtrados por área
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

    // Devuelve cargos filtrados por división
    public function getCargosByDivision(int $divisionId): array
    {
        $sql = <<<'SQL'
SELECT id_cargo, nombre_cargo
FROM public.cargo
WHERE id_division = ?
ORDER BY nombre_cargo ASC
SQL;
        return $this->fetchAll($sql, [$divisionId]);
    }

    // Obtiene el primer usuario activo con un cargo específico
    public function getUserByCargoId(int $cargoId): ?array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    (u.nombres || ' ' || u.apellidos) AS full_name,
    u.id_cargo
FROM public."USER" u
WHERE u.activo = TRUE
  AND u.id_cargo = ?
ORDER BY u.id_user ASC
LIMIT 1
SQL;
        return $this->fetchRow($sql, [$cargoId]);
    }

    // Devuelve el jefe de una división (con nombre)
    public function getDivisionBossByDivision(int $divisionId): ?array
    {
        $sql = <<<'SQL'
SELECT
    d.id_division,
    d.nombre_division,
    d.id_jf_division,
    (u.nombres || ' ' || u.apellidos) AS jefe_nombre
FROM public.division d
LEFT JOIN public."USER" u ON u.id_user = d.id_jf_division
WHERE d.id_division = ?
LIMIT 1
SQL;
        return $this->fetchRow($sql, [$divisionId]);
    }

    // Valida si un cargo pertenece a una división
    public function cargoBelongsToDivision(int $cargoId, int $divisionId): bool
    {
        $sql = 'SELECT 1 FROM public.cargo WHERE id_cargo = ? AND id_division = ? LIMIT 1';
        return (bool) $this->fetchRow($sql, [$cargoId, $divisionId]);
    }

    // Valida si un cargo pertenece a un área
    public function cargoBelongsToArea(int $cargoId, int $areaId): bool
    {
        $sql = 'SELECT 1 FROM public.cargo WHERE id_cargo = ? AND id_area = ? LIMIT 1';
        return (bool) $this->fetchRow($sql, [$cargoId, $areaId]);
    }

    // Valida si un área pertenece a una división
    public function areaBelongsToDivision(int $areaId, int $divisionId): bool
    {
        $sql = 'SELECT 1 FROM public.area WHERE id_area = ? AND id_division = ? LIMIT 1';
        return (bool) $this->fetchRow($sql, [$areaId, $divisionId]);
    }

    // Asigna un usuario como jefe de división
    public function assignDivisionBoss(int $divisionId, int $userId): bool
    {
        try {
            $ok = $this->db->table(self::TBL_DIVISION)
                ->where('id_division', $divisionId)
                ->update(['id_jf_division' => $userId]);

            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Asigna un usuario como jefe de área
    public function assignAreaBoss(int $areaId, int $userId): bool
    {
        try {
            $ok = $this->db->table(self::TBL_AREA)
                ->where('id_area', $areaId)
                ->update(['id_jf_area' => $userId]);

            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Inserta un cargo adicional a un usuario (usuario_cargo)
    public function insertUserCargo(int $userId, int $cargoId): bool
    {
        if ($userId <= 0 || $cargoId <= 0) return false;

        $sql = <<<'SQL'
INSERT INTO public.usuario_cargo (id_user, id_cargo)
VALUES (?, ?)
ON CONFLICT (id_user, id_cargo) DO NOTHING
SQL;

        try {
            $ok = (bool) $this->db->query($sql, [$userId, $cargoId]);
            $this->setDbError($ok ? null : $this->db->error());
            return $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Borra todos los cargos asociados a un usuario
    public function deleteUserCargos(int $userId): bool
    {
        try {
            $ok = $this->db->table(self::TBL_USUARIO_CARGO)
                ->where('id_user', $userId)
                ->delete();

            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Reemplaza cargos múltiples del usuario por una lista nueva
    public function replaceUserCargos(int $userId, array $cargoIds): bool
    {
        $cargoIds = array_values(array_unique(array_filter(array_map('intval', $cargoIds), fn($v) => $v > 0)));

        try {
            $okDel = $this->deleteUserCargos($userId);
            if (!$okDel) return false;

            foreach ($cargoIds as $cid) {
                if (!$this->insertUserCargo($userId, $cid)) return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Inserta un usuario en USER
    public function insertUser(array $data): bool
    {
        try {
            $ok = $this->userTable()->insert($data);
            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Actualiza un usuario por ID
    public function updateUser(int $id, array $data): bool
    {
        try {
            $ok = $this->userTable()->where('id_user', $id)->update($data);
            $this->setDbError($ok ? null : $this->db->error());
            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->setDbError(['message' => $e->getMessage()]);
            return false;
        }
    }

    // Devuelve supervisores preferidos según jefaturas y fallback
    public function getPreferredSupervisorsForArea(
        int $areaId,
        int $excludeUserId = 0,
        int $keepUserId = 0,
        int $gerenciaCargoId = 0
    ): array {
        $areaId          = (int) $areaId;
        $excludeUserId   = max(0, (int) $excludeUserId);
        $keepUserId      = max(0, (int) $keepUserId);
        $gerenciaCargoId = max(0, (int) $gerenciaCargoId);

        if ($areaId <= 0) return [];

        $metaSql = <<<'SQL'
SELECT
    a.id_area,
    a.id_jf_area,
    a.id_division,
    d.id_jf_division
FROM public.area a
LEFT JOIN public.division d ON d.id_division = a.id_division
WHERE a.id_area = ?
LIMIT 1
SQL;

        $meta = $this->fetchRow($metaSql, [$areaId]);
        if (!$meta) return [];

        $candidateIds = [];

        $jfArea = (int) ($meta['id_jf_area'] ?? 0);
        $jfDiv  = (int) ($meta['id_jf_division'] ?? 0);

        if ($jfArea > 0) {
            $candidateIds[] = $jfArea;
        } else {
            if ($jfDiv > 0) $candidateIds[] = $jfDiv;
        }

        if (empty($candidateIds) && $gerenciaCargoId > 0) {
            $ger = $this->getUserByCargoId($gerenciaCargoId);
            $gerId = (int) ($ger['id_user'] ?? 0);
            if ($gerId > 0) $candidateIds[] = $gerId;
        }

        if ($keepUserId > 0) $candidateIds[] = $keepUserId;

        $candidateIds = array_values(array_unique(array_filter(array_map('intval', $candidateIds), function ($v) use ($excludeUserId) {
            if ($v <= 0) return false;
            if ($excludeUserId > 0 && $v === $excludeUserId) return false;
            return true;
        })));

        if (empty($candidateIds)) return [];

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));

        $sql = <<<SQL
SELECT
    u.id_user,
    (
        (u.nombres || ' ' || u.apellidos)
        || CASE
            WHEN ca.nombre_cargo IS NULL OR ca.nombre_cargo = '' THEN ''
            ELSE (' — ' || ca.nombre_cargo)
           END
        || ' | Superior: '
        || COALESCE(
            (sup.nombres || ' ' || sup.apellidos)
            || CASE
                WHEN supca.nombre_cargo IS NULL OR supca.nombre_cargo = '' THEN ''
                ELSE (' — ' || supca.nombre_cargo)
               END,
            'Sin superior'
        )
    ) AS supervisor_label
FROM public."USER" u
LEFT JOIN public.cargo ca    ON ca.id_cargo = u.id_cargo
LEFT JOIN public."USER" sup  ON sup.id_user = u.id_supervisor
LEFT JOIN public.cargo supca ON supca.id_cargo = sup.id_cargo
WHERE u.activo = TRUE
  AND u.id_user IN ($placeholders)
SQL;

        $rows = $this->fetchAll($sql, $candidateIds);

        $pos = array_flip($candidateIds);
        usort($rows, fn($a, $b) => ($pos[(int)$a['id_user']] ?? 999) <=> ($pos[(int)$b['id_user']] ?? 999));

        return $rows;
    }

    // Devuelve supervisores activos dentro de un área
    public function getSupervisorsByAreaOnly(int $areaId, int $excludeUserId = 0): array
    {
        $sql = <<<'SQL'
SELECT
    u.id_user,
    (
        (u.nombres || ' ' || u.apellidos)
        || CASE
            WHEN ca.nombre_cargo IS NULL OR ca.nombre_cargo = '' THEN ''
            ELSE (' — ' || ca.nombre_cargo)
           END
        || ' | Superior: '
        || COALESCE(
            (sup.nombres || ' ' || sup.apellidos)
            || CASE
                WHEN supca.nombre_cargo IS NULL OR supca.nombre_cargo = '' THEN ''
                ELSE (' — ' || supca.nombre_cargo)
               END,
            'Sin superior'
        )
    ) AS supervisor_label
FROM public."USER" u
LEFT JOIN public.cargo ca    ON ca.id_cargo = u.id_cargo
LEFT JOIN public."USER" sup  ON sup.id_user = u.id_supervisor
LEFT JOIN public.cargo supca ON supca.id_cargo = sup.id_cargo
WHERE u.activo = TRUE
  AND ca.id_area = ?
  AND (? = 0 OR u.id_user <> ?)
ORDER BY supervisor_label ASC
SQL;

        return $this->fetchAll($sql, [$areaId, $excludeUserId, $excludeUserId]);
    }
    
}
