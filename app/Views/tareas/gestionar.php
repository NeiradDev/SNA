<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * ==========================================================
 * Vista: Gestionar Tareas
 * ==========================================================
 * Variables esperadas:
 *
 * $misTareas          → tareas donde asignado_a = usuario actual
 * $tareasAsignadas    → tareas creadas por mí (asignado_por)
 * ==========================================================
 */
?>

<div class="container py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Gestión de Tareas</h3>

    <div class="d-flex gap-2">
      <a href="<?= site_url('tareas/asignar') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Nueva tarea
      </a>

      <a href="<?= site_url('tareas/calendario') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-calendar3 me-1"></i> Calendario
      </a>
    </div>
  </div>

  <!-- =================== MIS TAREAS =================== -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">
      Mis tareas
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Título</th>
            <th>Área</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($misTareas)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                No tienes tareas asignadas.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($misTareas as $t): ?>
            <tr>
              <td><?= esc($t['titulo']) ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad']) ?></td>
              <td><?= esc($t['nombre_estado']) ?></td>
              <td><?= esc($t['fecha_inicio']) ?></td>
              <td><?= esc($t['fecha_fin'] ?? '-') ?></td>
              <td class="text-end">
                <a
                  href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                  class="btn btn-sm btn-outline-primary"
                  title="Editar tarea"
                >
                  <i class="bi bi-pencil-square"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- =================== TAREAS ASIGNADAS POR MÍ =================== -->
  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      Tareas asignadas por mí
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Título</th>
            <th>Área</th>
            <th>Asignado a</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Inicio</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tareasAsignadas)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                No has asignado tareas.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($tareasAsignadas as $t): ?>
            <tr>
              <td><?= esc($t['titulo']) ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad']) ?></td>
              <td><?= esc($t['nombre_estado']) ?></td>
              <td><?= esc($t['fecha_inicio']) ?></td>
              <td class="text-end">
                <a
                  href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                  class="btn btn-sm btn-outline-primary"
                  title="Editar / Reasignar"
                >
                  <i class="bi bi-pencil-square"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?= $this->endSection() ?>
