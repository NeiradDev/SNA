<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\BaseConnection;

/**
 * ============================================================
 * Model: UsuarioModel
 *
 * Responsabilidad:
 * - Consultas para listar usuarios (con joins a agencia/área/cargo/supervisor)
 * - Cargar catálogos (agencias, áreas, cargos por área)
 * - Cargar supervisores por área (incluye siempre gerencia id_area=1)
 * - Validar duplicidad de documento
 * - Insertar y actualizar usuarios guardando el último error de BD
 *
 * Nota:
 * - Mantiene nombres de métodos en inglés básico (estándar del proyecto)
 * - Nombres visibles/campos en español se manejan en vistas/mensajes
 * ============================================================
 */
class UsuarioModel extends Model
{
    /**
     * Último error capturado de la base de datos.
     * Útil para que el Controller muestre mensajes amigables (modal).
     *
     * Ejemplo:
     * [
     *   'code' => 23505,
     *   'message' => 'duplicate key value violates unique constraint ...'
     * ]
     */
    protected ?array $lastDbError = null;

    /**
     * Retorna una conexión a BD.
     * Centraliza \Config\Database::connect() para no repetirlo en cada método.
     */
    private function getDb(): BaseConnection
    {
        return \Config\Database::connect();
    }

    /**
     * ============================================================
     * getUserList()
     * Devuelve el listado de usuarios para la vista Lista_usuario.php
     *
     * - Incluye JOINs para traer nombres de agencia/área/cargo
     * - Trae el nombre completo del supervisor (si existe)
     * - Ordena por id_user DESC
     * - Aplica LIMIT (paginación simple del lado servidor)
     * ============================================================
     */
    public function getUserList(int $limit = 50): array
    {
        // ============================================================
        // OPCIÓN (COMENTADA): Traer usuarios desde una API externa
        // ============================================================
        // ¿Cuándo usarlo?
        // - Si tu backend está centralizado en otro servicio
        // - Si esta app solo consume y renderiza datos
        //
        // Requisitos:
        // - La API debe retornar un JSON array
        // - Cada item debe incluir las mismas claves que usa la vista
        //
        // $apiUrl = 'https://tu-dominio.com/api/usuarios?limit=' . $limit;
        // $token  = 'TU_TOKEN'; // si aplica
        //
        // $client = \Config\Services::curlrequest();
        //
        // try {
        //     $response = $client->get($apiUrl, [
        //         'headers' => [
        //             'Accept' => 'application/json',
        //             // 'Authorization' => 'Bearer ' . $token,
        //         ],
        //         'timeout' => 10,
        //     ]);
        //
        //     if ($response->getStatusCode() !== 200) {
        //         return [];
        //     }
        //
        //     $payload = json_decode($response->getBody(), true);
        //     return is_array($payload) ? $payload : [];
        // } catch (\Throwable $e) {
        //     // Para depurar (opcional):
        //     // $this->lastDbError = ['code' => 0, 'message' => $e->getMessage()];
        //     return [];
        // }
        // ============================================================

        // MODO ACTUAL: BD PostgreSQL
        $db = $this->getDb();

        $sql = <<<'SQL'
SELECT
    u.id_user,
    u.nombres,
    u.apellidos,
    u.cedula,
    u.activo,
    u.id_agencias,
    u.id_area,
    u.id_cargo,
    u.id_supervisor,
    ag.nombre_agencia,
    ar.nombre_area,
    ca.nombre_cargo,
    CASE
        WHEN sup.id_user IS NULL THEN NULL
        ELSE (sup.nombres || ' ' || sup.apellidos)
    END AS supervisor_nombre
FROM public."USER" u
LEFT JOIN public.agencias ag ON ag.id_agencias = u.id_agencias
LEFT JOIN public.area ar     ON ar.id_area     = u.id_area
LEFT JOIN public.cargo ca    ON ca.id_cargo    = u.id_cargo
LEFT JOIN public."USER" sup  ON sup.id_user    = u.id_supervisor
ORDER BY u.id_user DESC
LIMIT ?
SQL;

        return $db->query($sql, [$limit])->getResultArray();
    }

    /**
     * getAgencies()
     * Devuelve todas las agencias para poblar el combo "Agencia".
     */
    public function getAgencies(): array
    {
        $db = $this->getDb();

        $sql = 'SELECT id_agencias, nombre_agencia FROM public.agencias ORDER BY nombre_agencia ASC';
        return $db->query($sql)->getResultArray();
    }

    /**
     * getAreas()
     * Devuelve todas las áreas para poblar el combo "Área".
     */
    public function getAreas(): array
    {
        $db = $this->getDb();

        $sql = 'SELECT id_area, nombre_area FROM public.area ORDER BY nombre_area ASC';
        return $db->query($sql)->getResultArray();
    }

    /**
     * getCargosByArea()
     * Devuelve cargos según el área seleccionada.
     * Usado por el endpoint JSON: /usuarios/api/cargos?id_area=#
     */
    public function getCargosByArea(int $areaId): array
    {
        $db = $this->getDb();

        $sql = <<<'SQL'
SELECT
    c.id_cargo,
    c.nombre_cargo
FROM public.cargo c
WHERE c.id_area = ?
ORDER BY c.nombre_cargo ASC
SQL;

        return $db->query($sql, [$areaId])->getResultArray();
    }

    /**
     * getSupervisorsByArea()
     * Devuelve supervisores filtrados por área:
     * - Incluye usuarios del área seleccionada
     * - Siempre incluye usuarios con id_area = 1 (gerencia) para todas las áreas
     *
     * Usado por el endpoint JSON: /usuarios/api/supervisores?id_area=#
     */
    public function getSupervisorsByArea(int $areaId): array
    {
        $db = $this->getDb();

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

        return $db->query($sql, [$areaId])->getResultArray();
    }

    /**
     * docExists()
     * Verifica si un documento ya existe en la tabla USER.
     * - Aplica para cédula o pasaporte/CI/NU (alfanumérico).
     *
     * Requisito:
     * - La columna "cedula" en BD debe ser VARCHAR para permitir letras.
     */
    public function docExists(string $docNumber): bool
    {
        $db = $this->getDb();

        $docNumber = trim($docNumber);
        if ($docNumber === '') {
            return false;
        }

        $sql = 'SELECT 1 FROM public."USER" WHERE cedula = ? LIMIT 1';
        $row = $db->query($sql, [$docNumber])->getRowArray();

        return !empty($row);
    }

    /**
     * getLastDbError()
     * Devuelve el último error de BD capturado por insert/update.
     * - El Controller lo usa para detectar duplicados u otros problemas.
     */
    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    /**
     * insertUser()
     * Inserta un usuario en BD.
     *
     * - Mantiene retorno boolean (no rompe Controllers existentes)
     * - Si falla, guarda el error en $this->lastDbError
     */
    public function insertUser(array $data): bool
    {
        $db = $this->getDb();

        try {
            $ok = $db->table('public."USER"')->insert($data);

            // Si falla, guardamos el error de BD; si no, limpiamos el error anterior
            $this->lastDbError = $ok ? null : $db->error();

            return (bool) $ok;
        } catch (\Throwable $e) {
            // Si ocurre excepción, guardamos mensaje para diagnóstico
            $this->lastDbError = [
                'code'    => 0,
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }

    /**
     * getUserById()
     * Devuelve los datos del usuario para edición.
     * - Se usa en el Controller: edit($id)
     */
    public function getUserById(int $id): ?array
    {
        $db = $this->getDb();

        $sql = 'SELECT * FROM public."USER" WHERE id_user = ? LIMIT 1';
        $row = $db->query($sql, [$id])->getRowArray();

        return $row ?: null;
    }

    /**
     * docExistsForOtherUser()
     * Verifica duplicado de documento EXCLUYENDO el usuario actual.
     * - Se usa en update() para permitir que el usuario mantenga su mismo documento.
     */
    public function docExistsForOtherUser(string $docNumber, int $userId): bool
    {
        $db = $this->getDb();

        $sql = 'SELECT 1 FROM public."USER" WHERE cedula = ? AND id_user <> ? LIMIT 1';
        $row = $db->query($sql, [trim($docNumber), $userId])->getRowArray();

        return !empty($row);
    }

    /**
     * updateUser()
     * Actualiza un usuario por id_user.
     *
     * - Retorna bool
     * - Guarda error en $this->lastDbError si falla
     */
    public function updateUser(int $id, array $data): bool
    {
        $db = $this->getDb();

        try {
            $ok = $db->table('public."USER"')
                ->where('id_user', $id)
                ->update($data);

            $this->lastDbError = $ok ? null : $db->error();

            return (bool) $ok;
        } catch (\Throwable $e) {
            $this->lastDbError = [
                'code'    => 0,
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }

    // ============================================================
    // OPCIÓN (COMENTADA): Crear usuario mediante API externa
    // ============================================================
    // ¿Cuándo usarlo?
    // - Si el alta de usuarios debe hacerse en un servicio central
    //
    // public function insertUserViaApi(array $data): bool
    // {
    //     $apiUrl = 'https://tu-dominio.com/api/usuarios';
    //     $token  = 'TU_TOKEN'; // si aplica
    //
    //     $client = \Config\Services::curlrequest();
    //
    //     try {
    //         $response = $client->post($apiUrl, [
    //             'headers' => [
    //                 'Accept'       => 'application/json',
    //                 'Content-Type' => 'application/json',
    //                 // 'Authorization' => 'Bearer ' . $token,
    //             ],
    //             'json' => $data,
    //             'timeout' => 10,
    //         ]);
    //
    //         // Ajusta a tu estándar (200/201)
    //         return in_array($response->getStatusCode(), [200, 201], true);
    //     } catch (\Throwable $e) {
    //         $this->lastDbError = ['code' => 0, 'message' => $e->getMessage()];
    //         return false;
    //     }
    // }
    // ============================================================
}
