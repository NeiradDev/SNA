<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0"><?= esc($formTitle ?? 'Formulario') ?></h3>

  <a class="btn btn-outline-secondary btn-sm" href="<?= esc($backUrl ?? previous_url()) ?>">
    Volver
  </a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">

    <form method="post" action="<?= esc($actionUrl ?? '#') ?>">
      <?= csrf_field() ?>

      <?php foreach (($fields ?? []) as $f): ?>
        <?php
          $name     = $f['name'] ?? '';
          $type     = $f['type'] ?? 'text';
          $label    = $f['label'] ?? $name;
          $required = !empty($f['required']);
          $value    = $row[$name] ?? '';
          $options  = $f['options'] ?? null;
          $idAttr   = $f['id'] ?? null;
        ?>

        <div class="mb-3">
          <label class="form-label"><?= esc($label) ?></label>

          <?php if ($type === 'select'): ?>
            <select
              name="<?= esc($name) ?>"
              class="form-select"
              <?= $idAttr ? 'id="'.esc($idAttr).'"' : '' ?>
              <?= $required ? 'required' : '' ?>
            >
              <option value="">-- Seleccione --</option>
              <?php foreach (($options ?? []) as $opt): ?>
  <?php
    // ✅ Construimos data attributes si existen
    $dataAttrs = '';
    if (!empty($opt['data']) && is_array($opt['data'])) {
        foreach ($opt['data'] as $k => $v) {
            $dataAttrs .= ' data-' . esc((string)$k) . '="' . esc((string)$v) . '"';
        }
    }
  ?>
  <option value="<?= esc($opt['value']) ?>" <?= $dataAttrs ?>
    <?= ((string)$value === (string)$opt['value']) ? 'selected' : '' ?>>
    <?= esc($opt['label']) ?>
  </option>
<?php endforeach; ?>

            </select>
          <?php else: ?>
            <input
              type="<?= esc($type) ?>"
              name="<?= esc($name) ?>"
              class="form-control"
              <?= $idAttr ? 'id="'.esc($idAttr).'"' : '' ?>
              value="<?= esc((string)$value) ?>"
              <?= $required ? 'required' : '' ?>
            >
          <?php endif; ?>

          <?php if (!empty($f['help'])): ?>
            <div class="form-text"><?= esc($f['help']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <button class="btn btn-dark">Guardar</button>
      <a class="btn btn-outline-secondary" href="<?= esc($backUrl ?? previous_url()) ?>">Cancelar</a>
    </form>

  </div>
</div>

<?= $extraScript ?? '' ?>
<?= $this->endSection() ?>