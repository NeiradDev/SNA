<?php

namespace App\Controllers;

class Auth extends BaseController
{
    public function login()
    {
        // llamado de vista del formulario
        return view('auth/login_view');
    }
}
