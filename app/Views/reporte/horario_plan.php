<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
$id           = $row['id'] ?? '';
$enable_dow   = (int)($row['enable_dow'] ?? 1);
$enable_time  = substr((string)($row['enable_time'] ?? '08:00'), 0, 5);
$disable_dow  = (int)($row['disable_dow'] ?? 1);
$disable_time = substr((string)($row['disable_time'] ?? '18:00'), 0, 5);
$timezone     = (string)($row['timezone'] ?? 'America/Guayaquil');
$active       = !empty($row) ? (bool)($row['active'] ?? true) : true;

$dowLabels = [
  1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
  5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
];
?>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Horario Plan de Batalla</h3>
      <div class="text-muted small">Un solo horario desde tu base de datos.</div>
    </div>
    <span id="estadoActual" class="badge bg-secondary">Cargando…</span>
  </div>

  <div id="msg" class="alert d-none"></div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <input type="hidden" id="id" value="<?= esc($id) ?>">
      <input type="hidden" id="csrfName" value="<?= csrf_token() ?>">
      <input type="hidden" id="csrfHash" value="<?= csrf_hash() ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 border rounded bg-light">
            <div class="fw-bold mb-2">Habilita</div>
            <label class="form-label">Día</label>
            <select id="enable_dow" class="form-select mb-2">
              <?php foreach ($dowLabels as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($enable_dow === $k) ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label">Hora</label>
            <input type="time" id="enable_time" class="form-control" value="<?= esc($enable_time) ?>">
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3 border rounded bg-light">
            <div class="fw-bold mb-2">Deshabilita</div>
            <label class="form-label">Día</label>
            <select id="disable_dow" class="form-select mb-2">
              <?php foreach ($dowLabels as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($disable_dow === $k) ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label">Hora</label>
            <input type="time" id="disable_time" class="form-control" value="<?= esc($disable_time) ?>">
          </div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Zona horaria</label>
          <input type="text" id="timezone" class="form-control" value="<?= esc($timezone) ?>">
          <div class="form-text">Ej: America/Guayaquil</div>
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input type="checkbox" id="active" class="form-check-input" <?= $active ? 'checked' : '' ?>>
            <label class="form-check-label">Horario activo</label>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button class="btn btn-primary" id="btnSave" type="button">Guardar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const urlSave   = "<?= site_url('reporte/horario-plan/guardar') ?>";
const urlStatus = "<?= site_url('reporte/plan-status') ?>";

const msgEl = document.getElementById('msg');
const estadoEl = document.getElementById('estadoActual');

const idEl = document.getElementById('id');
const enableDowEl = document.getElementById('enable_dow');
const enableTimeEl = document.getElementById('enable_time');
const disableDowEl = document.getElementById('disable_dow');
const disableTimeEl = document.getElementById('disable_time');
const timezoneEl = document.getElementById('timezone');
const activeEl = document.getElementById('active');

const csrfNameEl = document.getElementById('csrfName');
const csrfHashEl = document.getElementById('csrfHash');

function showMessage(type, text) {
  msgEl.classList.remove('d-none','alert-success','alert-danger','alert-warning','alert-info');
  msgEl.classList.add('alert-'+type);
  msgEl.textContent = text;
}

async function refreshStatus() {
  try {
    const res = await fetch(urlStatus, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
    const data = await res.json();
    if (data.ok) {
      estadoEl.textContent = data.enabled ? 'HABILITADO ahora' : 'DESHABILITADO ahora';
      estadoEl.className = 'badge ' + (data.enabled ? 'bg-success' : 'bg-danger');
    }
  } catch(e) {
    estadoEl.textContent = 'Estado no disponible';
    estadoEl.className = 'badge bg-secondary';
  }
}

async function saveSchedule() {
  if (!timezoneEl.value.trim()) {
    showMessage('warning','Ingresa una zona horaria (ej: America/Guayaquil).');
    return;
  }

  const payload = {
    id: idEl.value ? parseInt(idEl.value,10) : null,
    enable_dow: parseInt(enableDowEl.value,10),
    enable_time: enableTimeEl.value,
    disable_dow: parseInt(disableDowEl.value,10),
    disable_time: disableTimeEl.value,
    timezone: timezoneEl.value.trim(),
    active: activeEl.checked ? 1 : 0
  };

  payload[csrfNameEl.value] = csrfHashEl.value;

  const res = await fetch(urlSave, {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest' },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  if (data.csrfHash) csrfHashEl.value = data.csrfHash;

  if (!data.ok) {
    showMessage('danger', data.errors ? JSON.stringify(data.errors) : (data.message || 'Error'));
    return;
  }

  if (data.id) idEl.value = data.id;

  showMessage('success','Horario guardado correctamente.');
  await refreshStatus();
}

document.getElementById('btnSave').addEventListener('click', saveSchedule);

(async function init(){
  await refreshStatus();
  setInterval(refreshStatus, 10000);
})();
</script>

<?= $this->endSection() ?>
