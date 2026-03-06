<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * =========================================================
 * Modelo: UsuarioCargoModel
 * =========================================================
 * Lee cargos adicionales del usuario
 * =========================================================
 */
class UsuarioCargoModel extends Model
{
    protected $table      = 'usuario_cargo';
    protected $returnType = 'array';
    protected $allowedFields = [
        'id_user',
        'id_cargo',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * =========================================================
     * Devuelve todos los cargos extra de un usuario
     * =========================================================
     */
    public function getCargoIdsByUser(int $idUser): array
    {
        $rows = $this->where('id_user', $idUser)->findAll();

        return array_map(
            static fn(array $row): int => (int) ($row['id_cargo'] ?? 0),
            $rows
        );
    }
}