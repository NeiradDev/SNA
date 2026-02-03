<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
$nombres = (string)($perfil['nombres'] ?? '');
$apellidos = (string)($perfil['apellidos'] ?? '');
$areaNombre = trim((string)($perfil['nombre_area'] ?? ''));
$cargoNombre = trim((string)($perfil['nombre_cargo'] ?? ''));
$jefeNombre = trim((string)($perfil['supervisor_nombre'] ?? ''));

$areaNombre = $areaNombre !== '' ? $areaNombre : 'N/D';
$cargoNombre = $cargoNombre !== '' ? $cargoNombre : 'N/D';
$jefeNombre = $jefeNombre !== '' ? $jefeNombre : 'N/D';

$oldCond = $old['condicion'] ?? '';
?>

<div class="container py-3">
  <h3 class="mb-3">Plan de Batalla</h3>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= site_url('reporte/plan') ?>" class="card shadow-sm p-3">
    <?= csrf_field() ?>

    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Nombres</label>
        <input type="text" class="form-control" value="<?= esc($nombres) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Apellidos</label>
        <input type="text" class="form-control" value="<?= esc($apellidos) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Área</label>
        <input type="text" class="form-control" value="<?= esc($areaNombre) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label">Cargo</label>
        <input type="text" class="form-control" value="<?= esc($cargoNombre) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Jefe inmediato</label>
        <input type="text" class="form-control" value="<?= esc($jefeNombre) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Condición</label>
        <select name="condicion" id="condicion" class="form-select" required>
          <option value="">-- Selecciona --</option>
          <?php foreach (['AFLUENCIA','NORMAL','EMERGENCIA','PELIGRO', 'INEXISTENCIA'] as $c): ?>
            <option value="<?= esc($c) ?>" <?= ($oldCond === $c ? 'selected' : '') ?>><?= esc($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <hr class="my-3">

    <div id="preguntasWrap" class="mt-2">
      <div class="text-muted">Selecciona una condición para ver las preguntas.</div>
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
    "AFLUENCIA": [
      "ECONOMIZA EN ACTIVIDADES INNECESARIAS QUE NO CONTRIBUYERON A LA AFLUENCIA.",
      "HAZ QUE TODA ACCION CUENTE Y NO TOMES PARTE EN NINGUNA ACCIÓN INÚTIL.",
      "CONSOLIDAR LAS GANANCIAS, EN CUALQUIER ÁREA EN QUE HAYAS OBTENIDO UNA GANANCIA, LA CONSERVAS.",
      "DESCUBRE POR TI MISMO Y PARA TI MISMO QUE CAUSÓ LA CONDICIÓN DE AFLUENCIA Y REFUERZALO.",
    ],
    "NORMAL": [
      "NO CAMBIAR NADA.",
      "LA ÉTICA ES MUY POCO SEVERA.",
      "SI UNA ESTADÍSTICA MEJORA, EXAMINALA Y AVERIGUA QUE MEJORÓ SIN ABANDONAR LO QUE ESTABAS HACIENDO ANTES.",
      "ENCUENTRA POR QUE EMPEORO UNA ESTADÍSTICA Y CORRÍGELO",
    ],
    "EMERGENCIA": [
      "PROMOCIONA Y PRODUCE.",
      "CAMBIE SU FORMA DE ACTUAR.",
      "ECONOMICE.",
      "PREPARESE PARA DAR SERVICIO.",
      "HACER MÁS ESTRICTA LA DISCIPLINA.",
    ],
    "PELIGRO": [
      "PASE POR ALTO HÁBITOS O RUTINAS NORMALES.",
      "RESUELVA LA SITUACIÓN Y CUALQUIER PELIGRO QUE HAYA EN ELLA.",
      "ASIGNESE UNA CONDICIÓN DE PELIGRO.",
      "AUTODISCIPLINA PARA CORREGIRLO Y VUÉLVASE HONESO Y RETO.",
      "REORGANICE SU VIDA PARA QUE LA SITUACIÓN PELIGROS NO LE ESTÉ OCURRIENDO CONTINUAMENTE.",
      "A OCURRIR.",
    ],
     "INEXISTENCIA": [
      "ENCUENTRE UNA LÍNEA DE COMUNICACIÓN.",
      "DESE A CONOCER.",
      "DESCUBRA LO QUE NECESITA O DESEA.",
      "HÁGALO,PRODUZCALO O PRESÉNTELO.",
    ],
  };

  const condicionEl = document.getElementById('condicion');
  const wrap = document.getElementById('preguntasWrap');

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  function renderPreguntas(condicion) {
    const preguntas = preguntasPorCondicion[condicion] || [];

    if (preguntas.length === 0) {
      wrap.innerHTML = '<div class="text-muted">Selecciona una condición para ver las preguntas.</div>';
      return;
    }

    let html = '<div class="row g-3">';
    preguntas.forEach((q, i) => {
      html += `
        <div class="col-12">
          <label class="form-label">${i+1}. ${q}</label>
          <input type="hidden" name="preguntas[${i}][q]" value="${escapeHtml(q)}">
          <textarea class="form-control" name="preguntas[${i}][a]" rows="2"
                    placeholder="Escribe tu respuesta..." required></textarea>
        </div>
      `;
    });
    html += '</div>';

    wrap.innerHTML = html;
  }

  condicionEl.addEventListener('change', (e) => renderPreguntas(e.target.value));

  (function init() {
    if (condicionEl.value) renderPreguntas(condicionEl.value);
  })();
</script>
<script>
/**
 * Si el usuario ya está dentro de "Plan de Batalla" y el horario se vence,
 * mostramos alerta y redirigimos sin recargar manualmente.
 */
(async function planPageAutoLock(){
  const urlStatus = "<?= site_url('api/horario-plan/status') ?>";
  const homeUrl   = "<?= site_url('home') ?>";

  async function check(){
    try{
      const res = await fetch(urlStatus, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const data = await res.json();
      if(data.ok && !data.enabled){
        alert('El Plan de Batalla quedó fuera de horario. Serás redirigido.');
        window.location.href = homeUrl;
      }
    }catch(e){}
  }

  // Chequeo cada 30s
  setInterval(check, 30000);
})();
</script>

<?= $this->endSection() ?>
