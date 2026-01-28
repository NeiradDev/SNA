<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Si no está logueado, manda al login
        if (! session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
