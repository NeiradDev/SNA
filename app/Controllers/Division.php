<?php

namespace App\Controllers;

use App\Controllers\BaseController;


class Division extends BaseController
{
    public function index()
    {
        return view('pages/division_views/division');
    }
}