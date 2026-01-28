<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\BaseConnection;
// use CodeIgniter\HTTP\CURLRequest; // (opcional) si luego tipas el cliente HTTP

class UsuarioModel extends Model
{
    /**
     * Guarda el último error de base de datos al fallar un INSERT.
     * Formato esperado: ['code' => int|string, 'message' => string]
     */
    protected ?array $lastDbError = null;

    /**
     * Devuelve una conexión a la base de datos.
     * Centralizar esto evita repetir \Config\Database::connect() en todos los métodos.
     */
    private function getDb(): BaseConnection
    {
        return \Config\Database::connect();
    }

    /**
     * Lista usuarios para la vista Lista_usuario.php.
     *
     * Nota: aquí dejamos una opción comentada para consumir una API externa.
     */
    public function getUserList(int $limit = 50): array
    {
        // ============================================================
        // OPCIÓN (COMENTADA): Traer usuarios desde una API externa
        // ============================================================
        // ¿Cuándo usarlo?
        // - Cuando tu back-end centraliza datos en otro servicio
        // - Cuando esta app solo consume y muestra información
        //
        // Requisitos:
        // - La API debe retornar un JSON array de usuarios
        // - Cada usuario debe contener las claves que usa tu vista
        //   (id_user, nombres, apellidos, cedula, activo, nombre_cargo,
        //    nombre_area, nombre_agencia, supervisor_nombre, etc.)
        //
        // $apiUrl = 'https://tu-dominio.com/api/usuarios?limit=' . $limit;
        // $token  = 'TU_TOKEN'; // si aplica
        //
        // $client = \Config\Services::curlrequest();
        //
        // try {
        //     $response = $client->get($apiUrl, [
        //         'headers' => [
        //             'Accept'        => 'application/json',
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
        //     // Si quieres depurar:
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
     * Lista de agencias para llenar el combo.
     */
    public function getAgencies(): array
    {
        $db = $this->getDb();

        $sql = 'SELECT id_agencias, nombre_agencia FROM public.agencias ORDER BY nombre_agencia ASC';
        return $db->query($sql)->getResultArray();
    }

    /**
     * Lista de áreas para llenar el combo.
     */
    public function getAreas(): array
    {
        $db = $this->getDb();

        $sql = 'SELECT id_area, nombre_area FROM public.area ORDER BY nombre_area ASC';
        return $db->query($sql)->getResultArray();
    }

    /**
     * Cargos filtrados por área (cargo.id_area es FK).
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
     * Supervisores filtrados por área seleccionada,
     * + siempre incluye usuarios de gerencia (id_area = 1) para todas las áreas.
     *
     * Retorna:
     * - id_user
     * - id_area
     * - supervisor_label (Nombre Apellido — Cargo)
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
     * Verifica si ya existe el número de documento (cédula o pasaporte/CI/NU).
     * Importante: este método asume que la columna "cedula" ya es VARCHAR.
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
     * Retorna el último error de BD capturado en insertUser().
     * Útil para que el Controller muestre mensajes amigables en el modal.
     */
    public function getLastDbError(): ?array
    {
        return $this->lastDbError;
    }

    /**
     * Inserta un usuario.
     *
     * - Mantiene retorno boolean para no romper el Controller actual.
     * - Si falla, guarda el error de BD en $this->lastDbError.
     */
    public function insertUser(array $data): bool
    {
        $db = $this->getDb();

        try {
            $ok = $db->table('public."USER"')->insert($data);

            // Si el builder devuelve false, guardamos error
            $this->lastDbError = $ok ? null : $db->error();

            return (bool) $ok;
        } catch (\Throwable $e) {
            // Si hay excepción, guardamos el mensaje para diagnóstico/controlador
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
    // - Si tu creación de usuarios debe hacerse en un servicio central
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
    //                 'Accept'        => 'application/json',
    //                 'Content-Type'  => 'application/json',
    //                 // 'Authorization' => 'Bearer ' . $token,
    //             ],
    //             'json' => $data,
    //             'timeout' => 10,
    //         ]);
    //
    //         // Puedes ajustar a tu estándar de API (201, 200, etc.)
    //         if (!in_array($response->getStatusCode(), [200, 201], true)) {
    //             return false;
    //         }
    //
    //         return true;
    //     } catch (\Throwable $e) {
    //         $this->lastDbError = ['code' => 0, 'message' => $e->getMessage()];
    //         return false;
    //     }
    // }
    // ============================================================
}
