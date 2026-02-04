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

    // HOME
    $routes->get('home', 'Home::home');
    $routes->get('perfil', 'Perfil::index');

    // ✅ Vista para editar el horario (UN SOLO HORARIO)
    $routes->get('reporte/horario-plan', 'HorarioPlan::index');

    // ✅ Ruta interna para que el sidebar consulte si el Plan está habilitado
    // (NO /api, NO externo, lee la BD)
    $routes->get('reporte/plan-status', 'HorarioPlan::status');

    // ✅ Guardar horario (AJAX interno, NO /api, lee/graba BD)
    $routes->post('reporte/horario-plan/guardar', 'HorarioPlan::save');

    // ✅ Plan de Batalla protegido por horario
    $routes->get('reporte/plan', 'Reporte::plan', ['filter' => 'horarioPlan']);
    $routes->post('reporte/plan', 'Reporte::storePlan', ['filter' => 'horarioPlan']);

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
    // ===== Division =====
    $routes->group('division', function ($routes) {
        $routes->get('/', 'Division::index');
        $routes->get('crear', 'Division::create');
        $routes->post('guardar', 'Division::store');
    });

    // ===== Cargos =====
    $routes->group('cargos', function (RouteCollection $routes) {
        $routes->get('', 'Cargos::index');
        $routes->post('create', 'Cargos::create');
        $routes->get('edit/(:num)', 'Cargos::edit/$1');
        $routes->post('update/(:num)', 'Cargos::update/$1');
        $routes->post('delete/(:num)', 'Cargos::delete/$1');
    });
        $routes->get('areas/(:segment)', 'Areas::view/$1');
        $routes->get('areas/orgchart-data/(:segment)', 'Areas::data/$1');
        $routes->get('reporte/plan', 'Reporte::plan');
        $routes->post('reporte/plan', 'Reporte::storePlan');
        $routes->group('tareas', function ($routes) {
        $routes->get('calendario', 'Tareas::calendario');
        $routes->get('asignar', 'Tareas::asignarForm');
        $routes->post('asignar', 'Tareas::asignarStore');

        // API
        $routes->get('events', 'Tareas::events'); // ?scope=mine|assigned
        $routes->get('users-by-area/(:num)', 'Tareas::usersByArea/$1');
        $routes->post('completar/(:num)', 'Tareas::marcarCumplida/$1');
    });
});
