<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ======================================================
// AUTENTICACIÓN
// ======================================================
$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('auth/attempt', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');

// ======================================================
// RUTAS PROTEGIDAS (AUTH)
// ======================================================
$routes->group('', ['filter' => 'auth'], function (RouteCollection $routes) {

    // --------------------------------------------------
    // HOME / PERFIL
    // --------------------------------------------------
    $routes->get('home', 'Home::home');
    $routes->get('perfil', 'Perfil::index');
    $routes->post('perfil/update-credentials', 'Perfil::updateCredentials');
    // --------------------------------------------------
    // HORARIO PLAN / REPORTE
    // --------------------------------------------------
    $routes->get('reporte/horario-plan', 'HorarioPlan::index');
    $routes->get('reporte/plan-status', 'HorarioPlan::status');
    $routes->post('reporte/horario-plan/guardar', 'HorarioPlan::save');

    $routes->get('reporte/plan', 'Reporte::plan', ['filter' => 'horarioPlan']);
    $routes->post('reporte/plan', 'Reporte::storePlan', ['filter' => 'horarioPlan']);

    // --------------------------------------------------
    // USUARIOS
    // --------------------------------------------------
    $routes->group('usuarios', function (RouteCollection $routes) {
        $routes->get('', 'Usuarios::index');
        $routes->get('nuevo', 'Usuarios::create');
        $routes->post('guardar', 'Usuarios::store');
        $routes->get('editar/(:num)', 'Usuarios::edit/$1');
        $routes->post('actualizar/(:num)', 'Usuarios::update/$1');

        // APIs internas
        $routes->get('api/cargos', 'Usuarios::getCargosByArea');
        $routes->get('api/supervisores', 'Usuarios::getSupervisorsByArea');
        $routes->get('api/areas', 'Usuarios::getAreasByDivision');
        $routes->get('api/cargos-division', 'Usuarios::getCargosByDivision');
        $routes->get('api/gerencia-user', 'Usuarios::getGerenciaUser');
        $routes->get('api/division-boss', 'Usuarios::getDivisionBossByDivision');
        $routes->get('api/area-boss', 'Usuarios::getAreaBossByArea');
    });

    // --------------------------------------------------
    // AGENCIAS / AREAS / DIVISION / CARGOS
    // --------------------------------------------------
    $routes->group('agencias', fn($r) => $r->get('', 'Agencias::index'));
    $routes->group('areas', fn($r) => $r->get('', 'Areas::index'));

    $routes->group('division', function ($routes) {
        $routes->get('/', 'Division::index');
        $routes->get('crear', 'Division::create');
        $routes->post('guardar', 'Division::store');
    });

    $routes->group('cargos', function (RouteCollection $routes) {
        $routes->get('', 'Cargos::index');
        $routes->post('create', 'Cargos::create');
        $routes->get('edit/(:num)', 'Cargos::edit/$1');
        $routes->post('update/(:num)', 'Cargos::update/$1');
        $routes->post('delete/(:num)', 'Cargos::delete/$1');
    });

    // --------------------------------------------------
    // TAREAS (🔥 IMPORTANTE)
    // --------------------------------------------------
    $routes->group('tareas', function (RouteCollection $routes) {

        // ======================
        // VISTAS
        // ======================
        $routes->get('calendario', 'Tareas::calendario');

        $routes->get('asignar', 'Tareas::asignarForm');
        $routes->post('asignar', 'Tareas::asignarStore');

        $routes->get('gestionar', 'Tareas::gestionar');

        $routes->get('editar/(:num)', 'Tareas::editar/$1');
        $routes->post('actualizar/(:num)', 'Tareas::actualizar/$1');

        // ======================
        // SATISFACCIÓN
        // ======================
        $routes->get('satisfaccion', 'Tareas::satisfaccion');

        // ======================
        // APIs internas (calendario + combos)
        // ======================
        $routes->get('events', 'Tareas::events'); // ?scope=mine|assigned
        $routes->get('users-by-area/(:num)', 'Tareas::usersByArea/$1');

        // Endpoint antiguo (si aún lo usas en otra vista)
        $routes->post('completar/(:num)', 'Tareas::marcarCumplida/$1');

        // =========================================================
        // ✅ ESTADO (ALINEADO A TU gestionar.php)
        // =========================================================
        // Tu JS hace POST a: /tareas/estado/{id}
        // Envia: id_estado_tarea = 3|4
        // Controller: Tareas::estado($id) -> llama a cambiarEstado()
        $routes->post('estado/(:num)', 'Tareas::estado/$1');

        // =========================================================
        // ✅ REVISIÓN POR LOTE (ALIAS RECOMENDADO)
        // =========================================================
        // POST /tareas/revision-batch
        // Envia: task_ids[] y action=approve|reject
        $routes->post('revision-batch', 'Tareas::revisionBatch');

        // =========================================================
        // ✅ COMPATIBILIDAD (por si tenías endpoints anteriores)
        // =========================================================
        $routes->post('cambiar-estado/(:num)', 'Tareas::cambiarEstado/$1');
        $routes->post('revisar-lote', 'Tareas::revisarLote');
    });

    // --------------------------------------------------
    // MANTENIMIENTO
    // --------------------------------------------------
    $routes->group('mantenimiento', function ($routes) {

        $routes->get('divisiones', 'Mantenimiento\Division::index');
        $routes->get('divisiones/crear', 'Mantenimiento\Division::create');
        $routes->post('divisiones/guardar', 'Mantenimiento\Division::store');
        $routes->get('divisiones/ver/(:num)', 'Mantenimiento\Division::show/$1');
        $routes->get('divisiones/editar/(:num)', 'Mantenimiento\Division::edit/$1');
        $routes->post('divisiones/actualizar/(:num)', 'Mantenimiento\Division::update/$1');

        $routes->get('areas', 'Mantenimiento\Area::index');
        $routes->get('areas/crear', 'Mantenimiento\Area::create');
        $routes->post('areas/guardar', 'Mantenimiento\Area::store');
        $routes->get('areas/ver/(:num)', 'Mantenimiento\Area::show/$1');
        $routes->get('areas/editar/(:num)', 'Mantenimiento\Area::edit/$1');
        $routes->post('areas/actualizar/(:num)', 'Mantenimiento\Area::update/$1');

        $routes->get('cargos', 'Mantenimiento\Cargo::index');
        $routes->get('cargos/crear', 'Mantenimiento\Cargo::create');
        $routes->post('cargos/guardar', 'Mantenimiento\Cargo::store');
        $routes->get('cargos/ver/(:num)', 'Mantenimiento\Cargo::show/$1');
        $routes->get('cargos/editar/(:num)', 'Mantenimiento\Cargo::edit/$1');
        $routes->post('cargos/actualizar/(:num)', 'Mantenimiento\Cargo::update/$1');
    });

    // --------------------------------------------------
    // ORGCHART
    // --------------------------------------------------
    $routes->group('orgchart', function ($routes) {
        $routes->get('division/(:num)', 'OrgChart::division/$1');
        $routes->get('api/division/(:num)', 'OrgChart::divisionData/$1');
    });
    $routes->get('reporte/completado', 'Reporte::Completado');
});
