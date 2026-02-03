<?php

namespace App\Models;

use CodeIgniter\Model;

class TareaModel extends Model
{
    protected $table      = 'public.tareas';
    protected $primaryKey = 'id_tarea';

    protected $allowedFields = [
        'titulo','descripcion',
        'prioridad','estado',
        'fecha_inicio','fecha_fin',
        'id_area','asignado_a','asignado_por',
        'created_at','completed_at'
    ];

    protected $useTimestamps = false;
}
