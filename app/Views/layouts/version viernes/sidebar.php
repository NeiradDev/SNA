<?php
/**
 * Sidebar SNA sin Bootstrap (HTML + CSS + JS vanilla)
 * Mantiene:
 *  - $menuItems con submenús, data-schedule e initial_visible
 *  - Perfil (imagen y nombre) con dropdown
 *  - Polling "Plan de Batalla" (reporte/plan-status)
 */

helper(['horario_plan', 'menu', 'url']);

/* === Construcción del menú (igual que tu original, sin Bootstrap) === */
$menuItems = [
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
  ['title' => 'Agencias',      'icon' => 'bi-houses',         'url' => 'agencias'],
  ['title' => 'Division',      'icon' => 'bi-grid',           'url' => 'division'],
  [
    'title' => 'Planificación',
    'icon'  => 'bi-calendar-check',
    'id'    => 'submenu-tareas',
    'sub'   => [
      ['title' => 'Calendario', 'url' => 'tareas/calendario'],
      ['title' => 'Asignar',    'url' => 'tareas/asignar'],
    ],
  ],
  ['title' => 'Usuarios',      'icon' => 'bi-people',         'url' => 'usuarios'],
  [
    'title' => 'Mantenimiento',
    'icon'  => 'bi-gear',
    'id'    => 'submenu-mantenimiento',
    'sub'   => [
      ['title' => 'Divisiones', 'url' => 'mantenimiento/divisiones'],
      ['title' => 'Areas',      'url' => 'mantenimiento/areas'],
      ['title' => 'Cargos',     'url' => 'mantenimiento/cargos'],
    ],
  ],
];

/* Extender desde BD (como en tu original) */
$menuItems = menu_build_items($menuItems);

/* Perfil desde sesión */
$logged    = (bool) session()->get('logged_in');
$nombres   = (string) session()->get('nombres');
$apellidos = (string) session()->get('apellidos');
$nombreCompleto = trim($nombres . ' ' . $apellidos);
$labelPerfil    = ($logged && $nombreCompleto !== '') ? $nombreCompleto : 'Mi perfil';
?>
<nav class="site-nav" id="site-nav" aria-label="Menú principal">
  <div class="name">
    Inicio
    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M11.5,22C11.64,22 11.77,22 11.9,21.96C12.55,21.82 13.09,21.38 13.34,20.78C13.44,20.54 13.5,20.27 13.5,20H9.5A2,2 0 0,0 11.5,22M18,10.5C18,7.43 15.86,4.86 13,4.18V3.5A1.5,1.5 0 0,0 11.5,2A1.5,1.5 0 0,0 10,3.5V4.18C7.13,4.86 5,7.43 5,10.5V16L3,18V19H20V18L18,16M19.97,10H21.97C21.82,6.79 20.24,3.97 17.85,2.15L16.42,3.58C18.46,5 19.82,7.35 19.97,10M6.58,3.58L5.15,2.15C2.76,3.97 1.18,6.79 1,10H3C3.18,7.35 4.54,5 6.58,3.58Z"></path>
    </svg>
  </div>

  <ul class="menu-root" id="menu">
    <?php foreach ($menuItems as $item): ?>
      <?php
        $hasSub = !empty($item['sub']) && is_array($item['sub']);
        $title  = $item['title'] ?? '';
        $icon   = $item['icon']  ?? '';
        $url    = $item['url']   ?? '#';
      ?>
      <li>
        <?php if ($hasSub): ?>
          <?php $menuId = esc($item['id'] ?? 'm_' . md5($title)); ?>
          <button
            class="menu-link has-children"
            type="button"
            aria-expanded="false"
            aria-controls="<?= $menuId ?>"
            data-menu-toggle="<?= $menuId ?>"
          >
            <?php if ($icon): ?><i class="<?= esc($icon) ?>" aria-hidden="true"></i><?php endif; ?>
            <span><?= esc($title) ?></span>
            <span class="caret" aria-hidden="true">▸</span>
          </button>

          <ul class="submenu" id="<?= $menuId ?>" hidden>
            <?php if (!empty($item['url'])): ?>
              <li>
                <a href="<?= base_url($item['url']) ?>" class="submenu-link">
                  <span class="chev">›</span> <?= esc($title) ?> (inicio)
                </a>
              </li>
            <?php endif; ?>

            <?php foreach ($item['sub'] as $sub): ?>
              <?php
                $subTitle       = $sub['title'] ?? '';
                $subUrl         = $sub['url']   ?? '#';
                $scheduleKey    = $sub['schedule_key'] ?? null;
                $initialVisible = $sub['initial_visible'] ?? true;
                $style          = $initialVisible ? '' : 'display:none;';
              ?>
              <li>
                <a href="<?= base_url($subUrl) ?>"
                   class="submenu-link"
                   <?= $scheduleKey ? 'data-schedule="'.esc($scheduleKey).'"' : '' ?>
                   style="<?= esc($style) ?>">
                  <span class="chev">›</span> <?= esc($subTitle) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <a href="<?= base_url($url) ?>" class="menu-link">
            <?php if ($icon): ?><i class="<?= esc($icon) ?>" aria-hidden="true"></i><?php endif; ?>
            <span><?= esc($title) ?></span>
          </a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <hr class="separator" aria-hidden="true">

  <!-- Perfil -->
  <div class="profile">
    <button
      class="profile-trigger"
      id="dropdownUser"
      type="button"
      aria-expanded="false"
      aria-controls="profile-menu"
      <?= $logged ? '' : 'disabled' ?>
    >
      <img src="<?= base_url('assets/img/img-login.png') ?>" alt="Usuario"
           width="30" height="30" class="avatar">
      <span class="profile-name"><?= esc($labelPerfil) ?></span>
      <span class="caret" aria-hidden="true">▾</span>
    </button>

    <ul class="profile-menu" id="profile-menu" hidden>
      <li>
        <?php if ($logged): ?>
          <a href="<?= base_url('perfil') ?>" class="profile-item">Perfil</a>
        <?php else: ?>
          <span class="profile-item disabled" aria-disabled="true">Perfil</span>
        <?php endif; ?>
      </li>
      <li><hr class="separator slim" aria-hidden="true"></li>
      <li>
        <?php if ($logged): ?>
          <form action="<?= base_url('logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="profile-item danger">Salir</button>
          </form>
        <?php else: ?>
          <span class="profile-item disabled" aria-disabled="true">Salir</span>
        <?php endif; ?>
      </li>
    </ul>
  </div>
</nav>

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