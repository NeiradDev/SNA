<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('auth/attempt', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');

$routes->group('', ['filter' => 'auth'], function (RouteCollection $routes) {

    //HOME principal (protegido)
    $routes->get('home', 'Home::home');


    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios', 'Usuarios::index');

    $routes->get('usuarios/nuevo', 'Usuarios::create');
    $routes->post('usuarios/guardar', 'Usuarios::store');
    $routes->get('usuarios/editar/(:num)', 'Usuarios::edit/$1');
    $routes->post('usuarios/actualizar/(:num)', 'Usuarios::update/$1');
    $routes->get('usuarios/api/cargos', 'Usuarios::getCargosByArea');
    $routes->get('usuarios/api/supervisores', 'Usuarios::getSupervisorsByArea');


    // ===== Agencias =====
    $routes->get('agencias', 'Agencias::index');

    // ===== Areas =====
    $routes->get('areas', 'Areas::index');

    // ===== Cargos =====
    $routes->get('cargos', 'Cargos::index');
    $routes->post('cargos/create', 'Cargos::create');
    $routes->get('cargos/edit/(:num)', 'Cargos::edit/$1');
    $routes->post('cargos/update/(:num)', 'Cargos::update/$1');
    $routes->post('cargos/delete/(:num)', 'Cargos::delete/$1');


});
