<?php

namespace App\Models;

use CodeIgniter\Model;

class AgenciaModel extends Model
{
    protected $table = 'agencias';
    protected $primaryKey = 'id_agencias';
    protected $allowedFields = ['nombre_agencia', 'direccion', 'ciudad'];
    protected $returnType = 'object';
    protected $useTimestamps = false; // Cambia a true si tienes campos created_at, updated_at
    
    // Si quieres validación automática
    protected $validationRules = [
        'nombre_agencia' => 'required|min_length[3]|max_length[100]',
        'direccion' => 'required|max_length[100]',
        'ciudad' => 'required|max_length[2]'
    ];
    
    protected $validationMessages = [
        'nombre_agencia' => [
            'required' => 'El nombre de la agencia es obligatorio',
            'min_length' => 'El nombre debe tener al menos 3 caracteres',
            'max_length' => 'El nombre no puede exceder 100 caracteres'
        ],
        'direccion' => [
            'required' => 'La dirección es obligatoria',
            'max_length' => 'La dirección no puede exceder 100 caracteres'
        ],
        'ciudad' => [
            'required' => 'La ciudad es obligatoria',
            'max_length' => 'La ciudad debe ser de 2 caracteres'
        ]
    ];
}