<?= $this->extend('layouts/main') ?> 
<?= $this->section('contenido') ?>

<?php
/**
 * ------------------------------------------------------------
 * Vista: tareas/asignar.php  (UI renombrada a "Actividades")
 * ------------------------------------------------------------
 *
 * ✅ Mantiene lo funcional:
 * - Actividad individual: fecha inicio/fin con hora (Flatpickr).
 * - Edición: fecha inicio fija; si scope=self, fecha fin NO se edita directo (solo solicitud).
 * - Prioridad automática según fecha fin (hidden real).
 * - Multi-asignación (asignado_a[]).
 * - Soporta recurrencia (daily/weekly) SOLO al crear.
 *
 * ✅ UI mejorada con paleta corporativa:
 * - #F20505
 * - #F22E2E
 * - #F27272
 * - #F2F2F2
 * - #0D0D0D
 *
 * ✅ Cambios visuales:
 * - Tarjetas modernas
 * - Encabezado con gradiente
 * - Inputs estilizados
 * - Botones corporativos
 * - Microanimaciones
 * - Modal integrado al mismo estilo
 */

// ------------------------------------------------------------
// 1) Contexto edición/creación
// ------------------------------------------------------------
$isEdit = !empty($tarea);

// ------------------------------------------------------------
// 2) Valores (old() > tarea)
// ------------------------------------------------------------
$old = $old ?? [];

$oldTitulo = $old['titulo'] ?? ($tarea['titulo'] ?? '');
$oldDesc   = $old['descripcion'] ?? ($tarea['descripcion'] ?? '');

// Prioridad (hidden real, visible calculada)
$oldPrioridad = (int)($old['id_prioridad'] ?? ($tarea['id_prioridad'] ?? 0));

// Área (old() > tarea)
$oldArea = (int)($old['id_area'] ?? ($tarea['id_area'] ?? 0));

/**
 * ✅ Multi-asignación:
 * - old('asignado_a') puede venir como array
 * - tarea['asignado_a'] (en edición) debe venir como array
 */
$rawOldAsignados = $old['asignado_a'] ?? ($tarea['asignado_a'] ?? []);
$oldAsignados = [];

if (is_array($rawOldAsignados)) {
    foreach ($rawOldAsignados as $v) {
        $n = (int)$v;
        if ($n > 0) $oldAsignados[] = $n;
    }
} else {
    $n = (int)$rawOldAsignados;
    if ($n > 0) $oldAsignados[] = $n;
}
$oldAsignados = array_values(array_unique($oldAsignados));

$oldInicio = $old['fecha_inicio'] ?? ($tarea['fecha_inicio'] ?? '');
$oldFin    = $old['fecha_fin'] ?? ($tarea['fecha_fin'] ?? '');

// ------------------------------------------------------------
// 3) Acción del form
// ------------------------------------------------------------
$formAction = $isEdit
    ? site_url('tareas/actualizar/' . (int)$tarea['id_tarea'])
    : site_url('tareas/asignar');

// ------------------------------------------------------------
// 4) Convertidor a DB format (Y-m-d H:i) desde valores variados
// ------------------------------------------------------------
$toDbDateTime = function($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';

    if (strpos($value, 'T') !== false && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
        return str_replace('T', ' ', substr($value, 0, 16));
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/', $value)) {
        return substr($value, 0, 16);
    }

    try {
        $tz = new \DateTimeZone('America/Guayaquil');
        $dt = new \DateTime($value, $tz);
        return $dt->format('Y-m-d H:i');
    } catch (\Throwable $e) {
        return '';
    }
};

// Valores DB para hidden (lo que se envía)
$oldInicioDb = $toDbDateTime($oldInicio);
$oldFinDb    = $toDbDateTime($oldFin);

// ------------------------------------------------------------
// 5) Datos sesión
// ------------------------------------------------------------
$currentUserId   = (int)(session()->get('id_user') ?? 0);
$currentUserArea = (int)(session()->get('id_area') ?? 0);

// ------------------------------------------------------------
// 6) Scope para UI
// ------------------------------------------------------------
$assignScope = $assignScope ?? [
    'mode'       => ($currentUserArea === 1 ? 'super' : 'unknown'),
    'divisionId' => null,
    'areaId'     => null,
];
$assignMode = (string)($assignScope['mode'] ?? 'unknown');

// ------------------------------------------------------------
// 7) Bloqueo UI según scope
// ------------------------------------------------------------
$lockAreaSelect = in_array($assignMode, ['area', 'self'], true);

// Área fija para jefe área / self
$scopeAreaId = (int)($assignScope['areaId'] ?? 0);

if (in_array($assignMode, ['area', 'self'], true) && $scopeAreaId <= 0 && $currentUserArea > 0 && $currentUserArea !== 1) {
    $scopeAreaId = $currentUserArea;
}

if ($lockAreaSelect && $scopeAreaId > 0) {
    $oldArea = $scopeAreaId;
}

// Nombre de área para mostrar cuando está bloqueada
$areaNameLocked = '';
if ($lockAreaSelect && $oldArea > 0 && !empty($areasDivision)) {
    foreach ($areasDivision as $a) {
        if ((int)$a['id_area'] === $oldArea) {
            $areaNameLocked = (string)$a['nombre_area'];
            break;
        }
    }
}

// ============================================================
// ✅ ESTADO FIJO (CREAR + EDITAR) - OCULTO EN UI
// ============================================================
$fixedEstadoId    = 2; // En proceso
$fixedEstadoLabel = 'En proceso';

if (!empty($estados) && is_array($estados)) {
    foreach ($estados as $e) {
        if ((int)($e['id_estado_tarea'] ?? 0) === 2) {
            $fixedEstadoLabel = (string)($e['nombre_estado'] ?? 'En proceso');
            break;
        }
    }
}

// ============================================================
// ✅ LÍMITE EDICIONES DE FECHA (SOLO EDITAR)
// ============================================================
$maxDateEdits     = 3;
$currentEditCount = $isEdit ? (int)($tarea['edit_count'] ?? 0) : 0;
$remainingEdits   = max(0, $maxDateEdits - $currentEditCount);

// ============================================================
// ✅ DEFAULTS DE RECURRENCIA (SOLO CREAR)
// ============================================================
$tzEc = new \DateTimeZone('America/Guayaquil');
$recStartDateDefault = (new \DateTimeImmutable('now', $tzEc))->format('Y-m-d');

$recStartTimeDefault = '13:45';
$recEndTimeDefault   = '14:45';

if (!$isEdit) {
    if (is_string($oldInicioDb) && strlen($oldInicioDb) >= 16) {
        $recStartTimeDefault = substr($oldInicioDb, 11, 5);
        $recStartDateDefault = substr($oldInicioDb, 0, 10) ?: $recStartDateDefault;
    }
    if (is_string($oldFinDb) && strlen($oldFinDb) >= 16) {
        $recEndTimeDefault = substr($oldFinDb, 11, 5);
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
:root{
  --ws-red-1:#F20505;
  --ws-red-2:#F22E2E;
  --ws-red-3:#F27272;
  --ws-light:#F2F2F2;
  --ws-dark:#0D0D0D;

  --ws-border:rgba(13,13,13,.10);
  --ws-border-strong:rgba(13,13,13,.18);
  --ws-shadow:0 18px 45px -28px rgba(13,13,13,.35);
  --ws-shadow-soft:0 10px 24px -18px rgba(13,13,13,.22);
  --ws-radius-xl:22px;
  --ws-radius-lg:18px;
  --ws-radius-md:14px;
  --ws-radius-sm:12px;
  --ws-transition:all .22s ease;
}

/* =========================================================
   Fondo / animación general
========================================================= */
.ws-activity-page{
  animation: wsFadeIn .38s ease;
}

@keyframes wsFadeIn{
  from{
    opacity:0;
    transform:translateY(10px);
  }
  to{
    opacity:1;
    transform:translateY(0);
  }
}

/* =========================================================
   Header superior
========================================================= */
.ws-page-hero{
  position:relative;
  overflow:hidden;
  border-radius:var(--ws-radius-xl);
  padding:1.4rem 1.4rem;
  margin-bottom:1.25rem;
  background:
    linear-gradient(135deg, var(--ws-dark) 0%, #231313 35%, var(--ws-red-2) 100%);
  color:#fff;
  box-shadow:var(--ws-shadow);
}

.ws-page-hero::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at top right, rgba(242,114,114,.35), transparent 30%),
    radial-gradient(circle at bottom left, rgba(242,242,242,.08), transparent 32%);
  pointer-events:none;
}

.ws-page-hero > *{
  position:relative;
  z-index:1;
}

.ws-page-title{
  margin:0;
  font-weight:900;
  letter-spacing:.3px;
}

.ws-page-subtitle{
  margin-top:.35rem;
  color:rgba(255,255,255,.86);
  font-size:.95rem;
}

.ws-top-pill{
  display:inline-flex;
  align-items:center;
  gap:.45rem;
  border-radius:999px;
  padding:.45rem .85rem;
  border:1px solid rgba(255,255,255,.16);
  background:rgba(255,255,255,.10);
  color:#fff;
  font-weight:800;
  font-size:.85rem;
  box-shadow:0 10px 25px -20px rgba(255,255,255,.35);
}

/* =========================================================
   Card principal
========================================================= */
.ws-main-card{
  border:1px solid var(--ws-border);
  border-radius:var(--ws-radius-xl);
  background:linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
  box-shadow:var(--ws-shadow);
  overflow:hidden;
}

.ws-main-card .card-body{
  padding:1.4rem;
}

/* =========================================================
   Secciones
========================================================= */
.ws-section{
  border:1px solid var(--ws-border);
  border-radius:var(--ws-radius-lg);
  background:#fff;
  padding:1rem;
  box-shadow:var(--ws-shadow-soft);
  transition:var(--ws-transition);
}

.ws-section:hover{
  transform:translateY(-1px);
}

.ws-section-title{
  display:flex;
  align-items:center;
  gap:.6rem;
  margin:0 0 .85rem 0;
  font-size:1rem;
  font-weight:900;
  letter-spacing:.2px;
  color:var(--ws-dark);
}

.ws-section-title::before{
  content:"";
  width:10px;
  height:10px;
  border-radius:999px;
  background:linear-gradient(135deg, var(--ws-red-1) 0%, var(--ws-red-3) 100%);
  box-shadow:0 0 0 5px rgba(242,46,46,.10);
}

/* =========================================================
   Labels / forms
========================================================= */
.form-label{
  font-weight:800 !important;
  color:var(--ws-dark);
  margin-bottom:.45rem;
}

.form-control,
.form-select{
  border:1px solid rgba(13,13,13,.14);
  border-radius:14px;
  min-height:46px;
  background:#fff;
  color:var(--ws-dark);
  transition:var(--ws-transition);
  box-shadow:none;
}

textarea.form-control{
  min-height:120px;
  resize:vertical;
}

.form-control:focus,
.form-select:focus{
  border-color:rgba(242,46,46,.45);
  box-shadow:0 0 0 .22rem rgba(242,46,46,.12);
}

.form-control[disabled],
.form-control[readonly],
.form-select[disabled]{
  background:#f7f7f7 !important;
  color:rgba(13,13,13,.70) !important;
  cursor:not-allowed;
}

.text-muted,
small.text-muted,
.date-help,
.users-note,
.recurrence-preview,
.recurrence_hint,
.text-muted.small{
  color:rgba(13,13,13,.68) !important;
}

/* =========================================================
   Box de usuarios
========================================================= */
.users-box{
  border:1px solid rgba(13,13,13,.14);
  border-radius:16px;
  padding:12px 12px;
  max-height:260px;
  overflow:auto;
  background:linear-gradient(180deg, #fff 0%, #fbfbfb 100%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6);
}

.users-box::-webkit-scrollbar{
  width:10px;
}
.users-box::-webkit-scrollbar-thumb{
  background:rgba(242,46,46,.35);
  border-radius:999px;
}
.users-box::-webkit-scrollbar-track{
  background:rgba(13,13,13,.05);
  border-radius:999px;
}

.users-empty{
  font-size:.92rem;
  color:rgba(13,13,13,.65);
}

.form-check{
  padding:.4rem 0 .4rem 1.9rem;
  border-radius:12px;
  transition:var(--ws-transition);
}

.form-check:hover{
  background:rgba(242,46,46,.05);
}

.form-check-input{
  width:1.1rem;
  height:1.1rem;
  border:1.5px solid rgba(13,13,13,.38);
  cursor:pointer;
}

.form-check-input:checked{
  background-color:var(--ws-red-2);
  border-color:var(--ws-red-2);
}

.form-check-input:focus{
  box-shadow:0 0 0 .18rem rgba(242,46,46,.12);
}

.form-check-label{
  color:var(--ws-dark);
  font-weight:600;
  cursor:pointer;
}

/* =========================================================
   Request / recurrence box
========================================================= */
.request-box{
  border:1px solid rgba(13,13,13,.12);
  border-radius:18px;
  padding:16px;
  background:
    linear-gradient(180deg, rgba(242,242,242,.92) 0%, #ffffff 100%);
  box-shadow:var(--ws-shadow-soft);
}

.recurrence-preview b{
  color:var(--ws-red-1);
  font-weight:900;
}

/* =========================================================
   Alerts
========================================================= */
.alert{
  border:none;
  border-radius:16px;
  box-shadow:var(--ws-shadow-soft);
}

.alert-info{
  background:linear-gradient(180deg, rgba(242,114,114,.15) 0%, rgba(242,242,242,.98) 100%);
  color:var(--ws-dark);
}

.alert-warning{
  background:linear-gradient(180deg, rgba(242,46,46,.14) 0%, rgba(242,242,242,.98) 100%);
  color:var(--ws-dark);
}

.alert-danger{
  background:linear-gradient(180deg, rgba(242,5,5,.14) 0%, rgba(242,242,242,.98) 100%);
  color:var(--ws-dark);
}

.alert-success{
  background:linear-gradient(180deg, rgba(242,114,114,.18) 0%, rgba(242,242,242,.98) 100%);
  color:var(--ws-dark);
}

/* =========================================================
   Badges
========================================================= */
.badge.bg-dark{
  background:linear-gradient(135deg, var(--ws-dark) 0%, #303030 100%) !important;
  border-radius:999px;
  padding:.5rem .75rem;
}

/* =========================================================
   Botones
========================================================= */
.btn-black{
  background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%);
  color:#fff;
  border:none;
  border-radius:14px;
  padding:.72rem 1.15rem;
  font-weight:900;
  letter-spacing:.2px;
  transition:var(--ws-transition);
  box-shadow:0 15px 30px -18px rgba(13,13,13,.38);
}
.btn-black:hover{
  color:#fff;
  transform:translateY(-1px);
  box-shadow:0 18px 34px -18px rgba(242,46,46,.28);
  filter:brightness(1.02);
}
.btn-black:focus{
  color:#fff;
  box-shadow:0 0 0 .22rem rgba(242,46,46,.15);
}

.btn-black-outline{
  background:#fff;
  color:var(--ws-dark);
  border:1px solid rgba(13,13,13,.18);
  border-radius:14px;
  padding:.72rem 1.15rem;
  font-weight:800;
  transition:var(--ws-transition);
}
.btn-black-outline:hover{
  background:var(--ws-dark);
  border-color:var(--ws-dark);
  color:#fff;
  transform:translateY(-1px);
}

.ws-submit-wrap{
  border-top:1px solid rgba(13,13,13,.08);
  margin-top:1.4rem;
  padding-top:1.1rem;
}

/* =========================================================
   Hidden UI
========================================================= */
.ws-hidden-ui{
  display:none !important;
}

/* =========================================================
   Flatpickr
========================================================= */
.flatpickr-calendar{
  border:none !important;
  border-radius:18px !important;
  overflow:hidden !important;
  box-shadow:0 20px 45px -25px rgba(13,13,13,.35) !important;
}

.flatpickr-months{
  background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%) !important;
}

.flatpickr-current-month,
.flatpickr-monthDropdown-months,
.flatpickr-weekday,
.flatpickr-prev-month,
.flatpickr-next-month{
  color:#fff !important;
  fill:#fff !important;
}

.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange{
  background:var(--ws-red-2) !important;
  border-color:var(--ws-red-2) !important;
}

.flatpickr-day.today{
  border-color:var(--ws-red-2) !important;
}

.flatpickr-time input:hover,
.flatpickr-time .flatpickr-am-pm:hover{
  background:rgba(242,46,46,.08) !important;
}

/* =========================================================
   Modal
========================================================= */
.modal-content{
  border:none;
  border-radius:22px;
  overflow:hidden;
  box-shadow:0 28px 55px -26px rgba(13,13,13,.40);
}

.modal-header{
  border:none;
  padding:1rem 1.15rem;
}

.modal-header.bg-dark{
  background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%) !important;
}

.modal-title{
  font-weight:900;
}

.modal-body{
  padding:1.2rem;
  background:linear-gradient(180deg, #fff 0%, var(--ws-light) 100%);
}

.modal-footer{
  border:none;
  padding:1rem 1.15rem 1.15rem;
  background:linear-gradient(180deg, #fff 0%, #fafafa 100%);
}

/* =========================================================
   Responsive
========================================================= */
@media (max-width: 767.98px){
  .ws-page-hero{
    padding:1.1rem;
  }

  .ws-main-card .card-body{
    padding:1rem;
  }

  .ws-section{
    padding:.9rem;
  }
}
</style>

<div class="container py-4 ws-activity-page">

  <!-- =========================================================
       Header visual
  ========================================================= -->
  <div class="ws-page-hero">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h3 class="ws-page-title">
          <?= $isEdit ? 'Editar / Reasignar Actividad' : 'Crear Actividad' ?>
        </h3>
        <div class="ws-page-subtitle">
          Gestión visual y operativa de actividades con el estilo corporativo del sistema.
        </div>
      </div>

      <div class="ws-top-pill">
        <?= $isEdit ? 'Modo edición' : 'Nueva actividad' ?>
      </div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= $formAction ?>" class="card ws-main-card" id="taskForm">
    <div class="card-body">
      <?= csrf_field() ?>

      <!-- =========================================================
           Hidden reales que el backend espera
      ========================================================= -->
      <input type="hidden" name="id_prioridad" id="id_prioridad" value="<?= (int)$oldPrioridad ?>">
      <input type="hidden" name="id_estado_tarea" value="<?= (int)$fixedEstadoId ?>">
      <input type="hidden" name="fecha_inicio" id="fecha_inicio" value="<?= esc($oldInicioDb) ?>">
      <input type="hidden" name="fecha_fin"    id="fecha_fin"    value="<?= esc($oldFinDb) ?>">
      <input type="hidden" name="review_action" id="review_action" value="">
      <input type="hidden" name="review_reason" id="review_reason" value="">
      <input type="hidden" name="review_requested_fecha_fin" id="review_requested_fecha_fin" value="">

      <div class="row g-4">

        <!-- =========================================================
             Datos generales
        ========================================================= -->
        <div class="col-12">
          <div class="ws-section">
            <h5 class="ws-section-title">Información general</h5>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">División</label>
                <input type="text" class="form-control"
                       value="<?= esc($divisionUsuario['nombre_division'] ?? '—') ?>" disabled>
              </div>

              <div class="col-md-6">
                <label class="form-label">Nombre de la actividad</label>
                <input type="text" name="titulo" class="form-control"
                       value="<?= esc($oldTitulo) ?>" required>
              </div>

              <div class="col-md-3">
                <label class="form-label">Prioridad</label>
                <select id="id_prioridad_ui" class="form-select" disabled>
                  <option value="">Prioridad Automática</option>
                  <?php foreach (($prioridades ?? []) as $p): ?>
                    <option value="<?= (int)$p['id_prioridad'] ?>"
                      <?= ((int)$p['id_prioridad'] === (int)$oldPrioridad ? 'selected' : '') ?>>
                      <?= esc($p['nombre_prioridad']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-1">La prioridad se calcula automáticamente por la fecha fin.</small>
              </div>

              <!-- Estado fijo oculto -->
              <div class="col-md-3 ws-hidden-ui">
                <label class="form-label">Estado</label>
                <select class="form-select" disabled>
                  <option value="<?= (int)$fixedEstadoId ?>" selected><?= esc($fixedEstadoLabel) ?></option>
                </select>
              </div>

              <div class="col-md-3">
                <label class="form-label">Área</label>

                <?php if ($lockAreaSelect): ?>
                  <input type="hidden" name="id_area" value="<?= (int)$oldArea ?>">
                  <input type="text" class="form-control"
                         value="<?= esc($areaNameLocked !== '' ? $areaNameLocked : 'Área asignada por tu perfil') ?>"
                         disabled>
                <?php else: ?>
                  <select name="id_area" id="id_area" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach (($areasDivision ?? []) as $a): ?>
                      <option value="<?= (int)$a['id_area'] ?>"
                        <?= ((int)$a['id_area'] === $oldArea ? 'selected' : '') ?>>
                        <?= esc($a['nombre_area']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
              </div>

              <div class="col-md-6">
                <label class="form-label">Asignar a</label>

                <?php if ($assignMode === 'self'): ?>
                  <div class="users-box">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" checked disabled>
                      <label class="form-check-label">
                        Tú (Autoasignación)
                      </label>
                    </div>

                    <input type="hidden" name="asignado_a[]" value="<?= (int)$currentUserId ?>">
                  </div>
                <?php else: ?>
                  <div id="assigneeBox" class="users-box">
                    <div class="users-empty">Selecciona un área para cargar usuarios.</div>
                  </div>

                  <?php if ($isEdit): ?>
                    <div class="users-note mt-2">
                      Nota: si desmarcas un usuario y guardas, su actividad quedará en estado <b>Cancelada</b> para ese usuario.
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- =========================================================
             Límite edición
        ========================================================= -->
        <?php if ($isEdit): ?>
          <div class="col-12">
            <div class="alert alert-info mb-0">
              <b>Edición de fechas:</b> puedes actualizar la fecha de esta actividad <b>máximo <?= (int)$maxDateEdits ?></b> veces.
              <br>
              <b>Ediciones restantes:</b> <span class="badge bg-dark"><?= (int)$remainingEdits ?></span>
              <br>
              <small class="text-muted">
                * El contador se consume <b>solo cuando el cambio de fecha es aprobado</b>.
              </small>
            </div>
          </div>
        <?php endif; ?>

        <!-- =========================================================
             Fechas individuales
        ========================================================= -->
        <div class="col-12">
          <div class="ws-section">
            <h5 class="ws-section-title">Programación de actividad</h5>

            <div class="row g-4">
              <div class="col-md-3">
                <label class="form-label">Fecha inicio</label>

                <input type="text"
                       id="fecha_inicio_ui"
                       class="form-control"
                       placeholder="Ej: 24/02/2026 09:15 AM"
                       autocomplete="off"
                       required>

                <div class="date-help mt-2">
                  <?= $isEdit ? 'Fecha inicio fija (no editable).' : 'No se permiten días anteriores a hoy.' ?>
                </div>
              </div>

              <div class="col-md-3">
                <label class="form-label">Fecha fin</label>

                <input type="text"
                       id="fecha_fin_ui"
                       class="form-control"
                       placeholder="Ej: 24/02/2026 11:00 AM"
                       autocomplete="off"
                       required>

                <div class="date-help mt-2">
                  <?php if ($isEdit && $assignMode === 'self'): ?>
                    Fecha fin bloqueada. Usa <b>Solicitar cambio de fecha</b>.
                  <?php else: ?>
                    Debe ser igual o mayor a inicio. <?= $isEdit ? '' : 'No se permiten días anteriores a hoy.' ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($isEdit && $assignMode === 'self'): ?>
                <div class="col-12">
                  <div class="request-box">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                      <div>
                        <b>Solicitudes a revisión</b>
                        <div class="text-muted small">
                          Solo tú (asignado) puedes solicitar cambio de fecha con motivo.
                        </div>
                      </div>

                      <button type="button"
                              class="btn btn-black"
                              id="btnOpenDateChange"
                              <?= ($remainingEdits <= 0 ? 'disabled' : '') ?>>
                        Solicitar cambio de fecha
                      </button>
                    </div>

                    <?php if ($remainingEdits <= 0): ?>
                      <div class="text-danger small mt-2">
                        ⚠️ No puedes solicitar más cambios de fecha (límite alcanzado).
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- =========================================================
             Recurrencia
        ========================================================= -->
        <?php if (!$isEdit): ?>
          <div class="col-12">
            <div class="ws-section">
              <h5 class="ws-section-title">Actividades diarias / recurrencia</h5>

              <div class="request-box">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                  <div>
                    <b>Repetir esta actividad</b>
                    <div class="text-muted small">
                      Activa esta opción solo si la actividad se repite diariamente o por días específicos.
                    </div>
                  </div>

                  <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="recurrence_enabled" name="recurrence_enabled" value="1">
                    <label class="form-check-label" for="recurrence_enabled">Activar</label>
                  </div>
                </div>

                <div id="recurrence_panel" class="mt-3" style="display:none;">
                  <div class="row g-3">

                    <div class="col-md-4">
                      <label class="form-label">Tipo</label>
                      <select class="form-select" id="repeat_type" name="repeat_type">
                        <option value="daily">Diaria (todos los días)</option>
                        <option value="weekly">Semanal (días específicos)</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Número de semanas</label>
                      <input type="number"
                             class="form-control"
                             id="weeks_count_ui"
                             value="1"
                             readonly>
                      <input type="hidden" id="weeks_count" name="weeks_count" value="1">
                      <div class="text-muted small mt-1">Fijo: 1 semana.</div>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Repetir hasta</label>
                      <input type="date" class="form-control" id="repeat_until" name="repeat_until" value="" readonly>
                      <div class="text-muted small mt-1">Fijo: 1 semana desde la fecha inicio serie.</div>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Fecha inicio serie</label>
                      <input type="date" class="form-control" id="rec_start_date" name="rec_start_date"
                             value="<?= esc($recStartDateDefault) ?>"
                             min="<?= esc($recStartDateDefault) ?>">
                      <div class="text-muted small mt-1">No se permiten días anteriores.</div>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Hora inicio</label>
                      <input type="time" class="form-control" id="rec_start_time" name="rec_start_time" value="<?= esc($recStartTimeDefault) ?>">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Hora fin</label>
                      <input type="time" class="form-control" id="rec_end_time" name="rec_end_time" value="<?= esc($recEndTimeDefault) ?>">
                    </div>

                    <div class="col-md-12" id="days_of_week_wrap" style="display:none;">
                      <label class="form-label mb-2">Días de la semana</label>

                      <div class="d-flex flex-wrap gap-3">
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="3">
                          <span class="form-check-label">Mié</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="4">
                          <span class="form-check-label">Jue</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="5">
                          <span class="form-check-label">Vie</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="6">
                          <span class="form-check-label">Sáb</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="7">
                          <span class="form-check-label">Dom</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="1">
                          <span class="form-check-label">Lun</span>
                        </label>
                        <label class="form-check m-0">
                          <input class="form-check-input" type="checkbox" name="days_of_week[]" value="2">
                          <span class="form-check-label">Mar</span>
                        </label>
                      </div>

                      <div class="text-muted small mt-2">
                        * Si escoges “Semanal”, debes marcar al menos un día.
                      </div>
                    </div>

                    <div class="col-12">
                      <div class="recurrence-preview" id="recurrence_preview"></div>
                      <div class="text-muted small mt-1" id="recurrence_hint"></div>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- =========================================================
             Descripción
        ========================================================= -->
        <div class="col-12">
          <div class="ws-section">
            <h5 class="ws-section-title">Descripción</h5>
            <label class="form-label">Descripción de la actividad</label>
            <textarea name="descripcion" class="form-control" rows="3"><?= esc($oldDesc) ?></textarea>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 ws-submit-wrap">
        <a href="<?= site_url('tareas/gestionar') ?>" class="btn btn-black-outline">Cancelar</a>

        <button type="submit" class="btn btn-black" id="btnSubmitMain">
          <?= $isEdit ? 'Guardar cambios' : 'Asignar' ?>
        </button>
      </div>
    </div>
  </form>
</div>

<!-- =========================================================
     MODAL: Solicitar cambio de fecha (SELF)
========================================================= -->
<div class="modal fade" id="modalDateChange" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Solicitar cambio de fecha fin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning">
          Esta solicitud <b>NO</b> cambia la fecha inmediatamente.<br>
          Se enviará a <b>revisión</b> de tu supervisor con el motivo.
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nueva fecha fin solicitada</label>
            <input type="text"
                   id="requested_end_ui"
                   class="form-control"
                   placeholder="Ej: 24/02/2026 06:00 PM"
                   autocomplete="off">
            <div class="text-muted small mt-1">
              Debe ser igual o mayor a la fecha inicio.
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Motivo del cambio (obligatorio)</label>
            <textarea id="requested_reason_ui"
                      class="form-control"
                      rows="4"
                      placeholder="Escribe el motivo..."></textarea>
            <div class="text-muted small mt-1">
              El supervisor verá este motivo para aprobar o rechazar.
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-black-outline" data-bs-dismiss="modal">Volver</button>
        <button type="button" class="btn btn-black" id="btnSendDateChange">
          Enviar a revisión
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
/* =========================================================
   Variables base desde PHP
========================================================= */
const assignMode    = <?= json_encode($assignMode) ?>;
const currentUserId = <?= (int)$currentUserId ?>;
const isEdit        = <?= $isEdit ? 'true' : 'false' ?>;

const oldArea = <?= (int)$oldArea ?>;
const oldUserIds = <?= json_encode($oldAsignados) ?>;

const remainingEdits = <?= (int)$remainingEdits ?>;

const areaEl      = document.getElementById('id_area');
const assigneeBox = document.getElementById('assigneeBox') || null;

const fechaInicioHidden = document.getElementById('fecha_inicio');
const fechaFinHidden    = document.getElementById('fecha_fin');

const fechaInicioUI = document.getElementById('fecha_inicio_ui');
const fechaFinUI    = document.getElementById('fecha_fin_ui');

const reviewActionHidden  = document.getElementById('review_action');
const reviewReasonHidden  = document.getElementById('review_reason');
const reviewReqEndHidden  = document.getElementById('review_requested_fecha_fin');

/* =========================================================
   Helpers fechas
========================================================= */
function toDbString(dateObj){
  const pad = (n) => String(n).padStart(2, '0');
  const y = dateObj.getFullYear();
  const m = pad(dateObj.getMonth()+1);
  const d = pad(dateObj.getDate());
  const h = pad(dateObj.getHours());
  const i = pad(dateObj.getMinutes());
  return `${y}-${m}-${d} ${h}:${i}`;
}
function roundUpTo15Min(dateObj){
  const d = new Date(dateObj.getTime());
  const ms = 15 * 60 * 1000;
  return new Date(Math.ceil(d.getTime() / ms) * ms);
}
function todayStart(){
  const d = new Date();
  d.setHours(0,0,0,0);
  return d;
}
function dayKeyFromDb(dbStr){
  if (!dbStr) return '';
  return dbStr.substring(0,10);
}

/* =========================================================
   Defaults al CREAR si no hay valores
========================================================= */
function applyCreateDefaultsIfEmpty(){
  if (isEdit) return;
  const hasStart = (fechaInicioHidden.value || '').trim() !== '';
  const hasEnd   = (fechaFinHidden.value || '').trim() !== '';
  if (hasStart || hasEnd) return;

  const start = roundUpTo15Min(new Date());
  const end   = new Date(start.getTime() + (60 * 60 * 1000));

  fechaInicioHidden.value = toDbString(start);
  fechaFinHidden.value    = toDbString(end);
}
applyCreateDefaultsIfEmpty();

const minDate = todayStart();

const initialStartDb = (fechaInicioHidden.value || '').trim();
const initialEndDb   = (fechaFinHidden.value || '').trim();

function allowPastKeep(which, newDb){
  if (!isEdit) return false;
  if (which === 'start') return (newDb === initialStartDb && initialStartDb !== '');
  if (which === 'end')   return (newDb === initialEndDb   && initialEndDb !== '');
  return false;
}

/* =========================================================
   Flatpickr Inicio
========================================================= */
const fpStart = flatpickr(fechaInicioUI, {
  enableTime: true,
  time_24hr: false,
  minuteIncrement: 5,
  allowInput: false,
  altInput: true,
  altFormat: "d/m/Y h:i K",
  dateFormat: "Y-m-d H:i",
  minDate: (!isEdit ? minDate : null),
  defaultDate: (fechaInicioHidden.value ? fechaInicioHidden.value : null),

  onReady: function(selectedDates){
    if (!selectedDates.length && fechaInicioHidden.value){
      this.setDate(fechaInicioHidden.value, true);
    }

    if (isEdit) {
      this.set('clickOpens', false);
      if (this.altInput) {
        this.altInput.setAttribute('readonly', 'readonly');
        this.altInput.style.background = '#f1f3f5';
        this.altInput.style.cursor = 'not-allowed';
      }
      fechaInicioUI.setAttribute('readonly', 'readonly');
    }
  },

  onChange: function(selectedDates){
    if (isEdit) {
      if (initialStartDb) {
        this.setDate(initialStartDb, true);
        fechaInicioHidden.value = initialStartDb;
      }
      return;
    }

    if (!selectedDates || !selectedDates.length) {
      fechaInicioHidden.value = '';
      return;
    }

    const start = selectedDates[0];
    const dbStr = toDbString(start);

    const todayKey = dayKeyFromDb(toDbString(new Date()));
    const newKey   = dayKeyFromDb(dbStr);

    if (newKey < todayKey && !allowPastKeep('start', dbStr)){
      alert("No puedes seleccionar un día anterior al de hoy.");
      this.clear();
      fechaInicioHidden.value = '';
      return;
    }

    fechaInicioHidden.value = dbStr;

    fpEnd.set('minDate', start);

    if (fechaFinHidden.value){
      const endDb = fechaFinHidden.value;
      if (endDb < dbStr){
        fpEnd.clear();
        fechaFinHidden.value = '';
      }
    }
  }
});

/* =========================================================
   Flatpickr Fin
========================================================= */
const fpEnd = flatpickr(fechaFinUI, {
  enableTime: true,
  time_24hr: false,
  minuteIncrement: 5,
  allowInput: true,
  altInput: true,
  altFormat: "d/m/Y h:i K",
  dateFormat: "Y-m-d H:i",
  defaultDate: (fechaFinHidden.value ? fechaFinHidden.value : null),
  minDate: (fechaInicioHidden.value ? fechaInicioHidden.value : (!isEdit ? minDate : null)),

  onReady: function(selectedDates){
    if (!selectedDates.length && fechaFinHidden.value){
      this.setDate(fechaFinHidden.value, true);
    }
    if (fechaInicioHidden.value){
      this.set('minDate', fechaInicioHidden.value);
    }

    if (isEdit && assignMode === 'self') {
      this.set('clickOpens', false);
      if (this.altInput) {
        this.altInput.setAttribute('readonly', 'readonly');
        this.altInput.style.background = '#f1f3f5';
        this.altInput.style.cursor = 'not-allowed';
      }
      fechaFinUI.setAttribute('readonly', 'readonly');
    }
  },

  onChange: function(selectedDates){

    if (isEdit && assignMode === 'self') {
      if (initialEndDb) {
        this.setDate(initialEndDb, true);
        fechaFinHidden.value = initialEndDb;
      }
      syncPriorityUi();
      return;
    }

    if (!selectedDates || !selectedDates.length) {
      fechaFinHidden.value = '';
      syncPriorityUi();
      return;
    }

    const end = selectedDates[0];
    const dbStr = toDbString(end);

    const todayKey = dayKeyFromDb(toDbString(new Date()));
    const newKey   = dayKeyFromDb(dbStr);

    if (newKey < todayKey && !allowPastKeep('end', dbStr)){
      alert("No puedes seleccionar un día anterior al de hoy.");
      this.clear();
      fechaFinHidden.value = '';
      syncPriorityUi();
      return;
    }

    if (fechaInicioHidden.value && dbStr < fechaInicioHidden.value){
      alert("La fecha final no puede ser menor a la fecha de inicio.");
      this.clear();
      fechaFinHidden.value = '';
      syncPriorityUi();
      return;
    }

    fechaFinHidden.value = dbStr;
    syncPriorityUi();
  }
});

if (fechaInicioHidden.value && !fechaInicioUI.value) fpStart.setDate(fechaInicioHidden.value, true);
if (fechaFinHidden.value && !fechaFinUI.value) fpEnd.setDate(fechaFinHidden.value, true);
if (fechaInicioHidden.value) fpEnd.set('minDate', fechaInicioHidden.value);

/* =========================================================
   Usuarios por área
========================================================= */
function renderUserCheckboxes(users){
  if (!assigneeBox) return;

  assigneeBox.innerHTML = '';

  if (!users || users.length === 0) {
    const div = document.createElement('div');
    div.className = 'users-empty';
    div.textContent = 'No hay usuarios disponibles para esta área.';
    assigneeBox.appendChild(div);
    return;
  }

  users.forEach(u => {
    const id = Number(u.id_user);

    const wrap = document.createElement('div');
    wrap.className = 'form-check';

    const input = document.createElement('input');
    input.className = 'form-check-input';
    input.type = 'checkbox';
    input.name = 'asignado_a[]';
    input.value = String(id);
    input.id = `asig_${id}`;

    if (oldUserIds.includes(id)) input.checked = true;

    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.setAttribute('for', input.id);
    label.textContent = u.label;

    wrap.appendChild(input);
    wrap.appendChild(label);
    assigneeBox.appendChild(wrap);
  });
}

async function loadUsersByArea(areaId){
  if (!assigneeBox) return;
  assigneeBox.innerHTML = '<div class="users-empty">Cargando...</div>';

  try {
    const res = await fetch(`<?= site_url('tareas/users-by-area') ?>/${areaId}`);
    const data = await res.json();
    renderUserCheckboxes(Array.isArray(data) ? data : []);
  } catch (e) {
    assigneeBox.innerHTML = '<div class="users-empty">Error cargando usuarios.</div>';
  }
}

if (assignMode !== 'self' && oldArea) loadUsersByArea(oldArea);

if (areaEl) {
  areaEl.addEventListener('change', () => {
    const v = Number(areaEl.value || 0);
    if (v > 0) loadUsersByArea(v);
  });
}

/* =========================================================
   Prioridad automática por fecha fin
========================================================= */
const prioridadHidden = document.getElementById('id_prioridad');
const prioridadUi     = document.getElementById('id_prioridad_ui');

function autoPriorityFromEndDb(endDb){
  if(!endDb) return 0;
  const endDayKey = endDb.slice(0,10);

  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  const parts = endDayKey.split('-').map(Number);
  const endDate = new Date(parts[0], parts[1]-1, parts[2]);

  const diffDays = Math.floor((endDate.getTime() - today.getTime()) / 86400000);

  if (diffDays <= 0) return 4;
  if (diffDays === 1) return 2;
  if (diffDays <= 3) return 3;
  return 1;
}

function syncPriorityUi(){
  const pid = autoPriorityFromEndDb(fechaFinHidden.value);
  if (!pid) return;

  if (prioridadHidden) prioridadHidden.value = String(pid);
  if (prioridadUi) {
    prioridadUi.value = String(pid);
    if (prioridadUi.value !== String(pid)) prioridadUi.value = '';
  }
}
syncPriorityUi();

/* =========================================================
   Modal solicitud cambio fecha (SELF)
========================================================= */
const modalDateChangeEl = document.getElementById('modalDateChange');
const modalDateChange   = modalDateChangeEl ? new bootstrap.Modal(modalDateChangeEl, { backdrop: 'static' }) : null;

const btnOpenDateChange = document.getElementById('btnOpenDateChange');
const btnSendDateChange = document.getElementById('btnSendDateChange');

const requestedEndUi    = document.getElementById('requested_end_ui');
const requestedReasonUi = document.getElementById('requested_reason_ui');

let fpRequestedEnd = null;

function initRequestedEndPicker(){
  if (!requestedEndUi) return;
  if (fpRequestedEnd) return;

  const minEnd = fechaInicioHidden.value ? fechaInicioHidden.value : todayStart();

  fpRequestedEnd = flatpickr(requestedEndUi, {
    enableTime: true,
    time_24hr: false,
    minuteIncrement: 5,
    allowInput: true,
    altInput: true,
    altFormat: "d/m/Y h:i K",
    dateFormat: "Y-m-d H:i",
    minDate: minEnd,
    defaultDate: (fechaFinHidden.value ? fechaFinHidden.value : null),
  });
}

if (btnOpenDateChange) {
  btnOpenDateChange.addEventListener('click', function(){
    if (remainingEdits <= 0) return;
    if (!modalDateChange) return;

    if (requestedReasonUi) requestedReasonUi.value = '';
    if (requestedEndUi) requestedEndUi.value = '';

    initRequestedEndPicker();

    if (fpRequestedEnd && fechaFinHidden.value) {
      fpRequestedEnd.setDate(fechaFinHidden.value, true);
    }

    modalDateChange.show();
  });
}

function clearReviewHidden(){
  if (!reviewActionHidden || !reviewReasonHidden || !reviewReqEndHidden) return;
  reviewActionHidden.value = '';
  reviewReasonHidden.value = '';
  reviewReqEndHidden.value = '';
}

if (btnSendDateChange) {
  btnSendDateChange.addEventListener('click', function(){
    if (!isEdit || assignMode !== 'self') return;

    const reason = (requestedReasonUi ? String(requestedReasonUi.value || '').trim() : '');
    const endDb  = (fpRequestedEnd ? fpRequestedEnd.input.value : '').trim();

    if (!endDb) { alert('Debes seleccionar la nueva fecha fin solicitada.'); return; }
    if (!reason) { alert('Debes escribir el motivo del cambio de fecha.'); return; }

    if (fechaInicioHidden.value && endDb < fechaInicioHidden.value) {
      alert('La fecha fin solicitada no puede ser menor a la fecha inicio.');
      return;
    }

    if (reviewActionHidden) reviewActionHidden.value = 'date_change';
    if (reviewReasonHidden) reviewReasonHidden.value = reason;
    if (reviewReqEndHidden) reviewReqEndHidden.value = endDb;

    document.getElementById('taskForm').requestSubmit();
  });
}

/* =========================================================
   Submit principal: validaciones
========================================================= */
document.getElementById('taskForm').addEventListener('submit', (e) => {
  if ((reviewActionHidden?.value || '').trim() === 'date_change') {
    const rr = (reviewReasonHidden?.value || '').trim();
    const rf = (reviewReqEndHidden?.value || '').trim();

    if (!rf) { alert('Debes seleccionar la nueva fecha fin solicitada.'); e.preventDefault(); return; }
    if (!rr) { alert('Debes escribir el motivo del cambio de fecha.'); e.preventDefault(); return; }
    return;
  }

  clearReviewHidden();

  if (!fechaInicioHidden.value){ alert("La fecha de inicio es obligatoria."); e.preventDefault(); return; }
  if (!fechaFinHidden.value){ alert("La fecha final es obligatoria."); e.preventDefault(); return; }

  if (fechaFinHidden.value < fechaInicioHidden.value){
    alert("La fecha final no puede ser menor a la fecha de inicio.");
    e.preventDefault();
    return;
  }

  if (assignMode !== 'self') {
    const checked = document.querySelectorAll('input[name="asignado_a[]"]:checked');
    if (!checked || checked.length === 0) {
      alert("Debes seleccionar al menos un usuario para asignar.");
      e.preventDefault();
      return;
    }
  }

  syncPriorityUi();
});

/* =========================================================
   Recurrencia UI (CREATE)
========================================================= */
(function initRecurrenceUi(){
  const chkRec   = document.getElementById('recurrence_enabled');
  const panel    = document.getElementById('recurrence_panel');
  const typeEl   = document.getElementById('repeat_type');

  const untilEl  = document.getElementById('repeat_until');
  const daysWrap = document.getElementById('days_of_week_wrap');

  const recStartDateEl = document.getElementById('rec_start_date');
  const recStartTimeEl = document.getElementById('rec_start_time');
  const recEndTimeEl   = document.getElementById('rec_end_time');

  const preview  = document.getElementById('recurrence_preview');
  const hint     = document.getElementById('recurrence_hint');

  const weeksHidden = document.getElementById('weeks_count');
  const weeksUi = document.getElementById('weeks_count_ui');

  if (!chkRec || !panel || !typeEl || !untilEl || !daysWrap || !preview || !hint) return;
  if (!recStartDateEl || !recStartTimeEl || !recEndTimeEl) return;
  if (!weeksHidden || !weeksUi) return;

  const pad2 = (n) => String(n).padStart(2,'0');
  const toYmd = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  const parseYmd = (s) => {
    const m = String(s || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]), 0,0,0,0);
  };

  function setUiDisabledForRecurrence(disabled){
    if (fechaInicioUI) fechaInicioUI.disabled = disabled;
    if (fechaFinUI) fechaFinUI.disabled = disabled;

    try {
      if (fpStart && fpStart.altInput) fpStart.altInput.disabled = disabled;
      if (fpEnd && fpEnd.altInput) fpEnd.altInput.disabled = disabled;
    } catch(e){}

    if (disabled){
      if (fechaInicioUI) fechaInicioUI.style.background = '#f1f3f5';
      if (fechaFinUI) fechaFinUI.style.background = '#f1f3f5';
      try {
        if (fpStart && fpStart.altInput) fpStart.altInput.style.background = '#f1f3f5';
        if (fpEnd && fpEnd.altInput) fpEnd.altInput.style.background = '#f1f3f5';
      } catch(e){}
    } else {
      if (fechaInicioUI) fechaInicioUI.style.background = '';
      if (fechaFinUI) fechaFinUI.style.background = '';
      try {
        if (fpStart && fpStart.altInput) fpStart.altInput.style.background = '';
        if (fpEnd && fpEnd.altInput) fpEnd.altInput.style.background = '';
      } catch(e){}
    }
  }

  function showHideDays(){
    daysWrap.style.display = (String(typeEl.value || 'daily') === 'weekly') ? 'block' : 'none';
  }

  function combineToDb(dateYmd, timeHHmm){
    if (!dateYmd || !timeHHmm) return '';
    return `${dateYmd} ${timeHHmm}`;
  }

  function computeRepeatUntilFixed(){
    const startDay = parseYmd(recStartDateEl.value);
    if (!startDay) return;

    const until = new Date(startDay.getTime());
    until.setDate(until.getDate() + 6);
    untilEl.value = toYmd(until);

    const today = new Date();
    today.setHours(0,0,0,0);
    const todayYmd = toYmd(today);

    recStartDateEl.min = todayYmd;
    if (recStartDateEl.value && recStartDateEl.value < todayYmd) {
      alert('No puedes seleccionar un día anterior al de hoy.');
      recStartDateEl.value = todayYmd;
      computeRepeatUntilFixed();
      return;
    }
  }

  function ensureRecurrenceHiddenDates(){
    const d = String(recStartDateEl.value || '').trim();
    const st = String(recStartTimeEl.value || '').trim();
    const et = String(recEndTimeEl.value || '').trim();

    if (!d || !st || !et) return;

    const startDb = combineToDb(d, st);
    const endDb   = combineToDb(d, et);

    if (endDb < startDb) return;

    fechaInicioHidden.value = startDb;
    fechaFinHidden.value    = endDb;

    syncPriorityUi();
  }

  function updatePreview(){
    if (!chkRec.checked) { preview.textContent = ''; hint.textContent = ''; return; }

    const st = String(recStartTimeEl.value || '').trim();
    const et = String(recEndTimeEl.value || '').trim();
    const until = String(untilEl.value || '').trim();

    if (st && et && until) {
      preview.innerHTML = `Se repetirá de <b>${st}</b> a <b>${et}</b> hasta <b>${until}</b>.`;
    } else if (st && et) {
      preview.innerHTML = `Horario de repetición: <b>${st}</b> a <b>${et}</b>.`;
    } else {
      preview.textContent = '';
    }

    hint.textContent = '';
  }

  function showHidePanel(){
    panel.style.display = chkRec.checked ? 'block' : 'none';
    showHideDays();

    if (chkRec.checked) {
      weeksHidden.value = '1';
      weeksUi.value = '1';

      computeRepeatUntilFixed();

      setUiDisabledForRecurrence(true);
      ensureRecurrenceHiddenDates();
      updatePreview();
    } else {
      setUiDisabledForRecurrence(false);

      try {
        if (fpStart && fpStart.selectedDates && fpStart.selectedDates[0]) {
          fechaInicioHidden.value = toDbString(fpStart.selectedDates[0]);
        }
        if (fpEnd && fpEnd.selectedDates && fpEnd.selectedDates[0]) {
          fechaFinHidden.value = toDbString(fpEnd.selectedDates[0]);
        }
      } catch(e){}

      updatePreview();
    }
  }

  chkRec.addEventListener('change', showHidePanel);
  typeEl.addEventListener('change', () => { showHideDays(); updatePreview(); });

  recStartDateEl.addEventListener('change', function(){
    const today = new Date();
    today.setHours(0,0,0,0);
    const todayYmd = toYmd(today);

    if (recStartDateEl.value && recStartDateEl.value < todayYmd) {
      alert('No puedes seleccionar un día anterior al de hoy.');
      recStartDateEl.value = todayYmd;
    }

    computeRepeatUntilFixed();
    ensureRecurrenceHiddenDates();
    updatePreview();
  });

  recStartTimeEl.addEventListener('input', () => { ensureRecurrenceHiddenDates(); updatePreview(); });
  recEndTimeEl.addEventListener('input', () => { ensureRecurrenceHiddenDates(); updatePreview(); });

  computeRepeatUntilFixed();
  showHidePanel();

  const form = document.getElementById('taskForm');
  if (!form) return;

  form.addEventListener('submit', function(e){
    if (!chkRec.checked) return;

    const startDay = parseYmd(recStartDateEl.value);
    if (!startDay) { alert('Debes seleccionar la fecha inicio serie.'); e.preventDefault(); return; }

    const today = new Date();
    today.setHours(0,0,0,0);
    if (startDay.getTime() < today.getTime()){
      alert('No puedes seleccionar un día anterior al de hoy.');
      e.preventDefault();
      return;
    }

    computeRepeatUntilFixed();

    const st = String(recStartTimeEl.value || '').trim();
    const et = String(recEndTimeEl.value || '').trim();
    if (!st || !et) { alert('Debes seleccionar la hora inicio y hora fin.'); e.preventDefault(); return; }

    const startDb = combineToDb(recStartDateEl.value, st);
    const endDb   = combineToDb(recStartDateEl.value, et);

    if (endDb < startDb) {
      alert('La hora fin no puede ser menor a la hora inicio.');
      e.preventDefault();
      return;
    }

    if (String(typeEl.value || 'daily') === 'weekly') {
      const checkedDays = document.querySelectorAll('input[name="days_of_week[]"]:checked');
      if (!checkedDays || checkedDays.length === 0) {
        alert('Debes seleccionar al menos un día para recurrencia semanal.');
        e.preventDefault();
        return;
      }
    }

    ensureRecurrenceHiddenDates();

    if (!fechaInicioHidden.value || !fechaFinHidden.value) {
      alert('No se pudieron generar fecha inicio/fin para la recurrencia. Revisa horario.');
      e.preventDefault();
      return;
    }
  });

})();
</script>

<?= $this->endSection() ?>