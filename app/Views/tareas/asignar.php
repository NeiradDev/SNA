<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * ------------------------------------------------------------
 * Vista: tareas/asignar.php
 *
 * ✅ REQUERIMIENTO NUEVO (CREAR + EDITAR):
 * - El Estado debe venir SIEMPRE fijo como "En proceso"
 * - id_estado_tarea = 2
 * - El usuario NO puede cambiarlo (solo visual)
 * - Se envía al POST con input hidden
 * ------------------------------------------------------------
 */

$isEdit = !empty($tarea);

// -------------------------
// Valores (old() > tarea)
// -------------------------
$oldTitulo = $old['titulo'] ?? ($tarea['titulo'] ?? '');
$oldDesc   = $old['descripcion'] ?? ($tarea['descripcion'] ?? '');

// Prioridad (visible pero calculada)
$oldPrioridad = (int)($old['id_prioridad'] ?? ($tarea['id_prioridad'] ?? 0));

// ✅ Área (old() > tarea)
$oldArea = (int)($old['id_area'] ?? ($tarea['id_area'] ?? 0));

/**
 * ✅ Multi-asignación:
 * - old('asignado_a') puede venir como array
 * - tarea['asignado_a'] (en edición) ya debe venir como array (por el Service)
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

// -------------------------
// Acción del form
// -------------------------
$formAction = $isEdit
    ? site_url('tareas/actualizar/' . (int)$tarea['id_tarea'])
    : site_url('tareas/asignar');

// -------------------------
// Convertidor a datetime-local
// -------------------------
$toDatetimeLocal = function($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';

    // ya viene en datetime-local
    if (strpos($value, 'T') !== false) return substr($value, 0, 16);

    // viene de BD: "YYYY-mm-dd HH:ii:ss"
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/', $value)) {
        return str_replace(' ', 'T', substr($value, 0, 16));
    }

    // intento genérico
    try {
        $tz = new \DateTimeZone('America/Guayaquil');
        $dt = new \DateTime($value, $tz);
        return $dt->format('Y-m-d\TH:i');
    } catch (\Throwable $e) {
        return '';
    }
};

$oldInicioLocal = $toDatetimeLocal($oldInicio);
$oldFinLocal    = $toDatetimeLocal($oldFin);

// -------------------------
// Datos sesión
// -------------------------
$currentUserId   = (int)(session()->get('id_user') ?? 0);
$currentUserArea = (int)(session()->get('id_area') ?? 0);

// -------------------------
// Scope para UI
// -------------------------
$assignScope = $assignScope ?? [
    'mode'       => ($currentUserArea === 1 ? 'super' : 'unknown'),
    'divisionId' => null,
    'areaId'     => null,
];

$assignMode = (string)($assignScope['mode'] ?? 'unknown');

// -------------------------
// Bloqueo UI según scope
// -------------------------
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
// ✅ ESTADO FIJO (CREAR + EDITAR)
// Siempre será: En proceso (id_estado_tarea = 2)
// ============================================================
$fixedEstadoId    = 2;
$fixedEstadoLabel = 'En proceso';

// Si el catálogo viene desde BD, tomamos el nombre real del estado 2
if (!empty($estados) && is_array($estados)) {
    foreach ($estados as $e) {
        if ((int)($e['id_estado_tarea'] ?? 0) === 2) {
            $fixedEstadoLabel = (string)($e['nombre_estado'] ?? 'En proceso');
            break;
        }
    }
}
?>

<style>
/* Botones (negro/blanco) */
.btn-black{
  background:#000;
  color:#fff;
  border:1px solid #000;
  transition: all .2s ease;
}
.btn-black:hover{
  background:#222;
  border-color:#222;
  color:#fff;
  transform: translateY(-1px);
}
.btn-black-outline{
  background:#fff;
  color:#000;
  border:1px solid #000;
  transition: all .2s ease;
}
.btn-black-outline:hover{
  background:#000;
  color:#fff;
}

/* Caja de checks */
.users-box{
  border:1px solid rgba(0,0,0,.18);
  border-radius:10px;
  padding:10px;
  max-height:260px;
  overflow:auto;
  background:#fff;
}
.users-empty{
  font-size:.9rem;
  color:rgba(0,0,0,.65);
}
.users-note{
  font-size:.85rem;
  color:rgba(0,0,0,.65);
}
</style>

<div class="container py-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h3 class="fw-bold mb-0">
      <?= $isEdit ? 'Editar / Reasignar Actividad' : 'Crear Actividad' ?>
    </h3>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= $formAction ?>" class="card shadow-sm p-4">
    <?= csrf_field() ?>

    <!-- Prioridad real (hidden) -->
    <input type="hidden" name="id_prioridad" id="id_prioridad" value="<?= (int)$oldPrioridad ?>">

    <!-- ✅ Estado real (hidden) - SIEMPRE 2 -->
    <input type="hidden" name="id_estado_tarea" value="<?= (int)$fixedEstadoId ?>">

    <div class="row g-4">

      <div class="col-md-6">
        <label class="form-label fw-semibold">División</label>
        <input type="text" class="form-control"
               value="<?= esc($divisionUsuario['nombre_division'] ?? '—') ?>" disabled>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Título</label>
        <input type="text" name="titulo" class="form-control"
               value="<?= esc($oldTitulo) ?>" required>
      </div>

      <!-- Prioridad visible (solo nombre) -->
      <div class="col-md-3">
        <label class="form-label fw-semibold">Prioridad</label>
        <select id="id_prioridad_ui" class="form-select" disabled>
          <option value="">Prioridad Automática</option>
          <?php foreach ($prioridades as $p): ?>
            <option value="<?= (int)$p['id_prioridad'] ?>"
              <?= ((int)$p['id_prioridad'] === (int)$oldPrioridad ? 'selected' : '') ?>>
              <?= esc($p['nombre_prioridad']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ✅ Estado visible (solo "En proceso") -->
      <div class="col-md-3">
        <label class="form-label fw-semibold">Estado</label>
        <select class="form-select" disabled>
          <option value="<?= (int)$fixedEstadoId ?>" selected><?= esc($fixedEstadoLabel) ?></option>
        </select>
        <small class="text-muted" style="font-size:.85rem;">Estado automático al crear o editar.</small>
      </div>

      <!-- Área -->
      <div class="col-md-3">
        <label class="form-label fw-semibold">Área</label>

        <?php if ($lockAreaSelect): ?>
          <!-- Si está bloqueada, mandamos hidden -->
          <input type="hidden" name="id_area" value="<?= (int)$oldArea ?>">

          <input type="text" class="form-control"
                 value="<?= esc($areaNameLocked !== '' ? $areaNameLocked : 'Área asignada por tu perfil') ?>"
                 disabled>
        <?php else: ?>
          <!-- Jefe de división / gerencia: puede elegir áreas -->
          <select name="id_area" id="id_area" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($areasDivision as $a): ?>
              <option value="<?= (int)$a['id_area'] ?>"
                <?= ((int)$a['id_area'] === $oldArea ? 'selected' : '') ?>>
                <?= esc($a['nombre_area']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <!-- Asignar a (CHECKBOXES) -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Asignar a</label>

        <?php if ($assignMode === 'self'): ?>
          <!-- Usuario normal: solo autoasignación -->
          <div class="users-box">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" checked disabled>
              <label class="form-check-label">
                Tú (Autoasignación)
              </label>
            </div>

            <!-- ✅ importante: enviamos como array -->
            <input type="hidden" name="asignado_a[]" value="<?= (int)$currentUserId ?>">
          </div>
        <?php else: ?>
          <!-- Otros modos: caja dinámica -->
          <div id="assigneeBox" class="users-box">
            <div class="users-empty">Selecciona un área para cargar usuarios.</div>
          </div>

          <?php if ($isEdit): ?>
            <div class="users-note mt-2">
              Nota: si desmarcas un usuario y guardas, su actividad quedará en estado <b>Cancelado</b> para ese usuario.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Fechas -->
      <div class="col-md-3">
        <label class="form-label fw-semibold">Fecha inicio</label>
        <input type="datetime-local"
               name="fecha_inicio"
               id="fecha_inicio"
               class="form-control"
               value="<?= esc($oldInicioLocal) ?>"
               required>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Fecha fin</label>
        <input type="datetime-local"
               name="fecha_fin"
               id="fecha_fin"
               class="form-control"
               value="<?= esc($oldFinLocal) ?>"
               required>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold">Descripción de la actividad</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= esc($oldDesc) ?></textarea>
      </div>

    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <a href="<?= site_url('tareas/gestionar') ?>" class="btn btn-black-outline">Cancelar</a>

      <button class="btn btn-black">
        <i class="bi <?= $isEdit ? 'bi-save' : 'bi-send' ?> me-1"></i>
        <?= $isEdit ? 'Guardar cambios' : 'Asignar' ?>
      </button>
    </div>

  </form>
</div>

<script>
/**
 * ===========================================================
 * CONFIG PHP -> JS
 * ===========================================================
 */
const assignMode    = <?= json_encode($assignMode) ?>;
const currentUserId = <?= (int)$currentUserId ?>;
const isEdit        = <?= $isEdit ? 'true' : 'false' ?>;

const oldArea = <?= (int)$oldArea ?>;

// ✅ IDs preseleccionados (en edición o old())
const oldUserIds = <?= json_encode($oldAsignados) ?>;

// Elementos
const areaEl      = document.getElementById('id_area'); // puede no existir si está bloqueado
const assigneeBox = document.getElementById('assigneeBox') || null;

/**
 * Renderiza lista de usuarios como CHECKBOXES:
 * name="asignado_a[]"
 */
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

    // Preselección (edición / old)
    if (oldUserIds.includes(id)) {
      input.checked = true;
    }

    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.setAttribute('for', input.id);
    label.textContent = u.label;

    wrap.appendChild(input);
    wrap.appendChild(label);

    assigneeBox.appendChild(wrap);
  });
}

/**
 * Carga usuarios por área desde endpoint seguro.
 * El Service ya filtra según permisos del usuario.
 */
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

// ✅ Si no es self, cargamos usuarios al iniciar si hay área
if (assignMode !== 'self' && oldArea) {
  loadUsersByArea(oldArea);
}

// ✅ Si el select de área existe, al cambiar recargamos usuarios
if (areaEl) {
  areaEl.addEventListener('change', () => {
    const v = Number(areaEl.value || 0);
    if (v > 0) loadUsersByArea(v);
  });
}

/**
 * ===========================================================
 * PRIORIDAD AUTO (combo visible pero disabled)
 * - Se calcula por fecha fin (por día)
 * ===========================================================
 */
const prioridadHidden = document.getElementById('id_prioridad');
const prioridadUi     = document.getElementById('id_prioridad_ui');

function autoPriorityFromEndInput(endValue){
  if(!endValue) return 0;

  const endDayKey = endValue.slice(0,10); // YYYY-mm-dd

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
  const endInput = document.getElementById('fecha_fin');
  const pid = autoPriorityFromEndInput(endInput.value);
  if (!pid) return;

  if (prioridadHidden) prioridadHidden.value = String(pid);
  if (prioridadUi) {
    prioridadUi.value = String(pid);
    if (prioridadUi.value !== String(pid)) prioridadUi.value = '';
  }
}

/**
 * ===========================================================
 * FECHAS (validación por día)
 * ===========================================================
 */
const fechaInicio = document.getElementById('fecha_inicio');
const fechaFin    = document.getElementById('fecha_fin');

const initialStart = fechaInicio.value || '';
const initialEnd   = fechaFin.value || '';

function pad2(n){ return String(n).padStart(2, '0'); }

function toLocalInputValue(d){
  const y  = d.getFullYear();
  const m  = pad2(d.getMonth() + 1);
  const da = pad2(d.getDate());
  const h  = pad2(d.getHours());
  const mi = pad2(d.getMinutes());
  return `${y}-${m}-${da}T${h}:${mi}`;
}

function getTodayStartLocal(){
  const d = new Date();
  d.setHours(0,0,0,0);
  return d;
}

function toDayKey(value){
  if(!value) return '';
  return value.slice(0,10);
}

const minToday = toLocalInputValue(getTodayStartLocal());

function isPastAllowed(value, which){
  if (!isEdit) return false;
  if (which === 'start') return value === initialStart && initialStart !== '';
  if (which === 'end')   return value === initialEnd   && initialEnd !== '';
  return false;
}

function minForEdit(currentValue){
  if(!isEdit) return minToday;
  if(!currentValue) return minToday;
  return (currentValue < minToday) ? currentValue : minToday;
}

function setMinRules(){
  fechaInicio.min = minForEdit(fechaInicio.value);

  if (fechaInicio.value) {
    fechaFin.min = fechaInicio.value;
  } else {
    fechaFin.min = minForEdit(fechaFin.value);
  }
}

setMinRules();
syncPriorityUi();

fechaInicio.addEventListener('change', () => {
  setMinRules();

  const todayKey  = toDayKey(minToday);
  const inicioKey = toDayKey(fechaInicio.value);

  if (inicioKey && inicioKey < todayKey && !isPastAllowed(fechaInicio.value, 'start')){
    alert("No puedes seleccionar un día anterior al de hoy.");
    fechaInicio.value = '';
    setMinRules();
    return;
  }

  if (fechaFin.value && fechaFin.value < fechaInicio.value){
    fechaFin.value = '';
    syncPriorityUi();
  }
});

fechaFin.addEventListener('change', () => {
  const todayKey = toDayKey(minToday);
  const finKey   = toDayKey(fechaFin.value);

  if (finKey && finKey < todayKey && !isPastAllowed(fechaFin.value, 'end')){
    alert("No puedes seleccionar un día anterior al de hoy.");
    fechaFin.value = '';
    syncPriorityUi();
    return;
  }

  if (fechaInicio.value && fechaFin.value && fechaFin.value < fechaInicio.value){
    alert("La fecha final no puede ser menor a la fecha de inicio.");
    fechaFin.value = '';
    syncPriorityUi();
    return;
  }

  syncPriorityUi();
});

document.querySelector('form').addEventListener('submit', (e) => {
  // Fechas obligatorias
  if (!fechaInicio.value){
    alert("La fecha de inicio es obligatoria.");
    e.preventDefault();
    return;
  }

  if (!fechaFin.value){
    alert("La fecha final es obligatoria.");
    e.preventDefault();
    return;
  }

  const todayKey  = toDayKey(minToday);
  const inicioKey = toDayKey(fechaInicio.value);
  const finKey    = toDayKey(fechaFin.value);

  const allowPastStart = isPastAllowed(fechaInicio.value, 'start');
  const allowPastEnd   = isPastAllowed(fechaFin.value, 'end');

  if ((inicioKey < todayKey && !allowPastStart) || (finKey < todayKey && !allowPastEnd)){
    alert("No puedes seleccionar un día anterior al de hoy.");
    e.preventDefault();
    return;
  }

  if (fechaFin.value < fechaInicio.value){
    alert("La fecha final no puede ser menor a la fecha de inicio.");
    e.preventDefault();
    return;
  }

  // ✅ Validación de asignados (solo si no es self)
  if (assignMode !== 'self') {
    const checked = document.querySelectorAll('input[name="asignado_a[]"]:checked');
    if (!checked || checked.length === 0) {
      alert("Debes seleccionar al menos un usuario para asignar.");
      e.preventDefault();
      return;
    }
  }

  // Sync final prioridad
  syncPriorityUi();
});
</script>

<?= $this->endSection() ?>
