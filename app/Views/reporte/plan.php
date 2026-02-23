<?= $this->extend('layouts/main') ?> 
<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: reporte/plan.php
 * =========================================================
 * OBJETIVO:
 * ✅ Mantener las MISMAS divisiones:
 *    - ACTIVIDADES URGENTES
 *    - ACTIVIDADES PENDIENTES
 *    - ÓRDENES QUE DEBO CUMPLIR
 *    - ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS
 *
 * ✅ Mostrarlo en 2 pestañas (esta semana / siguiente semana)
 *
 * ✅ FIX IMPORTANTE (TU PROBLEMA):
 * - Las pestañas fallaban por Bootstrap (BS4 vs BS5 o sin JS).
 * - Se agregó compatibilidad BS4 + BS5 y un FALLBACK JS que hace que
 *   cambie de pestaña aunque no cargue bootstrap.bundle.js
 *
 * ✅ Solo visualización (sin botones de acción sobre tareas)
 * ✅ Títulos sobrios (fondo blanco + letras negras)
 * =========================================================
 */

/* -----------------------------------------
 * Defaults defensivos (evita warnings)
 * ----------------------------------------- */
$old    = $old    ?? [];
$extras = $extras ?? [];

$urgentes       = $urgentes       ?? [];
$pendientes     = $pendientes     ?? [];
$ordenesMias    = $ordenesMias    ?? ($ordenesMi ?? []);
$ordenesJuniors = $ordenesJuniors ?? [];
$tieneJuniors   = $tieneJuniors   ?? ($hasJuniors ?? false);

/* -----------------------------------------
 * NUEVO: Arrays por semana si controller los manda
 * ----------------------------------------- */
$actSemanaActual    = $actSemanaActual    ?? [];
$actSemanaSiguiente = $actSemanaSiguiente ?? [];

/* -----------------------------------------
 * Rangos de semana (si controller los manda)
 * Estructura esperada:
 *   $semana = ['inicio'=>DateTime|string, 'fin'=>DateTime|string]
 * ----------------------------------------- */
$semana          = $semana          ?? null;
$semanaSiguiente = $semanaSiguiente ?? null;

/* -----------------------------------------
 * Usuario actual (para derivaciones)
 * ----------------------------------------- */
$idUser = (int) (session()->get('id_user') ?? 0);

/* -----------------------------------------
 * Campos de texto (cuotas / objetivo)
 * ----------------------------------------- */
$cuotaDesc    = (string)($cuota['descripcion'] ?? ($extras['cuota']['descripcion'] ?? ''));
$objetivoDesc = (string)($objetivo['descripcion'] ?? ($extras['objetivo']['descripcion'] ?? ''));

/* -----------------------------------------
 * Perfil visual
 * ----------------------------------------- */
/**
 * =========================================================
 * Vista: reporte/plan.php
 * =========================================================
 * OBJETIVO:
 * ✅ Mantener las MISMAS divisiones:
 *    - ACTIVIDADES URGENTES
 *    - ACTIVIDADES PENDIENTES
 *    - ÓRDENES QUE DEBO CUMPLIR
 *    - ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS
 *
 * ✅ Mostrarlo en 2 pestañas (esta semana / siguiente semana)
 *
 * ✅ FIX IMPORTANTE (TU PROBLEMA):
 * - Las pestañas fallaban por Bootstrap (BS4 vs BS5 o sin JS).
 * - Se agregó compatibilidad BS4 + BS5 y un FALLBACK JS que hace que
 *   cambie de pestaña aunque no cargue bootstrap.bundle.js
 *
 * ✅ Solo visualización (sin botones de acción sobre tareas)
 * ✅ Títulos sobrios (fondo blanco + letras negras)
 * =========================================================
 */

/* -----------------------------------------
 * Defaults defensivos (evita warnings)
 * ----------------------------------------- */
$old    = $old    ?? [];
$extras = $extras ?? [];

$urgentes       = $urgentes       ?? [];
$pendientes     = $pendientes     ?? [];
$ordenesMias    = $ordenesMias    ?? ($ordenesMi ?? []);
$ordenesJuniors = $ordenesJuniors ?? [];
$tieneJuniors   = $tieneJuniors   ?? ($hasJuniors ?? false);

/* -----------------------------------------
 * NUEVO: Arrays por semana si controller los manda
 * ----------------------------------------- */
$actSemanaActual    = $actSemanaActual    ?? [];
$actSemanaSiguiente = $actSemanaSiguiente ?? [];

/* -----------------------------------------
 * Rangos de semana (si controller los manda)
 * Estructura esperada:
 *   $semana = ['inicio'=>DateTime|string, 'fin'=>DateTime|string]
 * ----------------------------------------- */
$semana          = $semana          ?? null;
$semanaSiguiente = $semanaSiguiente ?? null;

/* -----------------------------------------
 * Usuario actual (para derivaciones)
 * ----------------------------------------- */
$idUser = (int) (session()->get('id_user') ?? 0);

/* -----------------------------------------
 * Campos de texto (cuotas / objetivo)
 * ----------------------------------------- */
$cuotaDesc    = (string)($cuota['descripcion'] ?? ($extras['cuota']['descripcion'] ?? ''));
$objetivoDesc = (string)($objetivo['descripcion'] ?? ($extras['objetivo']['descripcion'] ?? ''));

/* -----------------------------------------
 * Perfil visual
 * ----------------------------------------- */
$nombres     = (string)($perfil['nombres'] ?? '');
$apellidos   = (string)($perfil['apellidos'] ?? '');
$areaNombre  = trim((string)($perfil['nombre_area'] ?? ''));
$cargoNombre = trim((string)($perfil['nombre_cargo'] ?? ''));
$jefeNombre  = trim((string)($perfil['supervisor_nombre'] ?? ''));

$areaNombre  = $areaNombre  !== '' ? $areaNombre  : 'N/D';
$cargoNombre = $cargoNombre !== '' ? $cargoNombre : 'N/D';
$jefeNombre  = $jefeNombre  !== '' ? $jefeNombre  : 'N/D';

/* -----------------------------------------
 * Helpers de formato (fechas / texto)
 * ----------------------------------------- */
$fmtDate = function ($value): string {
    if ($value instanceof \DateTimeInterface) return $value->format('d/m/Y');

    $value = trim((string)$value);
    if ($value === '') return 'N/D';

    $ts = strtotime($value);
    if ($ts === false) return $value;

    return date('d/m/Y', $ts);
};

$weekLabel = function($week) use ($fmtDate): string {
    if (!is_array($week) || empty($week['inicio']) || empty($week['fin'])) return 'N/D';

    $ini = $week['inicio'] instanceof \DateTimeInterface ? $week['inicio']->format('Y-m-d') : (string)$week['inicio'];
    $fin = $week['fin'] instanceof \DateTimeInterface ? $week['fin']->format('Y-m-d') : (string)$week['fin'];

    return $fmtDate($ini) . ' → ' . $fmtDate($fin);
};

/* -----------------------------------------
 * Helpers de estado (badge + label)
 * ----------------------------------------- */
$getEstadoLabel = function(array $t): string {
    $nombre = trim((string)($t['nombre_estado'] ?? ''));
    if ($nombre !== '') return $nombre;

    $id = (int)($t['id_estado_tarea'] ?? 0);
    if ($id === 3) return 'REALIZADA';
    if ($id > 0)   return 'ESTADO #' . $id;
    return 'N/D';
};

$getEstadoBadge = function(array $t): string {
    $id = (int)($t['id_estado_tarea'] ?? 0);

    // ✅ Realizada si id_estado=3 o si tiene completed_at
    if ($id === 3 || !empty($t['completed_at'])) return 'bg-dark';

    return 'bg-secondary';
};

$isDone = function(array $t): bool {
    $id = (int)($t['id_estado_tarea'] ?? 0);
    return ($id === 3) || !empty($t['completed_at']);
};

/* -----------------------------------------
 * Lowercase seguro para data-text (búsqueda)
 * - usa mb_strtolower si existe, si no strtolower
 * ----------------------------------------- */
$lower = function(string $txt): string {
    if (function_exists('mb_strtolower')) return mb_strtolower($txt, 'UTF-8');
    return strtolower($txt);
};

/* ============================================================
 * Condición automática según satisfacción (TU lógica intacta)
 * ============================================================ */
/* -----------------------------------------
 * Helpers de formato (fechas / texto)
 * ----------------------------------------- */
$fmtDate = function ($value): string {
  if ($value instanceof \DateTimeInterface) return $value->format('d/m/Y');

  $value = trim((string)$value);
  if ($value === '') return 'N/D';

  $ts = strtotime($value);
  if ($ts === false) return $value;

  return date('d/m/Y', $ts);
};

$weekLabel = function ($week) use ($fmtDate): string {
  if (!is_array($week) || empty($week['inicio']) || empty($week['fin'])) return 'N/D';

  $ini = $week['inicio'] instanceof \DateTimeInterface ? $week['inicio']->format('Y-m-d') : (string)$week['inicio'];
  $fin = $week['fin'] instanceof \DateTimeInterface ? $week['fin']->format('Y-m-d') : (string)$week['fin'];

  return $fmtDate($ini) . ' → ' . $fmtDate($fin);
};

/* -----------------------------------------
 * Helpers de estado (badge + label)
 * ----------------------------------------- */
$getEstadoLabel = function (array $t): string {
  $nombre = trim((string)($t['nombre_estado'] ?? ''));
  if ($nombre !== '') return $nombre;

  $id = (int)($t['id_estado_tarea'] ?? 0);
  if ($id === 3) return 'REALIZADA';
  if ($id > 0)   return 'ESTADO #' . $id;
  return 'N/D';
};

$getEstadoBadge = function (array $t): string {
  $id = (int)($t['id_estado_tarea'] ?? 0);

  // ✅ Realizada si id_estado=3 o si tiene completed_at
  if ($id === 3 || !empty($t['completed_at'])) return 'bg-dark';

  return 'bg-secondary';
};

$isDone = function (array $t): bool {
  $id = (int)($t['id_estado_tarea'] ?? 0);
  return ($id === 3) || !empty($t['completed_at']);
};

/* -----------------------------------------
 * Lowercase seguro para data-text (búsqueda)
 * - usa mb_strtolower si existe, si no strtolower
 * ----------------------------------------- */
$lower = function (string $txt): string {
  if (function_exists('mb_strtolower')) return mb_strtolower($txt, 'UTF-8');
  return strtolower($txt);
};

/* ============================================================
 * Condición automática según satisfacción (TU lógica intacta)
 * ============================================================ */
$oldCond = $old['condicion'] ?? '';
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

    // Si llega 0..1 lo convertimos a %
    if ($porcentaje >= 0 && $porcentaje <= 1) $porcentaje *= 100;
  // Si llega 0..1 lo convertimos a %
  if ($porcentaje >= 0 && $porcentaje <= 1) $porcentaje *= 100;

    // Clamp 0..100
    $p = max(0, min(100, $porcentaje));
  // Clamp 0..100
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

/* ============================================================
 * Semana actual (EXACTO como antes, con tus arrays)
 * ============================================================ */
$urgentesThis       = $urgentes;
$pendientesThis     = $pendientes;
$ordenesMiasThis    = $ordenesMias;
$ordenesJuniorsThis = $ordenesJuniors;

/* ============================================================
 * Semana siguiente:
 * - si controller manda categorizadas => se usan
 * - si NO, derivamos desde $actSemanaSiguiente (SOLO MIS TAREAS)
 * ============================================================ */
$urgentesSiguiente       = $urgentesSiguiente       ?? [];
$pendientesSiguiente     = $pendientesSiguiente     ?? [];
$ordenesMiasSiguiente    = $ordenesMiasSiguiente    ?? [];
$ordenesJuniorsSiguiente = $ordenesJuniorsSiguiente ?? [];

/* ✅ Derivaciones CORREGIDAS:
 * Antes: filtrabas sin validar asignado_a, podía mezclar y dejar vacíos.
 * Ahora: derivamos SOLO tareas donde asignado_a = yo.
 */
if (empty($urgentesSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
    $urgentesSiguiente = array_values(array_filter($actSemanaSiguiente, function($t) use ($idUser) {
        return (int)($t['asignado_a'] ?? 0) === $idUser
            && (int)($t['id_prioridad'] ?? 0) === 4;
    }));
}

if (empty($pendientesSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
    $pendientesSiguiente = array_values(array_filter($actSemanaSiguiente, function($t) use ($idUser) {
        return (int)($t['asignado_a'] ?? 0) === $idUser
            && (int)($t['asignado_por'] ?? 0) === $idUser;
    }));
}

if (empty($ordenesMiasSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
    $ordenesMiasSiguiente = array_values(array_filter($actSemanaSiguiente, function($t) use ($idUser) {
        return (int)($t['asignado_a'] ?? 0) === $idUser
            && (int)($t['asignado_por'] ?? 0) !== $idUser;
    }));
}
// ✅ Juniors siguiente: SOLO si controller lo manda (porque actSemanaSiguiente suele ser "mis tareas")
?>

<style>
/* =========================================================
   Estilo compacto + sobrio
========================================================= */
.section-title{
  font-weight: 800;
  letter-spacing: .2px;
  color:#0B0B0B;
}

.section-title--invert{
  background:#FFFFFF;
  color:#0B0B0B;
  padding:10px 12px;
  border-radius:10px;
  border:1px solid rgba(0,0,0,.12);
}

/* Render horizontal de tareas */
.task-row{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  align-items:center;
  justify-content:space-between;
}
.task-main{
  min-width:260px;
  flex:1 1 420px;
}
.task-meta{
  display:flex;
  gap:14px;
  flex-wrap:wrap;
  align-items:center;
}
.task-meta small{ display:block; }

/* Toolbar */
.week-toolbar{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
  justify-content:space-between;
}
.week-toolbar .left{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
}
</style>

/* ============================================================
 * Semana actual (EXACTO como antes, con tus arrays)
 * ============================================================ */
$urgentesThis       = $urgentes;
$pendientesThis     = $pendientes;
$ordenesMiasThis    = $ordenesMias;
$ordenesJuniorsThis = $ordenesJuniors;

/* ============================================================
 * Semana siguiente:
 * - si controller manda categorizadas => se usan
 * - si NO, derivamos desde $actSemanaSiguiente (SOLO MIS TAREAS)
 * ============================================================ */
$urgentesSiguiente       = $urgentesSiguiente       ?? [];
$pendientesSiguiente     = $pendientesSiguiente     ?? [];
$ordenesMiasSiguiente    = $ordenesMiasSiguiente    ?? [];
$ordenesJuniorsSiguiente = $ordenesJuniorsSiguiente ?? [];

/* ✅ Derivaciones CORREGIDAS:
 * Antes: filtrabas sin validar asignado_a, podía mezclar y dejar vacíos.
 * Ahora: derivamos SOLO tareas donde asignado_a = yo.
 */
if (empty($urgentesSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
  $urgentesSiguiente = array_values(array_filter($actSemanaSiguiente, function ($t) use ($idUser) {
    return (int)($t['asignado_a'] ?? 0) === $idUser
      && (int)($t['id_prioridad'] ?? 0) === 4;
  }));
}

if (empty($pendientesSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
  $pendientesSiguiente = array_values(array_filter($actSemanaSiguiente, function ($t) use ($idUser) {
    return (int)($t['asignado_a'] ?? 0) === $idUser
      && (int)($t['asignado_por'] ?? 0) === $idUser;
  }));
}

if (empty($ordenesMiasSiguiente) && !empty($actSemanaSiguiente) && $idUser > 0) {
  $ordenesMiasSiguiente = array_values(array_filter($actSemanaSiguiente, function ($t) use ($idUser) {
    return (int)($t['asignado_a'] ?? 0) === $idUser
      && (int)($t['asignado_por'] ?? 0) !== $idUser;
  }));
}
// ✅ Juniors siguiente: SOLO si controller lo manda (porque actSemanaSiguiente suele ser "mis tareas")
?>

<style>
  /* =========================================================
   Estilo compacto + sobrio
========================================================= */
  .section-title {
    font-weight: 800;
    letter-spacing: .2px;
    color: #0B0B0B;
  }

  .section-title--invert {
    background: #FFFFFF;
    color: #0B0B0B;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, .12);
  }

  /* Render horizontal de tareas */
  .task-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .task-main {
    min-width: 260px;
    flex: 1 1 420px;
  }

  .task-meta {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    align-items: center;
  }

  .task-meta small {
    display: block;
  }

  /* Toolbar */
  .week-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
  }

  .week-toolbar .left {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
</style>

<div class="container py-3">

  <h3 class="mb-2 section-title">Plan de Batalla</h3>

  <?php if (!empty($planCompletado)): ?>
    <div class="alert alert-success text-center mt-4">
      <h5 class="mb-2">Has completado tu Plan de Batalla semanal.</h5>
      <p>Se volverá a habilitar el próximo miércoles desde las 00:00 am hasta las 23:59 pm.</p>
      <div class="fw-bold mt-2">
        Tiempo restante para habilitación:
        <span id="countdown"></span>
      </div>
    </div>
  <?php endif; ?>
  <h3 class="mb-2 section-title">Plan de Batalla</h3>

  <?php if (!empty($planCompletado)): ?>
    <div class="alert alert-success text-center mt-4">
      <h5 class="mb-2">Has completado tu Plan de Batalla semanal.</h5>
      <p>Se volverá a habilitar el próximo miércoles desde las 00:00 am hasta las 23:59 pm.</p>
      <div class="fw-bold mt-2">
        Tiempo restante para habilitación:
        <span id="countdown"></span>
      </div>
    </div>
  <?php endif; ?>

  <!-- =================== SATISFACCIÓN =================== -->
  <?php if (!empty($satisfaccion)): ?>
    <div class="card shadow-sm mb-3 p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <small class="text-muted"><?= esc($satisfaccion['titulo'] ?? 'Mi porcentaje de satisfacción') ?></small>
          <div class="fw-bold" style="font-size:1.4rem;">
            <?= esc(number_format((float)$satisfaccion['porcentaje'], 2)) ?>%
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

  <?php if (empty($planCompletado)): ?>

  <?php if (empty($planCompletado)): ?>

    <form method="post" action="<?= site_url('reporte/plan') ?>" class="card shadow-sm p-3">
      <?= csrf_field() ?>
    <form method="post" action="<?= site_url('reporte/plan') ?>" class="card shadow-sm p-3">
      <?= csrf_field() ?>

      <div class="row g-3">
      <div class="row g-3">

        <div class="col-md-3">
          <label class="form-label">Nombres</label>
          <input class="form-control" value="<?= esc($nombres) ?>" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombres</label>
          <input class="form-control" value="<?= esc($nombres) ?>" readonly>
        </div>

        <div class="col-md-3">
          <label class="form-label">Apellidos</label>
          <input class="form-control" value="<?= esc($apellidos) ?>" readonly>
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
          <label class="form-label">Área</label>
          <input class="form-control" value="<?= esc($areaNombre) ?>" readonly>
        </div>

        <div class="col-md-3">
          <label class="form-label">Cargo</label>
          <input class="form-control" value="<?= esc($cargoNombre) ?>" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Cargo</label>
          <input class="form-control" value="<?= esc($cargoNombre) ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Jefe inmediato</label>
          <input class="form-control" value="<?= esc($jefeNombre) ?>" readonly>
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

        <!-- % satisfacción (si existe) -->
        <input type="hidden" name="satisfaccion" value="<?= esc($p ?? 0) ?>">
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
            <?php foreach (['AFLUENCIA', 'NORMAL', 'EMERGENCIA', 'PELIGRO', 'INEXISTENCIA'] as $c): ?>
              <option value="<?= esc($c) ?>" <?= $condicionFinal === $c ? 'selected' : '' ?>>
                <?= esc($c) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <?php if ($bloquearCondicion): ?>
            <input type="hidden" name="condicion" value="<?= esc($condicionFinal) ?>">
          <?php endif; ?>
        </div>

        <!-- % satisfacción (si existe) -->
        <input type="hidden" name="satisfaccion" value="<?= esc($p ?? 0) ?>">
      </div>

      <hr class="my-3">
      <hr class="my-3">

      <div id="preguntasWrap" class="mt-2"></div>

      <hr class="my-4">
      <div id="preguntasWrap" class="mt-2"></div>

      <hr class="my-4">

      <!-- ===================================================
           DESCRIPCIÓN DEL PLAN DE BATALLA (MISMAS DIVISIONES)
           + 2 PESTAÑAS: esta semana / siguiente semana
           =================================================== -->
      <h5 class="fw-bold mb-2 section-title">DESCRIPCIÓN DEL PLAN DE BATALLA</h5>
      <!-- ===================================================
           DESCRIPCIÓN DEL PLAN DE BATALLA (MISMAS DIVISIONES)
           + 2 PESTAÑAS: esta semana / siguiente semana
           =================================================== -->
      <h5 class="fw-bold mb-2 section-title">DESCRIPCIÓN DEL PLAN DE BATALLA</h5>

      <small class="text-muted d-block mb-3">
        Visualización por semana (Jueves → Miércoles). Esta semana: <b><?= esc($weekLabel($semana)) ?></b> |
        Siguiente semana: <b><?= esc($weekLabel($semanaSiguiente)) ?></b>
      </small>

      <div class="week-toolbar mb-3">
        <div class="left">
          <input
            type="text"
            id="taskSearch"
            class="form-control form-control-sm"
            style="min-width:240px;"
            placeholder="Buscar en tareas (título o descripción)"
          >

          <select id="taskFilter" class="form-select form-select-sm" style="min-width:200px;">
            <option value="all">Todas</option>
            <option value="done">Solo realizadas</option>
            <option value="pending">Solo pendientes</option>
          </select>
        </div>

        <div>
          <span class="badge bg-dark">Realizada</span>
          <span class="badge bg-secondary ms-1">Pendiente</span>
        </div>
      </div>

      <!-- ===================================================
           TABS (COMPAT BS4 + BS5)
           - Incluimos data-toggle (BS4) + data-bs-toggle (BS5)
           - Incluimos data-target (BS4) + data-bs-target (BS5)
           - Como están dentro de un FORM, type="button" evita submit
           =================================================== -->
      <ul class="nav nav-tabs" id="weekTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link active"
            id="tab-actual"
            type="button"
            role="tab"
            aria-controls="pane-actual"
            aria-selected="true"

            data-bs-toggle="tab"
            data-bs-target="#pane-actual"

            data-toggle="tab"
            data-target="#pane-actual"
          >
            Actividades de esta semana
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            id="tab-sig"
            type="button"
            role="tab"
            aria-controls="pane-sig"
            aria-selected="false"

            data-bs-toggle="tab"
            data-bs-target="#pane-sig"

            data-toggle="tab"
            data-target="#pane-sig"
          >
            Actividades de la siguiente semana
          </button>
        </li>
      </ul>

      <div class="tab-content mt-3" id="weekTabsContent">

        <!-- ===================================================
             TAB: ESTA SEMANA (tus arrays actuales)
             =================================================== -->
        <div class="tab-pane fade show active" id="pane-actual" role="tabpanel" aria-labelledby="tab-actual">
      <small class="text-muted d-block mb-3">
        Visualización por semana (Jueves → Miércoles). Esta semana: <b><?= esc($weekLabel($semana)) ?></b> |
        Siguiente semana: <b><?= esc($weekLabel($semanaSiguiente)) ?></b>
      </small>

      <div class="week-toolbar mb-3">
        <div class="left">
          <input
            type="text"
            id="taskSearch"
            class="form-control form-control-sm"
            style="min-width:240px;"
            placeholder="Buscar en tareas (título o descripción)">

          <select id="taskFilter" class="form-select form-select-sm" style="min-width:200px;">
            <option value="all">Todas</option>
            <option value="done">Solo realizadas</option>
            <option value="pending">Solo pendientes</option>
          </select>
        </div>

        <div>
          <span class="badge bg-dark">Realizada</span>
          <span class="badge bg-secondary ms-1">Pendiente</span>
        </div>
      </div>

      <!-- ===================================================
           TABS (COMPAT BS4 + BS5)
           - Incluimos data-toggle (BS4) + data-bs-toggle (BS5)
           - Incluimos data-target (BS4) + data-bs-target (BS5)
           - Como están dentro de un FORM, type="button" evita submit
           =================================================== -->
      <ul class="nav nav-tabs" id="weekTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link active"
            id="tab-actual"
            type="button"
            role="tab"
            aria-controls="pane-actual"
            aria-selected="true"

            data-bs-toggle="tab"
            data-bs-target="#pane-actual"

            data-toggle="tab"
            data-target="#pane-actual">
            Actividades de esta semana
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            id="tab-sig"
            type="button"
            role="tab"
            aria-controls="pane-sig"
            aria-selected="false"

            data-bs-toggle="tab"
            data-bs-target="#pane-sig"

            data-toggle="tab"
            data-target="#pane-sig">
            Actividades de la siguiente semana
          </button>
        </li>
      </ul>

      <div class="tab-content mt-3" id="weekTabsContent">

        <!-- ===================================================
             TAB: ESTA SEMANA (tus arrays actuales)
             =================================================== -->
        <div class="tab-pane fade show active" id="pane-actual" role="tabpanel" aria-labelledby="tab-actual">

          <!-- ACTIVIDADES URGENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES URGENTES</div>

            <?php if (!empty($urgentesThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($urgentesThis as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';

                    // Texto para búsqueda (minúsculas)
                    $text = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay actividades urgentes esta semana.</div>
            <?php endif; ?>
          </div>
          <!-- ACTIVIDADES URGENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES URGENTES</div>

            <?php if (!empty($urgentesThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($urgentesThis as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';

                  // Texto para búsqueda (minúsculas)
                  $text = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay actividades urgentes esta semana.</div>
            <?php endif; ?>
          </div>

          <!-- ACTIVIDADES PENDIENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES PENDIENTES</div>

            <?php if (!empty($pendientesThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($pendientesThis as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-secondary ms-1">PENDIENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay pendientes.</div>
            <?php endif; ?>
          </div>

          <!-- ÓRDENES PARA MI -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ÓRDENES QUE DEBO CUMPLIR</div>

            <?php if (!empty($ordenesMiasThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($ordenesMiasThis as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">ASIGNADA</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No tengo órdenes asignadas.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <!-- ÓRDENES JUNIORS -->
            <div class="card border-dark shadow-sm p-3 mb-3">
              <div class="section-title--invert">ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</div>

              <?php if (!empty($ordenesJuniorsThis)): ?>
                <ul class="list-group list-group-flush mt-2">
                  <?php foreach ($ordenesJuniorsThis as $t): ?>
                    <?php
                      $title  = (string)($t['titulo'] ?? '');
                      $desc   = (string)($t['descripcion'] ?? '');
                      $status = $isDone($t) ? 'done' : 'pending';
                      $text   = $lower($title . ' ' . $desc);
                    ?>
                    <li class="list-group-item task-filter-item"
                        data-status="<?= esc($status) ?>"
                        data-text="<?= esc($text) ?>">
                      <div class="task-row">
                        <div class="task-main">
                          <div class="fw-bold"><?= esc($title) ?></div>
                          <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                        </div>

                        <div class="task-meta small">
                          <div class="text-nowrap">
                            <small class="text-muted">Inicio</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                          </div>
                          <div class="text-nowrap">
                            <small class="text-muted">Fin</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                          </div>
                        </div>
          <!-- ACTIVIDADES PENDIENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES PENDIENTES</div>

            <?php if (!empty($pendientesThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($pendientesThis as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';
                  $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-secondary ms-1">PENDIENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay pendientes.</div>
            <?php endif; ?>
          </div>

          <!-- ÓRDENES PARA MI -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ÓRDENES QUE DEBO CUMPLIR</div>

            <?php if (!empty($ordenesMiasThis)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($ordenesMiasThis as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';
                  $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">ASIGNADA</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No tengo órdenes asignadas.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <!-- ÓRDENES JUNIORS -->
            <div class="card border-dark shadow-sm p-3 mb-3">
              <div class="section-title--invert">ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</div>

              <?php if (!empty($ordenesJuniorsThis)): ?>
                <ul class="list-group list-group-flush mt-2">
                  <?php foreach ($ordenesJuniorsThis as $t): ?>
                    <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                    ?>
                    <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                      <div class="task-row">
                        <div class="task-main">
                          <div class="fw-bold"><?= esc($title) ?></div>
                          <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                        </div>

                        <div class="task-meta small">
                          <div class="text-nowrap">
                            <small class="text-muted">Inicio</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                          </div>
                          <div class="text-nowrap">
                            <small class="text-muted">Fin</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                          </div>
                        </div>

                        <div class="text-nowrap">
                          <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                          <span class="badge bg-secondary ms-1">JUNIOR</span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-muted mt-2">No hay tareas asignadas a juniors.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>

        <!-- ===================================================
             TAB: SIGUIENTE SEMANA
             - usa arrays del controller si existen
             - o deriva (mis tareas) desde $actSemanaSiguiente
             =================================================== -->
        <div class="tab-pane fade" id="pane-sig" role="tabpanel" aria-labelledby="tab-sig">

          <!-- ACTIVIDADES URGENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES URGENTES</div>

            <?php if (!empty($urgentesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($urgentesSiguiente as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>
                        <div class="text-nowrap">
                          <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                          <span class="badge bg-secondary ms-1">JUNIOR</span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-muted mt-2">No hay tareas asignadas a juniors.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>

        <!-- ===================================================
             TAB: SIGUIENTE SEMANA
             - usa arrays del controller si existen
             - o deriva (mis tareas) desde $actSemanaSiguiente
             =================================================== -->
        <div class="tab-pane fade" id="pane-sig" role="tabpanel" aria-labelledby="tab-sig">

          <!-- ACTIVIDADES URGENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES URGENTES</div>

            <?php if (!empty($urgentesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($urgentesSiguiente as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';
                  $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay actividades urgentes para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <!-- ACTIVIDADES PENDIENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES PENDIENTES</div>

            <?php if (!empty($pendientesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($pendientesSiguiente as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>
                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay actividades urgentes para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <!-- ACTIVIDADES PENDIENTES -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ACTIVIDADES PENDIENTES</div>

            <?php if (!empty($pendientesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($pendientesSiguiente as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';
                  $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-secondary ms-1">PENDIENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay pendientes para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <!-- ÓRDENES PARA MI -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ÓRDENES QUE DEBO CUMPLIR</div>

            <?php if (!empty($ordenesMiasSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($ordenesMiasSiguiente as $t): ?>
                  <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>
                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-secondary ms-1">PENDIENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No hay pendientes para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <!-- ÓRDENES PARA MI -->
          <div class="card border-dark shadow-sm p-3 mb-3">
            <div class="section-title--invert">ÓRDENES QUE DEBO CUMPLIR</div>

            <?php if (!empty($ordenesMiasSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($ordenesMiasSiguiente as $t): ?>
                  <?php
                  $title  = (string)($t['titulo'] ?? '');
                  $desc   = (string)($t['descripcion'] ?? '');
                  $status = $isDone($t) ? 'done' : 'pending';
                  $text   = $lower($title . ' ' . $desc);
                  ?>
                  <li class="list-group-item task-filter-item"
                    data-status="<?= esc($status) ?>"
                    data-text="<?= esc($text) ?>">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="fw-bold"><?= esc($title) ?></div>
                        <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                      </div>

                      <div class="task-meta small">
                        <div class="text-nowrap">
                          <small class="text-muted">Inicio</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                        </div>
                        <div class="text-nowrap">
                          <small class="text-muted">Fin</small>
                          <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                        </div>
                      </div>

                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">ASIGNADA</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No tengo órdenes asignadas para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <!-- ÓRDENES JUNIORS -->
            <div class="card border-dark shadow-sm p-3 mb-3">
              <div class="section-title--invert">ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</div>

              <?php if (!empty($ordenesJuniorsSiguiente)): ?>
                <ul class="list-group list-group-flush mt-2">
                  <?php foreach ($ordenesJuniorsSiguiente as $t): ?>
                    <?php
                      $title  = (string)($t['titulo'] ?? '');
                      $desc   = (string)($t['descripcion'] ?? '');
                      $status = $isDone($t) ? 'done' : 'pending';
                      $text   = $lower($title . ' ' . $desc);
                    ?>
                    <li class="list-group-item task-filter-item"
                        data-status="<?= esc($status) ?>"
                        data-text="<?= esc($text) ?>">
                      <div class="task-row">
                        <div class="task-main">
                          <div class="fw-bold"><?= esc($title) ?></div>
                          <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                        </div>

                        <div class="task-meta small">
                          <div class="text-nowrap">
                            <small class="text-muted">Inicio</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                          </div>
                          <div class="text-nowrap">
                            <small class="text-muted">Fin</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                          </div>
                        </div>
                      <div class="text-nowrap">
                        <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                        <span class="badge bg-dark ms-1">ASIGNADA</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted mt-2">No tengo órdenes asignadas para la siguiente semana.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <!-- ÓRDENES JUNIORS -->
            <div class="card border-dark shadow-sm p-3 mb-3">
              <div class="section-title--invert">ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</div>

              <?php if (!empty($ordenesJuniorsSiguiente)): ?>
                <ul class="list-group list-group-flush mt-2">
                  <?php foreach ($ordenesJuniorsSiguiente as $t): ?>
                    <?php
                    $title  = (string)($t['titulo'] ?? '');
                    $desc   = (string)($t['descripcion'] ?? '');
                    $status = $isDone($t) ? 'done' : 'pending';
                    $text   = $lower($title . ' ' . $desc);
                    ?>
                    <li class="list-group-item task-filter-item"
                      data-status="<?= esc($status) ?>"
                      data-text="<?= esc($text) ?>">
                      <div class="task-row">
                        <div class="task-main">
                          <div class="fw-bold"><?= esc($title) ?></div>
                          <div class="small text-muted"><?= esc($desc !== '' ? $desc : 'Sin descripción') ?></div>
                        </div>

                        <div class="task-meta small">
                          <div class="text-nowrap">
                            <small class="text-muted">Inicio</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_inicio'] ?? '')) ?></div>
                          </div>
                          <div class="text-nowrap">
                            <small class="text-muted">Fin</small>
                            <div class="fw-semibold"><?= esc($fmtDate($t['fecha_fin'] ?? '')) ?></div>
                          </div>
                        </div>

                        <div class="text-nowrap">
                          <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                          <span class="badge bg-secondary ms-1">JUNIOR</span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-muted mt-2">
                  No hay tareas asignadas a juniors para la siguiente semana.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>

      </div>

      <!-- ===================== CUOTAS ===================== -->
      <div class="card shadow-sm p-3 mb-3 mt-4">
        <label class="fw-bold">CUOTAS PARA LA SEMANA</label>
        <textarea class="form-control" name="cuota_descripcion" rows="3"><?= esc($cuotaDesc) ?></textarea>
      </div>

      <!-- =========== OBJETIVO ESTRATÉGICO =========== -->
      <div class="card shadow-sm p-3 mb-3">
        <label class="fw-bold text-dark">OBJETIVOS QUE CONTRIBUYEN AL PLAN ESTRATÉGICO</label>
        <textarea class="form-control" name="objetivo_estrategico" rows="3"><?= esc($objetivoDesc) ?></textarea>
      </div>
                        <div class="text-nowrap">
                          <span class="badge <?= esc($getEstadoBadge($t)) ?>"><?= esc($getEstadoLabel($t)) ?></span>
                          <span class="badge bg-secondary ms-1">JUNIOR</span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="text-muted mt-2">
                  No hay tareas asignadas a juniors para la siguiente semana.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>

      </div>

      <!-- ===================== CUOTAS ===================== -->
      <div class="card shadow-sm p-3 mb-3 mt-4">
        <label class="fw-bold">CUOTAS PARA LA SEMANA</label>
        <textarea class="form-control" name="cuota_descripcion" rows="3"><?= esc($cuotaDesc) ?></textarea>
      </div>

      <!-- =========== OBJETIVO ESTRATÉGICO =========== -->
      <div class="card shadow-sm p-3 mb-3">
        <label class="fw-bold text-dark">OBJETIVOS QUE CONTRIBUYEN AL PLAN ESTRATÉGICO</label>
        <textarea class="form-control" name="objetivo_estrategico" rows="3"><?= esc($objetivoDesc) ?></textarea>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save me-1"></i> Guardar Plan
        </button>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save me-1"></i> Guardar Plan
        </button>
      </div>

    </form>
  <?php endif; ?>

    </form>
  <?php endif; ?>

</div>

<script>
/* =========================================================
   Preguntas por condición (TU lógica intacta)
========================================================= */
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
  /* =========================================================
   Preguntas por condición (TU lógica intacta)
========================================================= */
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
  if (!wrap) return;

  const preguntas = preguntasPorCondicion[condicion] || [];
  if (!preguntas.length) {
    wrap.innerHTML = '<div class="text-muted">Selecciona una condición para ver las preguntas.</div>';
    return;
  }
  function renderPreguntas(condicion) {
    if (!wrap) return;

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

if (condicionEl) {
  condicionEl.addEventListener('change', e => renderPreguntas(e.target.value));
  if (condicionEl.value) renderPreguntas(condicionEl.value);
}

/* =========================================================
   Filtro + búsqueda en el TAB activo (solo visual)
========================================================= */
const searchEl = document.getElementById('taskSearch');
const filterEl = document.getElementById('taskFilter');

function getActivePane() {
  // ✅ en BS: pane activo tiene .active (y normalmente .show)
  return document.querySelector('#weekTabsContent .tab-pane.active');
}

function applyTaskFilter() {
  const pane = getActivePane();
  if (!pane) return;

  const q = (searchEl ? searchEl.value.trim().toLowerCase() : '');
  const f = (filterEl ? filterEl.value : 'all');

  const items = pane.querySelectorAll('.task-filter-item');

  items.forEach(item => {
    const text = (item.getAttribute('data-text') || '');
    const status = (item.getAttribute('data-status') || 'pending');

    const matchText = !q || text.includes(q);
    const matchStatus = (f === 'all') || (f === status);

    item.style.display = (matchText && matchStatus) ? '' : 'none';
  });
}

if (searchEl) searchEl.addEventListener('input', applyTaskFilter);
if (filterEl) filterEl.addEventListener('change', applyTaskFilter);

/* =========================================================
   ✅ FIX CRÍTICO: Tabs deben funcionar sí o sí
   - Si Bootstrap 5 está bien cargado: window.bootstrap.Tab existe
   - Si no existe (BS4 mal, o sin JS): fallback manual
========================================================= */
(function initWeekTabsFallback(){
  const tabsWrap = document.getElementById('weekTabs');
  const content  = document.getElementById('weekTabsContent');
  if (!tabsWrap || !content) return;

  const hasBS5 = !!(window.bootstrap && window.bootstrap.Tab);

  const buttons = tabsWrap.querySelectorAll('button[role="tab"]');

  buttons.forEach(btn => {
    btn.addEventListener('click', function(e){
      // ✅ Si BS5 existe, Bootstrap hará el toggle.
      // (En BS4 puede funcionar por data-toggle; si no, el fallback se ejecuta)
      if (hasBS5) return;

      e.preventDefault();

      // 1) Activar botón
      buttons.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      // 2) Activar panel objetivo
      const target =
        btn.getAttribute('data-bs-target') ||
        btn.getAttribute('data-target') ||
        '';

      if (!target) return;

      const panes = content.querySelectorAll('.tab-pane');
      panes.forEach(p => p.classList.remove('active', 'show'));

      const pane = document.querySelector(target);
      if (!pane) return;

      pane.classList.add('active', 'show');

      // ✅ refresca filtro al cambiar pestaña
      applyTaskFilter();
    });
  });
})();

/* =========================================================
   En Bootstrap, cuando sí existe el evento shown.bs.tab,
   re-aplicamos el filtro al cambiar de pestaña.
   (No pasa nada si el evento nunca dispara)
========================================================= */
document.querySelectorAll('#weekTabs button[role="tab"]').forEach(btn => {
  btn.addEventListener('shown.bs.tab', applyTaskFilter);
});

/* Aplica una vez al cargar */
applyTaskFilter();

/* =========================================================
   Countdown (solo si existe)
========================================================= */
(function initCountdown(){
  const countdownEl = document.getElementById('countdown');
  if (!countdownEl) return;

  const targetRaw = "<?= esc($proximoMiercoles ?? '') ?>";
  if (!targetRaw) {
    countdownEl.textContent = "N/D";
    return;
  }

  const targetDate = new Date(targetRaw.replace(' ', 'T')).getTime();
  if (!targetDate) {
    countdownEl.textContent = "N/D";
    return;
  }

  function updateCountdown() {
    const now = Date.now();
    const distance = targetDate - now;

    if (distance <= 0) {
      countdownEl.textContent = "Disponible ahora";
      return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

    countdownEl.textContent = days + "d " + hours + "h " + minutes + "m";
  }

  updateCountdown();
  setInterval(updateCountdown, 60000);
})();
  if (condicionEl) {
    condicionEl.addEventListener('change', e => renderPreguntas(e.target.value));
    if (condicionEl.value) renderPreguntas(condicionEl.value);
  }

  /* =========================================================
     Filtro + búsqueda en el TAB activo (solo visual)
  ========================================================= */
  const searchEl = document.getElementById('taskSearch');
  const filterEl = document.getElementById('taskFilter');

  function getActivePane() {
    // ✅ en BS: pane activo tiene .active (y normalmente .show)
    return document.querySelector('#weekTabsContent .tab-pane.active');
  }

  function applyTaskFilter() {
    const pane = getActivePane();
    if (!pane) return;

    const q = (searchEl ? searchEl.value.trim().toLowerCase() : '');
    const f = (filterEl ? filterEl.value : 'all');

    const items = pane.querySelectorAll('.task-filter-item');

    items.forEach(item => {
      const text = (item.getAttribute('data-text') || '');
      const status = (item.getAttribute('data-status') || 'pending');

      const matchText = !q || text.includes(q);
      const matchStatus = (f === 'all') || (f === status);

      item.style.display = (matchText && matchStatus) ? '' : 'none';
    });
  }

  if (searchEl) searchEl.addEventListener('input', applyTaskFilter);
  if (filterEl) filterEl.addEventListener('change', applyTaskFilter);

  /* =========================================================
     ✅ FIX CRÍTICO: Tabs deben funcionar sí o sí
     - Si Bootstrap 5 está bien cargado: window.bootstrap.Tab existe
     - Si no existe (BS4 mal, o sin JS): fallback manual
  ========================================================= */
  (function initWeekTabsFallback() {
    const tabsWrap = document.getElementById('weekTabs');
    const content = document.getElementById('weekTabsContent');
    if (!tabsWrap || !content) return;

    const hasBS5 = !!(window.bootstrap && window.bootstrap.Tab);

    const buttons = tabsWrap.querySelectorAll('button[role="tab"]');

    buttons.forEach(btn => {
      btn.addEventListener('click', function(e) {
        // ✅ Si BS5 existe, Bootstrap hará el toggle.
        // (En BS4 puede funcionar por data-toggle; si no, el fallback se ejecuta)
        if (hasBS5) return;

        e.preventDefault();

        // 1) Activar botón
        buttons.forEach(b => {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        // 2) Activar panel objetivo
        const target =
          btn.getAttribute('data-bs-target') ||
          btn.getAttribute('data-target') ||
          '';

        if (!target) return;

        const panes = content.querySelectorAll('.tab-pane');
        panes.forEach(p => p.classList.remove('active', 'show'));

        const pane = document.querySelector(target);
        if (!pane) return;

        pane.classList.add('active', 'show');

        // ✅ refresca filtro al cambiar pestaña
        applyTaskFilter();
      });
    });
  })();

  /* =========================================================
     En Bootstrap, cuando sí existe el evento shown.bs.tab,
     re-aplicamos el filtro al cambiar de pestaña.
     (No pasa nada si el evento nunca dispara)
  ========================================================= */
  document.querySelectorAll('#weekTabs button[role="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', applyTaskFilter);
  });

  /* Aplica una vez al cargar */
  applyTaskFilter();

  /* =========================================================
     Countdown (solo si existe)
  ========================================================= */
  (function initCountdown() {
    const countdownEl = document.getElementById('countdown');
    if (!countdownEl) return;

    const targetRaw = "<?= esc($proximoMiercoles ?? '') ?>";
    if (!targetRaw) {
      countdownEl.textContent = "N/D";
      return;
    }

    const targetDate = new Date(targetRaw.replace(' ', 'T')).getTime();
    if (!targetDate) {
      countdownEl.textContent = "N/D";
      return;
    }

    function updateCountdown() {
      const now = Date.now();
      const distance = targetDate - now;

      if (distance <= 0) {
        countdownEl.textContent = "Disponible ahora";
        return;
      }

      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

      countdownEl.textContent = days + "d " + hours + "h " + minutes + "m";
    }

    updateCountdown();
    setInterval(updateCountdown, 60000);
  })();
</script>

<?= $this->endSection() ?>