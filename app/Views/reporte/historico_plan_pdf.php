<?php 
/**
 * =========================================================
 * Vista PDF: reporte/historico_plan_pdf.php
 * =========================================================
 * ✅ IMPLEMENTADO:
 * - Mostrar NOMBRE de DIVISIÓN (no id)
 * - Mostrar NOMBRE de ÁREA (como ya estaba)
 * - Preguntas SIN JSON + ORDEN por CONDICIÓN (igual que la vista)
 * =========================================================
 */

function e($v){ return esc((string)$v); }

$scopeInfo    = $scopeInfo ?? [];
$selectedWeek = (string)($selectedWeek ?? '');
$historico    = $historico ?? [];
$tasksPack    = $tasksPack ?? [];

$urgentes       = $tasksPack['urgentes'] ?? [];
$pendientes     = $tasksPack['pendientes'] ?? [];
$ordenesMi      = $tasksPack['ordenesMi'] ?? [];
$ordenesJuniors = $tasksPack['ordenesJuniors'] ?? [];
$start          = $tasksPack['start'] ?? '';
$end            = $tasksPack['end'] ?? '';

$userName = trim(($historico['apellidos'] ?? '').' '.($historico['nombres'] ?? ''));

// ✅ Nombre de división (viene del Service: division_nombre / nombre_division)
$divisionName = (string)($historico['division_nombre'] ?? ($historico['nombre_division'] ?? ''));

// ✅ Nombre de área (prioriza snapshot)
$areaName = (string)($historico['area_nombre'] ?? ($historico['nombre_area'] ?? ''));

// =========================================================
// PREGUNTAS (SIN JSON) + ORDEN POR CONDICIÓN
// =========================================================
$questionsByCondition = [
  'AFLUENCIA' => [
    "ECONOMIZA EN ACTIVIDADES INNECESARIAS QUE NO CONTRIBUYERON A LA AFLUENCIA.",
    "HAZ QUE TODA ACCION CUENTE Y NO TOMES PARTE EN NINGUNA ACCIÓN INÚTIL.",
    "CONSOLIDAR LAS GANANCIAS.",
    "DESCUBRE QUÉ CAUSÓ LA AFLUENCIA Y REFUERZALO.",
  ],
  'NORMAL' => [
    "NO CAMBIAR NADA.",
    "LA ÉTICA ES MUY POCO SEVERA.",
    "EXAMINA LAS ESTADÍSTICAS.",
    "CORRIGE LO QUE EMPEORÓ.",
  ],
  'EMERGENCIA' => [
    "PROMOCIONA Y PRODUCE.",
    "CAMBIA TU FORMA DE ACTUAR.",
    "ECONOMIZA.",
    "PREPÁRATE PARA DAR SERVICIO.",
  ],
  'PELIGRO' => [
    "ROMPE HÁBITOS NORMALES.",
    "RESUELVE EL PELIGRO.",
    "AUTODISCIPLINA.",
    "REORGANIZA TU VIDA.",
  ],
  'INEXISTENCIA' => [
    "ENCUENTRA UNA LÍNEA DE COMUNICACIÓN.",
    "DASE A CONOCER.",
    "DESCUBRE LO QUE NECESITAN.",
    "PRODÚCELO.",
  ],
];

$cond = strtoupper(trim((string)($historico['condicion'] ?? '')));
$expectedQuestions = $questionsByCondition[$cond] ?? [];

// Respuestas guardadas (ya decodificadas desde service) => queremos SOLO valores
$rawAnswers = $historico['preguntas'] ?? [];
$answersList = [];

if (is_array($rawAnswers)) {
  foreach ($rawAnswers as $v) {
    if (is_array($v)) $answersList[] = trim(implode(' ', array_map('strval', $v)));
    else $answersList[] = (string)$v;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Histórico Plan</title>
  <style>
    body{ font-family: Arial, Helvetica, sans-serif; font-size: 12px; color:#111; }
    .h1{ font-size: 18px; font-weight: 800; margin: 0 0 6px; }
    .muted{ color:#555; }
    .box{ border:1px solid #ddd; border-radius:10px; padding:10px; margin-bottom:10px; }
    .title{ font-weight:800; margin-bottom:6px; }
    .badge{ display:inline-block; padding:4px 8px; border:1px solid #ddd; border-radius:999px; background:#f7f7f7; font-weight:700; }
    .task{ border:1px solid #ddd; border-radius:10px; padding:8px; margin-bottom:6px; }
    .task b{ font-size: 12px; }
    .small{ font-size: 11px; }
  </style>
</head>
<body>

  <div class="h1">Histórico Plan de Batalla</div>
  <div class="muted">
    Semana: <span class="badge"><?= e($selectedWeek) ?></span>
    &nbsp; | &nbsp; Usuario: <b><?= e($userName) ?></b>
    &nbsp; | &nbsp; Rango: <b><?= e($start) ?></b>/<b><?= e($end) ?></b>
  </div>

  <div class="box">
    <div class="title">Resumen</div>
    <div><b>Cédula:</b> <?= e($historico['cedula'] ?? '') ?></div>

    <!-- ✅ AQUÍ SE MUESTRA EL NOMBRE DE LA DIVISIÓN -->
    <div><b>División:</b> <?= e($divisionName !== '' ? $divisionName : '-') ?></div>

    <div><b>Área:</b> <?= e($areaName !== '' ? $areaName : '-') ?></div>
    <div><b>Cargo:</b> <?= e($historico['cargo_nombre'] ?? '-') ?></div>
    <div><b>Jefe inmediato:</b> <?= e($historico['jefe_inmediato'] ?? '-') ?></div>
    <div><b>Satisfacción:</b> <?= e($historico['satisfaccion'] ?? '0') ?>%</div>
    <div><b>Estado:</b> <?= e($historico['estado'] ?? '-') ?> — <b>Condición:</b> <?= e($historico['condicion'] ?? '-') ?></div>
  </div>

  <!-- ✅ PREGUNTAS SIN JSON + EN ORDEN POR CONDICIÓN -->
  <div class="box">
    <div class="title">Preguntas del Plan</div>

    <?php if (empty($expectedQuestions)): ?>
      <div class="muted">No hay plantilla de preguntas para la condición: <b><?= e($cond !== '' ? $cond : '-') ?></b></div>

      <?php if (!empty($answersList)): ?>
        <div class="small muted" style="margin-top:6px;">
          Se detectaron respuestas guardadas (mostrando solo valores):
        </div>
        <ul>
          <?php foreach ($answersList as $ans): ?>
            <li><b><?= e($ans) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php else: ?>
      <div class="small muted" style="margin-bottom:6px;">
        Condición: <b><?= e($cond) ?></b>
      </div>

      <?php foreach ($expectedQuestions as $idx => $questionText): ?>
        <?php $answer = $answersList[$idx] ?? ''; ?>
        <div class="task">
          <b><?= e($questionText) ?></b>
          <div class="small" style="margin-top:4px;">
            Respuesta: <?php if ($answer !== ''): ?><b><?= e($answer) ?></b><?php else: ?><span class="muted">—</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="box">
    <div class="title">ACTIVIDADES URGENTES</div>
    <?php if (empty($urgentes)): ?><div class="muted">Sin registros.</div><?php endif; ?>
    <?php foreach ($urgentes as $t): ?>
      <div class="task">
        <b><?= e($t['titulo'] ?? '') ?></b>
        <div class="small muted"><?= e($t['descripcion'] ?? '') ?></div>
        <div class="small">
          Prioridad: <b><?= e($t['nombre_prioridad'] ?? '') ?></b>
          — Estado: <b><?= e($t['nombre_estado'] ?? '') ?></b><br>
          Inicio: <?= e($t['fecha_inicio'] ?? '') ?> — Fin: <?= e($t['fecha_fin'] ?? '-') ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="box">
    <div class="title">ACTIVIDADES PENDIENTES</div>
    <?php if (empty($pendientes)): ?><div class="muted">Sin registros.</div><?php endif; ?>
    <?php foreach ($pendientes as $t): ?>
      <div class="task">
        <b><?= e($t['titulo'] ?? '') ?></b>
        <div class="small muted"><?= e($t['descripcion'] ?? '') ?></div>
        <div class="small">
          Prioridad: <b><?= e($t['nombre_prioridad'] ?? '') ?></b>
          — Estado: <b><?= e($t['nombre_estado'] ?? '') ?></b><br>
          Inicio: <?= e($t['fecha_inicio'] ?? '') ?> — Fin: <?= e($t['fecha_fin'] ?? '-') ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="box">
    <div class="title">ÓRDENES QUE DEBO CUMPLIR</div>
    <?php if (empty($ordenesMi)): ?><div class="muted">Sin registros.</div><?php endif; ?>
    <?php foreach ($ordenesMi as $t): ?>
      <div class="task">
        <b><?= e($t['titulo'] ?? '') ?></b>
        <div class="small muted"><?= e($t['descripcion'] ?? '') ?></div>
        <div class="small">
          Área: <b><?= e($t['nombre_area'] ?? '-') ?></b><br>
          Prioridad: <b><?= e($t['nombre_prioridad'] ?? '') ?></b>
          — Estado: <b><?= e($t['nombre_estado'] ?? '') ?></b><br>
          Inicio: <?= e($t['fecha_inicio'] ?? '') ?> — Fin: <?= e($t['fecha_fin'] ?? '-') ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="box">
    <div class="title">ÓRDENES QUE DEBEN REALIZAR SUS JUNIORS</div>
    <?php if (empty($ordenesJuniors)): ?><div class="muted">Sin registros.</div><?php endif; ?>
    <?php foreach ($ordenesJuniors as $t): ?>
      <div class="task">
        <b><?= e($t['titulo'] ?? '') ?></b>
        <div class="small muted"><?= e($t['descripcion'] ?? '') ?></div>
        <div class="small">
          Asignado a: <b><?= e(trim(($t['asignado_a_apellidos'] ?? '').' '.($t['asignado_a_nombres'] ?? ''))) ?></b><br>
          Área: <b><?= e($t['nombre_area'] ?? '-') ?></b><br>
          Prioridad: <b><?= e($t['nombre_prioridad'] ?? '') ?></b>
          — Estado: <b><?= e($t['nombre_estado'] ?? '') ?></b><br>
          Inicio: <?= e($t['fecha_inicio'] ?? '') ?> — Fin: <?= e($t['fecha_fin'] ?? '-') ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</body>
</html>