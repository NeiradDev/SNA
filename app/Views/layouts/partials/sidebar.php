<?php

helper('horario_plan');

$menuItems = [
  /*[
    'title' => 'Dashboard',
    'icon'  => 'bi-graph-up',
    'id'    => 'submenu-dashboard',
    'sub'   => [
      ['title' => 'Mi Equipo',   'url' => 'dashboard/equipo'],
      ['title' => 'Mi División', 'url' => 'dashboard/division'],
    ],
  ],*/
 [
  'title' => 'Reporte',
  'icon'  => 'bi-clipboard2-data',
  'id'    => 'submenu-reporte',
  'sub'   => [
 
    [
      'title'           => 'Plan de Batalla',
      'url'             => 'reporte/plan',
      'schedule_key'    => 'plan',
      'initial_visible' => isPlanEnabled(),
    ],

   
    [
      'title' => 'Horario Plan',
      'url'   => 'reporte/horario-plan',
    ],


    ['title' => 'Histórico', 'url' => 'reporte/historico'],
  ],
],
  [
    'title' => 'Agencias',
    'icon'  => 'bi-houses',
    'url'   => 'agencias'
  ],
  [
    'title' => 'Division',
    'icon'  => 'bi-grid',
    'url'   => 'division'
    /*'sub'   => [
      ['title' => 'TICs',           'url' => 'areas/tics'],
      ['title' => 'Contabilidad',   'url' => 'areas/contabilidad'],
      ['title' => 'Talento Humano', 'url' => 'areas/talento-humano'],
    ],*/
  ],
  [
    'title' => 'Planificación',
    'icon'  => 'bi-calendar-check',
    'id'    => 'submenu-tareas',
    'sub'   => [
      ['title' => 'Calendario', 'url' => 'tareas/calendario'],
      ['title' => 'Asignar',    'url' => 'tareas/asignar'],
    ],
  ],
  [
    'title' => 'Usuarios',
    'icon'  => 'bi-people',
    'url'   => 'usuarios'
  ],
 [
    'title' => 'Mantenimiento',
    'icon'  => 'bi bi-gear',
    'id'    => 'submenu-mantenimiento',
    'sub'   => [
      ['title' => 'Divisiones', 'url' => 'mantenimiento/divisiones'],
      ['title' => 'Areas',   'url' => 'mantenimiento/areas'],
      ['title' => 'Cargos',   'url' => 'mantenimiento/cargos'],
     
    ],
 ]
];

/**
 * ✅ Extender el submenu Áreas desde BD (sin duplicar)
 * NOTA: Esto puede agregar items sin schedule_key, y está bien.
 */
helper('menu');
$menuItems = menu_build_items($menuItems);

/**
 * ✅ Perfil: jalar nombre de sesión
 */
$logged    = (bool) session()->get('logged_in');
$nombres   = (string) session()->get('nombres');
$apellidos = (string) session()->get('apellidos');

$nombreCompleto = trim($nombres . ' ' . $apellidos);
$labelPerfil    = ($logged && $nombreCompleto !== '') ? $nombreCompleto : 'Mi perfil';
?>

<!-- ===== Sidebar fijo (desktop) ===== -->
<aside class="sna-sidebar col-auto px-sm-2 px-0 bg-dark d-none d-md-block" aria-label="Menú principal">
  <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white min-vh-100">

    <a href="<?= base_url('home') ?>" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
      <span class="fs-5 d-none d-sm-inline fw-bold">Inicio</span>
    </a>

    <nav class="w-100">
      <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start" id="menu">
        <?php foreach ($menuItems as $item): ?>
          <li class="nav-item w-100">
            <?php if (!empty($item['sub']) && is_array($item['sub'])): ?>
              <!-- Padre colapsable -->
              <a href="#<?= esc($item['id']) ?>"
                 data-bs-toggle="collapse"
                 class="nav-link px-0 align-middle text-white"
                 aria-expanded="false"
                 aria-controls="<?= esc($item['id']) ?>">
                <i class="fs-4 <?= esc($item['icon']) ?>"></i>
                <span class="ms-1 d-none d-sm-inline"><?= esc($item['title']) ?></span>
              </a>

              <ul class="collapse nav flex-column ms-3" id="<?= esc($item['id']) ?>" data-bs-parent="#menu">
                <?php if (!empty($item['url'])): ?>
                  <li class="w-100">
                    <a href="<?= base_url($item['url']) ?>" class="nav-link px-0 text-white-50 small font-monospace">
                      <span class="d-none d-sm-inline">› </span><?= esc($item['title']) ?> (inicio)
                    </a>
                  </li>
                <?php endif; ?>

                <?php foreach ($item['sub'] as $sub): ?>
                  <?php
                    /**
                     * ✅ Soporte de items programables:
                     * - schedule_key: etiqueta data-schedule
                     * - initial_visible: se oculta al render si false
                     */
                    $scheduleKey = $sub['schedule_key'] ?? null;
                    $initialVisible = $sub['initial_visible'] ?? true;
                    $style = $initialVisible ? '' : 'display:none;';
                  ?>
                  <li class="w-100">
                    <a href="<?= base_url($sub['url']) ?>"
                       class="nav-link px-0 text-white-50 small font-monospace"
                       <?= $scheduleKey ? 'data-schedule="'.esc($scheduleKey).'"' : '' ?>
                       style="<?= esc($style) ?>">
                      <span class="d-none d-sm-inline">› </span><?= esc($sub['title']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>

            <?php else: ?>
              <!-- Ítem simple -->
              <a href="<?= base_url($item['url'] ?? '#') ?>" class="nav-link px-0 align-middle text-white">
                <i class="fs-4 <?= esc($item['icon']) ?>"></i>
                <span class="ms-1 d-none d-sm-inline"><?= esc($item['title']) ?></span>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <hr class="w-100 border-secondary">

    <!-- ✅ PERFIL (DESKTOP) -->
    <div class="dropdown pb-4 w-100">
      <a href="<?= $logged ? '#' : 'javascript:void(0)' ?>"
         class="d-flex align-items-center text-white text-decoration-none dropdown-toggle <?= $logged ? '' : 'disabled' ?>"
         id="dropdownUser"
         data-bs-toggle="dropdown"
         aria-expanded="false">
        <img src="<?= base_url('assets/img/img-login.png') ?>" alt="User" width="30" height="30" class="rounded-circle shadow-sm">
        <span class="d-none d-sm-inline mx-2"><?= esc($labelPerfil) ?></span>
      </a>

      <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser">
        <li>
          <a class="dropdown-item <?= $logged ? '' : 'disabled' ?>" href="<?= $logged ? base_url('perfil') : '#' ?>">
            Perfil
          </a>
        </li>
        <li><hr class="dropdown-divider border-secondary"></li>
        <li>  
              <form action="<?= base_url('logout') ?>" method="post">
              <?= csrf_field() ?>
              <button type="submit" class="dropdown-item">Salir</button></form> 
        </li>
      </ul>
    </div>

  </div>
</aside>

<!-- ===== Offcanvas (móvil) ===== -->
<div class="offcanvas offcanvas-start text-bg-dark sna-offcanvas d-md-none" tabindex="-1" id="snaOffcanvas" aria-labelledby="snaOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="snaOffcanvasLabel">Menú</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column">

    <a href="<?= base_url('home') ?>" class="d-flex align-items-center text-white text-decoration-none mb-3">
      <span class="fs-6 fw-bold">Home</span>
    </a>

    <ul class="nav nav-pills flex-column mb-auto" aria-label="Menú móvil">
      <?php foreach ($menuItems as $item): ?>
        <li class="nav-item w-100 mb-1">
          <?php if (!empty($item['sub']) && is_array($item['sub'])): ?>
            <button class="btn w-100 text-start text-white d-flex align-items-center gap-2"
                    data-bs-toggle="collapse"
                    data-bs-target="#m-<?= esc($item['id']) ?>"
                    aria-expanded="false"
                    aria-controls="m-<?= esc($item['id']) ?>">
              <i class="fs-5 <?= esc($item['icon']) ?>"></i><span><?= esc($item['title']) ?></span>
            </button>

            <ul id="m-<?= esc($item['id']) ?>" class="collapse nav flex-column ms-4 mt-1">
              <?php if (!empty($item['url'])): ?>
                <li>
                  <a class="nav-link text-white-50 py-1" href="<?= base_url($item['url']) ?>" data-bs-dismiss="offcanvas">
                    › <?= esc($item['title']) ?> (inicio)
                  </a>
                </li>
              <?php endif; ?>

              <?php foreach ($item['sub'] as $sub): ?>
                <?php
                  $scheduleKey = $sub['schedule_key'] ?? null;
                  $initialVisible = $sub['initial_visible'] ?? true;
                  $style = $initialVisible ? '' : 'display:none;';
                ?>
                <li>
                  <a class="nav-link text-white-50 py-1"
                     href="<?= base_url($sub['url']) ?>"
                     data-bs-dismiss="offcanvas"
                     <?= $scheduleKey ? 'data-schedule="'.esc($scheduleKey).'"' : '' ?>
                     style="<?= esc($style) ?>">
                    › <?= esc($sub['title']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>

          <?php else: ?>
            <a class="nav-link text-white d-flex align-items-center gap-2"
               href="<?= base_url($item['url'] ?? '#') ?>"
               data-bs-dismiss="offcanvas">
              <i class="fs-5 <?= esc($item['icon']) ?>"></i><span><?= esc($item['title']) ?></span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <hr class="border-secondary">

    <!-- ✅ PERFIL (MÓVIL) -->
    <div class="mt-auto">
      <a href="<?= $logged ? '#' : 'javascript:void(0)' ?>"
         class="d-flex align-items-center text-white text-decoration-none dropdown-toggle <?= $logged ? '' : 'disabled' ?>"
         id="dropdownUserMobile"
         data-bs-toggle="dropdown"
         aria-expanded="false">
        <img src="<?= base_url('assets/img/img-login.png') ?>" alt="User" width="30" height="30" class="rounded-circle shadow-sm">
        <span class="mx-2"><?= esc($labelPerfil) ?></span>
      </a>

      <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUserMobile">
        <li>
          <a class="dropdown-item <?= $logged ? '' : 'disabled' ?>" href="<?= $logged ? base_url('perfil') : '#' ?>">
            Perfil
          </a>
        </li>
        <li><hr class="dropdown-divider border-secondary"></li>
        <li><a class="dropdown-item" href="<?= base_url('logout') ?>">Salir</a></li>
      </ul>
    </div>

  </div>
</div>

<script>
/**
 * ✅ Mostrar/Ocultar “Plan de Batalla” SIN refrescar página.
 * - Consulta /api/horario-plan/status cada 5 segundos.
 * - enabled=true => muestra data-schedule="plan"
 * - enabled=false => oculta
 */
(function planMenuAutoToggle() {

 const statusUrl = "<?= site_url('reporte/plan-status') ?>";
  /**
   * Función en inglés básico: setPlanVisibility
   */
  function setPlanVisibility(isEnabled) {
    document.querySelectorAll('[data-schedule="plan"]').forEach(el => {
      el.style.display = isEnabled ? '' : 'none';
    });
  }

  /**
   * Función en inglés básico: refreshPlanStatus
   */
  async function refreshPlanStatus() {
    try {
      const res = await fetch(statusUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await res.json();

      if (data && data.ok) {
        setPlanVisibility(!!data.enabled);
      }
    } catch (e) {
      // Si falla, no hacemos nada para evitar parpadeos.
    }
  }

  // Primera ejecución
  refreshPlanStatus();

  // Polling cada 5 segundos
  setInterval(refreshPlanStatus, 5000);

})();
</script>
