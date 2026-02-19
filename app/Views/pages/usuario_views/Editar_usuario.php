<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<div class="container-fluid">
<?php $errors = session()->getFlashdata('errors'); ?>

<?php if ($errors): ?>
  <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title">
            <i class="bi bi-exclamation-triangle me-2"></i>Revisa los datos
          </h5>
          <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <ul class="mb-0">
            <?php foreach ($errors as $msg): ?>
              <li><?= esc($msg) ?></li>
            <?php endforeach ?>
          </ul>
        </div>
        <div class="modal-footer">
          <button class="btn btn-dark px-4" data-bs-dismiss="modal">Entendido</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const el = document.getElementById('feedbackModal');
      if (el && window.bootstrap) new bootstrap.Modal(el).show();
    });
  </script>
<?php endif ?>

<div class="row">
  <div class="col-12 col-lg-10">
    <div class="card border-0 shadow-sm pt-2">
      <div class="card-body p-4">

<?php
/* ==========================================================
   Helpers de vista (se mantienen igual)
   ========================================================== */
if (!function_exists('html_attrs')) {
  function html_attrs(array $attrs): string {
    $out = [];
    foreach ($attrs as $k => $v) {
      if (is_bool($v)) { if ($v) $out[] = $k; continue; }
      if ($v === null) continue;
      $out[] = $k . '="' . esc((string)$v, 'attr') . '"';
    }
    return implode(' ', $out);
  }
}
if (!function_exists('input_tag')) {
  function input_tag(string $name, string $type = 'text', array $extra = []): string {
    $base = [
      'type'  => $type,
      'name'  => $name,
      'class' => 'form-control bg-light border-0',
      'value' => old($name) !== null ? old($name) : '',
    ];
    return '<input ' . html_attrs(array_merge($base, $extra)) . '>';
  }
}

/* ==========================================================
   Valores actuales del usuario (precarga)
   ========================================================== */
$userId = (int)($usuario['id_user'] ?? 0);

$val = function(string $key, $default = '') use ($usuario) {
  $old = old($key);
  if ($old !== null) return $old;
  return $usuario[$key] ?? $default;
};

// Campos simples
$nombresVal   = (string)$val('nombres', '');
$apellidosVal = (string)$val('apellidos', '');
$cedulaVal    = (string)$val('cedula', '');

// ✅ Nuevos campos
$correoVal    = (string)$val('correo', '');
$telefonoVal  = (string)$val('telefono', '');

// Relaciones
$idAgenciaVal  = (string)$val('id_agencias', '');
$idDivisionVal = (string)$val('id_division', '');
$idAreaVal     = (string)$val('id_area', '');
$idCargoVal    = (string)$val('id_cargo', '');
$idSupVal      = (string)$val('id_supervisor', '0');

// Flags
$isDivisionBossVal = old('is_division_boss') ? '1' : '0';
$isAreaBossVal     = old('is_area_boss') ? '1' : '0';

// Doc type
$docTypeVal = old('doc_type');
if ($docTypeVal === null) {
  $docTypeVal = preg_match('/^\d+$/', $cedulaVal) ? 'CEDULA' : 'PASAPORTE';
}

// Activo
$activoOld = old('activo');
$activoVal = ($activoOld === null) ? !empty($usuario['activo']) : (bool)$activoOld;

// Cargo secundario
$idCargoSecVal = (string)($val('id_cargo_secondary', ''));

// Gerencia
$gerDefault = (int)(old('id_cargo_gerencia') ?? ($gerenciaCargoIdDefault ?? 6));
if ($gerDefault <= 0) $gerDefault = 6;
?>

<form action="<?= base_url('usuarios/actualizar/' . $userId) ?>" method="POST" id="userForm">
  <?= csrf_field() ?>

  <input type="hidden"
         id="id_cargo_gerencia_hidden"
         name="id_cargo_gerencia"
         value="<?= esc((string)$gerDefault) ?>">

  <h5 class="mb-4 text-muted">
    <i class="bi bi-person-badge me-2"></i>Información Personal
  </h5>

  <div class="row g-3">

    <div class="col-md-6">
      <label class="form-label fw-bold small">Nombres</label>
      <?= input_tag('nombres', 'text', [
          'required' => true,
          'maxlength' => 64,
          'placeholder' => 'Ej. Juan Carlos',
          'value' => $nombresVal,
      ]) ?>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-bold small">Apellidos</label>
      <?= input_tag('apellidos', 'text', [
          'required' => true,
          'maxlength' => 64,
          'placeholder' => 'Ej. Armas',
          'value' => $apellidosVal,
      ]) ?>
    </div>

    <div class="col-md-3">
      <label class="form-label fw-bold small">Tipo de documento</label>
      <select name="doc_type" id="doc_type" class="form-select bg-light border-0" required>
        <option value="CEDULA"    <?= $docTypeVal !== 'PASAPORTE' ? 'selected' : '' ?>>Cédula</option>
        <option value="PASAPORTE" <?= $docTypeVal === 'PASAPORTE' ? 'selected' : '' ?>>Pasaporte/CI/NU</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label fw-bold small">Número de documento</label>
      <?= input_tag('cedula', 'text', [
          'id' => 'doc_number',
          'required' => true,
          'placeholder' => 'Ingrese el número',
          'value' => $cedulaVal,
      ]) ?>
      <div class="form-text small text-muted" id="doc_help"></div>
    </div>

    <!-- ✅ NUEVO: CORREO -->
    <div class="col-md-6">
      <label class="form-label fw-bold small">Correo (opcional)</label>
      <?= input_tag('correo', 'email', [
          'maxlength'   => 120,
          'placeholder' => 'correo@dominio.com',
          'value'       => $correoVal,
          'autocomplete'=> 'email',
      ]) ?>
      <div class="form-text small text-muted">Si se ingresa, puede validarse como único.</div>
    </div>

    <!-- ✅ NUEVO: TELÉFONO / CONTACTO -->
    <div class="col-md-6">
      <label class="form-label fw-bold small">Teléfono / Contacto (opcional)</label>
      <?= input_tag('telefono', 'text', [
          'maxlength'   => 20,
          'placeholder' => '+593 99 123 4567',
          'value'       => $telefonoVal,
          // Solo caracteres comunes de teléfono
          'pattern'     => '[0-9+()\\s-]{7,20}',
          'title'       => 'Use números y caracteres válidos: + ( ) espacios y guiones.',
          'autocomplete'=> 'tel',
      ]) ?>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-bold small">Contraseña (opcional)</label>
      <input type="password" name="password" class="form-control bg-light border-0" placeholder="Dejar vacío para no cambiar">
      <div class="form-text small text-muted">Solo se actualiza si escribe una nueva (mínimo 6 caracteres).</div>
    </div>

    <div class="col-12 my-4">
      <h5 class="text-muted"><i class="bi bi-building me-2"></i>Asignación y Estructura</h5>
      <hr class="opacity-25">
    </div>

    <div class="col-md-3">
      <label class="form-label fw-bold small">Agencia</label>
      <select name="id_agencias" id="id_agencias" class="form-select bg-light border-0" required>
        <option value="">Seleccione…</option>
        <?php foreach ($agencias ?? [] as $a): ?>
          <option value="<?= esc($a['id_agencias']) ?>"
            <?= (string)$idAgenciaVal === (string)$a['id_agencias'] ? 'selected' : '' ?>>
            <?= esc($a['nombre_agencia']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <!-- ✅ División única -->
    <div class="col-md-3" id="wrap_division">
      <label class="form-label fw-bold small">División</label>
      <select name="id_division" id="id_division" class="form-select bg-light border-0" required>
        <option value="">Seleccione…</option>
        <?php foreach ($division ?? [] as $d): ?>
          <option value="<?= esc($d['id_division']) ?>"
            <?= (string)$idDivisionVal === (string)$d['id_division'] ? 'selected' : '' ?>>
            <?= esc($d['nombre_division']) ?>
          </option>
        <?php endforeach ?>
      </select>
      <div class="form-text small text-muted">Base para cargar áreas y cargos.</div>
    </div>

    <!-- ✅ Área -->
    <div class="col-md-3 d-none" id="wrap_area">
      <label class="form-label fw-bold small">Área</label>
      <select name="id_area" id="id_area" class="form-select bg-light border-0">
        <option value="">Seleccione…</option>
      </select>
      <div class="form-text small text-muted">Solo en registro normal o Jefe de Área.</div>
    </div>

    <!-- Roles -->
    <div class="col-md-3" id="wrap_roles">
      <label class="form-label fw-bold small">Roles especiales</label>
      <div class="d-flex gap-3 flex-wrap bg-light border-0 rounded p-2">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="is_division_boss" name="is_division_boss" value="1"
            <?= $isDivisionBossVal === '1' ? 'checked' : '' ?>>
          <label class="form-check-label small fw-bold" for="is_division_boss">Jefe de División</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="is_area_boss" name="is_area_boss" value="1"
            <?= $isAreaBossVal === '1' ? 'checked' : '' ?>>
          <label class="form-check-label small fw-bold" for="is_area_boss">Jefe de Área</label>
        </div>
      </div>
      <div class="form-text small text-muted">El formulario se ajusta sin combobox ilógicos.</div>
    </div>

    <!-- Cargo principal -->
    <div class="col-md-3" id="wrap_cargo_primary">
      <label class="form-label fw-bold small" id="cargo_primary_label">Cargo (principal)</label>
      <select name="id_cargo" id="id_cargo" class="form-select bg-light border-0" required>
        <option value="">Seleccione…</option>
      </select>
      <div class="form-text small text-muted" id="cargo_primary_help">Se carga según el rol.</div>
    </div>

    <!-- Cargo secundario -->
    <div class="col-md-3 d-none" id="wrap_cargo_secondary">
      <label class="form-label fw-bold small">Cargo (secundario - Área)</label>
      <select name="id_cargo_secondary" id="id_cargo_secondary" class="form-select bg-light border-0">
        <option value="">Seleccione…</option>
      </select>
      <div class="form-text small text-muted">Obligatorio si es Jefe de División y Jefe de Área.</div>
    </div>

    <!-- Supervisor manual -->
    <div class="col-md-3" id="wrap_supervisor">
      <label class="form-label fw-bold small">Supervisor</label>
      <select name="id_supervisor" id="id_supervisor" class="form-select bg-light border-0">
        <option value="0">Sin supervisor</option>
      </select>
      <div class="form-text small text-muted">Solo aplica en registro normal.</div>
    </div>

    <div class="col-12 d-none" id="wrap_supervisor_note">
      <div class="p-2 bg-light border rounded">
        <span class="small text-muted" id="supervisor_note_text">—</span>
      </div>
    </div>

    <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="activo" id="activo"
          <?= $activoVal ? 'checked' : '' ?>>
        <label class="form-check-label" for="activo">Usuario activo</label>
      </div>
      <div>
        <a href="<?= base_url('usuarios') ?>" class="btn btn-light px-4 me-2">Cancelar</a>
        <button type="submit" class="btn btn-dark px-5 shadow-sm">Guardar Cambios</button>
      </div>
    </div>

  </div>
</form>

      </div>
    </div>

    <!-- Panel de gerencia (igual) -->
    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body p-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-8">
            <label class="form-label fw-bold small mb-1">Usuario con cargo de Gerencia</label>
            <input type="text"
                   id="gerencia_user_name"
                   class="form-control bg-light border-0"
                   value="<?= esc(($gerenciaUser['full_name'] ?? 'No asignado')) ?>"
                   readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold small mb-1">ID cargo Gerencia (editable)</label>
            <input type="number"
                   min="1"
                   step="1"
                   id="id_cargo_gerencia_ui"
                   class="form-control bg-light border-0"
                   value="<?= esc((string)$gerDefault) ?>">
            <div class="form-text small text-muted">
              Se guarda independiente (localStorage) y se copia al hidden al enviar.
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="col-md-2">
    <div class="card border-0 bg-dark text-white shadow-sm mb-3">
      <div class="card-body">
        <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Ayuda</h6>
        <p class="small opacity-75 mb-0">
          Si algún dato está erróneo, comuníquese con el administrador.
        </p>
      </div>
    </div>
  </div>

</div>
</div>

<script>
(() => {
  const byId = (id) => document.getElementById(id);

  // =========================
  // ✅ Precargas del EDITAR (usuario actual)
  // - old() ya viene aplicado en PHP en los selects base
  // - aquí solo lo usamos para preseleccionar combos cargados por fetch
  // =========================
  const CURRENT = {
    userId:     "<?= esc((string)$userId) ?>",
    divisionId: "<?= esc((string)$idDivisionVal) ?>",
    areaId:     "<?= esc((string)$idAreaVal) ?>",
    cargoId:    "<?= esc((string)$idCargoVal) ?>",
    cargoSecId: "<?= esc((string)$idCargoSecVal) ?>",
    supId:      "<?= esc((string)$idSupVal) ?>",
  };

  // =========================
  // Documento
  // =========================
  const docType   = byId('doc_type');
  const docNumber = byId('doc_number');
  const docHelp   = byId('doc_help');

  const DOC = {
    CEDULA: { regex: /[^0-9]/g, max: 10, msg: 'Cédula: solo números, 10 caracteres.' },
    OTHER:  { regex: /[^a-zA-Z0-9]/g, max: 15, msg: 'Pasaporte/CI/NU: alfanumérico, 15 caracteres.' }
  };

  const applyDocRules = () => {
    const r = (docType.value === 'CEDULA') ? DOC.CEDULA : DOC.OTHER;
    docNumber.value       = docNumber.value.replace(r.regex, '').slice(0, r.max);
    docNumber.maxLength   = r.max;
    docNumber.inputMode   = (docType.value === 'CEDULA') ? 'numeric' : 'text';
    docNumber.placeholder = (docType.value === 'CEDULA') ? 'Solo números (10 dígitos)' : 'Letras y números (hasta 15)';
    docHelp.textContent   = r.msg;
  };

  docType.addEventListener('change', applyDocRules);
  docNumber.addEventListener('input', applyDocRules);
  document.addEventListener('DOMContentLoaded', applyDocRules);

  // =========================
  // Form controls
  // =========================
  const form = byId('userForm');

  const divisionUI = byId('id_division');
  const areaUI     = byId('id_area');

  const cargoPrimary   = byId('id_cargo');
  const cargoSecondary = byId('id_cargo_secondary');

  const supUI = byId('id_supervisor');

  const isDivisionBoss = byId('is_division_boss');
  const isAreaBoss     = byId('is_area_boss');

  const wrapArea           = byId('wrap_area');
  const wrapSupervisor     = byId('wrap_supervisor');
  const wrapSupNote        = byId('wrap_supervisor_note');
  const supNoteText        = byId('supervisor_note_text');

  const wrapCargoSecondary = byId('wrap_cargo_secondary');
  const cargoPrimaryLabel  = byId('cargo_primary_label');
  const cargoPrimaryHelp   = byId('cargo_primary_help');

  // Gerencia UI (FUERA) + hidden (DENTRO)
  const gerenciaCargoUI      = byId('id_cargo_gerencia_ui');
  const gerenciaCargoHidden  = byId('id_cargo_gerencia_hidden');
  const gerenciaUserName     = byId('gerencia_user_name');

  // =========================
  // URLs internas
  // =========================
  const URLS = {
    areas:          "<?= base_url('usuarios/api/areas') ?>",
    cargosArea:     "<?= base_url('usuarios/api/cargos') ?>",
    cargosDivision: "<?= base_url('usuarios/api/cargos-division') ?>",
    sups:           "<?= base_url('usuarios/api/supervisores') ?>",
    gerencia:       "<?= base_url('usuarios/api/gerencia-user') ?>",
  };

  const fetchJson = async (url) => {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return null;
    return res.json();
  };

  const resetSelect = (sel, placeholder = 'Seleccione…') => {
    sel.innerHTML = `<option value="">${placeholder}</option>`;
  };

  const setSelectOptions = (sel, rows, valueKey, labelKey, placeholder = 'Seleccione…') => {
    resetSelect(sel, placeholder);
    if (!Array.isArray(rows)) return;
    rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r[valueKey];
      opt.textContent = r[labelKey];
      sel.appendChild(opt);
    });
  };

  // =========================
  // ✅ Gerencia (independiente)
  // - guarda en localStorage
  // - copia al hidden al enviar
  // =========================
  const LS_KEY = 'sna_id_cargo_gerencia';

  const syncGerenciaHiddenFromUI = () => {
    const val = String(gerenciaCargoUI.value || '6').trim();
    gerenciaCargoHidden.value = val || '6';
  };

  const saveGerenciaToLocalStorage = () => {
    const val = String(gerenciaCargoUI.value || '6').trim();
    localStorage.setItem(LS_KEY, val || '6');
  };

  const loadGerenciaFromLocalStorage = () => {
    const saved = localStorage.getItem(LS_KEY);
    if (saved && saved.trim() !== '') {
      gerenciaCargoUI.value = saved.trim();
    }
    syncGerenciaHiddenFromUI();
  };

  const refreshGerenciaUser = async () => {
    const id = parseInt(gerenciaCargoHidden.value || '6', 10);
    const data = await fetchJson(`${URLS.gerencia}?id_cargo_gerencia=${encodeURIComponent(id)}`);
    gerenciaUserName.value = (data && data.ok && data.user && data.user.full_name) ? data.user.full_name : 'No asignado';
  };

  gerenciaCargoUI.addEventListener('input', async () => {
    syncGerenciaHiddenFromUI();
    saveGerenciaToLocalStorage();
    await refreshGerenciaUser();

    // recarga supervisores solo si es normal
    if (!isDivisionBoss.checked && !isAreaBoss.checked) {
      await loadSupervisorsNormal(areaUI.value, CURRENT.supId);
    }
  });

  // copia final antes de enviar (por si acaso)
  form.addEventListener('submit', () => {
    syncGerenciaHiddenFromUI();
  });

  // =========================
  // Cargas dependientes (con preselect)
  // =========================
  const loadAreasByDivision = async (divisionId, preselect = '') => {
    resetSelect(areaUI);
    if (!divisionId) return;

    const rows = await fetchJson(`${URLS.areas}?id_division=${encodeURIComponent(divisionId)}`);
    setSelectOptions(areaUI, rows || [], 'id_area', 'nombre_area');

    if (preselect) areaUI.value = String(preselect);
  };

  const loadCargosByArea = async (areaId, targetSelect, preselect = '') => {
    targetSelect.innerHTML = '<option value="">Cargando…</option>';
    if (!areaId) { resetSelect(targetSelect); return; }

    const rows = await fetchJson(`${URLS.cargosArea}?id_area=${encodeURIComponent(areaId)}`);
    setSelectOptions(targetSelect, rows || [], 'id_cargo', 'nombre_cargo');

    if (preselect) targetSelect.value = String(preselect);
  };

  const loadCargosByDivision = async (divisionId, targetSelect, preselect = '') => {
    targetSelect.innerHTML = '<option value="">Cargando…</option>';
    if (!divisionId) { resetSelect(targetSelect); return; }

    const rows = await fetchJson(`${URLS.cargosDivision}?id_division=${encodeURIComponent(divisionId)}`);
    setSelectOptions(targetSelect, rows || [], 'id_cargo', 'nombre_cargo');

    if (preselect) targetSelect.value = String(preselect);
  };

  /**
   * loadSupervisorsNormal()
   * ✅ EDITAR:
   * - excluye al usuario actual para evitar auto-supervisión
   * ⚠️ Nota:
   * - tu Service ya no usa id_cargo_gerencia aquí, pero tu endpoint puede ignorarlo
   * - dejamos el parámetro para no romper tu Crear
   */
  const loadSupervisorsNormal = async (areaId, preselect = '') => {
    supUI.innerHTML = '<option value="0">Cargando…</option>';
    if (!areaId) {
      supUI.innerHTML = '<option value="0">Sin supervisor</option>';
      return;
    }

    const gId = parseInt(gerenciaCargoHidden.value || '6', 10);

    const rows = await fetchJson(
      `${URLS.sups}?id_area=${encodeURIComponent(areaId)}&id_cargo_gerencia=${encodeURIComponent(gId)}&exclude_user_id=${encodeURIComponent(CURRENT.userId)}`
    );

    const safeRows = Array.isArray(rows) ? rows : [];
    supUI.innerHTML = '<option value="0">Sin supervisor</option>' + safeRows.map(i =>
      `<option value="${i.id_user}">${i.supervisor_label}</option>`
    ).join('');

    if (preselect !== '' && preselect !== null && preselect !== undefined) {
      supUI.value = String(preselect);
    }
  };

  // =========================
  // ✅ Importante:
  // cuando ocultamos algo, lo limpiamos para que NO viaje y NO valide mal.
  // =========================
  const clearAreaIfHidden = () => {
    areaUI.value = '';
    resetSelect(areaUI);
  };

  const clearSecondaryIfHidden = () => {
    cargoSecondary.value = '';
    resetSelect(cargoSecondary);
  };

  // =========================
  // Lógica de roles (misma del Crear, pero con precargas)
  // =========================
  const applyLogicalVisibility = async () => {
    const divId = divisionUI.value;
    const divisionBoss = isDivisionBoss.checked;
    const areaBoss     = isAreaBoss.checked;

    // Sin división: limpia dependientes (evita estados raros)
    if (!divId) {
      wrapArea.classList.add('d-none');
      clearAreaIfHidden();

      resetSelect(cargoPrimary);
      wrapCargoSecondary.classList.add('d-none');
      clearSecondaryIfHidden();

      wrapSupervisor.classList.remove('d-none');
      wrapSupNote.classList.add('d-none');
      return;
    }

    // Caso 1: Jefe División solo
    if (divisionBoss && !areaBoss) {
      wrapArea.classList.add('d-none');
      clearAreaIfHidden();

      wrapCargoSecondary.classList.add('d-none');
      clearSecondaryIfHidden();
      cargoSecondary.required = false;

      cargoPrimaryLabel.textContent = 'Cargo (principal - División)';
      cargoPrimaryHelp.textContent = 'Se carga por división (jefatura de división).';
      await loadCargosByDivision(divId, cargoPrimary, CURRENT.cargoId);

      wrapSupervisor.classList.add('d-none');
      wrapSupNote.classList.remove('d-none');
      supNoteText.textContent = 'Supervisor automático: Gerencia.';
      return;
    }

    // Caso 2: Jefe Área solo
    if (areaBoss && !divisionBoss) {
      wrapArea.classList.remove('d-none');
      await loadAreasByDivision(divId, CURRENT.areaId);

      wrapCargoSecondary.classList.add('d-none');
      clearSecondaryIfHidden();
      cargoSecondary.required = false;

      cargoPrimaryLabel.textContent = 'Cargo (principal - Área)';
      cargoPrimaryHelp.textContent = 'Se carga por área (jefatura de área).';
      await loadCargosByArea(areaUI.value, cargoPrimary, CURRENT.cargoId);

      wrapSupervisor.classList.add('d-none');
      wrapSupNote.classList.remove('d-none');
      supNoteText.textContent = 'Supervisor automático: Jefe de División de esa división.';
      return;
    }

    // Caso 3: Ambos roles
    if (divisionBoss && areaBoss) {
      wrapArea.classList.remove('d-none');
      await loadAreasByDivision(divId, CURRENT.areaId);

      cargoPrimaryLabel.textContent = 'Cargo (principal - División)';
      cargoPrimaryHelp.textContent = 'Tu cargo principal será el vinculado a la división.';
      await loadCargosByDivision(divId, cargoPrimary, CURRENT.cargoId);

      wrapCargoSecondary.classList.remove('d-none');
      cargoSecondary.required = true;
      await loadCargosByArea(areaUI.value, cargoSecondary, CURRENT.cargoSecId);

      wrapSupervisor.classList.add('d-none');
      wrapSupNote.classList.remove('d-none');
      supNoteText.textContent = 'Supervisor automático: Gerencia (evita auto-supervisión).';
      return;
    }

    // Caso 4: Normal
    wrapArea.classList.remove('d-none');
    await loadAreasByDivision(divId, CURRENT.areaId);

    wrapCargoSecondary.classList.add('d-none');
    clearSecondaryIfHidden();
    cargoSecondary.required = false;

    cargoPrimaryLabel.textContent = 'Cargo (principal)';
    cargoPrimaryHelp.textContent = 'Se carga por área.';
    await loadCargosByArea(areaUI.value, cargoPrimary, CURRENT.cargoId);

    wrapSupervisor.classList.remove('d-none');
    wrapSupNote.classList.add('d-none');
    await loadSupervisorsNormal(areaUI.value, CURRENT.supId);
  };

  // =========================
  // Eventos (igual que Crear)
  // =========================
  isDivisionBoss.addEventListener('change', applyLogicalVisibility);
  isAreaBoss.addEventListener('change', applyLogicalVisibility);
  divisionUI.addEventListener('change', async () => {
    // cuando cambie división, resetea “precargas” de área/cargos para que no queden desfasadas
    CURRENT.areaId = '';
    CURRENT.cargoId = '';
    CURRENT.cargoSecId = '';
    CURRENT.supId = '0';
    await applyLogicalVisibility();
  });

  areaUI.addEventListener('change', async () => {
    const divisionBoss = isDivisionBoss.checked;
    const areaBoss     = isAreaBoss.checked;

    if (divisionBoss && !areaBoss) return;

    if (!divisionBoss && (areaBoss || (!divisionBoss && !areaBoss))) {
      await loadCargosByArea(areaUI.value, cargoPrimary, '');
    }

    if (divisionBoss && areaBoss) {
      await loadCargosByArea(areaUI.value, cargoSecondary, '');
    }

    if (!divisionBoss && !areaBoss) {
      await loadSupervisorsNormal(areaUI.value, '');
    }
  });

  // =========================
  // Init (igual que Crear, pero con precarga real de EDITAR)
  // =========================
  document.addEventListener('DOMContentLoaded', async () => {
    loadGerenciaFromLocalStorage();
    await refreshGerenciaUser();

    // Si el select de división ya viene seleccionado (PHP), lo respetamos
    await applyLogicalVisibility();
  });

})();
</script>

<?= $this->endSection() ?>
