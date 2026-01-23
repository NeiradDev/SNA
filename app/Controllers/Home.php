<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function home()
    {
        // llamado de vista del formulario
        return view('pages/home');
    }
}
