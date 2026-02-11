<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0">Mantenimiento: <?= esc($entityTitle ?? 'Listado') ?></h3>

  <a class="btn btn-dark btn-sm" href="<?= esc($createUrl ?? '#') ?>">
    Crear
  </a>
</div>

<div class="card shadow-sm">
  <div class="card-body table-responsive">

    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <?php foreach (($columns ?? []) as $col): ?>
            <th><?= esc($col['label'] ?? '') ?></th>
          <?php endforeach; ?>
          <th class="text-end" style="width: 180px;">Acciones</th>
        </tr>
      </thead>

      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach (($columns ?? []) as $col): ?>
                <?php $key = $col['key'] ?? ''; ?>
                <td><?= esc($r[$key] ?? '—') ?></td>
              <?php endforeach; ?>

              <td class="text-end">
                <?php
                  $pkKey = $columns[0]['key'] ?? null;
                  $id = $pkKey ? ($r[$pkKey] ?? 0) : 0;
                ?>

                <a class="btn btn-outline-secondary btn-sm"
                   href="<?= base_url(($actionsBase ?? '') . '/ver/' . $id) ?>">
                  Ver
                </a>

                <a class="btn btn-outline-primary btn-sm"
                   href="<?= base_url(($actionsBase ?? '') . '/editar/' . $id) ?>">
                  Editar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="<?= (count($columns ?? []) + 1) ?>" class="text-center py-4 text-muted">
              No hay registros.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>
</div>
<?= $this->endSection() ?>