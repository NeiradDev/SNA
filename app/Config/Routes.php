<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Ruta para el Login (en raiz)
$routes->get('/', 'Auth::login'); 

// Ruta explícita para home:
$routes->get('home', 'Home::home');
