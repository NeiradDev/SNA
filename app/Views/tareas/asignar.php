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
/* ===================================================== */
/* FIX DEFINITIVO SOLO PARA ESTA VISTA                    */
/* - fuerza modo "light" en selects y texto visible       */
/* ===================================================== */
.tareas-form-fix,
.tareas-form-fix *{
  color-scheme: light; /* 👈 evita dropdown en modo dark del navegador */
}

.tareas-form-fix .form-control,
.tareas-form-fix .form-select,
.tareas-form-fix textarea{
  color:#000 !important;
  background:#fff !important;

  /* 👇 CLAVE: Chrome/Edge a veces ignora 'color' en selects */
  -webkit-text-fill-color:#000 !important;
}

/* Labels */
.tareas-form-fix label{
  color:#000 !important;
}

/* Placeholders */
.tareas-form-fix .form-control::placeholder,
.tareas-form-fix textarea::placeholder{
  color:#6c757d !important;
}

/* 👇 CLAVE ESPECÍFICA: el combo "Asignar a" y sus opciones */
.tareas-form-fix #asignado_a{
  color:#000 !important;
  background:#fff !important;
  -webkit-text-fill-color:#000 !important;
  color-scheme: light !important;
}

.tareas-form-fix #asignado_a option{
  color:#000 !important;
  background:#fff !important;
}

/* (Opcional) también para los otros selects por si se ven raros */
.tareas-form-fix select.form-select option{
  color:#000 !important;
  background:#fff !important;
}
</style>

<div class="container py-3 tareas-form-fix">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">
      <?= $isEdit ? 'Editar / Reasignar Tarea' : 'Asignar Tarea' ?>
    </h3>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="<?= site_url('tareas/calendario') ?>">
        <i class="bi bi-calendar3 me-1"></i> Calendario
      </a>

      <a class="btn btn-outline-primary" href="<?= site_url('tareas/gestionar') ?>">
        <i class="bi bi-list-check me-1"></i> Administrar tareas
      </a>

      <a class="btn btn-outline-success" href="<?= site_url('tareas/satisfaccion') ?>">
        <i class="bi bi-graph-up-arrow me-1"></i> Satisfacción
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= $formAction ?>" class="card shadow-sm p-3">
    <?= csrf_field() ?>

    <div class="row g-3">

      <div class="col-md-6">
        <label class="form-label">División</label>
        <input type="text" class="form-control"
               value="<?= esc($divisionUsuario['nombre_division'] ?? '—') ?>" disabled>
      </div>

      <div class="col-md-6">
        <label class="form-label">Título</label>
        <input type="text" name="titulo" class="form-control"
               value="<?= esc($oldTitulo) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Prioridad</label>
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
        <label class="form-label">Estado</label>
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
        <label class="form-label">Área</label>
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
        <label class="form-label">Asignar a</label>
        <select name="asignado_a" id="asignado_a" class="form-select" required>
          <option value="">-- Selecciona un área --</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fecha inicio</label>
        <input type="datetime-local" name="fecha_inicio"
               class="form-control" value="<?= esc($oldInicio) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fecha fin</label>
        <input type="datetime-local" name="fecha_fin"
               class="form-control" value="<?= esc($oldFin) ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= esc($oldDesc) ?></textarea>
      </div>

    </div>

    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary">
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
    opt.textContent = u.label; // aquí viene el nombre
    if (Number(u.id_user) === Number(oldUser)) opt.selected = true;
    userEl.appendChild(opt);
  });

  /* 🔥 Asegura que el navegador re-renderice el texto del select */
  userEl.style.color = '#000';
  userEl.style.webkitTextFillColor = '#000';
}

if (oldArea) loadUsers(oldArea);
areaEl.addEventListener('change', () => areaEl.value && loadUsers(areaEl.value));
</script>

<?= $this->endSection() ?>
