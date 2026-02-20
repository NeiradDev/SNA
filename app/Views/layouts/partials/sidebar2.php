<?php
/* Perfil desde sesión */
$logged    = (bool) session()->get('logged_in');
$nombres   = (string) session()->get('nombres');
$apellidos = (string) session()->get('apellidos');
$nombreCompleto = trim($nombres . ' ' . $apellidos);
$labelPerfil    = ($logged && $nombreCompleto !== '') ? $nombreCompleto : 'Mi perfil';
$menuAllowed = $menuAllowed ?? [];
?>

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
      <div class="brand">
        <img src="<?= base_url('assets/img/logo-bpc.png') ?>" alt="logo-bpc" class="logo">
        <span class="name"> SNA</span>
      </div> <!-- Cierre .brand -->
  </div> <!-- Cierre .left -->

  <div class="right">
    <a href="<?= base_url('tareas/satisfaccion') ?>" class="icons-header">
      <img src="<?= base_url('assets/img/icons/porcentaje.svg') ?>" alt="satisfaccion"></a>
    <a href="<?= base_url('tareas/calendario') ?>">
      <img src="<?= base_url('assets/img/icons/calendario.svg') ?>" alt="calendario"></a>
    <a href="<?= base_url('tareas/gestionar') ?>">
      <img src="<?= base_url('assets/img/icons/actividades.svg') ?>" alt="actividades"></a>
  </div>
</header>

<div class="sidebar" id="sidebar">
    <nav>
        <ul>

            <!-- REPORTE -->
            <li class="has-sub">
                <a href="#" class="toggle">
                    <img src="<?= base_url('assets/img/icons/report.svg') ?>" alt="">
                    <span>Reporte</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    <li><a href="<?= base_url('/reporte/horario-plan') ?>">Horario Plan</a></li>
                    <li><a href="#">Histórico</a></li>
                    <li><a href="<?= base_url('/reporte/plan') ?>">Horario Plan</a></li>
                    <li><a href="<?= base_url('/reporte/completado') ?>">Completado</a></li>
                </ul>
            </li>

            <!-- AGENCIAS -->
            <li>
                <a href="<?= base_url('/agencias') ?>">
                    <img src="<?= base_url('assets/img/icons/agencias.svg') ?>" alt="">
                    <span>Agencias</span>
                </a>
            </li>

            <!-- DIVISION -->
            <li>
                <a href="<?= base_url('/division') ?>">
                    <img src="<?= base_url('assets/img/icons/division.svg') ?>" alt="">
                    <span>División</span>
                </a>
            </li>

            <!-- PLANIFICACIÓN -->
            <li class="has-sub">
                <a href="#" class="toggle">
                    <img src="<?= base_url('assets/img/icons/planificacion.svg') ?>" alt="">
                    <span>Planificación</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    <li><a href="<?= base_url('/tareas/asignar') ?>">Asignar</a></li>
                </ul>
            </li>

            <!-- USUARIOS -->
            <li>
                <a href="<?= base_url('/usuarios') ?>">
                    <img src="<?= base_url('assets/img/icons/users.svg') ?>" alt="">
                    <span>Usuarios</span>
                </a>
            </li>

            <!-- MANTENIMIENTO -->
            <li class="has-sub">
                <a href="#" class="toggle">
                    <img src="<?= base_url('assets/img/icons/settings.svg') ?>" alt="">
                    <span>Mantenimiento</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    <li><a href="<?= base_url('/mantenimiento/divisiones') ?>">Divisiones</a></li>
                    <li><a href="<?= base_url('/mantenimiento/areas') ?>">Áreas</a></li>
                    <li><a href="<?= base_url('/mantenimiento/cargos') ?>">Cargos</a></li>
                </ul>
            </li>
            <!-- Logout -->
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
 * JS vanilla:
 * - Toggle de submenús
 * - Dropdown de perfil
 * - Polling para "Plan de Batalla"
 */
(
  // Dropdown de perfil
  var profileBtn = document.getElementById('dropdownUser');
  var profileMenu = document.getElementById('profile-menu');
  if (profileBtn && profileMenu && !profileBtn.hasAttribute('disabled')) {
    profileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var expanded = profileBtn.getAttribute('aria-expanded') === 'true';
      profileBtn.setAttribute('aria-expanded', String(!expanded));
      if (expanded) profileMenu.setAttribute('hidden', '');
      else profileMenu.removeAttribute('hidden');
    });
    document.addEventListener('click', function (e) {
      if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileBtn.setAttribute('aria-expanded', 'false');
        profileMenu.setAttribute('hidden', '');
      }
    });
  }

  // Polling: Plan de Batalla
  var statusUrl = "<?= site_url('reporte/plan-status') ?>";
  function setPlanVisibility(isEnabled) {
    document.querySelectorAll('[data-schedule="plan"]').forEach(function (el) {
      el.style.display = isEnabled ? '' : 'none';
    });
  }
  async function refreshPlanStatus() {
    try {
      var res = await fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      var data = await res.json();
      if (data && typeof data.enabled !== 'undefined') {
        setPlanVisibility(!!data.enabled);
      }
    } catch (e) { /* silencioso */ }
  }
  refreshPlanStatus();
  setInterval(refreshPlanStatus, 5000);
})();
</script>
