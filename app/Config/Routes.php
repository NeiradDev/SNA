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

    // ===== CRUD users (Users controller) =====
    $routes->get('users', 'Users::index');

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

    // ===== Link SNA =====
    $routes->get('link-sna-horario', 'LinkSnaHorario::index');
    $routes->post('link-sna-horario/save', 'LinkSnaHorario::save');
    $routes->get('api/link-sna-status', 'LinkSnaStatus::status');

    // ===== Módulos =====
    $routes->get('tareas', 'Tareas::index');
    $routes->get('perfil', 'Perfil::index');

    // ===== Tus rutas en español (Users_crt controller) =====
    $routes->get('usuarios', 'Users_crt::index');
    $routes->get('usuarios/nuevo', 'Users_crt::crear');
    $routes->post('usuarios/guardar', 'Users_crt::store');
});
