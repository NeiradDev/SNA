<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Asignar Tarea</h3>
    <a class="btn btn-outline-secondary" href="<?= site_url('tareas/calendario') ?>">
      <i class="bi bi-calendar3 me-1"></i> Ver calendario
    </a>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <?php
    $oldTitulo = $old['titulo'] ?? '';
    $oldDesc = $old['descripcion'] ?? '';
    $oldPrioridad = $old['prioridad'] ?? 'Normal';
    $oldArea = (int)($old['id_area'] ?? ($areaFija ?? 0));
    $oldAsignado = (int)($old['asignado_a'] ?? 0);
    $oldInicio = $old['fecha_inicio'] ?? '';
    $oldFin = $old['fecha_fin'] ?? '';
  ?>

  <form method="post" action="<?= site_url('tareas/asignar') ?>" class="card shadow-sm p-3">
    <?= csrf_field() ?>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Título</label>
        <input type="text" name="titulo" class="form-control" value="<?= esc($oldTitulo) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Prioridad</label>
        <select name="prioridad" class="form-select" required>
          <option value="Normal" <?= $oldPrioridad==='Normal'?'selected':'' ?>>Normal</option>
          <option value="Urgente" <?= $oldPrioridad==='Urgente'?'selected':'' ?>>Urgente</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Área</label>
        <select name="id_area" id="id_area" class="form-select" required <?= (!$esGerencia ? 'disabled' : '') ?>>
          <option value="">-- Selecciona --</option>
          <?php foreach ($areas as $a): ?>
            <option value="<?= (int)$a['id_area'] ?>" <?= ((int)$a['id_area'] === $oldArea ? 'selected':'') ?>>
              <?= esc($a['nombre_area']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if (!$esGerencia): ?>
          <input type="hidden" name="id_area" value="<?= (int)$oldArea ?>">
        <?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label">Asignar a (usuario del área)</label>
        <select name="asignado_a" id="asignado_a" class="form-select" required>
          <option value="">-- Selecciona un área --</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fecha inicio</label>
        <input type="datetime-local" name="fecha_inicio" class="form-control" value="<?= esc($oldInicio) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fecha fin (opcional)</label>
        <input type="datetime-local" name="fecha_fin" class="form-control" value="<?= esc($oldFin) ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Descripción (opcional)</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= esc($oldDesc) ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary">
        <i class="bi bi-send me-1"></i> Asignar
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

    const res = await fetch(`<?= site_url('tareas/users-by-area') ?>/${areaId}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
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

  // Init: si ya hay un área seleccionada, carga usuarios
  if (oldArea) loadUsers(oldArea);

  // Si no es gerencia, el select está disabled (no cambia)
  if (areaEl && !areaEl.disabled) {
    areaEl.addEventListener('change', () => {
      const v = areaEl.value;
      if (!v) {
        userEl.innerHTML = `<option value="">-- Selecciona un área --</option>`;
        return;
      }
      loadUsers(v);
    });
  }
</script>

<?= $this->endSection() ?>
