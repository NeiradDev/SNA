<?php

/* ===============================
   PERFIL DESDE SESIÓN
=================================*/
$logged         = (bool) session()->get('logged_in');
$nombres        = (string) session()->get('nombres');
$apellidos      = (string) session()->get('apellidos');
$nombreCompleto = trim($nombres . ' ' . $apellidos);
$labelPerfil    = ($logged && $nombreCompleto !== '') ? $nombreCompleto : 'Mi perfil';

/* ===============================
   MENÚ PERMITIDO (POR PERMISOS)
=================================*/
$menuAllowed = is_array($menuAllowed ?? null) ? $menuAllowed : [];

/* ===============================
   PERMISOS DIRECTOS (opcionales)
   Si BaseController envía $permissions,
   aquí los usamos para links de la barra superior.
=================================*/
$permissions = is_array($permissions ?? null) ? $permissions : [];

/**
 * =========================================================
 * Helper local para validar permiso
 * =========================================================
 */
if (!function_exists('sidebar_has_permission')) {
  function sidebar_has_permission(array $permissions, string $code): bool
  {
    return in_array($code, $permissions, true);
  }
}
?>

<header>
  <div class="left">
    <div class="menu-container">
      <div class="menu" id="menu">
        <div></div>
        <div></div>
        <div></div>
      </div>
    </div>

    <div class="brand" onclick="window.location.href='<?= base_url('home') ?>'">
      <img src="<?= base_url('assets/img/logo-bpc.png') ?>" alt="logo-bpc" class="logo">
    </div>
  </div>

  <div class="right">
    <?php if (sidebar_has_permission($permissions, 'tareas.satisfaccion') || (int) session()->get('id_cargo') === 1): ?>
      <a href="<?= base_url('tareas/satisfaccion') ?>">
        <img src="<?= base_url('assets/img/icons/browse-svgrepo-com.svg') ?>" alt="satisfaccion">
        <span class="text-mini">Satisfacción</span>
      </a>
    <?php endif; ?>

    <?php if (sidebar_has_permission($permissions, 'tareas.calendario') || (int) session()->get('id_cargo') === 1): ?>
      <a href="<?= base_url('tareas/calendario') ?>">
        <img src="<?= base_url('assets/img/icons/calendario2.svg') ?>" alt="calendario">
        <span class="text-mini">Calendario</span>
      </a>
    <?php endif; ?>

    <?php if (sidebar_has_permission($permissions, 'tareas.gestionar') || (int) session()->get('id_cargo') === 1): ?>
      <a href="<?= base_url('tareas/gestionar') ?>">
        <img src="<?= base_url('assets/img/icons/actividades2.svg') ?>" alt="actividades">
        <span class="text-mini">Actividades</span>
      </a>
    <?php endif; ?>

    <?php if (sidebar_has_permission($permissions, 'perfil.ver') || (int) session()->get('id_cargo') === 1): ?>
      <a href="<?= base_url('perfil') ?>">
        <img src="<?= base_url('assets/img/icons/user-svgrepo-com.svg') ?>" alt="perfil">
        <span class="text-mini"><?= esc($labelPerfil) ?></span>
      </a>
    <?php endif; ?>
  </div>
</header>

<div class="sidebar" id="sidebar">
  <nav>
    <ul>

      <!-- ========================= -->
      <!-- REPORTE -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['reporte'])): ?>
        <li class="has-sub">
          <a href="#" class="toggle">
            <img src="<?= base_url('assets/img/icons/report.svg') ?>" alt="reportes">
            <span>Reporte</span>
            <span class="arrow"></span>
          </a>

          <ul class="sub-menu">
            <?php if (in_array('horario_plan', $menuAllowed['reporte'], true)): ?>
              <li>
                <a href="<?= base_url('reporte/horario-plan') ?>">Horario Plan</a>
              </li>
            <?php endif; ?>

            <?php if (in_array('historico', $menuAllowed['reporte'], true)): ?>
              <li>
                <a href="<?= base_url('reporte/historico-plan') ?>">Histórico</a>
              </li>
            <?php endif; ?>

            <?php if (in_array('plan_batalla', $menuAllowed['reporte'], true)): ?>
              <li data-schedule="plan">
                <a href="<?= base_url('reporte/plan') ?>">Plan de batalla</a>
              </li>
            <?php endif; ?>

            <?php if (in_array('completado', $menuAllowed['reporte'], true)): ?>
              <li>
                <a href="<?= base_url('reporte/completado') ?>">Completado</a>
              </li>
            <?php endif; ?>
          </ul>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- AGENCIAS -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['agencias'])): ?>
        <li>
          <a href="<?= base_url('agencias') ?>">
            <img src="<?= base_url('assets/img/icons/agencias.svg') ?>" alt="agencias">
            <span>Agencias</span>
          </a>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- DIVISIÓN -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['division'])): ?>
        <li>
          <a href="<?= base_url('division') ?>">
            <img src="<?= base_url('assets/img/icons/information-svgrepo-com.svg') ?>" alt="division">
            <span>División</span>
          </a>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- PLANIFICACIÓN -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['planificacion'])): ?>
        <li class="has-sub">
          <a href="#" class="toggle">
            <img src="<?= base_url('assets/img/icons/inspiration-svgrepo-com.svg') ?>" alt="planificacion">
            <span>Planificación</span>
            <span class="arrow"></span>
          </a>

          <ul class="sub-menu">
            <li>
              <a href="<?= base_url('tareas/asignar') ?>">Crear tarea</a>
            </li>
          </ul>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- USUARIOS -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['usuarios'])): ?>
        <li>
          <a href="<?= base_url('usuarios') ?>">
            <img src="<?= base_url('assets/img/icons/table-of-contents-svgrepo-com.svg') ?>" alt="usuarios">
            <span>Usuarios</span>
          </a>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- MANTENIMIENTO -->
      <!-- ========================= -->
      <?php if (!empty($menuAllowed['mantenimiento'])): ?>
        <li class="has-sub">
          <a href="#" class="toggle">
            <img src="<?= base_url('assets/img/icons/set-up-svgrepo-com.svg') ?>" alt="mantenimiento">
            <span>Mantenimiento</span>
            <span class="arrow"></span>
          </a>

          <ul class="sub-menu">
            <li>
              <a href="<?= base_url('mantenimiento/divisiones') ?>">Divisiones</a>
            </li>
            <li>
              <a href="<?= base_url('mantenimiento/areas') ?>">Áreas</a>
            </li>
            <li>
              <a href="<?= base_url('mantenimiento/cargos') ?>">Cargos</a>
            </li>
            <li>
              <a href="<?= base_url('mantenimiento/permisos') ?>">Permisos</a>
            </li>
          </ul>
        </li>
      <?php endif; ?>

      <!-- ========================= -->
      <!-- LOGOUT -->
      <!-- ========================= -->
      <li>
        <a href="<?= base_url('logout') ?>">
          <img src="<?= base_url('assets/img/icons/logout.svg') ?>" alt="salir">
          <span>SALIR</span>
        </a>
      </li>

    </ul>
  </nav>
</div>

<script>
  /**
   * =========================================================
   * Toggle de submenús
   * =========================================================
   */
  document.querySelectorAll('.toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentElement.classList.toggle('open');
    });
  });

  /**
   * =========================================================
   * Plan de Batalla (visibilidad según horario dinámico)
   * =========================================================
   */
  var statusUrl = "<?= site_url('reporte/plan-status') ?>";

  function setPlanVisibility(isEnabled) {
    document.querySelectorAll('[data-schedule="plan"]').forEach(function(el) {
      el.style.display = isEnabled ? '' : 'none';
    });
  }

  async function refreshPlanStatus() {
    try {
      var res = await fetch(statusUrl, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!res.ok) return;

      var data = await res.json();

      if (data && typeof data.enabled !== 'undefined') {
        setPlanVisibility(!!data.enabled);
      }
    } catch (e) {
      // Silencioso por ahora
    }
  }

  refreshPlanStatus();
  setInterval(refreshPlanStatus, 5000);
</script>