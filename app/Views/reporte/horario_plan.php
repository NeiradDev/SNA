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
  1 => 'Lunes',
  2 => 'Martes',
  3 => 'Miércoles',
  4 => 'Jueves',
  5 => 'Viernes',
  6 => 'Sábado',
  7 => 'Domingo'
];
?>

<style>
  :root{
    --ws-red-strong:#F20505;
    --ws-red-main:#F22E2E;
    --ws-red-soft:#F27272;
    --ws-light:#F2F2F2;
    --ws-dark:#0D0D0D;
  }

  .schedule-page{
    animation:scheduleFadeIn .38s ease;
  }

  @keyframes scheduleFadeIn{
    from{opacity:0; transform:translateY(10px);}
    to{opacity:1; transform:translateY(0);}
  }

  .schedule-title{
    font-weight:900;
    letter-spacing:.3px;
    color:var(--ws-dark);
    margin-bottom:.2rem;
  }

  .schedule-subtitle{
    color:rgba(13,13,13,.72);
    font-size:.95rem;
  }

  .schedule-card{
    border:1px solid rgba(13,13,13,.10);
    border-radius:18px;
    background:linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
    box-shadow:0 14px 30px -22px rgba(13,13,13,.18);
    transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
  }

  .schedule-card:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 36px -24px rgba(13,13,13,.24);
    border-color:rgba(242,46,46,.18);
  }

  .schedule-hero{
    position:relative;
    overflow:hidden;
  }

  .schedule-hero::before{
    content:"";
    position:absolute;
    inset:0;
    background:
      radial-gradient(circle at top right, rgba(242,46,46,.10), transparent 34%),
      radial-gradient(circle at bottom left, rgba(242,114,114,.12), transparent 38%);
    pointer-events:none;
  }

  .schedule-hero-content{
    position:relative;
    z-index:1;
  }

  .schedule-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(242,46,46,.10);
    border:1px solid rgba(242,46,46,.20);
    color:var(--ws-dark);
    font-weight:800;
    font-size:.86rem;
  }

  .schedule-kpis{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }

  .schedule-kpi{
    min-width:145px;
    padding:12px 14px;
    border-radius:14px;
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    color:#fff;
    box-shadow:0 14px 28px -20px rgba(13,13,13,.34);
    animation:scheduleFloat .38s ease;
  }

  .schedule-kpi strong{
    display:block;
    font-size:1.08rem;
    line-height:1.1;
  }

  .schedule-kpi small{
    display:block;
    margin-top:4px;
    opacity:.88;
  }

  @keyframes scheduleFloat{
    from{opacity:0; transform:translateY(10px) scale(.98);}
    to{opacity:1; transform:translateY(0) scale(1);}
  }

  .schedule-section-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:12px 14px;
    border-radius:12px;
    font-weight:900;
    letter-spacing:.3px;
    text-transform:uppercase;
    background:linear-gradient(135deg, #ffffff 0%, var(--ws-light) 100%);
    border:1px solid rgba(13,13,13,.12);
    color:var(--ws-dark);
  }

  .schedule-section-title small{
    font-weight:700;
    opacity:.75;
    text-transform:none;
  }

  .schedule-form-shell{
    padding:1rem;
  }

  .schedule-block{
    height:100%;
    border:1px solid rgba(13,13,13,.10);
    border-radius:16px;
    padding:16px;
    background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.95) 100%);
    box-shadow:0 10px 24px -22px rgba(13,13,13,.18);
    transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
  }

  .schedule-block:hover{
    transform:translateY(-1px);
    border-color:rgba(242,46,46,.20);
    box-shadow:0 14px 28px -22px rgba(13,13,13,.22);
  }

  .schedule-block-danger{
    background:linear-gradient(180deg, rgba(242,114,114,.10) 0%, rgba(242,242,242,.96) 100%);
    border-color:rgba(242,46,46,.18);
  }

  .schedule-block-title{
    font-weight:900;
    color:var(--ws-dark);
    margin-bottom:12px;
    letter-spacing:.2px;
  }

  .schedule-label{
    font-weight:800;
    color:var(--ws-dark);
    margin-bottom:6px;
  }

  .schedule-form-shell .form-control,
  .schedule-form-shell .form-select{
    border-radius:12px;
    border-color:rgba(13,13,13,.12);
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .schedule-form-shell .form-control:focus,
  .schedule-form-shell .form-select:focus{
    border-color:rgba(242,46,46,.40);
    box-shadow:0 0 0 .2rem rgba(242,46,46,.12);
    transform:translateY(-1px);
  }

  .schedule-switch-card{
    border:1px solid rgba(13,13,13,.10);
    border-radius:16px;
    padding:16px;
    background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.95) 100%);
    box-shadow:0 10px 24px -22px rgba(13,13,13,.18);
  }

  .schedule-status-badge{
    border-radius:999px;
    padding:9px 14px;
    font-weight:900;
    font-size:.84rem;
    letter-spacing:.2px;
    border:1px solid rgba(13,13,13,.08);
  }

  .schedule-status-neutral{
    background:linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
    color:var(--ws-dark);
  }

  .schedule-status-on{
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    color:#fff;
  }

  .schedule-status-off{
    background:linear-gradient(135deg, rgba(242,114,114,.20) 0%, rgba(242,46,46,.20) 100%);
    color:var(--ws-dark);
    border-color:rgba(242,46,46,.24);
  }

  .schedule-alert{
    border-radius:14px;
    font-weight:700;
    border-width:1px;
  }

  .schedule-btn-save{
    min-width:180px;
    border-radius:12px;
    font-weight:900;
    letter-spacing:.2px;
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    border-color:var(--ws-dark);
    color:#fff;
    box-shadow:0 14px 28px -18px rgba(13,13,13,.36);
    transition:transform .18s ease, box-shadow .18s ease, opacity .18s ease;
  }

  .schedule-btn-save:hover{
    color:#fff;
    transform:translateY(-1px);
    box-shadow:0 18px 32px -18px rgba(13,13,13,.42);
    opacity:.96;
  }

  .schedule-note{
    border:1px dashed rgba(13,13,13,.16);
    border-radius:14px;
    padding:14px;
    background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.94) 100%);
    color:rgba(13,13,13,.75);
  }

  .form-check-input:checked{
    background-color:var(--ws-red-main);
    border-color:var(--ws-red-main);
  }

  .form-check-input:focus{
    box-shadow:0 0 0 .2rem rgba(242,46,46,.12);
    border-color:rgba(242,46,46,.40);
  }

  @media (max-width: 768px){
    .schedule-kpis{
      width:100%;
    }

    .schedule-kpi{
      flex:1 1 calc(50% - 10px);
    }
  }
</style>

<div class="container py-3 schedule-page">

  <!-- HEADER -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h3 class="schedule-title">Horario Plan de Batalla</h3>
      <div class="schedule-subtitle">Configura un solo horario desde tu base de datos con estilo visual unificado.</div>
    </div>

    <div class="schedule-kpis">
      <div class="schedule-kpi">
        <strong><?= esc($timezone) ?></strong>
        <small>Zona horaria</small>
      </div>
      <div class="schedule-kpi">
        <strong><?= $active ? 'Activo' : 'Inactivo' ?></strong>
        <small>Configuración actual</small>
      </div>
    </div>
  </div>

  <!-- HERO -->
  <div class="card schedule-card schedule-hero mb-3">
    <div class="card-body schedule-hero-content d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <span class="schedule-chip">Automatización semanal</span>
        <div class="mt-2 text-muted">
          Define cuándo se habilita y cuándo se deshabilita el Plan de Batalla para todos los usuarios.
        </div>
      </div>

      <span id="estadoActual" class="schedule-status-badge schedule-status-neutral">Cargando…</span>
    </div>
  </div>

  <!-- MENSAJES -->
  <div id="msg" class="alert schedule-alert d-none"></div>

  <!-- FORM -->
  <div class="card schedule-card">
    <div class="card-body schedule-form-shell">

      <input type="hidden" id="id" value="<?= esc($id) ?>">
      <input type="hidden" id="csrfName" value="<?= csrf_token() ?>">
      <input type="hidden" id="csrfHash" value="<?= csrf_hash() ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <div class="schedule-block schedule-block-danger">
            <div class="schedule-block-title">Habilita</div>

            <label class="form-label schedule-label">Día</label>
            <select id="enable_dow" class="form-select mb-3">
              <?php foreach ($dowLabels as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($enable_dow === $k) ? 'selected' : '' ?>>
                  <?= esc($label) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label class="form-label schedule-label">Hora</label>
            <input type="time" id="enable_time" class="form-control" value="<?= esc($enable_time) ?>">
          </div>
        </div>

        <div class="col-md-6">
          <div class="schedule-block">
            <div class="schedule-block-title">Deshabilita</div>

            <label class="form-label schedule-label">Día</label>
            <select id="disable_dow" class="form-select mb-3">
              <?php foreach ($dowLabels as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($disable_dow === $k) ? 'selected' : '' ?>>
                  <?= esc($label) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label class="form-label schedule-label">Hora</label>
            <input type="time" id="disable_time" class="form-control" value="<?= esc($disable_time) ?>">
          </div>
        </div>

        <div class="col-md-8">
          <div class="schedule-block">
            <label class="form-label schedule-label">Zona horaria</label>
            <input type="text" id="timezone" class="form-control" value="<?= esc($timezone) ?>">
            <div class="form-text">Ejemplo recomendado: <b>America/Guayaquil</b></div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="schedule-switch-card h-100 d-flex flex-column justify-content-center">
            <div class="schedule-block-title mb-2">Estado del horario</div>
            <div class="form-check">
              <input type="checkbox" id="active" class="form-check-input" <?= $active ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold" for="active">Horario activo</label>
            </div>
            <div class="text-muted small mt-2">
              Activa o desactiva esta programación global desde aquí.
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="schedule-note">
            El sistema consulta el estado actual automáticamente y actualiza el badge superior cada 10 segundos.
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button class="btn schedule-btn-save" id="btnSave" type="button">
            Guardar horario
          </button>
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
  msgEl.classList.remove(
    'd-none',
    'alert-success',
    'alert-danger',
    'alert-warning',
    'alert-info'
  );

  msgEl.classList.add('alert-' + type);
  msgEl.textContent = text;
}

function setStatusBadge(enabled, fallbackText = null) {
  estadoEl.classList.remove(
    'schedule-status-neutral',
    'schedule-status-on',
    'schedule-status-off'
  );

  if (fallbackText) {
    estadoEl.textContent = fallbackText;
    estadoEl.classList.add('schedule-status-neutral');
    return;
  }

  if (enabled) {
    estadoEl.textContent = 'HABILITADO ahora';
    estadoEl.classList.add('schedule-status-on');
  } else {
    estadoEl.textContent = 'DESHABILITADO ahora';
    estadoEl.classList.add('schedule-status-off');
  }
}

async function refreshStatus() {
  try {
    const res = await fetch(urlStatus, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const data = await res.json();

    if (data.ok) {
      setStatusBadge(!!data.enabled);
    } else {
      setStatusBadge(false, 'Estado no disponible');
    }
  } catch (e) {
    setStatusBadge(false, 'Estado no disponible');
  }
}

async function saveSchedule() {
  if (!timezoneEl.value.trim()) {
    showMessage('warning', 'Ingresa una zona horaria (ej: America/Guayaquil).');
    return;
  }

  const payload = {
    id: idEl.value ? parseInt(idEl.value, 10) : null,
    enable_dow: parseInt(enableDowEl.value, 10),
    enable_time: enableTimeEl.value,
    disable_dow: parseInt(disableDowEl.value, 10),
    disable_time: disableTimeEl.value,
    timezone: timezoneEl.value.trim(),
    active: activeEl.checked ? 1 : 0
  };

  payload[csrfNameEl.value] = csrfHashEl.value;

  const btn = document.getElementById('btnSave');
  const oldText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    const res = await fetch(urlSave, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (data.csrfHash) {
      csrfHashEl.value = data.csrfHash;
    }

    if (!data.ok) {
      showMessage(
        'danger',
        data.errors ? JSON.stringify(data.errors) : (data.message || 'Error')
      );
      return;
    }

    if (data.id) {
      idEl.value = data.id;
    }

    showMessage('success', 'Horario guardado correctamente.');
    await refreshStatus();

  } catch (e) {
    showMessage('danger', 'Ocurrió un error al guardar el horario.');
  } finally {
    btn.disabled = false;
    btn.textContent = oldText;
  }
}

document.getElementById('btnSave').addEventListener('click', saveSchedule);

(async function init() {
  await refreshStatus();
  setInterval(refreshStatus, 10000);
})();
</script>

<?= $this->endSection() ?>