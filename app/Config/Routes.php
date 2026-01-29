<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('auth/attempt', 'Auth::attempt');
$routes->post('logout', 'Auth::logout');

$routes->group('', ['filter' => 'auth'], function (RouteCollection $routes) {

    //HOME principal (protegido)
    $routes->get('home', 'Home::home');

    $routes->get('perfil', 'Perfil::index');

    // ===== Usuarios =====
    $routes->group('usuarios', function (RouteCollection $routes) {
        $routes->get('', 'Usuarios::index');
        $routes->get('nuevo', 'Usuarios::create');
        $routes->post('guardar', 'Usuarios::store');
        $routes->get('editar/(:num)', 'Usuarios::edit/$1');
        $routes->post('actualizar/(:num)', 'Usuarios::update/$1');
        $routes->get('api/cargos', 'Usuarios::getCargosByArea');
        $routes->get('api/supervisores', 'Usuarios::getSupervisorsByArea');
    });

    // ===== API =====
    $routes->group('api', function (RouteCollection $routes) {
        $routes->get('metricas/cumplimiento', 'Api\Metricas::cumplimiento');
    });

    // ===== Agencias =====
    $routes->group('agencias', function (RouteCollection $routes) {
        $routes->get('', 'Agencias::index');
    });

    // ===== Areas =====
    $routes->group('areas', function (RouteCollection $routes) {
        $routes->get('', 'Areas::index');
    });

    // ===== Cargos =====
    $routes->group('cargos', function (RouteCollection $routes) {
        $routes->get('', 'Cargos::index');
        $routes->post('create', 'Cargos::create');
        $routes->get('edit/(:num)', 'Cargos::edit/$1');
        $routes->post('update/(:num)', 'Cargos::update/$1');
        $routes->post('delete/(:num)', 'Cargos::delete/$1');
    });
});
