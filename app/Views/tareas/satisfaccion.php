<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<div class="container py-3">

  <h3 class="mb-3">Mi porcentaje de satisfacción</h3>

  <div class="card shadow-sm text-center p-4">

    <div style="font-size:3rem;font-weight:900;">
      <?= esc($data['porcentaje']) ?>%
    </div>

    <div class="text-muted mb-2">
      Semana <?= esc($data['inicio']) ?> → <?= esc($data['fin']) ?>
    </div>

    <div class="d-flex justify-content-center gap-4 mt-3">
      <div>
        <strong><?= $data['realizadas'] ?></strong><br>
        <small>Realizadas</small>
      </div>
      <div>
        <strong><?= $data['no_realizadas'] ?></strong><br>
        <small>No realizadas</small>
      </div>
    </div>

  </div>

</div>

<?= $this->endSection() ?>
