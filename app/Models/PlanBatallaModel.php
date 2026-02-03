<?php

namespace App\Models;

use CodeIgniter\Model;

class PlanBatallaModel extends Model
{
    // Si te da problemas el schema, cambia a: 'plan_batalla'
    protected $table      = 'public.plan_batalla';
    protected $primaryKey = 'id_plan';

    protected $allowedFields = [
        'id_user',
        'cedula',
        'nombres',
        'apellidos',
        'id_area',
        'area_nombre',
        'id_cargo',
        'cargo_nombre',
        'id_supervisor',
        'jefe_inmediato',
        'condicion',
        'preguntas_json',
        'created_at',
    ];

    protected $useTimestamps = false;
}
