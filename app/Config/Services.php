<?php

namespace Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    // ... (otros métodos que ya tengas)

    /**
     * Servicio de dominio para Usuarios
     * - Shared por defecto (singleton de CI4)
     */
    public static function usuarioService($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('usuarioService');
        }

        return new \App\Services\UsuarioService(
            new \App\Models\UsuarioModel(),
            \Config\Services::validation()
        );
    }
    public static function divisionService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('divisionService');
    }

    return new \App\Services\DivisionService(
        new \App\Models\DivisionModel()
    );
}

}
