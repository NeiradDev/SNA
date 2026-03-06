<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * =========================================================
 * Modelo: PermisoModel
 * =========================================================
 * Maneja la tabla public.permiso
 * =========================================================
 */
class PermisoModel extends Model
{
    protected $table            = 'permiso';
    protected $primaryKey       = 'id_permiso';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'codigo',
        'nombre_permiso',
        'modulo',
        'descripcion',
        'activo',
        'created_at',
    ];

    protected $useTimestamps = false;
}