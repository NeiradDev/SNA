<?php
/* Perfil desde sesión */
$logged    = (bool) session()->get('logged_in');
$nombres   = (string) session()->get('nombres');
$apellidos = (string) session()->get('apellidos');
$nombreCompleto = trim($nombres . ' ' . $apellidos);
$labelPerfil    = ($logged && $nombreCompleto !== '') ? $nombreCompleto : 'Mi perfil';
?>


<header>
  <div class="left">
    <div class="menu-container">
      <div class="menu">
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
    <a href="<?= base_url('tareas/satisfaccion') ?>">
      <img src="<?= base_url('assets/img/icons/percent.svg') ?>" alt="satisfaccion"></a>
    <a href="<?= base_url('tareas/calendario') ?>">
      <img src="<?= base_url('assets/img/icons/calendar-week.svg') ?>" alt="calendario"></a>
    <a href="<?= base_url('tareas/gestionar') ?>">
      <img src="<?= base_url('assets/img/icons/bar-chart-line.svg') ?>" alt="actividades"></a>
  </div>
</header>
<div class="sidebar">
    <nav>
        <ul>
            <li>
                <a href="#">
                    <img src="<?= base_url('assets/icons/search.svg') ?>" alt="">
                    <span>Buscar</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <img src="<?= base_url('assets/icons/search.svg') ?>" alt="">
                    <span>Buscar</span>
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
(function () {
  // Toggle submenús
  document.querySelectorAll('[data-menu-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-menu-toggle');
      var submenu = document.getElementById(id);
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!expanded));
      if (submenu) {
        if (expanded) submenu.setAttribute('hidden', '');
        else submenu.removeAttribute('hidden');
      }
    });
  });

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