<?php
/**
 * Parámetros:
 * - $action (string) URL del form
 * - $method (string) POST|PUT (en CI4 usar POST y un hidden _method si deseas)
 * - $usuario (array|null) datos existentes cuando editas
 * - $errors (array|null) errores de validación
 * - $agencias (array)
 * - $areas (array)
 * - $cargos (array|null) opcional si precargas
 * - $supervisores (array|null) opcional si precargas
 */
helper('ui');

$isEdit = !empty($usuario);
?>
<form action="<?= esc($action) ?>" method="post" novalidate>
  <?= csrf_field() ?>

  <div class="row g-3">

    <div class="col-md-6">
      <label class="form-label">Nombres</label>
      <input type="text" name="nombres" class="form-control <?= isset($errors['nombres']) ? 'is-invalid' : '' ?>"
             value="<?= old('nombres', $usuario['nombres'] ?? '') ?>">
      <?= form_error_bs($errors ?? null, 'nombres') ?>
    </div>

    <div class="col-md-6">
      <label class="form-label">Apellidos</label>
      <input type="text" name="apellidos" class="form-control <?= isset($errors['apellidos']) ? 'is-invalid' : '' ?>"
             value="<?= old('apellidos', $usuario['apellidos'] ?? '') ?>">
      <?= form_error_bs($errors ?? null, 'apellidos') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Tipo de documento</label>
      <select name="doc_type" class="form-select <?= isset($errors['doc_type']) ? 'is-invalid' : '' ?>">
        <?php $docType = old('doc_type', $usuario['doc_type'] ?? 'CEDULA'); ?>
        <option value="CEDULA"   <?= $docType==='CEDULA'?'selected':'' ?>>CÉDULA</option>
        <option value="PASAPORTE"<?= $docType==='PASAPORTE'?'selected':'' ?>>PASAPORTE</option>
      </select>
      <?= form_error_bs($errors ?? null, 'doc_type') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Documento</label>
      <input type="text" name="cedula" class="form-control <?= isset($errors['cedula']) ? 'is-invalid' : '' ?>"
             value="<?= old('cedula', $usuario['cedula'] ?? '') ?>">
      <?= form_error_bs($errors ?? null, 'cedula') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Contraseña <?= $isEdit ? '(opcional)' : '' ?></label>
      <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
      <?= form_error_bs($errors ?? null, 'password') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Agencia</label>
      <select name="id_agencias" class="form-select <?= isset($errors['id_agencias']) ? 'is-invalid' : '' ?>">
        <option value="">Seleccione…</option>
        <?php $selAg = (int) old('id_agencias', $usuario['id_agencias'] ?? 0); ?>
        <?php foreach ($agencias as $a): ?>
          <option value="<?= (int)$a['id_agencias'] ?>" <?= $selAg===(int)$a['id_agencias']?'selected':'' ?>>
            <?= esc($a['nombre_agencia'] ?? $a['id_agencias']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?= form_error_bs($errors ?? null, 'id_agencias') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Área</label>
      <select name="id_area" id="id_area" class="form-select <?= isset($errors['id_area']) ? 'is-invalid' : '' ?>">
        <option value="">Seleccione…</option>
        <?php $selAr = (int) old('id_area', $usuario['id_area'] ?? 0); ?>
        <?php foreach ($areas as $ar): ?>
          <option value="<?= (int)$ar['id_area'] ?>" <?= $selAr===(int)$ar['id_area']?'selected':'' ?>>
            <?= esc($ar['nombre_area'] ?? $ar['id_area']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?= form_error_bs($errors ?? null, 'id_area') ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Cargo</label>
      <select name="id_cargo" id="id_cargo" class="form-select <?= isset($errors['id_cargo']) ? 'is-invalid' : '' ?>">
        <?php $selCg = (int) old('id_cargo', $usuario['id_cargo'] ?? 0); ?>
        <?php if (!empty($cargos)): ?>
          <?php foreach ($cargos as $cg): ?>
            <option value="<?= (int)$cg['id_cargo'] ?>" <?= $selCg===(int)$cg['id_cargo']?'selected':'' ?>>
              <?= esc($cg['nombre_cargo'] ?? $cg['id_cargo']) ?>
            </option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="">Seleccione un área primero…</option>
        <?php endif; ?>
      </select>
      <?= form_error_bs($errors ?? null, 'id_cargo') ?>
    </div>

    <div class="col-md-6">
      <label class="form-label">Supervisor</label>
      <select name="id_supervisor" id="id_supervisor" class="form-select">
        <?php $selSp = (int) old('id_supervisor', $usuario['id_supervisor'] ?? 0); ?>
        <?php if (!empty($supervisores)): ?>
          <option value="">— Ninguno —</option>
          <?php foreach ($supervisores as $sp): ?>
            <option value="<?= (int)$sp['id_user'] ?>" <?= $selSp===(int)$sp['id_user']?'selected':'' ?>>
              <?= esc(($sp['nombres'] ?? '').' '.($sp['apellidos'] ?? '')) ?>
            </option>
          <?php else: ?>
            <option value="">Seleccione un área primero…</option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="">— Ninguno —</option>
        <?php endif; ?>
      </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
      <?php $checked = old('activo', $usuario['activo'] ?? false) ? 'checked' : ''; ?>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="activo" id="activoSwitch" <?= $checked ?>>
        <label class="form-check-label" for="activoSwitch">Activo</label>
      </div>
    </div>

  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-dark px-4"><?= $isEdit ? 'Actualizar' : 'Guardar' ?></button>
    <a href="<?= base_url('usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>

<script>
  // Dependencias por área → cargos y supervisores
  document.addEventListener('DOMContentLoaded', () => {
    const selArea = document.getElementById('id_area');
    const selCargo = document.getElementById('id_cargo');
    const selSup   = document.getElementById('id_supervisor');

    async function fetchJSON(url) {
      const res = await fetch(url);
      return res.ok ? res.json() : [];
    }

    async function loadCargos(areaId) {
      if (!areaId) { selCargo.innerHTML = '<option value="">Seleccione un área primero…</option>'; return; }
      const data = await fetchJSON('<?= base_url('usuarios/getCargosByArea') ?>?id_area=' + areaId);
      selCargo.innerHTML = data.map(c => `<option value="${c.id_cargo}">${c.nombre_cargo ?? c.id_cargo}</option>`).join('') || '<option value="">—</option>';
    }

    async function loadSupervisores(areaId) {
      if (!areaId) { selSup.innerHTML = '<option value="">— Ninguno —</option>'; return; }
      const data = await fetchJSON('<?= base_url('usuarios/getSupervisorsByArea') ?>?id_area=' + areaId);
      selSup.innerHTML = '<option value="">— Ninguno —</option>' + (data.map(s => `<option value="${s.id_user}">${(s.nombres ?? '')+' '+(s.apellidos ?? '')}</option>`).join(''));
    }

    selArea?.addEventListener('change', async (e) => {
      const id = e.target.value;
      await Promise.all([loadCargos(id), loadSupervisores(id)]);
    });
  });
</script>