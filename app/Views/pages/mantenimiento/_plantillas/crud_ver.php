<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0">Detalle: <?= esc($entityTitle ?? '') ?></h3>

  <a class="btn btn-outline-secondary btn-sm" href="<?= esc($backUrl ?? previous_url()) ?>">
    Volver
  </a>
</div>

<div class="card shadow-sm">
  <div class="card-body">

    <dl class="row mb-0">
      <?php foreach (($labels ?? []) as $key => $label): ?>
        <dt class="col-sm-3"><?= esc($label) ?></dt>
        <dd class="col-sm-9"><?= esc($row[$key] ?? '—') ?></dd>
      <?php endforeach; ?>
    </dl>

    <?php if (!empty($editUrl)): ?>
      <div class="mt-3">
        <a class="btn btn-outline-primary" href="<?= esc($editUrl) ?>">Editar</a>
      </div>
    <?php endif; ?>

  </div>
</div>
<?= $this->endSection() ?>