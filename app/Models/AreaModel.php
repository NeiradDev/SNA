<?php

namespace App\Models;

use CodeIgniter\Model;

class AreaModel extends Model
{
    protected $table      = 'area';
    protected $primaryKey = 'ID_AREA';
    protected $returnType = 'array';
    protected $allowedFields = ['NOMBRE_AREA'];
}
