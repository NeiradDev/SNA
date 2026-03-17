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

    // ==================================================
    // HOME / PERFIL
    // ==================================================
    $routes->get('home', 'Home::home');
    $routes->get('perfil', 'Perfil::index', ['filter' => 'permission:perfil.ver']);
    $routes->post('perfil/update-credentials', 'Perfil::updateCredentials', ['filter' => 'permission:perfil.ver']);

    // ==================================================
    // HORARIO PLAN / REPORTE
    // ==================================================
    $routes->get('reporte/horario-plan', 'HorarioPlan::index', ['filter' => 'permission:reporte.horario_plan']);
    $routes->get('reporte/plan-status', 'HorarioPlan::status', ['filter' => 'permission:reporte.plan']);
    $routes->post('reporte/horario-plan/guardar', 'HorarioPlan::save', ['filter' => 'permission:reporte.horario_plan']);

    // --------------------------------------------------
    // PLAN DE BATALLA
    // --------------------------------------------------
    $routes->get('reporte/plan', 'Reporte::plan', ['filter' => 'permission:reporte.plan']);
    $routes->post('reporte/plan', 'Reporte::storePlan', ['filter' => 'permission:reporte.plan']);

    // --------------------------------------------------
    // HISTÓRICO / COMPLETADO
    // --------------------------------------------------
    $routes->get('reporte/completado', 'Reporte::Completado', ['filter' => 'permission:reporte.completado']);
    $routes->get('reporte/historico-plan', 'HistoricoPlan::index', ['filter' => 'permission:reporte.historico']);
    $routes->get('reporte/historico-plan/pdf', 'HistoricoPlan::pdf', ['filter' => 'permission:reporte.historico']);

    // ==================================================
    // USUARIOS
    // ==================================================
    $routes->group('usuarios', ['filter' => 'permission:usuarios.ver'], function (RouteCollection $routes) {

        $routes->get('', 'Usuarios::index');

        $routes->get('nuevo', 'Usuarios::create', ['filter' => 'permission:usuarios.crear']);
        $routes->post('guardar', 'Usuarios::store', ['filter' => 'permission:usuarios.crear']);

        $routes->get('editar/(:num)', 'Usuarios::edit/$1', ['filter' => 'permission:usuarios.editar']);
        $routes->post('actualizar/(:num)', 'Usuarios::update/$1', ['filter' => 'permission:usuarios.editar']);

        // APIs internas
        $routes->get('api/cargos', 'Usuarios::getCargosByArea', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/supervisores', 'Usuarios::getSupervisorsByArea', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/areas', 'Usuarios::getAreasByDivision', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/cargos-division', 'Usuarios::getCargosByDivision', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/gerencia-user', 'Usuarios::getGerenciaUser', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/division-boss', 'Usuarios::getDivisionBossByDivision', ['filter' => 'permission:usuarios.ver']);
        $routes->get('api/area-boss', 'Usuarios::getAreaBossByArea', ['filter' => 'permission:usuarios.ver']);
    });

    // ==================================================
    // AGENCIAS
    // ==================================================
    $routes->group('agencias', ['filter' => 'permission:agencias.ver'], function (RouteCollection $routes) {
        $routes->get('', 'Agencias::index');
    });

    // ==================================================
    // ÁREAS
    // ==================================================
    $routes->group('areas', ['filter' => 'permission:areas.ver'], function (RouteCollection $routes) {
        $routes->get('', 'Areas::index');
    });

    // ==================================================
    // DIVISIÓN
    // IMPORTANTE:
    // también deja entrar con orgchart.ver
    // ==================================================
    $routes->group('division', ['filter' => 'permission:division.ver,orgchart.ver'], function (RouteCollection $routes) {
        $routes->get('/', 'Division::index');
        $routes->get('', 'Division::index');
        $routes->get('crear', 'Division::create', ['filter' => 'permission:division.crear']);
        $routes->post('guardar', 'Division::store', ['filter' => 'permission:division.crear']);
    });

    // ==================================================
    // CARGOS
    // ==================================================
    $routes->group('cargos', ['filter' => 'permission:cargos.ver'], function (RouteCollection $routes) {
        $routes->get('', 'Cargos::index');
        $routes->post('create', 'Cargos::create', ['filter' => 'permission:cargos.crear']);
        $routes->get('edit/(:num)', 'Cargos::edit/$1', ['filter' => 'permission:cargos.editar']);
        $routes->post('update/(:num)', 'Cargos::update/$1', ['filter' => 'permission:cargos.editar']);
        $routes->post('delete/(:num)', 'Cargos::delete/$1', ['filter' => 'permission:cargos.eliminar']);
    });

    // ==================================================
    // TAREAS
    // ==================================================
    $routes->group('tareas', function (RouteCollection $routes) {

        // --------------------------------------------------
        // VISTAS PRINCIPALES
        // --------------------------------------------------
        $routes->get('calendario', 'Tareas::calendario', ['filter' => 'permission:tareas.calendario']);

        $routes->get('asignar', 'Tareas::asignarForm', ['filter' => 'permission:tareas.asignar']);
        $routes->post('asignar', 'Tareas::asignarStore', ['filter' => 'permission:tareas.asignar']);

        $routes->get('gestionar', 'Tareas::gestionar', ['filter' => 'permission:tareas.gestionar']);

        $routes->get('editar/(:num)', 'Tareas::editar/$1', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('actualizar/(:num)', 'Tareas::actualizar/$1', ['filter' => 'permission:tareas.gestionar']);

        // --------------------------------------------------
        // SATISFACCIÓN
        // --------------------------------------------------
        $routes->get('satisfaccion', 'Tareas::satisfaccion', ['filter' => 'permission:tareas.satisfaccion']);

        // --------------------------------------------------
        // APIs internas
        // --------------------------------------------------
        $routes->get('events', 'Tareas::events', ['filter' => 'permission:tareas.calendario']);
        $routes->get('users-by-area/(:num)', 'Tareas::usersByArea/$1', ['filter' => 'permission:tareas.asignar']);

        // --------------------------------------------------
        // ESTADOS / ACCIONES
        // --------------------------------------------------
        $routes->post('completar/(:num)', 'Tareas::marcarCumplida/$1', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('estado/(:num)', 'Tareas::estado/$1', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('revision-batch', 'Tareas::revisionBatch', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('cancelar/(:num)', 'Tareas::cancelar/$1', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('cambiar-estado/(:num)', 'Tareas::cambiarEstado/$1', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('revisar-lote', 'Tareas::revisarLote', ['filter' => 'permission:tareas.gestionar']);

        // --------------------------------------------------
        // REVISIÓN SUPERVISOR
        // --------------------------------------------------
        $routes->post('revision/cancel-request', 'Tareas::cancelReviewRequest', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('revision/approve-done', 'Tareas::approveReviewAsDone', ['filter' => 'permission:tareas.gestionar']);
        $routes->post('revision/force-not-done', 'Tareas::forceReviewAsNotDone', ['filter' => 'permission:tareas.gestionar']);

        // --------------------------------------------------
        // ACTUALIZAR SOLO HORA DE FECHA FIN
        // --------------------------------------------------
        $routes->post('review-update-time/(:num)', 'Tareas::reviewUpdateTime/$1', ['filter' => 'permission:tareas.gestionar']);

        // --------------------------------------------------
        // NOTIFICACIONES DE DECISIÓN
        // --------------------------------------------------
        $routes->post('decision-seen', 'Tareas::markDecisionSeen', ['filter' => 'permission:tareas.gestionar']);
    });

    // ==================================================
    // MANTENIMIENTO
    // ==================================================
    $routes->group('mantenimiento', function (RouteCollection $routes) {

        // ----------------------------------------------
        // DIVISIONES
        // ----------------------------------------------
        $routes->get('divisiones', 'Mantenimiento\Division::index', ['filter' => 'permission:mantenimiento.divisiones.ver']);
        $routes->get('divisiones/crear', 'Mantenimiento\Division::create', ['filter' => 'permission:mantenimiento.divisiones.crear']);
        $routes->post('divisiones/guardar', 'Mantenimiento\Division::store', ['filter' => 'permission:mantenimiento.divisiones.crear']);
        $routes->get('divisiones/ver/(:num)', 'Mantenimiento\Division::show/$1', ['filter' => 'permission:mantenimiento.divisiones.ver']);
        $routes->get('divisiones/editar/(:num)', 'Mantenimiento\Division::edit/$1', ['filter' => 'permission:mantenimiento.divisiones.editar']);
        $routes->post('divisiones/actualizar/(:num)', 'Mantenimiento\Division::update/$1', ['filter' => 'permission:mantenimiento.divisiones.editar']);

        // ----------------------------------------------
        // ÁREAS
        // ----------------------------------------------
        $routes->get('areas', 'Mantenimiento\Area::index', ['filter' => 'permission:mantenimiento.areas.ver']);
        $routes->get('areas/crear', 'Mantenimiento\Area::create', ['filter' => 'permission:mantenimiento.areas.crear']);
        $routes->post('areas/guardar', 'Mantenimiento\Area::store', ['filter' => 'permission:mantenimiento.areas.crear']);
        $routes->get('areas/ver/(:num)', 'Mantenimiento\Area::show/$1', ['filter' => 'permission:mantenimiento.areas.ver']);
        $routes->get('areas/editar/(:num)', 'Mantenimiento\Area::edit/$1', ['filter' => 'permission:mantenimiento.areas.editar']);
        $routes->post('areas/actualizar/(:num)', 'Mantenimiento\Area::update/$1', ['filter' => 'permission:mantenimiento.areas.editar']);

        // ----------------------------------------------
        // CARGOS
        // ----------------------------------------------
        $routes->get('cargos', 'Mantenimiento\Cargo::index', ['filter' => 'permission:mantenimiento.cargos.ver']);
        $routes->get('cargos/crear', 'Mantenimiento\Cargo::create', ['filter' => 'permission:mantenimiento.cargos.crear']);
        $routes->post('cargos/guardar', 'Mantenimiento\Cargo::store', ['filter' => 'permission:mantenimiento.cargos.crear']);
        $routes->get('cargos/ver/(:num)', 'Mantenimiento\Cargo::show/$1', ['filter' => 'permission:mantenimiento.cargos.ver']);
        $routes->get('cargos/editar/(:num)', 'Mantenimiento\Cargo::edit/$1', ['filter' => 'permission:mantenimiento.cargos.editar']);
        $routes->post('cargos/actualizar/(:num)', 'Mantenimiento\Cargo::update/$1', ['filter' => 'permission:mantenimiento.cargos.editar']);

        // ----------------------------------------------
        // PERMISOS POR CARGO
        // ----------------------------------------------
        $routes->get('permisos', 'Mantenimiento\Permisos::index');
        $routes->post('permisos/guardar', 'Mantenimiento\Permisos::save');
    });

    // ==================================================
    // ORGCHART
    // ==================================================
    $routes->group('orgchart', ['filter' => 'permission:orgchart.ver'], function (RouteCollection $routes) {
        $routes->get('division/(:num)', 'OrgChart::division/$1');
        $routes->get('api/division/(:num)', 'OrgChart::divisionData/$1');
    });

    // ==================================================
    // AUDITORÍA DE TAREAS
    // ==================================================
    $routes->get('tareas/audit/(:num)', 'Admin\Tareas::audit/$1', ['filter' => 'permission:tareas.gestionar']);
});
