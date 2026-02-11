<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
$nombres     = (string)($perfil['nombres'] ?? '');
$apellidos   = (string)($perfil['apellidos'] ?? '');
$areaNombre  = trim((string)($perfil['nombre_area'] ?? ''));
$cargoNombre = trim((string)($perfil['nombre_cargo'] ?? ''));
$jefeNombre  = trim((string)($perfil['supervisor_nombre'] ?? ''));

$areaNombre  = $areaNombre  !== '' ? $areaNombre  : 'N/D';
$cargoNombre = $cargoNombre !== '' ? $cargoNombre : 'N/D';
$jefeNombre  = $jefeNombre  !== '' ? $jefeNombre  : 'N/D';

$oldCond = $old['condicion'] ?? '';

/* =========================================================
   CONDICIÓN AUTOMÁTICA SEGÚN % DE SATISFACCIÓN
========================================================= */
$condicionAuto = '';
$porcentaje = null;

if (!empty($satisfaccion)) {
    $porcentajeRaw = $satisfaccion['porcentaje'] ?? null;

    if (is_string($porcentajeRaw)) {
        $limpio = preg_replace('/[^0-9,.\-]/', '', $porcentajeRaw);
        $limpio = str_replace(',', '.', $limpio);
        if ($limpio !== '' && is_numeric($limpio)) {
            $porcentaje = (float) $limpio;
        }
    } elseif (is_numeric($porcentajeRaw)) {
        $porcentaje = (float) $porcentajeRaw;
    }

    if ($porcentaje === null) {
        $realizadas   = (int)($satisfaccion['realizadas'] ?? 0);
        $noRealizadas = (int)($satisfaccion['no_realizadas'] ?? 0);
        $total        = $realizadas + $noRealizadas;
        $porcentaje   = $total > 0 ? round(($realizadas / $total) * 100, 2) : 0.0;
    }

    if ($porcentaje >= 0 && $porcentaje <= 1) {
        $porcentaje *= 100;
    }

    $p = max(0, min(100, $porcentaje));

    if ($p <= 20) {
        $condicionAuto = 'INEXISTENCIA';
    } elseif ($p <= 39) {
        $condicionAuto = 'PELIGRO';
    } elseif ($p <= 69) {
        $condicionAuto = 'EMERGENCIA';
    } elseif ($p <= 89) {
        $condicionAuto = 'NORMAL';
    } else {
        $condicionAuto = 'AFLUENCIA';
    }
}

$bloquearCondicion = $condicionAuto !== '';
$condicionFinal = $condicionAuto !== '' ? $condicionAuto : $oldCond;
?>

<div class="container py-3">

  <h3 class="mb-2">Plan de Batalla</h3>

  <!-- =================== SATISFACCIÓN =================== -->
  <?php if (!empty($satisfaccion)): ?>
    <div class="card shadow-sm mb-3 p-3">
      <div class="d-flex align-items-center justify-content-between">

        <div>
          <small class="text-muted">Mi porcentaje de satisfacción</small>
          <div class="fw-bold" style="font-size:1.4rem;">
            <?= esc($satisfaccion['porcentaje']) ?>%
          </div>
          <small class="text-muted">
            Semana <?= esc($satisfaccion['inicio']) ?> → <?= esc($satisfaccion['fin']) ?>
          </small>
        </div>

        <div class="text-end">
          <small class="d-block"><?= (int)$satisfaccion['realizadas'] ?> realizadas</small>
          <small class="d-block"><?= (int)$satisfaccion['no_realizadas'] ?> no realizadas</small>
        </div>

      </div>
    </div>
  <?php endif; ?>
  <!-- =================================================== -->

  <form method="post" action="<?= site_url('reporte/plan') ?>" class="card shadow-sm p-3">
    <?= csrf_field() ?>

    <div class="row g-3">

      <div class="col-md-3">
        <label class="form-label">Nombres</label>
        <input class="form-control" value="<?= esc($nombres) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Apellidos</label>
        <input class="form-control" value="<?= esc($apellidos) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Área</label>
        <input class="form-control" value="<?= esc($areaNombre) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Cargo</label>
        <input class="form-control" value="<?= esc($cargoNombre) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Jefe inmediato</label>
        <input class="form-control" value="<?= esc($jefeNombre) ?>" readonly>
      </div>

      <!-- CONDICIÓN AUTO -->
      <div class="col-md-6">
        <label class="form-label">
          Condición
          <?php if ($condicionAuto !== ''): ?>
            <small class="text-muted">(calculada automáticamente y bloqueada)</small>
          <?php endif; ?>
        </label>
        <select name="condicion" id="condicion" class="form-select" required <?= $bloquearCondicion ? 'disabled' : '' ?>>
          <option value="">-- Selecciona --</option>
          <?php foreach (['AFLUENCIA','NORMAL','EMERGENCIA','PELIGRO','INEXISTENCIA'] as $c): ?>
            <option value="<?= esc($c) ?>" <?= $condicionFinal === $c ? 'selected' : '' ?>>
              <?= esc($c) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($bloquearCondicion): ?>
          <input type="hidden" name="condicion" value="<?= esc($condicionFinal) ?>">
        <?php endif; ?>
      </div>
<!-- ✅ SATISFACCIÓN REAL QUE SE GUARDA -->
<input type="hidden" name="satisfaccion" value="<?= esc($p ?? 0) ?>">
    </div>

    <hr class="my-3">

    <div id="preguntasWrap" class="mt-2"></div>
<hr class="my-4">

<h5 class="fw-bold mb-3">DESCRIPCIÓN DEL PLAN DE BATALLA</h5>

<div class="row g-3">

  <!-- ACTIVIDADES URGENTES -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold text-danger">
        ACTIVIDADES URGENTES
      </label>
      <textarea class="form-control"
                name="descripcion[actividades_urgentes]"
                rows="3"
                placeholder="Describe las actividades urgentes de esta semana..."></textarea>
    </div>
  </div>

  <!-- ACTIVIDADES PENDIENTES -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold">
        ACTIVIDADES PENDIENTES
      </label>
      <textarea class="form-control"
                name="descripcion[actividades_pendientes]"
                rows="3"
                placeholder="Actividades que deben completarse próximamente..."></textarea>
    </div>
  </div>

  <!-- ORDENES QUE DEBO CUMPLIR -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold">
        ÓRDENES QUE DEBO CUMPLIR
      </label>
      <textarea class="form-control"
                name="descripcion[ordenes_personales]"
                rows="3"
                placeholder="Órdenes o directrices que debo ejecutar..."></textarea>
    </div>
  </div>

  <!-- ORDENES JUNIORS -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold">
        ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS
      </label>
      <textarea class="form-control"
                name="descripcion[ordenes_juniors]"
                rows="3"
                placeholder="Instrucciones para el equipo o juniors..."></textarea>
    </div>
  </div>

  <!-- CUOTAS -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold">
        CUOTAS PARA LA SEMANA
      </label>
      <textarea class="form-control"
                name="descripcion[cuotas_semana]"
                rows="3"
                placeholder="Metas numéricas o cuotas asignadas..."></textarea>
    </div>
  </div>

  <!-- OBJETIVOS ESTRATÉGICOS -->
  <div class="col-12">
    <div class="card shadow-sm p-3">
      <label class="form-label fw-bold text-primary">
        OBJETIVOS QUE CONTRIBUYEN AL PLAN ESTRATÉGICO
      </label>
      <textarea class="form-control"
                name="descripcion[objetivos_estrategicos]"
                rows="3"
                placeholder="Objetivos alineados a la estrategia organizacional..."></textarea>
    </div>
  </div>

</div>

    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary">
        <i class="bi bi-save me-1"></i> Guardar Plan
      </button>
    </div>

  </form>
</div>

<script>
const preguntasPorCondicion = {
 AFLUENCIA: [
    "ECONOMIZA EN ACTIVIDADES INNECESARIAS QUE NO CONTRIBUYERON A LA AFLUENCIA.",
    "HAZ QUE TODA ACCION CUENTE Y NO TOMES PARTE EN NINGUNA ACCIÓN INÚTIL.",
    "CONSOLIDAR LAS GANANCIAS.",
    "DESCUBRE QUÉ CAUSÓ LA AFLUENCIA Y REFUERZALO.",
  ],
  NORMAL: [
    "NO CAMBIAR NADA.",
    "LA ÉTICA ES MUY POCO SEVERA.",
    "EXAMINA LAS ESTADÍSTICAS.",
    "CORRIGE LO QUE EMPEORÓ.",
  ],
  EMERGENCIA: [
    "PROMOCIONA Y PRODUCE.",
    "CAMBIA TU FORMA DE ACTUAR.",
    "ECONOMIZA.",
    "PREPÁRATE PARA DAR SERVICIO.",
  ],
  PELIGRO: [
    "ROMPE HÁBITOS NORMALES.",
    "RESUELVE EL PELIGRO.",
    "AUTODISCIPLINA.",
    "REORGANIZA TU VIDA.",
  ],
  INEXISTENCIA: [
    "ENCUENTRA UNA LÍNEA DE COMUNICACIÓN.",
    "DASE A CONOCER.",
    "DESCUBRE LO QUE NECESITAN.",
    "PRODÚCELO.",
  ],
};

const condicionEl = document.getElementById('condicion');
const wrap = document.getElementById('preguntasWrap');

function renderPreguntas(condicion) {
  const preguntas = preguntasPorCondicion[condicion] || [];
  if (!preguntas.length) {
    wrap.innerHTML = '<div class="text-muted">Selecciona una condición para ver las preguntas.</div>';
    return;
  }

  let html = '<div class="row g-3">';
  preguntas.forEach((q, i) => {
    html += `
      <div class="col-12">
        <label class="form-label">${i+1}. ${q}</label>
        <textarea class="form-control" name="preguntas[${i}][a]" rows="2" required></textarea>
      </div>`;
  });
  html += '</div>';
  wrap.innerHTML = html;
}

condicionEl.addEventListener('change', e => renderPreguntas(e.target.value));
if (condicionEl.value) renderPreguntas(condicionEl.value);
</script>

<?= $this->endSection() ?>
