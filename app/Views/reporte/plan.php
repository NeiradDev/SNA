<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: reporte/plan.php
 * =========================================================
 * ✅ FIX: evita "Undefined array key porcentaje"
 * ✅ Soporta que $satisfaccion venga como:
 *    - array directo (porcentaje, realizadas, no_realizadas...)
 *    - o como resumen con cards[] (getSatisfaccionResumen)
 *
 * ✅ FIX SEMANA:
 * - Si NO llega $semana / $semanaSiguiente correctamente desde el controller,
 *   esta vista las calcula aquí mismo en JUEVES → MIÉRCOLES (America/Guayaquil)
 *
 * ✅ NUEVO:
 * - Paginación visual por bloque de actividades
 * - Búsqueda y filtro por estado
 * - Tabs semana pasada / semana actual
 * - Animaciones y mejoras visuales
 * - Tema completo usando SOLO la paleta:
 *   #F20505 #F22E2E #F27272 #F2F2F2 #0D0D0D
 * =========================================================
 */

$old    = $old    ?? [];
$extras = $extras ?? [];

$urgentes       = $urgentes       ?? [];
$pendientes     = $pendientes     ?? [];
$ordenesMias    = $ordenesMias    ?? ($ordenesMi ?? []);
$ordenesJuniors = $ordenesJuniors ?? [];
$tieneJuniors   = $tieneJuniors   ?? ($hasJuniors ?? false);

$semana          = $semana          ?? null;
$semanaSiguiente = $semanaSiguiente ?? null;

$planCompletado   = $planCompletado   ?? false;
$proximoMiercoles = $proximoMiercoles ?? '';

$idUser = (int) (session()->get('id_user') ?? 0);

$cuotaDesc    = (string)($cuota['descripcion'] ?? ($extras['cuota']['descripcion'] ?? ''));
$objetivoDesc = (string)($objetivo['descripcion'] ?? ($extras['objetivo']['descripcion'] ?? ''));

$nombres     = (string)($perfil['nombres'] ?? '');
$apellidos   = (string)($perfil['apellidos'] ?? '');
$areaNombre  = trim((string)($perfil['nombre_area'] ?? ''));
$cargoNombre = trim((string)($perfil['nombre_cargo'] ?? ''));
$jefeNombre  = trim((string)($perfil['supervisor_nombre'] ?? ''));

$areaNombre  = $areaNombre  !== '' ? $areaNombre  : 'N/D';
$cargoNombre = $cargoNombre !== '' ? $cargoNombre : 'N/D';
$jefeNombre  = $jefeNombre  !== '' ? $jefeNombre  : 'N/D';

/* =========================================================
   ADAPTADOR de satisfacción
========================================================= */
$cardPrincipal = [];
if (is_array($satisfaccion ?? null) && !empty($satisfaccion['cards']) && is_array($satisfaccion['cards'])) {
  $cardPrincipal = $satisfaccion['cards'][0] ?? [];
}

$satisfaccion = array_merge(
  [
    'titulo'        => 'Mi porcentaje de satisfacción',
    'porcentaje'    => 0,
    'realizadas'    => 0,
    'no_realizadas' => 0,
    'inicio'        => '',
    'fin'           => ''
  ],
  (is_array($cardPrincipal) && !empty($cardPrincipal))
    ? $cardPrincipal
    : (is_array($satisfaccion ?? null) ? $satisfaccion : [])
);

$satisfaccionPct = (float) ($satisfaccion['porcentaje'] ?? 0);

/* -----------------------------------------
 * Helpers
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

/* =========================================================
   FIX SEMANA (JUEVES → MIÉRCOLES)
========================================================= */
$isValidWeek = function ($week): bool {
  return is_array($week)
    && array_key_exists('inicio', $week)
    && array_key_exists('fin', $week)
    && !empty($week['inicio'])
    && !empty($week['fin']);
};

$calcBusinessWeek = function (?array $baseWeek = null): array {
  $tz = new DateTimeZone('America/Guayaquil');
  $now = new DateTimeImmutable('now', $tz);

  $isThursday = ((int)$now->format('N') === 4);
  $isBeforeNoon = ((int)$now->format('H') < 12);
  $anchor = ($isThursday && $isBeforeNoon) ? $now->modify('-1 day') : $now;

  $start = $anchor->modify('thursday this week')->setTime(0, 0, 0);
  $end   = $start->modify('wednesday next week')->setTime(23, 59, 59);

  if (
    is_array($baseWeek) && isset($baseWeek['inicio'], $baseWeek['fin'])
    && $baseWeek['inicio'] instanceof DateTimeImmutable
    && $baseWeek['fin'] instanceof DateTimeImmutable
  ) {
    $start = $baseWeek['inicio'];
    $end   = $baseWeek['fin'];
  }

  return [
    'inicio' => $start,
    'fin'    => $end,
    'semana' => $start->format('Y-m-d') . ' → ' . $end->format('Y-m-d'),
    'start'  => $start,
    'end'    => $end,
  ];
};

$calcNextBusinessWeek = function (array $weekActual): array {
  $tz = new \DateTimeZone('America/Guayaquil');

  $inicio = $weekActual['inicio'] instanceof \DateTimeInterface
    ? new \DateTime($weekActual['inicio']->format('Y-m-d H:i:s'), $tz)
    : new \DateTime((string)$weekActual['inicio'], $tz);

  $fin = $weekActual['fin'] instanceof \DateTimeInterface
    ? new \DateTime($weekActual['fin']->format('Y-m-d H:i:s'), $tz)
    : new \DateTime((string)$weekActual['fin'], $tz);

  $inicio->modify('+7 days');
  $fin->modify('+7 days');

  $inicio->setTime(0, 0, 0);
  $fin->setTime(23, 59, 59);

  return ['inicio' => $inicio, 'fin' => $fin];
};

if (!$isValidWeek($semana)) {
  $semana = $calcBusinessWeek(null);
}

if (!$isValidWeek($semanaSiguiente)) {
  $semanaSiguiente = $calcNextBusinessWeek($semana);
}

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
  if ($id === 3 || !empty($t['completed_at'])) return 'bg-dark';
  return 'bg-secondary';
};

$isDone = function (array $t): bool {
  $id = (int)($t['id_estado_tarea'] ?? 0);
  return ($id === 3) || !empty($t['completed_at']);
};

$lower = function (string $txt): string {
  if (function_exists('mb_strtolower')) return mb_strtolower($txt, 'UTF-8');
  return strtolower($txt);
};

/* ============================================================
   Condición automática según satisfacción
============================================================ */
$oldCond = $old['condicion'] ?? '';
$condicionAuto = '';
$porcentaje = null;
$p = 0.0;

if (!empty($satisfaccion)) {
  $porcentajeRaw = $satisfaccion['porcentaje'] ?? null;

  if (is_numeric($porcentajeRaw)) {
    $porcentaje = (float) $porcentajeRaw;
  }

  if ($porcentaje === null) {
    $realizadas   = (int)($satisfaccion['realizadas'] ?? 0);
    $noRealizadas = (int)($satisfaccion['no_realizadas'] ?? 0);
    $total        = $realizadas + $noRealizadas;
    $porcentaje   = $total > 0 ? round(($realizadas / $total) * 100, 2) : 0.0;
  }

  if ($porcentaje >= 0 && $porcentaje <= 1) $porcentaje *= 100;

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

/* Arrays por semana */
$urgentesThis       = $urgentes;
$pendientesThis     = $pendientes;
$ordenesMiasThis    = $ordenesMias;
$ordenesJuniorsThis = $ordenesJuniors;

$urgentesSiguiente       = $urgentesSiguiente       ?? [];
$pendientesSiguiente     = $pendientesSiguiente     ?? [];
$ordenesMiasSiguiente    = $ordenesMiasSiguiente    ?? [];
$ordenesJuniorsSiguiente = $ordenesJuniorsSiguiente ?? [];

$satIni = trim((string)($satisfaccion['inicio'] ?? ''));
$satFin = trim((string)($satisfaccion['fin'] ?? ''));
?>

<style>
  :root{
    --ws-red-strong: #F20505;
    --ws-red-main: #F22E2E;
    --ws-red-soft: #F27272;
    --ws-light: #F2F2F2;
    --ws-dark: #0D0D0D;
  }

  .plan-page-wrap {
    animation: wsFadeInPage .45s ease;
  }

  @keyframes wsFadeInPage {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .section-title {
    font-weight: 900;
    letter-spacing: .4px;
    color: var(--ws-dark);
  }

  .plan-hero-card,
  .plan-card,
  .plan-form-shell {
    border: 1px solid rgba(13,13,13,.10);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(242,242,242,.98) 0%, rgba(255,255,255,.98) 100%);
    box-shadow: 0 14px 34px -24px rgba(13,13,13,.20);
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
  }

  .plan-card:hover,
  .plan-hero-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 36px -24px rgba(13,13,13,.24);
    border-color: rgba(242,46,46,.22);
  }

  .plan-hero-card {
    overflow: hidden;
    position: relative;
  }

  .plan-hero-card::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
      radial-gradient(circle at top right, rgba(242,46,46,.10), transparent 35%),
      radial-gradient(circle at bottom left, rgba(242,114,114,.12), transparent 35%);
    pointer-events: none;
  }

  .plan-hero-content {
    position: relative;
    z-index: 1;
  }

  .plan-form-shell {
    padding: 1rem;
  }

  .section-title--invert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: linear-gradient(135deg, #ffffff 0%, var(--ws-light) 100%);
    color: var(--ws-dark);
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(13,13,13,.12);
    font-weight: 900;
    letter-spacing: .3px;
    text-transform: uppercase;
  }

  .section-title--invert small {
    font-weight: 700;
    opacity: .75;
    text-transform: none;
  }

  .plan-danger-soft{
    background: linear-gradient(180deg, rgba(242,114,114,.10) 0%, rgba(242,242,242,.98) 100%);
    border: 1px solid rgba(242,46,46,.22);
    color: var(--ws-dark);
    box-shadow: 0 14px 32px -24px rgba(242,46,46,.18);
  }

  .plan-danger-soft:hover{
    border-color: rgba(242,5,5,.28);
    box-shadow: 0 18px 38px -24px rgba(242,46,46,.22);
  }

  .plan-danger-soft .section-title--invert{
    background: linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    color: #fff;
    border-color: rgba(13,13,13,.08);
  }

  .plan-danger-soft .section-title--invert small{
    color: rgba(242,242,242,.92);
    opacity: 1;
  }

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

  .task-main .fw-bold {
    color: var(--ws-dark);
    transition: color .2s ease;
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

  .week-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding: 14px;
    border: 1px solid rgba(13,13,13,.10);
    background: linear-gradient(135deg, rgba(242,242,242,.98) 0%, rgba(255,255,255,.98) 100%);
    border-radius: 16px;
    box-shadow: 0 10px 26px -24px rgba(13,13,13,.28);
  }

  .week-toolbar .left {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .week-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    background: rgba(242,46,46,.10);
    border: 1px solid rgba(242,46,46,.20);
    color: var(--ws-dark);
    border-radius: 999px;
    font-size: .86rem;
    font-weight: 800;
  }

  .task-filter-item {
    border: 1px solid rgba(13,13,13,.08) !important;
    border-radius: 14px !important;
    margin-bottom: 10px;
    background: linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.92) 100%);
    box-shadow: 0 8px 18px -20px rgba(13,13,13,.35);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, opacity .22s ease;
    animation: taskItemIn .35s ease;
  }

  .task-filter-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px -24px rgba(13,13,13,.25);
    border-color: rgba(242,46,46,.22) !important;
  }

  .plan-danger-soft .task-filter-item{
    background: linear-gradient(180deg, #ffffff 0%, rgba(242,114,114,.08) 100%);
    border-color: rgba(242,46,46,.12) !important;
  }

  .plan-danger-soft .task-filter-item:hover{
    border-color: rgba(242,5,5,.24) !important;
    box-shadow: 0 18px 34px -24px rgba(242,46,46,.20);
  }

  .task-filter-item:hover .task-main .fw-bold {
    color: var(--ws-red-main);
  }

  .plan-danger-soft .task-filter-item:hover .task-main .fw-bold{
    color: var(--ws-red-strong);
  }

  @keyframes taskItemIn {
    from {
      opacity: 0;
      transform: translateY(8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .task-filter-item.is-hidden-by-page {
    display: none !important;
  }

  .week-info-banner {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(242,46,46,.08) 0%, rgba(242,114,114,.12) 100%);
    border: 1px solid rgba(242,46,46,.14);
    color: var(--ws-dark);
  }

  .week-info-banner b {
    color: var(--ws-red-strong);
  }

  .nav-tabs.plan-tabs {
    border-bottom: 0;
    gap: 10px;
    display: flex;
    flex-wrap: wrap;
  }

  .nav-tabs.plan-tabs .nav-link {
    border: 1px solid rgba(13,13,13,.12);
    border-radius: 14px;
    color: var(--ws-dark);
    font-weight: 800;
    background: linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
    padding: .8rem 1rem;
    transition: all .2s ease;
  }

  .nav-tabs.plan-tabs .nav-link:hover {
    transform: translateY(-1px);
    border-color: rgba(242,46,46,.28);
    color: var(--ws-red-main);
    box-shadow: 0 10px 24px -20px rgba(13,13,13,.25);
  }

  .nav-tabs.plan-tabs .nav-link.active {
    color: #fff;
    background: linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    border-color: transparent;
    box-shadow: 0 14px 28px -20px rgba(13,13,13,.34);
  }

  .task-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
    flex-wrap: wrap;
    padding-top: 6px;
  }

  .task-pagination-info {
    font-size: .9rem;
    color: var(--ws-dark);
    font-weight: 700;
  }

  .task-pagination-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .task-page-btn {
    border: 1px solid rgba(13,13,13,.14);
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
    color: var(--ws-dark);
    font-weight: 800;
    padding: .45rem .85rem;
    transition: all .2s ease;
  }

  .task-page-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    background: linear-gradient(180deg, rgba(242,114,114,.10) 0%, rgba(242,46,46,.14) 100%);
    border-color: rgba(242,46,46,.28);
    color: var(--ws-red-strong);
  }

  .task-page-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
  }

  .plan-mini-kpis {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .plan-mini-kpi {
    min-width: 120px;
    border-radius: 14px;
    padding: 12px 14px;
    background: linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    color: #fff;
    box-shadow: 0 16px 30px -22px rgba(13,13,13,.34);
    animation: wsFloatIn .45s ease;
  }

  .plan-mini-kpi strong {
    display: block;
    font-size: 1.1rem;
    line-height: 1.1;
  }

  .plan-mini-kpi small {
    display: block;
    opacity: .88;
    margin-top: 4px;
  }

  @keyframes wsFloatIn {
    from {
      opacity: 0;
      transform: translateY(10px) scale(.98);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  .plan-textarea-card {
    border: 1px solid rgba(13,13,13,.10);
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.95) 100%);
    box-shadow: 0 14px 34px -24px rgba(13,13,13,.18);
  }

  .plan-textarea-card textarea {
    border-radius: 12px;
    border-color: rgba(13,13,13,.12);
    transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
  }

  .plan-textarea-card textarea:focus,
  .plan-form-shell .form-control:focus,
  .plan-form-shell .form-select:focus {
    border-color: rgba(242,46,46,.40);
    box-shadow: 0 0 0 .2rem rgba(242,46,46,.12);
    transform: translateY(-1px);
  }

  .plan-save-btn {
    min-width: 180px;
    border-radius: 12px;
    font-weight: 800;
    letter-spacing: .2px;
    background: linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    border-color: var(--ws-dark);
    box-shadow: 0 14px 28px -18px rgba(13,13,13,.36);
    transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
  }

  .plan-save-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 32px -18px rgba(13,13,13,.42);
    opacity: .96;
  }

  .tab-pane {
    animation: wsFadeTab .28s ease;
  }

  @keyframes wsFadeTab {
    from {
      opacity: 0;
      transform: translateY(6px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .task-empty-box {
    border: 1px dashed rgba(13,13,13,.18);
    border-radius: 14px;
    padding: 16px;
    background: linear-gradient(180deg, rgba(255,255,255,.92) 0%, rgba(242,242,242,.92) 100%);
    color: var(--ws-dark);
  }

  .badge-urgente-custom{
    background: linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-main) 100%);
    color: #fff;
    border: 1px solid rgba(13,13,13,.08);
    box-shadow: 0 8px 18px -14px rgba(13,13,13,.26);
  }

  @media (max-width: 768px) {
    .plan-mini-kpis {
      width: 100%;
    }

    .plan-mini-kpi {
      flex: 1 1 calc(50% - 10px);
    }

    .task-row {
      align-items: flex-start;
    }

    .task-main {
      min-width: 100%;
    }
  }
</style>

<div class="container py-3 plan-page-wrap">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h3 class="mb-0 section-title">Plan de Batalla</h3>

    <div class="plan-mini-kpis">
      <div class="plan-mini-kpi">
        <strong><?= esc(number_format($satisfaccionPct, 2)) ?>%</strong>
        <small>Satisfacción</small>
      </div>
      <div class="plan-mini-kpi">
        <strong><?= (int)($satisfaccion['realizadas'] ?? 0) ?></strong>
        <small>Realizadas</small>
      </div>
      <div class="plan-mini-kpi">
        <strong><?= (int)($satisfaccion['no_realizadas'] ?? 0) ?></strong>
        <small>No realizadas</small>
      </div>
    </div>
  </div>

  <?php if (!empty($planCompletado)): ?>
    <div class="alert alert-success text-center mt-4 plan-hero-card">
      <div class="plan-hero-content">
        <h5 class="mb-2">Has completado tu Plan de Batalla semanal.</h5>
        <p>Se volverá a habilitar la próxima semana</p>
        <div class="fw-bold mt-2">
          Tiempo restante para habilitación:
          <span id="countdown"></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($satisfaccion)): ?>
    <div class="card shadow-sm mb-3 p-3 plan-hero-card">
      <div class="plan-hero-content d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <small class="text-muted"><?= esc($satisfaccion['titulo'] ?? 'Mi porcentaje de satisfacción') ?></small>
          <div class="fw-bold" style="font-size:1.65rem; color:#0D0D0D;">
            <?= esc(number_format($satisfaccionPct, 2)) ?>%
          </div>

          <?php if ($satIni !== '' && $satFin !== ''): ?>
            <small class="text-muted">Semana <?= esc($satIni) ?> → <?= esc($satFin) ?></small>
          <?php else: ?>
            <small class="text-muted">Semana <?= esc($weekLabel($semana)) ?> (Jueves → Miércoles)</small>
          <?php endif; ?>
        </div>

        <div class="text-end">
          <small class="d-block"><?= (int)($satisfaccion['realizadas'] ?? 0) ?> realizadas</small>
          <small class="d-block"><?= (int)($satisfaccion['no_realizadas'] ?? 0) ?> no realizadas</small>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($planCompletado)): ?>

    <form method="post" action="<?= site_url('reporte/plan') ?>" class="card shadow-sm plan-form-shell">
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

        <input type="hidden" name="satisfaccion" value="<?= esc($p ?? 0) ?>">
      </div>

      <hr class="my-3">
      <div id="preguntasWrap" class="mt-2"></div>
      <hr class="my-4">

      <h5 class="fw-bold mb-2 section-title">DESCRIPCIÓN DEL PLAN DE BATALLA</h5>

      <div class="week-info-banner mb-3">
        <span class="week-chip">Jueves → Miércoles</span>
        <span>Semana pasada: <b><?= esc($weekLabel($semana)) ?></b></span>
        <span>Semana actual: <b><?= esc($weekLabel($semanaSiguiente)) ?></b></span>
      </div>

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

        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-dark">Realizada</span>
          <span class="badge bg-secondary">Pendiente</span>
        </div>
      </div>

      <ul class="nav nav-tabs plan-tabs" id="weekTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-actual" type="button" role="tab"
            aria-controls="pane-actual" aria-selected="true"
            data-bs-toggle="tab" data-bs-target="#pane-actual"
            data-toggle="tab" data-target="#pane-actual">
            Actividades de la semana pasada
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-sig" type="button" role="tab"
            aria-controls="pane-sig" aria-selected="false"
            data-bs-toggle="tab" data-bs-target="#pane-sig"
            data-toggle="tab" data-target="#pane-sig">
            Actividades de esta semana
          </button>
        </li>
      </ul>

      <div class="tab-content mt-3" id="weekTabsContent">

        <!-- TAB SEMANA PASADA -->
        <div class="tab-pane fade show active" id="pane-actual" role="tabpanel" aria-labelledby="tab-actual">

          <div class="card shadow-sm p-3 mb-3 plan-card plan-danger-soft">
            <div class="section-title--invert">
              <span>ACTIVIDADES URGENTES</span>
              <small>Semana pasada</small>
            </div>

            <?php if (!empty($urgentesThis)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="pasada-urgentes">
                <?php foreach ($urgentesThis as $t): ?>
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
                        <span class="badge badge-urgente-custom ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
              <div class="task-pagination" data-page-nav="pasada-urgentes"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No hay actividades urgentes en la semana pasada.</div>
            <?php endif; ?>
          </div>

          <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
            <div class="section-title--invert">
              <span>ACTIVIDADES PENDIENTES</span>
              <small>Semana pasada</small>
            </div>

            <?php if (!empty($pendientesThis)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="pasada-pendientes">
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
              <div class="task-pagination" data-page-nav="pasada-pendientes"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No hay pendientes en la semana pasada.</div>
            <?php endif; ?>
          </div>

          <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
            <div class="section-title--invert">
              <span>ÓRDENES QUE DEBO CUMPLIR</span>
              <small>Semana pasada</small>
            </div>

            <?php if (!empty($ordenesMiasThis)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="pasada-mias">
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
              <div class="task-pagination" data-page-nav="pasada-mias"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No tengo órdenes asignadas en la semana pasada.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
              <div class="section-title--invert">
                <span>ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</span>
                <small>Semana pasada</small>
              </div>

              <?php if (!empty($ordenesJuniorsThis)): ?>
                <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="pasada-juniors">
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
                <div class="task-pagination" data-page-nav="pasada-juniors"></div>
              <?php else: ?>
                <div class="task-empty-box mt-2">No hay tareas asignadas a juniors en la semana pasada.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>

        <!-- TAB SEMANA ACTUAL -->
        <div class="tab-pane fade" id="pane-sig" role="tabpanel" aria-labelledby="tab-sig">

          <div class="card shadow-sm p-3 mb-3 plan-card plan-danger-soft">
            <div class="section-title--invert">
              <span>ACTIVIDADES URGENTES</span>
              <small>Semana actual</small>
            </div>

            <?php if (!empty($urgentesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="actual-urgentes">
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
                        <span class="badge badge-urgente-custom ms-1">URGENTE</span>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
              <div class="task-pagination" data-page-nav="actual-urgentes"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No hay actividades urgentes en la semana actual.</div>
            <?php endif; ?>
          </div>

          <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
            <div class="section-title--invert">
              <span>ACTIVIDADES PENDIENTES</span>
              <small>Semana actual</small>
            </div>

            <?php if (!empty($pendientesSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="actual-pendientes">
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
              <div class="task-pagination" data-page-nav="actual-pendientes"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No hay pendientes en la semana actual.</div>
            <?php endif; ?>
          </div>

          <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
            <div class="section-title--invert">
              <span>ÓRDENES QUE DEBO CUMPLIR</span>
              <small>Semana actual</small>
            </div>

            <?php if (!empty($ordenesMiasSiguiente)): ?>
              <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="actual-mias">
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
              <div class="task-pagination" data-page-nav="actual-mias"></div>
            <?php else: ?>
              <div class="task-empty-box mt-2">No tengo órdenes asignadas en la semana actual.</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tieneJuniors)): ?>
            <div class="card border-dark shadow-sm p-3 mb-3 plan-card">
              <div class="section-title--invert">
                <span>ÓRDENES QUE DEBEN REALIZAR MIS JUNIORS</span>
                <small>Semana actual</small>
              </div>

              <?php if (!empty($ordenesJuniorsSiguiente)): ?>
                <ul class="list-group list-group-flush mt-2 task-paginated-list" data-page-group="actual-juniors">
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
                <div class="task-pagination" data-page-nav="actual-juniors"></div>
              <?php else: ?>
                <div class="task-empty-box mt-2">No hay tareas asignadas a juniors en la semana actual.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <div class="card shadow-sm p-3 mb-3 mt-4 plan-textarea-card">
        <label class="fw-bold">CUOTAS PARA LA SEMANA</label>
        <textarea class="form-control" name="cuota_descripcion" rows="3"><?= esc($cuotaDesc) ?></textarea>
      </div>

      <div class="card shadow-sm p-3 mb-3 plan-textarea-card">
        <label class="fw-bold text-dark">OBJETIVOS QUE CONTRIBUYEN AL PLAN ESTRATÉGICO</label>
        <textarea class="form-control" name="objetivo_estrategico" rows="3"><?= esc($objetivoDesc) ?></textarea>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary plan-save-btn" type="submit">
          <i class="bi bi-save me-1"></i> Guardar Plan
        </button>
      </div>

    </form>
  <?php endif; ?>

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
        <label class="form-label">${i + 1}. ${q}</label>
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

  const searchEl = document.getElementById('taskSearch');
  const filterEl = document.getElementById('taskFilter');

  const PAGE_SIZE = 5;
  const taskPagerState = {};

  function getActivePane() {
    return document.querySelector('#weekTabsContent .tab-pane.active');
  }

  function getVisibleItemsForList(listEl, q, f) {
    const allItems = Array.from(listEl.querySelectorAll('.task-filter-item'));

    return allItems.filter(item => {
      const text = (item.getAttribute('data-text') || '');
      const status = (item.getAttribute('data-status') || 'pending');

      const matchText = !q || text.includes(q);
      const matchStatus = (f === 'all') || (f === status);

      return matchText && matchStatus;
    });
  }

  function renderPaginationForList(listEl, q, f) {
    const group = listEl.getAttribute('data-page-group');
    if (!group) return;

    const nav = document.querySelector(`[data-page-nav="${group}"]`);
    const allItems = Array.from(listEl.querySelectorAll('.task-filter-item'));
    const visibleItems = getVisibleItemsForList(listEl, q, f);

    allItems.forEach(item => {
      item.style.display = 'none';
      item.classList.add('is-hidden-by-page');
    });

    if (!visibleItems.length) {
      if (nav) nav.innerHTML = `
        <div class="task-pagination-info">
          No hay actividades que coincidan con el filtro actual.
        </div>
      `;
      return;
    }

    const totalPages = Math.max(1, Math.ceil(visibleItems.length / PAGE_SIZE));

    if (!taskPagerState[group]) {
      taskPagerState[group] = 1;
    }

    if (taskPagerState[group] > totalPages) {
      taskPagerState[group] = totalPages;
    }

    if (taskPagerState[group] < 1) {
      taskPagerState[group] = 1;
    }

    const currentPage = taskPagerState[group];
    const start = (currentPage - 1) * PAGE_SIZE;
    const end = start + PAGE_SIZE;

    visibleItems.slice(start, end).forEach(item => {
      item.style.display = '';
      item.classList.remove('is-hidden-by-page');
    });

    if (!nav) return;

    nav.innerHTML = `
      <div class="task-pagination-info">
        Mostrando ${start + 1}-${Math.min(end, visibleItems.length)} de ${visibleItems.length} actividades
      </div>
      <div class="task-pagination-controls">
        <button type="button" class="task-page-btn" data-page-action="prev" data-page-group="${group}" ${currentPage <= 1 ? 'disabled' : ''}>
          ← Anterior
        </button>
        <button type="button" class="task-page-btn" data-page-action="next" data-page-group="${group}" ${currentPage >= totalPages ? 'disabled' : ''}>
          Siguiente →
        </button>
      </div>
    `;
  }

  function applyTaskFilter() {
    const pane = getActivePane();
    if (!pane) return;

    const q = (searchEl ? searchEl.value.trim().toLowerCase() : '');
    const f = (filterEl ? filterEl.value : 'all');

    const lists = pane.querySelectorAll('.task-paginated-list');
    lists.forEach(listEl => renderPaginationForList(listEl, q, f));
  }

  function resetPaginationInActivePane() {
    const pane = getActivePane();
    if (!pane) return;

    const lists = pane.querySelectorAll('.task-paginated-list');
    lists.forEach(listEl => {
      const group = listEl.getAttribute('data-page-group');
      if (group) taskPagerState[group] = 1;
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function() {
      resetPaginationInActivePane();
      applyTaskFilter();
    });
  }

  if (filterEl) {
    filterEl.addEventListener('change', function() {
      resetPaginationInActivePane();
      applyTaskFilter();
    });
  }

  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-page-action]');
    if (!btn) return;

    const action = btn.getAttribute('data-page-action');
    const group = btn.getAttribute('data-page-group');
    if (!group) return;

    if (!taskPagerState[group]) {
      taskPagerState[group] = 1;
    }

    if (action === 'prev') {
      taskPagerState[group] = Math.max(1, taskPagerState[group] - 1);
    }

    if (action === 'next') {
      taskPagerState[group] = taskPagerState[group] + 1;
    }

    applyTaskFilter();
  });

  (function initWeekTabsFallback() {
    const tabsWrap = document.getElementById('weekTabs');
    const content = document.getElementById('weekTabsContent');
    if (!tabsWrap || !content) return;

    const hasBS5 = !!(window.bootstrap && window.bootstrap.Tab);
    const buttons = tabsWrap.querySelectorAll('button[role="tab"]');

    buttons.forEach(btn => {
      btn.addEventListener('click', function(e) {
        if (hasBS5) return;

        e.preventDefault();

        buttons.forEach(b => {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });

        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

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
        resetPaginationInActivePane();
        applyTaskFilter();
      });
    });
  })();

  document.querySelectorAll('#weekTabs button[role="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function() {
      resetPaginationInActivePane();
      applyTaskFilter();
    });
  });

  applyTaskFilter();

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