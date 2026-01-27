<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Ruta para el Login (en raiz)
$routes->get('/', 'Auth::login'); 

// Ruta explícita para home:
$routes->get('home', 'Home::home');
// RUTA PARA VER USUARIOS
$routes->get('usuarios', 'Users_crt::index');
// RUTA PARA CREAR USUARIOS
$routes->get('usuarios/nuevo', 'Users_crt::crear');
$routes->post('usuarios/guardar', 'Users_crt::store'); // procesar el formulario