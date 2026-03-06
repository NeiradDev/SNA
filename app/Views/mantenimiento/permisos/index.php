<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
  /* =========================================================
     Vista: mantenimiento/permisos/index.php
     ========================================================= */

  .ws-page-wrap{
    display:flex;
    flex-direction:column;
    gap:16px;
  }

  .ws-card{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.045);
    border-radius:18px;
    padding:18px;
    box-shadow:0 14px 34px -18px rgba(0,0,0,.45);
  }

  .ws-divider{
    height:1px;
    background:linear-gradient(90deg, transparent, rgba(255,255,255,.18), transparent);
    margin:14px 0;
  }

  .ws-hero{
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
  }

  .ws-title{
    font-weight:900;
    letter-spacing:.4px;
    text-transform:uppercase;
    margin:0 0 6px 0;
  }

  .ws-subtitle{
    opacity:.88;
    font-size:.96rem;
    max-width:850px;
    line-height:1.5;
  }

  .ws-badge-box{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }

  .ws-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.05);
    border-radius:999px;
    padding:.45rem .8rem;
    font-size:.88rem;
    font-weight:700;
  }

  .ws-alert{
    border-radius:14px;
  }

  .ws-grid-top{
    display:grid;
    grid-template-columns: minmax(360px, 1.5fr) minmax(280px, .95fr);
    gap:16px;
    align-items:stretch;
  }

  .ws-field-label{
    font-weight:900;
    margin-bottom:8px;
    letter-spacing:.2px;
  }

  .ws-help-list{
    margin:0;
    padding-left:18px;
    opacity:.92;
  }

  .ws-help-list li + li{
    margin-top:7px;
  }

  .ws-summary-grid{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:12px;
  }

  .ws-stat{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.04);
    border-radius:14px;
    padding:14px;
  }

  .ws-stat-label{
    font-size:.85rem;
    opacity:.75;
    margin-bottom:4px;
  }

  .ws-stat-value{
    font-size:1.12rem;
    font-weight:900;
    line-height:1.2;
  }

  .ws-toolbar{
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    gap:12px;
    align-items:center;
  }

  .ws-toolbar-left,
  .ws-toolbar-right{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
  }

  .btn-soft{
    border:1px solid rgba(255,255,255,.16);
    background:rgba(255,255,255,.06);
    color:inherit;
    border-radius:10px;
    padding:.5rem .8rem;
    font-size:.9rem;
    font-weight:700;
    transition:.18s ease;
  }

  .btn-soft:hover{
    background:rgba(255,255,255,.12);
    color:inherit;
    transform:translateY(-1px);
  }

  .ws-mode-box{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.035);
    border-radius:14px;
    padding:12px;
  }

  .ws-mode-options{
    display:flex;
    flex-wrap:wrap;
    gap:18px;
  }

  .ws-base-box{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.03);
    border-radius:14px;
    padding:14px;
  }

  .ws-target-box{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.03);
    border-radius:14px;
    padding:14px;
  }

  .ws-selected-note{
    margin-top:10px;
    font-size:.92rem;
    opacity:.86;
  }

  .ws-mobile-note{
    font-size:.88rem;
    opacity:.8;
  }

  .ws-target-list{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:10px;
    max-height:280px;
    overflow:auto;
    padding-right:4px;
  }

  .ws-target-item{
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.03);
    border-radius:12px;
    padding:10px 12px;
    transition:.18s ease;
  }

  .ws-target-item:hover{
    border-color:rgba(255,255,255,.18);
    background:rgba(255,255,255,.05);
  }

  .ws-target-item.is-checked{
    border-color:rgba(13,110,253,.55);
    background:rgba(13,110,253,.12);
    box-shadow:0 0 0 1px rgba(13,110,253,.18) inset;
  }

  .ws-module-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:14px;
  }

  .ws-module-card{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.035);
    border-radius:16px;
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:12px;
  }

  .ws-module-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(255,255,255,.12);
  }

  .ws-module-title{
    font-weight:900;
    letter-spacing:.3px;
    text-transform:uppercase;
    margin:0;
    font-size:1rem;
  }

  .ws-module-sub{
    font-size:.85rem;
    opacity:.76;
    margin-top:4px;
  }

  .ws-counter{
    white-space:nowrap;
    font-size:.84rem;
    padding:.3rem .6rem;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.05);
    font-weight:800;
  }

  .ws-module-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(255,255,255,.10);
  }

  /* =========================================================
     CHECKS EN HORIZONTAL
     ========================================================= */
  .ws-permissions-list{
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap:10px;
  }

  .ws-permission-item{
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.03);
    border-radius:12px;
    padding:12px;
    transition:.18s ease;
    min-height:118px;
  }

  .ws-permission-item:hover{
    background:rgba(255,255,255,.06);
    border-color:rgba(255,255,255,.20);
  }

  .ws-permission-item.is-checked{
    border-color:rgba(25,135,84,.62);
    background:rgba(25,135,84,.12);
    box-shadow:0 0 0 1px rgba(25,135,84,.18) inset;
  }

  .ws-permission-row{
    display:flex;
    align-items:flex-start;
    gap:12px;
  }

  .ws-permission-body{
    flex:1;
    min-width:0;
  }

  .ws-permission-name{
    font-weight:900;
    margin-bottom:4px;
    line-height:1.25;
  }

  .ws-permission-code{
    font-size:.82rem;
    opacity:.74;
    word-break:break-word;
    margin-bottom:5px;
    border-top:1px dashed rgba(255,255,255,.10);
    padding-top:5px;
  }

  .ws-permission-desc{
    font-size:.88rem;
    opacity:.9;
    line-height:1.35;
  }

  /* =========================================================
     CHECKS CON BORDE NEGRO
     ========================================================= */
  .permission-checkbox,
  .target-cargo-checkbox{
    width:1.15rem;
    height:1.15rem;
    border:2px solid #000000 !important;
    background-color:#ffffff !important;
    cursor:pointer;
    box-shadow:none !important;
    border-radius:.2rem;
  }

  .permission-checkbox:hover,
  .target-cargo-checkbox:hover{
    border-color:#000000 !important;
    box-shadow:0 0 0 2px rgba(0,0,0,.08) !important;
  }

  .permission-checkbox:focus,
  .target-cargo-checkbox:focus{
    border-color:#000000 !important;
    box-shadow:0 0 0 3px rgba(0,0,0,.12) !important;
  }

  .permission-checkbox:checked,
  .target-cargo-checkbox:checked{
    border-color:#000000 !important;
    background-color:#ffffff !important;
    box-shadow:0 0 0 2px rgba(0,0,0,.10) !important;
  }

  .ws-empty{
    border:1px dashed rgba(255,255,255,.18);
    border-radius:16px;
    padding:26px 18px;
    text-align:center;
    opacity:.88;
  }

  .ws-sticky-actions{
    position:sticky;
    bottom:12px;
    z-index:10;
  }

  .ws-save-bar{
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    align-items:center;
    gap:12px;
  }

  .ws-save-text{
    max-width:760px;
    opacity:.88;
  }

  @media (max-width: 1400px){
    .ws-permissions-list{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 1200px){
    .ws-summary-grid{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ws-target-list{
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 991px){
    .ws-grid-top{
      grid-template-columns: 1fr;
    }

    .ws-module-grid{
      grid-template-columns: 1fr;
    }

    .ws-permissions-list{
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 576px){
    .ws-summary-grid{
      grid-template-columns: 1fr;
    }

    .ws-card{
      padding:14px;
    }

    .ws-title{
      font-size:1.05rem;
    }

    .ws-subtitle{
      font-size:.92rem;
    }
  }
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php
$cargos                = is_array($cargos ?? null) ? $cargos : [];
$selectedCargoId       = (int) ($selectedCargoId ?? 0);
$permissionsByModule   = is_array($permissionsByModule ?? null) ? $permissionsByModule : [];
$assignedPermissionIds = is_array($assignedPermissionIds ?? null) ? $assignedPermissionIds : [];

if (!function_exists('e_perm_view')) {
    function e_perm_view($value): string
    {
        return esc((string) $value);
    }
}

if (!function_exists('module_label_view')) {
    function module_label_view(string $module): string
    {
        $map = [
            'home'          => 'Inicio',
            'perfil'        => 'Perfil',
            'usuarios'      => 'Usuarios',
            'agencias'      => 'Agencias',
            'division'      => 'División',
            'areas'         => 'Áreas',
            'cargos'        => 'Cargos',
            'tareas'        => 'Tareas',
            'reporte'       => 'Reporte',
            'mantenimiento' => 'Mantenimiento',
            'orgchart'      => 'Organigrama',
        ];

        return $map[$module] ?? ucfirst($module);
    }
}

if (!function_exists('cargo_label_view')) {
    function cargo_label_view(array $cargo): string
    {
        $nombre = trim((string) ($cargo['nombre_cargo'] ?? ''));
        $area   = trim((string) ($cargo['nombre_area'] ?? ''));
        $div    = trim((string) ($cargo['nombre_division'] ?? ''));

        if ($area !== '') {
            return $nombre . ' · Área: ' . $area;
        }

        if ($div !== '') {
            return $nombre . ' · División: ' . $div;
        }

        return $nombre;
    }
}

$selectedCargoLabel = '';
foreach ($cargos as $cargoItem) {
    if ((int) ($cargoItem['id_cargo'] ?? 0) === $selectedCargoId) {
        $selectedCargoLabel = cargo_label_view($cargoItem);
        break;
    }
}

$totalModules = count($permissionsByModule);
$totalPermissions = 0;

foreach ($permissionsByModule as $permissionList) {
    $totalPermissions += is_countable($permissionList) ? count($permissionList) : 0;
}

$totalAssigned = count($assignedPermissionIds);
$percentageAssigned = ($totalPermissions > 0)
    ? round(($totalAssigned / $totalPermissions) * 100)
    : 0;
?>

<div class="container-fluid py-4">
  <div class="ws-page-wrap">

    <div class="ws-card">
      <div class="ws-hero">
        <div>
          <h2 class="ws-title">Permisos por cargo</h2>
          <!--<div class="ws-subtitle">
            Administra de forma visual qué módulos, vistas y acciones puede usar cada cargo del sistema.
            Puedes cargar un cargo base para revisar sus permisos y, si deseas, aplicar esos cambios masivamente a varios cargos.
          </div>
        </div>-->

        
      </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
      <div class="alert alert-success ws-alert mb-0">
        <?= esc((string) session()->getFlashdata('success')) ?>
      </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
      <div class="alert alert-danger ws-alert mb-0">
        <?= esc((string) session()->getFlashdata('error')) ?>
      </div>
    <?php endif; ?>

    <div class="ws-grid-top">
      <div class="ws-card">
        <div class="ws-field-label">1. Cargo base para cargar permisos</div>

        <div class="ws-base-box">
          <form method="get" action="<?= site_url('mantenimiento/permisos') ?>" id="baseCargoForm">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-lg-9">
                <select name="cargo" id="cargo" class="form-select">
                  <option value="">-- Seleccionar cargo base --</option>
                  <?php foreach ($cargos as $cargo): ?>
                    <?php $cargoId = (int) ($cargo['id_cargo'] ?? 0); ?>
                    <option value="<?= $cargoId ?>" <?= $selectedCargoId === $cargoId ? 'selected' : '' ?>>
                      <?= e_perm_view(cargo_label_view($cargo)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-lg-3 d-grid">
                <button type="submit" class="btn btn-primary">
                  Cargar permisos
                </button>
              </div>
            </div>

            <?php if ($selectedCargoId > 0 && $selectedCargoLabel !== ''): ?>
              <div class="mt-3 ws-mobile-note">
                Cargo base cargado: <strong><?= e_perm_view($selectedCargoLabel) ?></strong>
              </div>
            <?php endif; ?>
          </form>
        </div>

        <div class="ws-divider"></div>

        <div class="ws-field-label">2. Aplicación del guardado</div>

        <div class="ws-mode-box">
          <div class="ws-mode-options">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="selection_mode" id="mode_single" checked>
              <label class="form-check-label" for="mode_single">
                Guardar solo en el cargo base
              </label>
            </div>

            <!--<div class="form-check">
              <input class="form-check-input" type="radio" name="selection_mode" id="mode_multi">
              <label class="form-check-label" for="mode_multi">
                Aplicar a varios cargos
              </label>
            </div>-->
          </div>
        </div>

        <div class="ws-target-box d-none" id="multiTargetBox">
          <div class="ws-field-label">3. Selecciona cargos destino</div>

          <div class="ws-target-list">
            <?php foreach ($cargos as $cargo): ?>
              <?php $cargoId = (int) ($cargo['id_cargo'] ?? 0); ?>
              <label class="ws-target-item" for="target_cargo_<?= $cargoId ?>">
                <div class="form-check m-0">
                  <input
                    class="form-check-input target-cargo-checkbox"
                    type="checkbox"
                    value="<?= $cargoId ?>"
                    id="target_cargo_<?= $cargoId ?>"
                    name="target_cargo_ids[]"
                  >
                  <span class="ms-2"><?= e_perm_view(cargo_label_view($cargo)) ?></span>
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="ws-selected-note" id="multiSelectedNote">
            No has seleccionado cargos destino aún.
          </div>
        </div>
      </div>

      <div class="ws-card">
        <div class="ws-field-label">Cómo usar esta pantalla</div>
        <ul class="ws-help-list">
          <li><strong>Paso 1:</strong> carga un cargo base para ver sus permisos actuales.</li>
          <li><strong>Paso 2:</strong> marca o desmarca permisos en la parte inferior.</li>
          <li><strong>Paso 3:</strong> decide si guardar solo en el cargo base o aplicar a varios cargos.</li>
          <li>Los permisos afectan tanto el menú visible como el acceso real a las rutas protegidas.</li>
        </ul>
      </div>
    </div>

    <?php if ($selectedCargoId <= 0): ?>
      <div class="ws-card">
        <div class="ws-empty">
          <h5 class="mb-2">Primero debes cargar un cargo</h5>
          <div>
            Selecciona un cargo en la parte superior y presiona <strong>Cargar permisos</strong>.
          </div>
        </div>
      </div>
    <?php else: ?>

      <div class="ws-summary-grid">
        <div class="ws-stat">
          <div class="ws-stat-label">Cargo base</div>
          <div class="ws-stat-value"><?= e_perm_view($selectedCargoLabel !== '' ? $selectedCargoLabel : ('ID ' . $selectedCargoId)) ?></div>
        </div>

        <div class="ws-stat">
          <div class="ws-stat-label">Módulos</div>
          <div class="ws-stat-value"><?= $totalModules ?></div>
        </div>

        <div class="ws-stat">
          <div class="ws-stat-label">Permisos activos</div>
          <div class="ws-stat-value"><?= $totalAssigned ?> / <?= $totalPermissions ?></div>
        </div>

        <div class="ws-stat">
          <div class="ws-stat-label">Cobertura</div>
          <div class="ws-stat-value"><?= $percentageAssigned ?>%</div>
        </div>
      </div>

      <form method="post" action="<?= site_url('mantenimiento/permisos/guardar') ?>" id="permissionForm">
        <?= csrf_field() ?>

        <input type="hidden" name="id_cargo" value="<?= $selectedCargoId ?>">
        <div id="multiTargetsHidden"></div>

        <div class="ws-card">
          <div class="ws-toolbar">
            <div class="ws-toolbar-left">
              <button type="button" class="btn-soft" onclick="checkAllPermissions(true)">Marcar todo</button>
              <button type="button" class="btn-soft" onclick="checkAllPermissions(false)">Desmarcar todo</button>
            </div>

            <div class="ws-toolbar-right">
              <button type="button" class="btn-soft" onclick="expandAllModules()">Expandir módulos</button>
              <button type="button" class="btn-soft" onclick="collapseAllModules()">Contraer módulos</button>
            </div>
          </div>
        </div>

        <div class="ws-module-grid">
          <?php foreach ($permissionsByModule as $module => $permissionList): ?>
            <?php
              $moduleKey      = preg_replace('/[^a-z0-9\-_]/i', '-', (string) $module);
              $moduleCount    = is_countable($permissionList) ? count($permissionList) : 0;
              $moduleAssigned = 0;

              foreach ($permissionList as $permission) {
                  $pid = (int) ($permission['id_permiso'] ?? 0);
                  if (in_array($pid, $assignedPermissionIds, true)) {
                      $moduleAssigned++;
                  }
              }
            ?>
            <div class="ws-module-card" data-module-card="<?= e_perm_view($moduleKey) ?>">
              <div class="ws-module-header">
                <div>
                  <h5 class="ws-module-title"><?= e_perm_view(module_label_view((string) $module)) ?></h5>
                  <div class="ws-module-sub">
                    <?= $moduleAssigned ?> asignados de <?= $moduleCount ?> permisos
                  </div>
                </div>

                <span class="ws-counter"><?= $moduleCount ?> permisos</span>
              </div>

              <div class="ws-module-actions">
                <button type="button" class="btn-soft" onclick="toggleModule('<?= e_perm_view($moduleKey) ?>', true)">
                  Marcar módulo
                </button>
                <button type="button" class="btn-soft" onclick="toggleModule('<?= e_perm_view($moduleKey) ?>', false)">
                  Limpiar módulo
                </button>
                <button type="button" class="btn-soft" onclick="toggleModuleBody('<?= e_perm_view($moduleKey) ?>')">
                  Mostrar / ocultar
                </button>
              </div>

              <div class="ws-permissions-list" id="module-body-<?= e_perm_view($moduleKey) ?>">
                <?php foreach ($permissionList as $permission): ?>
                  <?php
                    $permissionId   = (int) ($permission['id_permiso'] ?? 0);
                    $permissionName = (string) ($permission['nombre_permiso'] ?? '');
                    $permissionCode = (string) ($permission['codigo'] ?? '');
                    $permissionDesc = (string) ($permission['descripcion'] ?? '');
                    $isChecked      = in_array($permissionId, $assignedPermissionIds, true);
                  ?>
                  <div class="ws-permission-item <?= $isChecked ? 'is-checked' : '' ?>">
                    <div class="ws-permission-row">
                      <div class="form-check m-0">
                        <input
                          class="form-check-input permission-checkbox module-<?= e_perm_view($moduleKey) ?>"
                          type="checkbox"
                          name="permission_ids[]"
                          value="<?= $permissionId ?>"
                          id="perm_<?= $permissionId ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                          onchange="updateCheckedStyles()"
                        >
                      </div>

                      <div class="ws-permission-body">
                        <label class="w-100 m-0" for="perm_<?= $permissionId ?>">
                          <div class="ws-permission-name"><?= e_perm_view($permissionName) ?></div>
                          <div class="ws-permission-code"><?= e_perm_view($permissionCode) ?></div>

                          <?php if ($permissionDesc !== ''): ?>
                            <div class="ws-permission-desc"><?= e_perm_view($permissionDesc) ?></div>
                          <?php else: ?>
                            <div class="ws-permission-desc">Sin descripción adicional.</div>
                          <?php endif; ?>
                        </label>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="ws-sticky-actions mt-3">
          <div class="ws-card">
            <div class="ws-save-bar">
              <div class="ws-save-text">
                En modo individual se actualizará solo el cargo base cargado.  
                En modo masivo, se aplicarán los mismos permisos a todos los cargos destino seleccionados.
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= site_url('mantenimiento/permisos?cargo=' . $selectedCargoId) ?>" class="btn btn-outline-secondary">
                  Recargar
                </a>
                <button type="submit" class="btn btn-success">
                  Guardar permisos
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  function checkAllPermissions(state) {
    document.querySelectorAll('.permission-checkbox').forEach(function (checkbox) {
      checkbox.checked = !!state;
    });
    updateCheckedStyles();
  }

  function toggleModule(moduleKey, state) {
    document.querySelectorAll('.module-' + moduleKey).forEach(function (checkbox) {
      checkbox.checked = !!state;
    });
    updateCheckedStyles();
  }

  function toggleModuleBody(moduleKey) {
    var body = document.getElementById('module-body-' + moduleKey);
    if (!body) return;

    body.style.display = (body.style.display === 'none') ? '' : 'none';
  }

  function expandAllModules() {
    document.querySelectorAll('[id^="module-body-"]').forEach(function (el) {
      el.style.display = '';
    });
  }

  function collapseAllModules() {
    document.querySelectorAll('[id^="module-body-"]').forEach(function (el) {
      el.style.display = 'none';
    });
  }

  function updateCheckedStyles() {
    document.querySelectorAll('.ws-permission-item').forEach(function (item) {
      var checkbox = item.querySelector('.permission-checkbox');
      if (!checkbox) return;

      item.classList.toggle('is-checked', checkbox.checked);
    });

    document.querySelectorAll('.ws-target-item').forEach(function (item) {
      var checkbox = item.querySelector('.target-cargo-checkbox');
      if (!checkbox) return;

      item.classList.toggle('is-checked', checkbox.checked);
    });
  }

  function syncMultiTargetsToForm() {
    var hiddenContainer = document.getElementById('multiTargetsHidden');
    if (!hiddenContainer) return;

    hiddenContainer.innerHTML = '';

    document.querySelectorAll('.target-cargo-checkbox:checked').forEach(function (checkbox) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'target_cargo_ids[]';
      input.value = checkbox.value;
      hiddenContainer.appendChild(input);
    });
  }

  function updateMultiSelectedNote() {
    var checked = document.querySelectorAll('.target-cargo-checkbox:checked');
    var note = document.getElementById('multiSelectedNote');
    if (!note) return;

    note.textContent = checked.length === 0
      ? 'No has seleccionado cargos destino aún.'
      : 'Cargos destino seleccionados: ' + checked.length;
  }

  function updateSelectionMode() {
    var singleMode = document.getElementById('mode_single');
    var multiBox = document.getElementById('multiTargetBox');

    if (!singleMode || !multiBox) return;

    if (singleMode.checked) {
      multiBox.classList.add('d-none');
    } else {
      multiBox.classList.remove('d-none');
    }
  }

  document.getElementById('mode_single')?.addEventListener('change', updateSelectionMode);
  document.getElementById('mode_multi')?.addEventListener('change', updateSelectionMode);

  document.querySelectorAll('.target-cargo-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      updateCheckedStyles();
      updateMultiSelectedNote();
      syncMultiTargetsToForm();
    });
  });

  document.getElementById('permissionForm')?.addEventListener('submit', function () {
    syncMultiTargetsToForm();
  });

  updateSelectionMode();
  updateCheckedStyles();
  updateMultiSelectedNote();
  syncMultiTargetsToForm();
</script>
<?= $this->endSection() ?>