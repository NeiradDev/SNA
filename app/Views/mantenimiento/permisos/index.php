<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>

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

<div>

  <div>

    <h2>Permisos por cargo</h2>

    <?php if (session()->getFlashdata('success')): ?>
      <div>
        <?= esc((string) session()->getFlashdata('success')) ?>
      </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
      <div>
        <?= esc((string) session()->getFlashdata('error')) ?>
      </div>
    <?php endif; ?>

    <div>

      <div>
        <div>1. Cargo base para cargar permisos</div>

        <div>
          <form method="get" action="<?= site_url('mantenimiento/permisos') ?>" id="baseCargoForm">

            <div>
              <div>
                <select name="cargo" id="cargo">
                  <option value="">-- Seleccionar cargo base --</option>

                  <?php foreach ($cargos as $cargo): ?>
                    <?php $cargoId = (int) ($cargo['id_cargo'] ?? 0); ?>
                    <option value="<?= $cargoId ?>" <?= $selectedCargoId === $cargoId ? 'selected' : '' ?>>
                      <?= e_perm_view(cargo_label_view($cargo)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <button type="submit">Cargar permisos</button>
              </div>
            </div>

            <?php if ($selectedCargoId > 0 && $selectedCargoLabel !== ''): ?>
              <div>
                Cargo base cargado: <strong><?= e_perm_view($selectedCargoLabel) ?></strong>
              </div>
            <?php endif; ?>
          </form>
        </div>

        <hr>

        <div>2. Aplicación del guardado</div>

        <div>
          <div>
            <div>
              <input type="radio" name="selection_mode" id="mode_single" checked>
              <label for="mode_single">Guardar solo en el cargo base</label>
            </div>
          </div>
        </div>

        <div id="multiTargetBox" style="display:none">
          <div>3. Selecciona cargos destino</div>

          <div>
            <?php foreach ($cargos as $cargo): ?>
              <?php $cargoId = (int) ($cargo['id_cargo'] ?? 0); ?>
              <label for="target_cargo_<?= $cargoId ?>">
                <div>
                  <input
                    type="checkbox"
                    value="<?= $cargoId ?>"
                    id="target_cargo_<?= $cargoId ?>"
                    name="target_cargo_ids[]"
                  >
                  <span><?= e_perm_view(cargo_label_view($cargo)) ?></span>
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <div id="multiSelectedNote">
            No has seleccionado cargos destino aún.
          </div>
        </div>
      </div>

      <div>
        <div>Cómo usar esta pantalla</div>
        <ul>
          <li><strong>Paso 1:</strong> carga un cargo base para ver sus permisos actuales.</li>
          <li><strong>Paso 2:</strong> marca o desmarca permisos en la parte inferior.</li>
          <li><strong>Paso 3:</strong> decide si guardar solo en el cargo base o aplicar a varios cargos.</li>
          <li>Los permisos afectan tanto el menú visible como el acceso real a las rutas protegidas.</li>
        </ul>
      </div>
    </div>

    <?php if ($selectedCargoId <= 0): ?>
      <div>
        <h5>Primero debes cargar un cargo</h5>
        <div>
          Selecciona un cargo en la parte superior y presiona <strong>Cargar permisos</strong>.
        </div>
      </div>

    <?php else: ?>

      <div>
        <div>
          <div>Cargo base</div>
          <div><?= e_perm_view($selectedCargoLabel !== '' ? $selectedCargoLabel : ('ID ' . $selectedCargoId)) ?></div>
        </div>

        <div>
          <div>Módulos</div>
          <div><?= $totalModules ?></div>
        </div>

        <div>
          <div>Permisos activos</div>
          <div><?= $totalAssigned ?> / <?= $totalPermissions ?></div>
        </div>

        <div>
          <div>Cobertura</div>
          <div><?= $percentageAssigned ?>%</div>
        </div>
      </div>

      <form method="post" action="<?= site_url('mantenimiento/permisos/guardar') ?>" id="permissionForm">
        <?= csrf_field() ?>

        <input type="hidden" name="id_cargo" value="<?= $selectedCargoId ?>">
        <div id="multiTargetsHidden"></div>

        <div>
          <div>
            <div>
              <button type="button" onclick="checkAllPermissions(true)">Marcar todo</button>
              <button type="button" onclick="checkAllPermissions(false)">Desmarcar todo</button>
            </div>

            <div>
              <button type="button" onclick="expandAllModules()">Expandir módulos</button>
              <button type="button" onclick="collapseAllModules()">Contraer módulos</button>
            </div>
          </div>
        </div>

        <div>
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

            <div data-module-card="<?= e_perm_view($moduleKey) ?>">
              <div>
                <div>
                  <h5><?= e_perm_view(module_label_view((string) $module)) ?></h5>
                  <div>
                    <?= $moduleAssigned ?> asignados de <?= $moduleCount ?> permisos
                  </div>
                </div>

                <span><?= $moduleCount ?> permisos</span>
              </div>

              <div>
                <button type="button" onclick="toggleModule('<?= e_perm_view($moduleKey) ?>', true)">Marcar módulo</button>
                <button type="button" onclick="toggleModule('<?= e_perm_view($moduleKey) ?>', false)">Limpiar módulo</button>
                <button type="button" onclick="toggleModuleBody('<?= e_perm_view($moduleKey) ?>')">Mostrar / ocultar</button>
              </div>

              <div id="module-body-<?= e_perm_view($moduleKey) ?>">
                <?php foreach ($permissionList as $permission): ?>
                  <?php
                    $permissionId   = (int) ($permission['id_permiso'] ?? 0);
                    $permissionName = (string) ($permission['nombre_permiso'] ?? '');
                    $permissionCode = (string) ($permission['codigo'] ?? '');
                    $permissionDesc = (string) ($permission['descripcion'] ?? '');
                    $isChecked      = in_array($permissionId, $assignedPermissionIds, true);
                  ?>

                  <div>
                    <div>
                      <div>
                        <input
                          type="checkbox"
                          name="permission_ids[]"
                          value="<?= $permissionId ?>"
                          id="perm_<?= $permissionId ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                          onchange="updateCheckedStyles()"
                        >
                      </div>

                      <div>
                        <label for="perm_<?= $permissionId ?>">
                          <div><?= e_perm_view($permissionName) ?></div>
                          <div><?= e_perm_view($permissionCode) ?></div>

                          <?php if ($permissionDesc !== ''): ?>
                            <div><?= e_perm_view($permissionDesc) ?></div>
                          <?php else: ?>
                            <div>Sin descripción adicional.</div>
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

        <div>
          <div>
            <div>
              En modo individual se actualizará solo el cargo base cargado.  
              En modo masivo, se aplicarán los mismos permisos a todos los cargos destino seleccionados.
            </div>

            <div>
              <a href="<?= site_url('mantenimiento/permisos?cargo=' . $selectedCargoId) ?>">Recargar</a>
              <button type="submit">Guardar permisos</button>
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