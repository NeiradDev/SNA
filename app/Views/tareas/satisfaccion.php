<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * Vista: satisfaccion.php
 *
 * ✅ Mensaje corto y NO invasivo (inline, sin alert grande)
 * ✅ Se quitó el botón "Volver"
 * ✅ FIX: también se muestra "Mi Área a cargo:" (sin duplicar por area_id)
 * ✅ FIX: si eres jefe de área, se prioriza mostrar "Mi Área a cargo:" si existe
 */

// --------------------------------------------------
// Helpers defensivos (evitan "Undefined index")
// --------------------------------------------------
$payload = (is_array($data ?? null)) ? $data : [];

/**
 * ------------------------------------------------------------
 * Helper: normalizar porcentaje a 0..100 (igual a tu snippet)
 * ------------------------------------------------------------
 */
$normalizePercent = function ($porcentajeRaw, int $realizadas, int $noRealizadas): float {

  $porcentaje = null;

  if (is_string($porcentajeRaw)) {
    $limpio = preg_replace('/[^0-9,.\-]/', '', $porcentajeRaw);
    $limpio = str_replace(',', '.', $limpio);
    if ($limpio !== '' && is_numeric($limpio)) {
      $porcentaje = (float)$limpio;
    }
  } elseif (is_numeric($porcentajeRaw)) {
    $porcentaje = (float)$porcentajeRaw;
  }

  if ($porcentaje === null) {
    $total = $realizadas + $noRealizadas;
    $porcentaje = $total > 0 ? round(($realizadas / $total) * 100, 2) : 0.0;
  }

  if ($porcentaje >= 0 && $porcentaje <= 1) {
    $porcentaje *= 100;
  }

  $p = max(0, min(100, (float)$porcentaje));
  return round($p, 2);
};

$getCondition = function (float $p): string {
  if ($p <= 20) return 'INEXISTENCIA';
  if ($p <= 39) return 'PELIGRO';
  if ($p <= 69) return 'EMERGENCIA';
  if ($p <= 89) return 'NORMAL';
  return 'AFLUENCIA';
};

$normalizeHistory = function($raw): array {
  if (!is_array($raw) || empty($raw)) return [];

  $isPairs = isset($raw[0]) && is_array($raw[0]) && (array_key_exists('label', $raw[0]) || array_key_exists('value', $raw[0]));
  if ($isPairs) {
    $out = [];
    foreach ($raw as $row) {
      if (!is_array($row)) continue;
      $label = (string)($row['label'] ?? '');
      $val   = (float)($row['value'] ?? 0);
      if ($label === '') continue;
      $out[] = ['label' => $label, 'value' => max(0, min(100, $val))];
    }
    return $out;
  }

  $labels = (array)($raw['labels'] ?? []);
  $values = (array)($raw['values'] ?? []);
  $out = [];

  $n = min(count($labels), count($values));
  for ($i=0; $i<$n; $i++) {
    $label = (string)$labels[$i];
    $val   = (float)$values[$i];
    if ($label === '') continue;
    $out[] = ['label' => $label, 'value' => max(0, min(100, $val))];
  }

  return $out;
};

$normalizeCard = function(array $block) use ($payload, $normalizeHistory, $normalizePercent, $getCondition): array {

  $realizadas   = (int)($block['realizadas'] ?? 0);
  $noRealizadas = (int)($block['no_realizadas'] ?? 0);

  $porcentaje = $normalizePercent(($block['porcentaje'] ?? null), $realizadas, $noRealizadas);
  $condicionAuto = $getCondition($porcentaje);

  $isCritCond = in_array($condicionAuto, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true);
  $colorStart = $isCritCond ? 'rgba(225,6,0,0.10)' : 'rgba(2,48,89,0.08)';
  $colorEnd   = $isCritCond ? 'rgba(225,6,0,0.22)' : 'rgba(2,48,89,0.20)';

  $title    = (string)($block['titulo'] ?? ($block['title'] ?? 'Mi porcentaje de satisfacción'));
  $subtitle = (string)($block['subtitle'] ?? 'Resumen semanal');

  $inicio = (string)($block['inicio'] ?? ($payload['inicio'] ?? ''));
  $fin    = (string)($block['fin']    ?? ($payload['fin']    ?? ''));

  $scope = (string)($block['scope'] ?? ($block['mode'] ?? 'personal'));
  if (!in_array($scope, ['division','area','personal'], true)) $scope = 'personal';

  $divisionId = isset($block['division_id']) ? (int)$block['division_id'] : (isset($block['divisionId']) ? (int)$block['divisionId'] : 0);
  $areaId     = isset($block['area_id'])     ? (int)$block['area_id']     : (isset($block['areaId'])     ? (int)$block['areaId']     : 0);

  $avg4 = null;
  if (array_key_exists('avg_4_weeks', $block)) $avg4 = (float)$block['avg_4_weeks'];
  if (array_key_exists('avg4', $block))        $avg4 = (float)$block['avg4'];

  $history = $normalizeHistory($block['history'] ?? []);
  $isCritical = ($porcentaje < 50);

  return [
    'title'         => $title,
    'subtitle'      => $subtitle,
    'scope'         => $scope,
    'divisionId'    => $divisionId,
    'areaId'        => $areaId,

    'porcentaje'    => $porcentaje,
    'realizadas'    => $realizadas,
    'no_realizadas' => $noRealizadas,

    'inicio'        => $inicio,
    'fin'           => $fin,

    'avg4'          => (is_numeric($avg4) ? max(0, min(100, (float)$avg4)) : null),
    'history'       => $history,

    'colorStart'    => $colorStart,
    'colorEnd'      => $colorEnd,

    'condicion'     => $condicionAuto,
    'isCritical'    => $isCritical,
  ];
};

// --------------------------------------------------
// Cards/items
// --------------------------------------------------
$rawCards = [];
if (isset($payload['cards']) && is_array($payload['cards']) && !empty($payload['cards'])) {
  $rawCards = $payload['cards'];
} elseif (isset($payload['items']) && is_array($payload['items']) && !empty($payload['items'])) {
  $rawCards = $payload['items'];
} else {
  $rawCards = [$payload];
}

$cards = [];
foreach ($rawCards as $c) {
  if (is_array($c)) $cards[] = $normalizeCard($c);
}

// --------------------------------------------------
// ✅ DEDUP + ROLES
// --------------------------------------------------
$toLower = function(string $s): string {
  $s = trim($s);
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
};

// Dedup tarjetas (por scope + ids)
$uniqueCards = [];
$seenCards   = [];

foreach ($cards as $c) {

  if (!is_array($c)) continue;

  $scope = (string)($c['scope'] ?? 'personal');

  if ($scope === 'area') {
    $aid  = (int)($c['areaId'] ?? 0);
    $tkey = $toLower((string)($c['title'] ?? ''));
    $key  = ($aid > 0) ? ("area:$aid") : ("area_title:$tkey");
  } elseif ($scope === 'division') {
    $did  = (int)($c['divisionId'] ?? 0);
    $tkey = $toLower((string)($c['title'] ?? ''));
    $key  = ($did > 0) ? ("division:$did") : ("division_title:$tkey");
  } else {
    $key = "personal:1";
  }

  if (isset($seenCards[$key])) continue;

  $seenCards[$key] = true;
  $uniqueCards[]   = $c;
}

$cards = array_values($uniqueCards);

// Detectar rol
$hasDivisionRole = false;
$hasAreaRole     = false;

$divisionRoleKeys = ['id_jf_division','is_jf_division','jf_division','jefe_division','idJfDivision','isJfDivision'];
foreach ($divisionRoleKeys as $k) {
  if (!empty($payload[$k])) { $hasDivisionRole = true; break; }
}

$areaRoleKeys = ['id_jf_area','is_jf_area','jf_area','jefe_area','idJfArea','isJfArea'];
foreach ($areaRoleKeys as $k) {
  if (!empty($payload[$k])) { $hasAreaRole = true; break; }
}

$divisionIdx = null;
$personalIdx = null;
$areaIdxs    = [];

foreach ($cards as $i => $c) {
  $sc = (string)($c['scope'] ?? 'personal');
  if ($sc === 'division' && $divisionIdx === null) $divisionIdx = $i;
  if ($sc === 'personal' && $personalIdx === null) $personalIdx = $i;
  if ($sc === 'area') $areaIdxs[] = $i;
}

if ($divisionIdx !== null) $hasDivisionRole = true;

if (
  (isset($payload['ranking_areas']) && is_array($payload['ranking_areas']) && !empty($payload['ranking_areas'])) ||
  (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas'])  && !empty($payload['rankingAreas'])) ||
  (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas']) && !empty($payload['rankings']['areas']))
) {
  $hasDivisionRole = true;
}

if (!empty($areaIdxs)) $hasAreaRole = true;

// --------------------------------------------------
// Aplicar vista final según rol
// --------------------------------------------------
if ($hasDivisionRole) {

  $newCards = [];
  if ($divisionIdx !== null) {
    $oldSub = (string)($cards[$divisionIdx]['subtitle'] ?? '');
    $cards[$divisionIdx]['subtitle'] = trim('Mi porcentaje de satisfacción' . ($oldSub !== '' ? (' · ' . $oldSub) : ''));
    $newCards[] = $cards[$divisionIdx];
  }

  /**
   * ✅ FIX: Incluir también "Mi Área a cargo:".
   * - Dedupe por areaId.
   * - Si existe duplicado del mismo area_id:
   *   preferimos "Mi Área a cargo:" sobre "Área:".
   */
  $areaCards = [];
  $areaSeen  = []; // key => index dentro de $areaCards para permitir reemplazo

  foreach ($cards as $c) {
    if (!is_array($c)) continue;
    if ((string)($c['scope'] ?? '') !== 'area') continue;

    $aid = (int)($c['areaId'] ?? 0);
    $title = (string)($c['title'] ?? '');
    $tLower = $toLower($title);

    $isAreaNormal  = (strpos($tLower, $toLower('Área:')) === 0);
    $isMiAreaCargo = (strpos($tLower, $toLower('Mi Área a cargo:')) === 0);

    // ✅ Solo dejamos cards de área que sean "Área:" o "Mi Área a cargo:"
    if (!$isAreaNormal && !$isMiAreaCargo) continue;

    $key = ($aid > 0) ? ("area:$aid") : ("area_title:" . $toLower($title));

    // Si ya existe, decidir si reemplazamos
    if (isset($areaSeen[$key])) {
      $pos = (int)$areaSeen[$key];

      if (isset($areaCards[$pos])) {
        $prevTitle = (string)($areaCards[$pos]['title'] ?? '');
        $prevLower = $toLower($prevTitle);

        $prevIsAreaNormal  = (strpos($prevLower, $toLower('Área:')) === 0);
        $prevIsMiAreaCargo = (strpos($prevLower, $toLower('Mi Área a cargo:')) === 0);

        // Preferimos "Mi Área a cargo:" si aparece
        if ($isMiAreaCargo && $prevIsAreaNormal) {
          $areaCards[$pos] = $c;
        }

        // Si ya era Mi Área a cargo, no reemplazamos
        // Si ambos son iguales, no pasa nada
      }

      continue;
    }

    $areaSeen[$key] = count($areaCards);
    $areaCards[] = $c;
  }

  // Mantener ranking->cards sintéticas (por si faltan cards de áreas)
  $rankAreasRaw = [];
  if (isset($payload['ranking_areas']) && is_array($payload['ranking_areas'])) $rankAreasRaw = $payload['ranking_areas'];
  if (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas']))  $rankAreasRaw = $payload['rankingAreas'];
  if (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas'])) $rankAreasRaw = $payload['rankings']['areas'];

  foreach ((array)$rankAreasRaw as $ra) {

    if (!is_array($ra)) continue;

    $aid = (int)($ra['area_id'] ?? ($ra['id_area'] ?? 0));
    $aname = trim((string)($ra['area'] ?? ($ra['nombre_area'] ?? ($ra['area_name'] ?? 'Área'))));

    $key  = ($aid > 0) ? ("area:$aid") : ("area_title:" . $toLower($aname));

    // Si ya existe como card (Área o Mi Área a cargo), no duplicamos
    if (isset($areaSeen[$key])) continue;

    $done = (int)($ra['realizadas'] ?? 0);
    $not  = (int)($ra['no_realizadas'] ?? 0);

    $synthetic = [
      'titulo'        => 'Área: ' . ($aname !== '' ? $aname : 'Área'),
      'subtitle'      => 'Resumen semanal',
      'scope'         => 'area',
      'area_id'       => $aid,
      'porcentaje'    => ($ra['porcentaje'] ?? null),
      'realizadas'    => $done,
      'no_realizadas' => $not,
      'inicio'        => (string)($payload['inicio'] ?? ''),
      'fin'           => (string)($payload['fin'] ?? ''),
    ];

    $areaCards[] = $normalizeCard($synthetic);
    $areaSeen[$key] = count($areaCards) - 1;
  }

  usort($areaCards, fn($a,$b) => ((float)($b['porcentaje'] ?? 0) <=> (float)($a['porcentaje'] ?? 0)));
  $cards = array_values(array_merge($newCards, $areaCards));

} elseif ($hasAreaRole) {

  $targetAreaId = 0;
  if (!empty($payload['id_jf_area'])) $targetAreaId = (int)$payload['id_jf_area'];

  $chosen = null;

  // 1) Preferir explícitamente "Mi Área a cargo:" si existe
  foreach ($cards as $c) {
    if ((string)($c['scope'] ?? '') !== 'area') continue;
    $t = (string)($c['title'] ?? '');
    if (stripos($t, 'Mi Área a cargo:') === 0) { $chosen = $c; break; }
  }

  // 2) Si no, preferir la que coincide con id_jf_area
  if ($chosen === null && $targetAreaId > 0) {
    foreach ($cards as $c) {
      if ((string)($c['scope'] ?? '') === 'area' && (int)($c['areaId'] ?? 0) === $targetAreaId) {
        $chosen = $c;
        break;
      }
    }
  }

  // 3) Si no, escoger la primera de área
  if ($chosen === null) {
    foreach ($cards as $c) {
      if ((string)($c['scope'] ?? '') === 'area') { $chosen = $c; break; }
    }
  }

  // 4) Fallback: personal si no hay área
  if ($chosen === null && $personalIdx !== null) $chosen = $cards[$personalIdx];

  $cards = ($chosen !== null) ? [$chosen] : [];

} else {

  // Solo personal
  $newCards = [];
  if ($personalIdx !== null) $newCards[] = $cards[$personalIdx];
  elseif (!empty($cards)) $newCards[] = $cards[0];
  $cards = array_values($newCards);
}

// Ordenar: división arriba, luego % desc
usort($cards, function($a, $b) {

  $sa = (string)($a['scope'] ?? '');
  $sb = (string)($b['scope'] ?? '');

  if ($sa === 'division' && $sb !== 'division') return -1;
  if ($sb === 'division' && $sa !== 'division') return  1;

  return ((float)($b['porcentaje'] ?? 0) <=> (float)($a['porcentaje'] ?? 0));
});

// Rankings
$rankingAreas = [];
if (isset($payload['ranking_areas']) && is_array($payload['ranking_areas'])) $rankingAreas = $payload['ranking_areas'];
if (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas']))  $rankingAreas = $payload['rankingAreas'];
if (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas'])) $rankingAreas = $payload['rankings']['areas'];

$rankingUsersByArea = [];
if (isset($payload['ranking_users_by_area']) && is_array($payload['ranking_users_by_area'])) $rankingUsersByArea = $payload['ranking_users_by_area'];
if (isset($payload['rankingUsersByArea'])    && is_array($payload['rankingUsersByArea']))    $rankingUsersByArea = $payload['rankingUsersByArea'];
if (isset($payload['rankings']['users_by_area']) && is_array($payload['rankings']['users_by_area'])) $rankingUsersByArea = $payload['rankings']['users_by_area'];

$historyGlobal = $normalizeHistory($payload['history_global'] ?? ($payload['historyGlobal'] ?? []));

// Normalizar ranking áreas
$rankingAreasUi = [];
foreach ((array)$rankingAreas as $ra) {
  if (!is_array($ra)) continue;

  $rDone = (int)($ra['realizadas'] ?? 0);
  $rNot  = (int)($ra['no_realizadas'] ?? 0);

  $rankingAreasUi[] = [
    'area_id'      => (int)($ra['area_id'] ?? ($ra['id_area'] ?? 0)),
    'area'         => (string)($ra['area'] ?? ($ra['nombre_area'] ?? 'Área')),
    'porcentaje'   => $normalizePercent(($ra['porcentaje'] ?? null), $rDone, $rNot),
    'realizadas'   => $rDone,
    'no_realizadas'=> $rNot,
  ];
}
usort($rankingAreasUi, fn($a,$b) => $b['porcentaje'] <=> $a['porcentaje']);

// Dedup ranking
$uniqueRank = [];
$seenRank   = [];
foreach ($rankingAreasUi as $ra) {

  $aid = (int)($ra['area_id'] ?? 0);
  $nm  = $toLower((string)($ra['area'] ?? ''));

  $key = ($aid > 0) ? ("id:$aid") : ("name:$nm");

  if (!isset($seenRank[$key])) {
    $seenRank[$key] = count($uniqueRank);
    $uniqueRank[] = $ra;
    continue;
  }

  $pos = (int)$seenRank[$key];
  if ((float)($ra['porcentaje'] ?? 0) > (float)($uniqueRank[$pos]['porcentaje'] ?? 0)) {
    $uniqueRank[$pos] = $ra;
  }
}
$rankingAreasUi = $uniqueRank;
usort($rankingAreasUi, fn($a,$b) => $b['porcentaje'] <=> $a['porcentaje']);

// UI helpers
$getBarColor = function(float $p) use ($getCondition): string {
  $cond = $getCondition($p);
  $isCrit = in_array($cond, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true);
  return $isCrit ? '#E10600' : '#023059';
};

$getBadgeHtml = function(float $p) use ($getCondition): string {
  $cond = $getCondition($p);
  if (in_array($cond, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true)) {
    return '<span class="badge bg-danger">'.$cond.'</span>';
  }
  return '<span class="badge bg-dark">'.$cond.'</span>';
};

// --------------------------------------------------
// ✅ Mensaje corto y NO invasivo (solo Jueves < 12:00)
// --------------------------------------------------
$tzLocal  = new \DateTimeZone('America/Guayaquil');
$nowLocal = new \DateTimeImmutable('now', $tzLocal);
$isGrace  = ((int)$nowLocal->format('N') === 4) && ((int)$nowLocal->format('H') < 12);

$hintMsg = $isGrace
  ? 'Nota: hasta las <strong>12:00</strong> del jueves se mantiene el corte anterior.'
  : '';
?>

<style>
  .sat-wrap{ padding: 12px 0; }

  .sat-card{
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,.12);
    background: #fff;
  }

  .sat-card.critical{
    border: 2px solid #E10600;
    box-shadow: 0 12px 24px rgba(225,6,0,.10);
  }

  .sat-title{ font-weight: 900; margin-bottom: 4px; color:#0B0B0B; }
  .sat-sub{ color:#555; font-size:.92rem; }

  .circle-wrapper{ position:relative; width:220px; height:220px; margin:auto; }
  .circle-center{ position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; }
  .circle-percent{ font-size:2.8rem; font-weight:900; color:#000; line-height:1; }
  .circle-subtitle{ margin-top:6px; font-size:.9rem; color:#333; }

  .semana-text{ text-align:center; margin-top:10px; font-size:.92rem; color:#555; }

  .stat-box{
    background:#f8f9fa;
    border-radius:14px;
    padding:14px 16px;
    min-width:160px;
    text-align:center;
    border: 1px solid rgba(0,0,0,.08);
  }
  .stat-box strong{ font-size:1.7rem; font-weight:900; line-height:1.1; }

  .sat-foot{ margin-top: 12px; font-size: .9rem; color: #555; text-align: center; }

  .rank-card{ border-radius: 14px; border: 1px solid rgba(0,0,0,.10); background: #fff; }
  .rank-title{ font-weight: 900; color:#0B0B0B; margin:0; }

  .rank-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:10px 12px;
    border-top:1px solid rgba(0,0,0,.06);
  }
  .rank-row:first-child{ border-top:none; }

  .rank-name{ font-weight:700; color:#111; margin:0; font-size:.95rem; }
  .rank-meta{ font-size:.85rem; color:#666; margin:0; }

  .mini-bar{
    height:10px;
    border-radius: 10px;
    background: rgba(0,0,0,.10);
    overflow:hidden;
    width: 160px;
    flex: 0 0 160px;
  }
  .mini-bar > div{ height:100%; border-radius:10px; }

  .hist-row{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    border-top:1px solid rgba(0,0,0,.06);
  }
  .hist-row:first-child{ border-top:none; }
  .hist-label{ width: 170px; font-size:.85rem; color:#333; flex: 0 0 170px; }
  .hist-bar{ flex: 1 1 auto; height:10px; border-radius: 10px; background: rgba(0,0,0,.10); overflow:hidden; }
  .hist-bar > div{ height:100%; border-radius:10px; }
  .hist-val{ width: 70px; text-align:right; font-weight:800; font-size:.88rem; color:#111; flex: 0 0 70px; }

  /* ✅ Mensaje no invasivo */
  .sat-hint{
    margin-top: 6px;
    font-size: .85rem;
    color: #6c757d;
  }
</style>

<div class="container sat-wrap">

  <!-- Header -->
  <div class="mb-3">
    <h2 class="m-0 fw-bold" style="color:#0B0B0B;">Satisfacción</h2>

    <div class="text-muted" style="font-size:.92rem;">
      Semana <strong><?= esc((string)($payload['inicio'] ?? ($cards[0]['inicio'] ?? ''))) ?></strong>
      → <strong><?= esc((string)($payload['fin'] ?? ($cards[0]['fin'] ?? ''))) ?></strong>
      (Jueves → Miércoles)
    </div>

    <?php if (!empty($hintMsg)): ?>
      <div class="sat-hint">ℹ️ <?= $hintMsg ?></div>
    <?php endif; ?>
  </div>

  <!-- TARJETAS -->
  <?php foreach ($cards as $idx => $it): ?>
    <?php
      $gradId = 'innerGrad_' . $idx;
      $cardClass = $it['isCritical'] ? 'critical' : '';
      $barColor = $getBarColor((float)$it['porcentaje']);
    ?>

    <div class="mb-4">

      <div class="text-center">
        <h3 class="sat-title">
          <?= esc($it['title']) ?>
          <?= $getBadgeHtml((float)$it['porcentaje']) ?>
        </h3>

        <div class="sat-sub">
          <?= esc($it['subtitle']) ?>
        </div>
      </div>

      <div class="sat-card <?= esc($cardClass) ?> p-3 p-md-4 mt-3">

        <!-- CÍRCULO -->
        <div class="circle-wrapper mb-2">
          <svg width="220" height="220" aria-hidden="true">
            <defs>
              <radialGradient id="<?= esc($gradId) ?>" cx="50%" cy="50%" r="60%">
                <stop offset="0%" stop-color="<?= esc($it['colorStart']) ?>"/>
                <stop offset="100%" stop-color="<?= esc($it['colorEnd']) ?>"/>
              </radialGradient>
            </defs>
            <circle cx="110" cy="110" r="95" fill="url(#<?= esc($gradId) ?>)"/>
          </svg>

          <div class="circle-center">
            <div class="circle-percent"><?= number_format((float)$it['porcentaje'], 2) ?>%</div>
            <div class="circle-subtitle">Satisfacción</div>
          </div>
        </div>

        <!-- BARRA -->
        <div class="mt-2">
          <div class="progress" style="height:10px; border-radius:10px; background: rgba(0,0,0,.12);">
            <div class="progress-bar"
                 role="progressbar"
                 style="width: <?= (float)$it['porcentaje'] ?>%;
                        background-color: <?= esc($barColor) ?>;">
            </div>
          </div>

          <?php if ($it['avg4'] !== null): ?>
            <div class="text-center mt-2" style="font-size:.90rem; color:#333;">
              Promedio últimas 4 semanas:
              <strong><?= number_format((float)$it['avg4'], 2) ?>%</strong>
            </div>
          <?php endif; ?>
        </div>

        <div class="semana-text">
          Semana <?= esc($it['inicio']) ?> → <?= esc($it['fin']) ?>
        </div>

        <!-- CONTADORES -->
        <div class="d-flex justify-content-center gap-3 gap-md-4 flex-wrap mt-3">
          <div class="stat-box">
            <strong style="color:#023059;"><?= (int)$it['realizadas'] ?></strong><br>
            <small>Realizadas</small>
          </div>

          <div class="stat-box">
            <strong style="color:#E10600;"><?= (int)$it['no_realizadas'] ?></strong><br>
            <small>No realizadas</small>
          </div>
        </div>

        <!-- HISTÓRICO -->
        <?php if (!empty($it['history'])): ?>
          <div class="mt-4">
            <div class="rank-card p-3">
              <div class="d-flex align-items-center justify-content-between">
                <p class="rank-title">Promedio histórico por semanas</p>
                <small class="text-muted">Últimas 4 + actual</small>
              </div>

              <?php foreach ($it['history'] as $h): ?>
                <?php
                  $hv = (float)($h['value'] ?? 0);
                  $hc = $getBarColor($hv);
                ?>
                <div class="hist-row">
                  <div class="hist-label"><?= esc((string)($h['label'] ?? '')) ?></div>
                  <div class="hist-bar">
                    <div style="width: <?= $hv ?>%; background: <?= esc($hc) ?>;"></div>
                  </div>
                  <div class="hist-val"><?= number_format($hv, 2) ?>%</div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- RANKINGS -->
        <?php if ($it['scope'] === 'division' && !empty($rankingAreasUi)): ?>
          <div class="mt-4">
            <div class="rank-card p-3">
              <div class="d-flex align-items-center justify-content-between">
                <p class="rank-title">Ranking de áreas</p>
                <small class="text-muted">Ordenado por %</small>
              </div>

              <?php $divisionPct = (float)$it['porcentaje']; ?>

              <div class="rank-row" style="background: rgba(2,48,89,.04);">
                <div style="min-width: 220px;">
                  <p class="rank-name">
                    División (total)
                    <?= $getBadgeHtml($divisionPct) ?>
                  </p>
                  <p class="rank-meta">Comparativa global</p>
                </div>
                <div class="mini-bar">
                  <div style="width: <?= $divisionPct ?>%; background: <?= esc($getBarColor($divisionPct)) ?>;"></div>
                </div>
                <div style="width: 70px; text-align:right; font-weight:900;">
                  <?= number_format($divisionPct, 2) ?>%
                </div>
              </div>

              <?php foreach ($rankingAreasUi as $pos => $ra): ?>
                <?php $p = (float)$ra['porcentaje']; $c = $getBarColor($p); ?>
                <div class="rank-row">
                  <div style="min-width: 220px;">
                    <p class="rank-name">
                      <?= esc(($pos+1) . '. ' . (string)$ra['area']) ?>
                      <?= $getBadgeHtml($p) ?>
                    </p>
                    <p class="rank-meta"><?= (int)$ra['realizadas'] ?> realizadas · <?= (int)$ra['no_realizadas'] ?> no realizadas</p>
                  </div>

                  <div class="mini-bar">
                    <div style="width: <?= $p ?>%; background: <?= esc($c) ?>;"></div>
                  </div>

                  <div style="width: 70px; text-align:right; font-weight:900;">
                    <?= number_format($p, 2) ?>%
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($it['scope'] === 'area'): ?>
          <?php
            $areaId = (int)$it['areaId'];

            $usersRank = [];
            if ($areaId > 0 && isset($rankingUsersByArea[$areaId]) && is_array($rankingUsersByArea[$areaId])) {
              $usersRank = $rankingUsersByArea[$areaId];
            }

            $usersRankUi = [];
            foreach ($usersRank as $u) {
              if (!is_array($u)) continue;

              $uid = (int)($u['user_id'] ?? ($u['id_user'] ?? 0));
              $nm  = (string)($u['nombre'] ?? ($u['name'] ?? 'Usuario #' . $uid));

              $uDone = (int)($u['realizadas'] ?? 0);
              $uNot  = (int)($u['no_realizadas'] ?? 0);
              $uPct  = $normalizePercent(($u['porcentaje'] ?? null), $uDone, $uNot);

              $usersRankUi[] = [
                'user_id'      => $uid,
                'nombre'       => ($nm !== '' ? $nm : ('Usuario #' . $uid)),
                'porcentaje'   => $uPct,
                'realizadas'   => $uDone,
                'no_realizadas'=> $uNot,
              ];
            }
            usort($usersRankUi, fn($a,$b) => $b['porcentaje'] <=> $a['porcentaje']);
          ?>

          <?php if (!empty($usersRankUi)): ?>
            <div class="mt-4">
              <div class="rank-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <p class="rank-title">Ranking de usuarios del área</p>
                  <small class="text-muted">Ordenado por %</small>
                </div>

                <?php foreach ($usersRankUi as $pos => $u): ?>
                  <?php $p = (float)$u['porcentaje']; $c = $getBarColor($p); ?>
                  <div class="rank-row">
                    <div style="min-width: 220px;">
                      <p class="rank-name">
                        <?= esc(($pos+1) . '. ' . $u['nombre']) ?>
                        <?= $getBadgeHtml($p) ?>
                      </p>
                      <p class="rank-meta"><?= (int)$u['realizadas'] ?> realizadas · <?= (int)$u['no_realizadas'] ?> no realizadas</p>
                    </div>

                    <div class="mini-bar">
                      <div style="width: <?= $p ?>%; background: <?= esc($c) ?>;"></div>
                    </div>

                    <div style="width: 70px; text-align:right; font-weight:900;">
                      <?= number_format($p, 2) ?>%
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="sat-foot">
          Si algo no cuadra, avísale al administrador para revisarlo.
        </div>

      </div>
    </div>
  <?php endforeach; ?>

  <!-- HISTÓRICO GLOBAL -->
  <?php if (!empty($historyGlobal)): ?>
    <div class="mb-4">
      <div class="rank-card p-3">
        <div class="d-flex align-items-center justify-content-between">
          <p class="rank-title">Promedio histórico general</p>
          <small class="text-muted">Últimas 4 + actual</small>
        </div>

        <?php foreach ($historyGlobal as $h): ?>
          <?php $hv = (float)($h['value'] ?? 0); $hc = $getBarColor($hv); ?>
          <div class="hist-row">
            <div class="hist-label"><?= esc((string)($h['label'] ?? '')) ?></div>
            <div class="hist-bar">
              <div style="width: <?= $hv ?>%; background: <?= esc($hc) ?>;"></div>
            </div>
            <div class="hist-val"><?= number_format($hv, 2) ?>%</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->endSection() ?>