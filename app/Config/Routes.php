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

    //Mantienes dashboard (ya no es el principal)
    $routes->get('dashboard', 'Dashboard::index');

    // ===== CRUD users (Users controller) =====
    $routes->get('users', 'Users::index');
    $routes->get('users/new', 'Users::new');
    $routes->post('users', 'Users::create');

    $routes->get('users/edit/(:num)', 'Users::edit/$1');
    $routes->post('users/update/(:num)', 'Users::update/$1');
    $routes->get('users/toggle/(:num)', 'Users::toggle/$1');

    $routes->get('api/cargos/(:num)', 'Users::cargosByArea/$1');
    $routes->get('api/supervisores/(:num)', 'Users::supervisoresByArea/$1');

    // ===== Agencias =====
    $routes->get('agencias', 'Agencias::index');
    $routes->post('agencias/create', 'Agencias::create');
    $routes->get('agencias/edit/(:num)', 'Agencias::edit/$1');
    $routes->post('agencias/update/(:num)', 'Agencias::update/$1');
    $routes->get('agencias/delete/(:num)', 'Agencias::delete/$1');

    // ===== Areas =====
    $routes->get('areas', 'Areas::index');
    $routes->post('areas/create', 'Areas::create');
    $routes->get('areas/edit/(:num)', 'Areas::edit/$1');
    $routes->post('areas/update/(:num)', 'Areas::update/$1');
    $routes->post('areas/delete/(:num)', 'Areas::delete/$1');

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
