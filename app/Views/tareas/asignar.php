<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
$isEdit = !empty($tarea);

$oldTitulo    = $old['titulo'] ?? ($tarea['titulo'] ?? '');
$oldDesc      = $old['descripcion'] ?? ($tarea['descripcion'] ?? '');
$oldPrioridad = (int)($old['id_prioridad'] ?? ($tarea['id_prioridad'] ?? 0));
$oldEstado    = (int)($old['id_estado_tarea'] ?? ($tarea['id_estado_tarea'] ?? 0));
$oldArea      = (int)($old['id_area'] ?? ($tarea['id_area'] ?? 0));
$oldAsignado  = (int)($old['asignado_a'] ?? ($tarea['asignado_a'] ?? 0));
$oldInicio    = $old['fecha_inicio'] ?? ($tarea['fecha_inicio'] ?? '');
$oldFin       = $old['fecha_fin'] ?? ($tarea['fecha_fin'] ?? '');

$formAction = $isEdit
  ? site_url('tareas/actualizar/' . (int)$tarea['id_tarea'])
  : site_url('tareas/asignar');
?>

<style>
/* ===== BOTONES NEGROS ELEGANTES ===== */
.btn-black {
  background:#000;
  color:#fff;
  border:1px solid #000;
  transition: all .2s ease;
}

.btn-black:hover {
  background:#222;
  border-color:#222;
  color:#fff;
  transform: translateY(-1px);
}

.btn-black-outline {
  background:#fff;
  color:#000;
  border:1px solid #000;
  transition: all .2s ease;
}

.btn-black-outline:hover {
  background:#000;
  color:#fff;
}
</style>

<div class="container py-4">

  <!-- HEADER -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h3 class="fw-bold mb-0">
      <?= $isEdit ? 'Editar / Reasignar Tarea' : 'Asignar Tarea' ?>
    </h3>

    <div class="d-flex gap-2 flex-wrap">

      <a class="btn btn-black-outline btn-sm" href="<?= site_url('tareas/gestionar') ?>">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>

      <a class="btn btn-black btn-sm" href="<?= site_url('tareas/calendario') ?>">
        <i class="bi bi-calendar3"></i>
      </a>

      <a class="btn btn-black btn-sm" href="<?= site_url('tareas/gestionar') ?>">
        <i class="bi bi-list-check"></i>
      </a>

      <a class="btn btn-black btn-sm" href="<?= site_url('tareas/satisfaccion') ?>">
        <i class="bi bi-graph-up-arrow"></i>
      </a>

    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <!-- FORM -->
  <form method="post" action="<?= $formAction ?>" class="card shadow-sm p-4">
    <?= csrf_field() ?>

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

      <div class="col-md-3">
        <label class="form-label fw-semibold">Prioridad</label>
        <select name="id_prioridad" class="form-select" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($prioridades as $p): ?>
            <option value="<?= (int)$p['id_prioridad'] ?>"
              <?= ((int)$p['id_prioridad'] === $oldPrioridad ? 'selected' : '') ?>>
              <?= esc($p['nombre_prioridad']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Estado</label>
        <select name="id_estado_tarea" class="form-select" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($estados as $e): ?>
            <option value="<?= (int)$e['id_estado_tarea'] ?>"
              <?= ((int)$e['id_estado_tarea'] === $oldEstado ? 'selected' : '') ?>>
              <?= esc($e['nombre_estado']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Área</label>
        <select name="id_area" id="id_area" class="form-select" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($areasDivision as $a): ?>
            <option value="<?= (int)$a['id_area'] ?>"
              <?= ((int)$a['id_area'] === $oldArea ? 'selected' : '') ?>>
              <?= esc($a['nombre_area']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Asignar a</label>
        <select name="asignado_a" id="asignado_a" class="form-select" required>
          <option value="">-- Selecciona un área --</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Fecha inicio</label>
        <input type="datetime-local" name="fecha_inicio"
               class="form-control" value="<?= esc($oldInicio) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">Fecha fin</label>
        <input type="datetime-local" name="fecha_fin"
               class="form-control" value="<?= esc($oldFin) ?>">
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= esc($oldDesc) ?></textarea>
      </div>

    </div>

    <!-- BOTONES INFERIORES -->
    <div class="d-flex justify-content-end gap-2 mt-4">

      <a href="<?= site_url('tareas/gestionar') ?>" class="btn btn-black-outline">
        Cancelar
      </a>

      <button class="btn btn-black">
        <i class="bi <?= $isEdit ? 'bi-save' : 'bi-send' ?> me-1"></i>
        <?= $isEdit ? 'Guardar cambios' : 'Asignar' ?>
      </button>

    </div>

  </form>
</div>

<script>
const areaEl = document.getElementById('id_area');
const userEl = document.getElementById('asignado_a');
const oldArea = <?= (int)$oldArea ?>;
const oldUser = <?= (int)$oldAsignado ?>;

async function loadUsers(areaId) {
  userEl.innerHTML = `<option value="">Cargando...</option>`;

  const res = await fetch(`<?= site_url('tareas/users-by-area') ?>/${areaId}`);
  const data = await res.json();

  userEl.innerHTML = `<option value="">-- Selecciona --</option>`;
  data.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id_user;
    opt.textContent = u.label;
    if (Number(u.id_user) === Number(oldUser)) opt.selected = true;
    userEl.appendChild(opt);
  });
}

if (oldArea) loadUsers(oldArea);
areaEl.addEventListener('change', () => areaEl.value && loadUsers(areaEl.value));
</script>

<?= $this->endSection() ?>
