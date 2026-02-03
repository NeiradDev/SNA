<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model de la tabla link_sna_schedule (PostgreSQL).
 */
class ProgramacionEnlaceModel extends Model
{
    protected $table      = 'link_sna_schedule';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'enable_dow',
        'enable_time',
        'disable_dow',
        'disable_time',
        'timezone',
        'active',
        'updated_at',
    ];

    protected $useTimestamps = false;
}
