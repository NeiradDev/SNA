<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * ============================================================
 * Vista: satisfaccion.php
 * ============================================================
 *
 * OBJETIVO (lo que pediste):
 * ✅ Ordenar tarjetas por porcentaje DESC
 * ✅ Resaltar en ROJO áreas críticas (<50%)
 * ✅ Agregar barra comparativa debajo del círculo
 * ✅ Mostrar promedio histórico últimas 4 semanas
 * ✅ Ranking de usuarios dentro del área
 * ✅ Ranking de áreas dentro de la división
 * ✅ Gráfica comparativa división vs áreas (SIN librerías externas: barras HTML)
 * ✅ Promedio histórico por semanas (últimas 4 + actual, SIN Chart.js)
 * ✅ Semana de negocio: JUEVES → MIÉRCOLES (esto lo calcula el Service; aquí solo se muestra)
 *
 * ✅ NUEVO (lo que pediste ahora):
 * - Normalizar porcentaje como tu snippet:
 *   * soporta string "85%", "0.85", "85,2 %"
 *   * si porcentaje es null => calcula (realizadas / total)*100
 *   * si porcentaje viene 0..1 => lo multiplica x100
 *   * clamp 0..100
 * - Condición automática según rangos:
 *   0–20 INEXISTENCIA
 *   21–39 PELIGRO
 *   40–69 EMERGENCIA
 *   70–89 NORMAL
 *   90–100 AFLUENCIA
 *
 * ------------------------------------------------------------
 * ENTRADAS ESPERADAS (flexible / con fallback)
 * ------------------------------------------------------------
 *
 * 1) Nuevo (recomendado desde TareaService::getSatisfaccionResumen):
 *   $data = [
 *     'inicio' => 'YYYY-mm-dd',   // inicio de semana (jueves)
 *     'fin'    => 'YYYY-mm-dd',   // fin visible (miércoles)
 *     'cards'  => [
 *        [
 *          'titulo' => 'Satisfacción de mi división: ...',
 *          'scope'  => 'division'|'area'|'personal',
 *          'porcentaje' => float|string|null,
 *          'realizadas' => int,
 *          'no_realizadas' => int,
 *          // opcionales para UI avanzada:
 *          'division_id'  => int|null,
 *          'area_id'      => int|null,
 *          'avg_4_weeks'  => float|null,     // promedio últimas 4 semanas
 *          'history'      => [ ['label'=>'...', 'value'=>..], ... ]  // histórico por semanas
 *        ],
 *        ...
 *     ],
 *
 *     // Ranking áreas (para card division)
 *     'ranking_areas' => [
 *        ['area_id'=>1,'area'=>'Ventas','porcentaje'=>80,'realizadas'=>8,'no_realizadas'=>2],
 *        ...
 *     ],
 *
 *     // Ranking usuarios por área (para card area)
 *     'ranking_users_by_area' => [
 *        5 => [
 *          ['user_id'=>10,'nombre'=>'Juan Perez','porcentaje'=>70,'realizadas'=>7,'no_realizadas'=>3],
 *          ...
 *        ]
 *     ],
 *
 *     // Histórico global opcional (últimas 4 + actual)
 *     'history_global' => [
 *        ['label'=>'2026-01-01→2026-01-07','value'=>65.2],
 *        ...
 *     ],
 *   ];
 *
 * 2) Compatibilidad con tu vista vieja:
 *   $data['items'] = [...]
 *   o $data directo como un solo bloque.
 */

// --------------------------------------------------
// Helpers defensivos (evitan "Undefined index")
// --------------------------------------------------
$payload = (is_array($data ?? null)) ? $data : [];

/**
 * ------------------------------------------------------------
 * Helper: normalizar porcentaje a 0..100 (igual a tu snippet)
 * - soporta string: "85%", "0.85", "85,2 %"
 * - soporta numérico
 * - si viene null => calcula con realizadas/no_realizadas
 * - si viene 0..1 => convierte a 0..100
 * - clamp 0..100
 * ------------------------------------------------------------
 */
$normalizePercent = function ($porcentajeRaw, int $realizadas, int $noRealizadas): float {

  $porcentaje = null;

  // Caso A: viene como string
  if (is_string($porcentajeRaw)) {

    // 1) Limpiar todo menos números, coma, punto y signo
    $limpio = preg_replace('/[^0-9,.\-]/', '', $porcentajeRaw);

    // 2) Convertir coma a punto (para decimales)
    $limpio = str_replace(',', '.', $limpio);

    // 3) Validar numérico
    if ($limpio !== '' && is_numeric($limpio)) {
      $porcentaje = (float)$limpio;
    }
  }
  // Caso B: viene como número
  elseif (is_numeric($porcentajeRaw)) {
    $porcentaje = (float)$porcentajeRaw;
  }

  // Caso C: si no vino porcentaje, calcularlo por conteo
  if ($porcentaje === null) {
    $total = $realizadas + $noRealizadas;
    $porcentaje = $total > 0 ? round(($realizadas / $total) * 100, 2) : 0.0;
  }

  // Si viene en rango 0..1, convertirlo a 0..100
  if ($porcentaje >= 0 && $porcentaje <= 1) {
    $porcentaje *= 100;
  }

  // Clamp final
  $p = max(0, min(100, (float)$porcentaje));
  return round($p, 2);
};

/**
 * ------------------------------------------------------------
 * Helper: condición automática según porcentaje (tus rangos)
 * ------------------------------------------------------------
 */
$getCondition = function (float $p): string {

  if ($p <= 20) {
    return 'INEXISTENCIA';
  } elseif ($p <= 39) {
    return 'PELIGRO';
  } elseif ($p <= 69) {
    return 'EMERGENCIA';
  } elseif ($p <= 89) {
    return 'NORMAL';
  }

  return 'AFLUENCIA';
};

/**
 * Devuelve un array de histórico en formato estándar:
 * - Acepta: [['label'=>..., 'value'=>...], ...] o ['labels'=>[], 'values'=>[]]
 */
$normalizeHistory = function($raw): array {
  if (!is_array($raw) || empty($raw)) return [];

  // Caso A: viene como lista de pares
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

  // Caso B: viene como ['labels'=>[], 'values'=>[]]
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

/**
 * Normaliza un bloque/tarjeta para UI
 * - acepta llaves: titulo/title, scope, porcentaje, realizadas, no_realizadas, inicio/fin
 * - agrega colores sobrios (rojo/azul oscuro) y condición automática
 */
$normalizeCard = function(array $block) use ($payload, $normalizeHistory, $normalizePercent, $getCondition): array {

  // -----------------------------
  // 1) Conteos para fallback
  // -----------------------------
  $realizadas   = (int)($block['realizadas'] ?? 0);
  $noRealizadas = (int)($block['no_realizadas'] ?? 0);

  // -----------------------------
  // 2) Porcentaje normalizado (tu snippet)
  // -----------------------------
  $porcentaje = $normalizePercent(($block['porcentaje'] ?? null), $realizadas, $noRealizadas);

  // -----------------------------
  // 3) Condición automática (tus rangos)
  // -----------------------------
  $condicionAuto = $getCondition($porcentaje);

  // -----------------------------
  // 4) Colores sobrios (SIN verde / SIN neon)
  //    - condiciones críticas => rojo suave
  //    - condiciones normales/altas => azul oscuro suave
  // -----------------------------
  $isCritCond = in_array($condicionAuto, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true);

  $colorStart = $isCritCond ? 'rgba(225,6,0,0.10)' : 'rgba(2,48,89,0.08)';
  $colorEnd   = $isCritCond ? 'rgba(225,6,0,0.22)' : 'rgba(2,48,89,0.20)';

  // Títulos/labels (compat)
  $title = (string)($block['titulo'] ?? ($block['title'] ?? 'Mi porcentaje de satisfacción'));
  $subtitle = (string)($block['subtitle'] ?? 'Resumen semanal');

  // Fechas de semana (fallback a nivel raíz)
  $inicio = (string)($block['inicio'] ?? ($payload['inicio'] ?? ''));
  $fin    = (string)($block['fin']    ?? ($payload['fin']    ?? ''));

  // Scope esperado
  $scope = (string)($block['scope'] ?? ($block['mode'] ?? 'personal'));
  if (!in_array($scope, ['division','area','personal'], true)) {
    $scope = 'personal';
  }

  // IDs para linkear ranking/series
  $divisionId = isset($block['division_id']) ? (int)$block['division_id'] : (isset($block['divisionId']) ? (int)$block['divisionId'] : 0);
  $areaId     = isset($block['area_id'])     ? (int)$block['area_id']     : (isset($block['areaId'])     ? (int)$block['areaId']     : 0);

  // Promedio 4 semanas (si viene del service)
  $avg4 = null;
  if (array_key_exists('avg_4_weeks', $block)) $avg4 = (float)$block['avg_4_weeks'];
  if (array_key_exists('avg4', $block))        $avg4 = (float)$block['avg4'];

  // Histórico por semanas (si viene del service)
  $history = $normalizeHistory($block['history'] ?? []);

  // Bandera crítica (como pediste antes: <50%)
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

    // NUEVO: condición automática estilo snippet
    'condicion'     => $condicionAuto,

    'isCritical'    => $isCritical,
  ];
};

// --------------------------------------------------
// 1) Obtener cards/items de forma flexible
// --------------------------------------------------
$rawCards = [];

// Preferencia: $data['cards'] (nuevo service)
if (isset($payload['cards']) && is_array($payload['cards']) && !empty($payload['cards'])) {
  $rawCards = $payload['cards'];
}
// Compat: $data['items'] (vista anterior)
elseif (isset($payload['items']) && is_array($payload['items']) && !empty($payload['items'])) {
  $rawCards = $payload['items'];
}
// Fallback: un solo bloque (el mismo $data)
else {
  $rawCards = [$payload];
}

// Normalizar
$cards = [];
foreach ($rawCards as $c) {
  if (is_array($c)) $cards[] = $normalizeCard($c);
}



// --------------------------------------------------
// 1.1) ✅ VALIDACIÓN FINAL (SIN "MI ÁREA A CARGO")
// --------------------------------------------------
// Reglas (tal cual pediste):
// - JEFE DE DIVISIÓN (id_jf_division):
//   * Mostrar SOLO el % de satisfacción de la DIVISIÓN (tarjeta scope=division).
//   * Las ÁREAS y sus % se ven en el Ranking de áreas (sin duplicados).
// - JEFE DE ÁREA (id_jf_area) y NO jefe de división:
//   * Mostrar SOLO su ÁREA (una tarjeta scope=area).
// - Usuario NORMAL (no jefe de división ni de área):
//   * Mostrar SOLO su % PERSONAL (tarjeta scope=personal).
//
// Además:
// - Se deduplican tarjetas para evitar áreas repetidas.

// Lowercase seguro (mbstring opcional)
$toLower = function(string $s): string {
  $s = trim($s);
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
};

// --------------------------------------------------
// A) DEDUP de tarjetas (evita repetidos por área/división/personal)
// --------------------------------------------------
$uniqueCards = [];
$seenCards   = [];

foreach ($cards as $c) {

  if (!is_array($c)) continue;

  $scope = (string)($c['scope'] ?? 'personal');

  if ($scope === 'area') {
    $aid  = (int)($c['areaId'] ?? 0);
    $tkey = $toLower((string)($c['title'] ?? ''));
    $key  = ($aid > 0) ? ("area:$aid") : ("area_title:$tkey");
  }
  elseif ($scope === 'division') {
    $did  = (int)($c['divisionId'] ?? 0);
    $tkey = $toLower((string)($c['title'] ?? ''));
    $key  = ($did > 0) ? ("division:$did") : ("division_title:$tkey");
  }
  else {
    // Personal: solo una tarjeta
    $key = "personal:1";
  }

  if (isset($seenCards[$key])) {
    continue;
  }

  $seenCards[$key] = true;
  $uniqueCards[]   = $c;
}

$cards = array_values($uniqueCards);

// --------------------------------------------------
// B) Detectar rol (jefe de división / jefe de área)
// --------------------------------------------------
$hasDivisionRole = false;
$hasAreaRole     = false;

// Señales explícitas desde payload (si el Service las manda)
$divisionRoleKeys = ['id_jf_division','is_jf_division','jf_division','jefe_division','idJfDivision','isJfDivision'];
foreach ($divisionRoleKeys as $k) {
  if (!empty($payload[$k])) { $hasDivisionRole = true; break; }
}

$areaRoleKeys = ['id_jf_area','is_jf_area','jf_area','jefe_area','idJfArea','isJfArea'];
foreach ($areaRoleKeys as $k) {
  if (!empty($payload[$k])) { $hasAreaRole = true; break; }
}

// Señales implícitas por tarjetas / ranking
$divisionIdx = null;
$personalIdx = null;
$areaIdxs    = [];

foreach ($cards as $i => $c) {

  $sc = (string)($c['scope'] ?? 'personal');

  if ($sc === 'division' && $divisionIdx === null) $divisionIdx = $i;
  if ($sc === 'personal' && $personalIdx === null) $personalIdx = $i;
  if ($sc === 'area') $areaIdxs[] = $i;
}

// Si existe tarjeta de división o ranking_areas, asumimos rol de división
if ($divisionIdx !== null) $hasDivisionRole = true;

if (
  (isset($payload['ranking_areas']) && is_array($payload['ranking_areas']) && !empty($payload['ranking_areas'])) ||
  (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas'])  && !empty($payload['rankingAreas'])) ||
  (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas']) && !empty($payload['rankings']['areas']))
) {
  $hasDivisionRole = true;
}

// Si existe alguna tarjeta de área, asumimos rol de área (si no lo sabíamos)
if (!empty($areaIdxs)) $hasAreaRole = true;

// --------------------------------------------------
// C) Aplicar vista final según rol (prioridad: división > área > personal)
// --------------------------------------------------
if ($hasDivisionRole) {

  // ✅ JEFE DE DIVISIÓN:
  // - Mostrar el % de satisfacción GLOBAL de la DIVISIÓN (tarjeta scope=division).
  // - Además mostrar TODAS las ÁREAS (una tarjeta por área, sin repetidos).
  // - Las áreas también se siguen viendo en el Ranking de áreas dentro de la tarjeta de división (como estaba antes).
  // - NO se muestra el % PERSONAL aquí, para que no mezclemos roles.

  $newCards = [];

  // --------------------------------------------------
  // 1) Tarjeta de DIVISIÓN (tu indicador global)
  // --------------------------------------------------
  if ($divisionIdx !== null) {

    // Aclaración solicitada:
    // "Mi porcentaje de satisfacción" es el KPI global de la división
    $oldSub = (string)($cards[$divisionIdx]['subtitle'] ?? '');
    $cards[$divisionIdx]['subtitle'] = trim(
      'Mi porcentaje de satisfacción' . ($oldSub !== '' ? (' · ' . $oldSub) : '')
    );

    $newCards[] = $cards[$divisionIdx];
  }

  // --------------------------------------------------
  // 2) Tarjetas de ÁREAS (si ya vienen como cards)
  //    - Ya vienen deduplicadas arriba, pero igual las mapeamos por seguridad.
  // --------------------------------------------------
  $areaCards = [];
  $areaSeen  = [];

  foreach ($cards as $c) {

    if (!is_array($c)) continue;
    if ((string)($c['scope'] ?? '') !== 'area') continue;

    $aid  = (int)($c['areaId'] ?? 0);
    $tkey = $toLower((string)($c['title'] ?? ''));

    $key = ($aid > 0) ? ("area:$aid") : ("area_title:$tkey");

    if (isset($areaSeen[$key])) {
      continue;
    }

    $areaSeen[$key] = true;
    $areaCards[] = $c;
  }

  // --------------------------------------------------
  // 3) Si NO vinieron cards de áreas (o faltan), armarlas desde ranking_areas
  //    Esto asegura que SIEMPRE veas todas las áreas con su % (sin duplicados).
  // --------------------------------------------------
  $rankAreasRaw = [];

  if (isset($payload['ranking_areas']) && is_array($payload['ranking_areas'])) $rankAreasRaw = $payload['ranking_areas'];
  if (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas']))  $rankAreasRaw = $payload['rankingAreas'];
  if (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas'])) $rankAreasRaw = $payload['rankings']['areas'];

  foreach ((array)$rankAreasRaw as $ra) {

    if (!is_array($ra)) continue;

    $aid = (int)($ra['area_id'] ?? ($ra['id_area'] ?? 0));

    $aname = (string)($ra['area'] ?? ($ra['nombre_area'] ?? ($ra['area_name'] ?? 'Área')));
    $aname = trim($aname);

    $tkey = $toLower($aname);
    $key  = ($aid > 0) ? ("area:$aid") : ("area_title:$tkey");

    // Si ya existe como card, no la repetimos
    if (isset($areaSeen[$key])) {
      continue;
    }

    $done = (int)($ra['realizadas'] ?? 0);
    $not  = (int)($ra['no_realizadas'] ?? 0);

    // Creamos un "bloque" compatible para pasarlo por normalizeCard()
    $synthetic = [
      'titulo'       => 'Satisfacción Área: ' . ($aname !== '' ? $aname : 'Área'),
      'subtitle'     => 'Resumen semanal',
      'scope'        => 'area',
      'area_id'      => $aid,
      'porcentaje'   => ($ra['porcentaje'] ?? null),
      'realizadas'   => $done,
      'no_realizadas'=> $not,
      // Mantener fechas, si existen
      'inicio'       => (string)($payload['inicio'] ?? ''),
      'fin'          => (string)($payload['fin'] ?? ''),
    ];

    $areaCards[] = $normalizeCard($synthetic);
    $areaSeen[$key] = true;
  }

  // Ordenar áreas por % desc (como tu regla original)
  usort($areaCards, function($a, $b) {
    return ((float)($b['porcentaje'] ?? 0) <=> (float)($a['porcentaje'] ?? 0));
  });

  // --------------------------------------------------
  // 4) Resultado final: División + Áreas
  // --------------------------------------------------
  $cards = array_values(array_merge($newCards, $areaCards));
}
elseif ($hasAreaRole) {

  // ✅ SOLO mi área (una tarjeta)
  $targetAreaId = 0;
  if (!empty($payload['id_jf_area'])) $targetAreaId = (int)$payload['id_jf_area'];

  $chosen = null;

  // 1) Preferir la que coincide con id_jf_area
  if ($targetAreaId > 0) {
    foreach ($cards as $c) {
      if ((string)($c['scope'] ?? '') === 'area' && (int)($c['areaId'] ?? 0) === $targetAreaId) {
        $chosen = $c;
        break;
      }
    }
  }

  // 2) Si no, escoger la primera de área
  if ($chosen === null) {
    foreach ($cards as $c) {
      if ((string)($c['scope'] ?? '') === 'area') { $chosen = $c; break; }
    }
  }

  // 3) Fallback personal si no hay área
  if ($chosen === null && $personalIdx !== null) {
    $chosen = $cards[$personalIdx];
  }

  $cards = ($chosen !== null) ? [$chosen] : [];
}
else {

  // ✅ SOLO personal
  $newCards = [];

  if ($personalIdx !== null) {
    $newCards[] = $cards[$personalIdx];
  }
  // Fallback: si no existe "personal", tomar la primera
  elseif (!empty($cards)) {
    $newCards[] = $cards[0];
  }

  $cards = array_values($newCards);
}

// --------------------------------------------------
// 2) Ordenar tarjetas (mantener lógica + mejorar lectura)
//    - Si existe tarjeta de DIVISIÓN, va primero.
//    - Luego orden por porcentaje DESC (como tu regla original).
// --------------------------------------------------
usort($cards, function($a, $b) {

  $sa = (string)($a['scope'] ?? '');
  $sb = (string)($b['scope'] ?? '');

  // Prioridad: la tarjeta "division" siempre arriba
  if ($sa === 'division' && $sb !== 'division') return -1;
  if ($sb === 'division' && $sa !== 'division') return  1;

  // Resto: por porcentaje desc
  return ((float)($b['porcentaje'] ?? 0) <=> (float)($a['porcentaje'] ?? 0));
});

// --------------------------------------------------
// 3) Ranking/Comparativas (acepta varios nombres)
// --------------------------------------------------
$rankingAreas = [];
if (isset($payload['ranking_areas']) && is_array($payload['ranking_areas'])) $rankingAreas = $payload['ranking_areas'];
if (isset($payload['rankingAreas'])  && is_array($payload['rankingAreas']))  $rankingAreas = $payload['rankingAreas'];
if (isset($payload['rankings']['areas']) && is_array($payload['rankings']['areas'])) $rankingAreas = $payload['rankings']['areas'];

// Ranking de usuarios por área
$rankingUsersByArea = [];
if (isset($payload['ranking_users_by_area']) && is_array($payload['ranking_users_by_area'])) $rankingUsersByArea = $payload['ranking_users_by_area'];
if (isset($payload['rankingUsersByArea'])    && is_array($payload['rankingUsersByArea']))    $rankingUsersByArea = $payload['rankingUsersByArea'];
if (isset($payload['rankings']['users_by_area']) && is_array($payload['rankings']['users_by_area'])) $rankingUsersByArea = $payload['rankings']['users_by_area'];

// Histórico global opcional
$historyGlobal = $normalizeHistory($payload['history_global'] ?? ($payload['historyGlobal'] ?? []));

// --------------------------------------------------
// 4) Normalizar rankingAreas para UI (porcentaje seguro + sort)
//    ✅ OJO: aquí también aplicamos normalizePercent (por si viene "85%")
// --------------------------------------------------
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


// --------------------------------------------------
// 4.1) ✅ DEDUP de ranking de áreas (evita filas repetidas)
// - si se repite el mismo area_id (o el mismo nombre), nos quedamos con la mejor fila
// --------------------------------------------------
$uniqueRank = [];
$seenRank   = [];

foreach ($rankingAreasUi as $ra) {

  $aid = (int)($ra['area_id'] ?? 0);
  $nm  = $toLower((string)($ra['area'] ?? ''));

  $key = ($aid > 0) ? ("id:$aid") : ("name:$nm");

  // Si no existe, lo guardamos
  if (!isset($seenRank[$key])) {
    $seenRank[$key] = count($uniqueRank);
    $uniqueRank[] = $ra;
    continue;
  }

  // Si existe, mantenemos el de mayor porcentaje (por seguridad)
  $pos = (int)$seenRank[$key];
  if ((float)($ra['porcentaje'] ?? 0) > (float)($uniqueRank[$pos]['porcentaje'] ?? 0)) {
    $uniqueRank[$pos] = $ra;
  }
}

$rankingAreasUi = $uniqueRank;
usort($rankingAreasUi, fn($a,$b) => $b['porcentaje'] <=> $a['porcentaje']);

// --------------------------------------------------
// Helper UI: color barra (sobrio, rojo/azul)
// - si condición está en INEXISTENCIA/PELIGRO/EMERGENCIA => rojo
// - si no => azul oscuro
// --------------------------------------------------
$getBarColor = function(float $p) use ($getCondition): string {
  $cond = $getCondition($p);
  $isCrit = in_array($cond, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true);
  return $isCrit ? '#E10600' : '#023059';
};

// --------------------------------------------------
// Helper UI: badge según condición (SIN verde)
// --------------------------------------------------
$getBadgeHtml = function(float $p) use ($getCondition): string {

  $cond = $getCondition($p);

  // Críticos: rojo
  if (in_array($cond, ['INEXISTENCIA','PELIGRO','EMERGENCIA'], true)) {
    return '<span class="badge bg-danger">'.$cond.'</span>';
  }

  // Normal / Afluencia: negro
  return '<span class="badge bg-dark">'.$cond.'</span>';
};
?>

<style>
  /* =========================================================
     Estilos: blanco/negro/rojo - compacto - sin neón
     ========================================================= */
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

  .sat-title{
    font-weight: 900;
    margin-bottom: 4px;
    color:#0B0B0B;
  }

  .sat-sub{
    color:#555;
    font-size:.92rem;
  }

  /* Círculo */
  .circle-wrapper{
    position:relative;
    width:220px;
    height:220px;
    margin:auto;
  }

  .circle-center{
    position:absolute;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    text-align:center;
  }

  .circle-percent{
    font-size:2.8rem;
    font-weight:900;
    color:#000;
    line-height:1;
  }

  .circle-subtitle{
    margin-top:6px;
    font-size:.9rem;
    color:#333;
  }

  .semana-text{
    text-align:center;
    margin-top:10px;
    font-size:.92rem;
    color:#555;
  }

  .stat-box{
    background:#f8f9fa;
    border-radius:14px;
    padding:14px 16px;
    min-width:160px;
    text-align:center;
    border: 1px solid rgba(0,0,0,.08);
  }

  .stat-box strong{
    font-size:1.7rem;
    font-weight:900;
    line-height:1.1;
  }

  .sat-foot{
    margin-top: 12px;
    font-size: .9rem;
    color: #555;
    text-align: center;
  }

  /* Ranking */
  .rank-card{
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,.10);
    background: #fff;
  }

  .rank-title{
    font-weight: 900;
    color:#0B0B0B;
    margin:0;
  }

  .rank-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:10px 12px;
    border-top:1px solid rgba(0,0,0,.06);
  }

  .rank-row:first-child{ border-top:none; }

  .rank-name{
    font-weight:700;
    color:#111;
    margin:0;
    font-size:.95rem;
  }

  .rank-meta{
    font-size:.85rem;
    color:#666;
    margin:0;
  }

  .mini-bar{
    height:10px;
    border-radius: 10px;
    background: rgba(0,0,0,.10);
    overflow:hidden;
    width: 160px;
    flex: 0 0 160px;
  }

  .mini-bar > div{
    height:100%;
    border-radius:10px;
  }

  /* Histórico por semanas (barras compactas) */
  .hist-row{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    border-top:1px solid rgba(0,0,0,.06);
  }

  .hist-row:first-child{ border-top:none; }

  .hist-label{
    width: 170px;
    font-size:.85rem;
    color:#333;
    flex: 0 0 170px;
  }

  .hist-bar{
    flex: 1 1 auto;
    height:10px;
    border-radius: 10px;
    background: rgba(0,0,0,.10);
    overflow:hidden;
  }

  .hist-bar > div{
    height:100%;
    border-radius:10px;
  }

  .hist-val{
    width: 70px;
    text-align:right;
    font-weight:800;
    font-size:.88rem;
    color:#111;
    flex: 0 0 70px;
  }
</style>

<div class="container sat-wrap">

  <!-- Header superior con botón volver -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="m-0 fw-bold" style="color:#0B0B0B;">Satisfacción</h2>
      <div class="text-muted" style="font-size:.92rem;">
        Semana <strong><?= esc((string)($payload['inicio'] ?? ($cards[0]['inicio'] ?? ''))) ?></strong>
        → <strong><?= esc((string)($payload['fin'] ?? ($cards[0]['fin'] ?? ''))) ?></strong>
        (Jueves → Miércoles)
      </div>
    </div>

    <a href="<?= esc(previous_url()) ?>" class="btn btn-outline-dark">
      ← Volver
    </a>
  </div>

  <!-- =========================================================
       TARJETAS (ordenadas desc por porcentaje)
       ========================================================= -->
  <?php foreach ($cards as $idx => $it): ?>
    <?php
      // ID único del gradiente para que no choque entre tarjetas
      $gradId = 'innerGrad_' . $idx;

      // Clase crítica (por regla antigua: <50)
      $cardClass = $it['isCritical'] ? 'critical' : '';

      // Color barra comparativa (según condición)
      $barColor = $getBarColor((float)$it['porcentaje']);
    ?>

    <div class="mb-4">

      <!-- Título + badge condición -->
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
            <div class="circle-percent"><?= number_format((float)$it['porcentaje'], 1) ?>%</div>
            <div class="circle-subtitle">Satisfacción</div>
          </div>
        </div>

        <!-- BARRA COMPARATIVA (debajo del círculo) -->
        <div class="mt-2">
          <div class="progress" style="height:10px; border-radius:10px; background: rgba(0,0,0,.12);">
            <div class="progress-bar"
                 role="progressbar"
                 style="width: <?= (float)$it['porcentaje'] ?>%;
                        background-color: <?= esc($barColor) ?>;">
            </div>
          </div>

          <!-- Promedio 4 semanas (si existe) -->
          <?php if ($it['avg4'] !== null): ?>
            <div class="text-center mt-2" style="font-size:.90rem; color:#333;">
              Promedio últimas 4 semanas:
              <strong><?= number_format((float)$it['avg4'], 2) ?>%</strong>
            </div>
          <?php endif; ?>
        </div>

        <!-- SEMANA -->
        <div class="semana-text">
          Semana <?= esc($it['inicio']) ?> → <?= esc($it['fin']) ?>
        </div>

        <!-- CONTADORES -->
        <div class="d-flex justify-content-center gap-3 gap-md-4 flex-wrap mt-3">

          <div class="stat-box">
            <strong style="color:#023059;">
              <?= (int)$it['realizadas'] ?>
            </strong><br>
            <small>Realizadas</small>
          </div>

          <div class="stat-box">
            <strong style="color:#E10600;">
              <?= (int)$it['no_realizadas'] ?>
            </strong><br>
            <small>No realizadas</small>
          </div>

        </div>

        <!-- =========================================================
             HISTÓRICO POR SEMANAS (si viene en la tarjeta)
             ========================================================= -->
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
                  <div class="hist-val"><?= number_format($hv, 1) ?>%</div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- =========================================================
             RANKINGS (depende del scope)
             ========================================================= -->

        <?php if ($it['scope'] === 'division'): ?>
          <?php if (!empty($rankingAreasUi)): ?>
            <div class="mt-4">
              <div class="rank-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <p class="rank-title">Ranking de áreas (División vs Áreas)</p>
                  <small class="text-muted">Ordenado por %</small>
                </div>

                <?php
                  // Comparativa: % de la división (esta tarjeta)
                  $divisionPct = (float)$it['porcentaje'];
                ?>

                <!-- Línea comparativa de la división -->
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
                    <?= number_format($divisionPct, 1) ?>%
                  </div>
                </div>

                <!-- Áreas -->
                <?php foreach ($rankingAreasUi as $pos => $ra): ?>
                  <?php
                    $p = (float)$ra['porcentaje'];
                    $c = $getBarColor($p);
                  ?>
                  <div class="rank-row">
                    <div style="min-width: 220px;">
                      <p class="rank-name">
                        <?= esc(($pos+1) . '. ' . (string)$ra['area']) ?>
                        <?= $getBadgeHtml($p) ?>
                      </p>
                      <p class="rank-meta">
                        <?= (int)$ra['realizadas'] ?> realizadas · <?= (int)$ra['no_realizadas'] ?> no realizadas
                      </p>
                    </div>

                    <div class="mini-bar">
                      <div style="width: <?= $p ?>%; background: <?= esc($c) ?>;"></div>
                    </div>

                    <div style="width: 70px; text-align:right; font-weight:900;">
                      <?= number_format($p, 1) ?>%
                    </div>
                  </div>
                <?php endforeach; ?>

              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($it['scope'] === 'area'): ?>
          <?php
            // Ranking usuarios del área actual
            $areaId = (int)$it['areaId'];

            $usersRank = [];
            if ($areaId > 0 && isset($rankingUsersByArea[$areaId]) && is_array($rankingUsersByArea[$areaId])) {
              $usersRank = $rankingUsersByArea[$areaId];
            }

            // Normalizar ranking usuarios (porcentaje + nombre)
            $usersRankUi = [];
            foreach ($usersRank as $u) {
              if (!is_array($u)) continue;

              $uid = (int)($u['user_id'] ?? ($u['id_user'] ?? 0));
              $nm  = (string)($u['nombre'] ?? ($u['name'] ?? 'Usuario #' . $uid));

              $uDone = (int)($u['realizadas'] ?? 0);
              $uNot  = (int)($u['no_realizadas'] ?? 0);

              // ✅ Porcentaje también normalizado (por si viene string o 0..1)
              $uPct = $normalizePercent(($u['porcentaje'] ?? null), $uDone, $uNot);

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
                  <?php
                    $p = (float)$u['porcentaje'];
                    $c = $getBarColor($p);
                  ?>
                  <div class="rank-row">
                    <div style="min-width: 220px;">
                      <p class="rank-name">
                        <?= esc(($pos+1) . '. ' . $u['nombre']) ?>
                        <?= $getBadgeHtml($p) ?>
                      </p>
                      <p class="rank-meta">
                        <?= (int)$u['realizadas'] ?> realizadas · <?= (int)$u['no_realizadas'] ?> no realizadas
                      </p>
                    </div>

                    <div class="mini-bar">
                      <div style="width: <?= $p ?>%; background: <?= esc($c) ?>;"></div>
                    </div>

                    <div style="width: 70px; text-align:right; font-weight:900;">
                      <?= number_format($p, 1) ?>%
                    </div>
                  </div>
                <?php endforeach; ?>

              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Footer -->
        <div class="sat-foot">
          Si algún dato está erróneo, comuníquese con el administrador.
        </div>

      </div>
    </div>
  <?php endforeach; ?>

  <!-- =========================================================
       HISTÓRICO GLOBAL (opcional)
       ========================================================= -->
  <?php if (!empty($historyGlobal)): ?>
    <div class="mb-4">
      <div class="rank-card p-3">
        <div class="d-flex align-items-center justify-content-between">
          <p class="rank-title">Promedio histórico general</p>
          <small class="text-muted">Últimas 4 + actual</small>
        </div>

        <?php foreach ($historyGlobal as $h): ?>
          <?php
            $hv = (float)($h['value'] ?? 0);
            $hc = $getBarColor($hv);
          ?>
          <div class="hist-row">
            <div class="hist-label"><?= esc((string)($h['label'] ?? '')) ?></div>
            <div class="hist-bar">
              <div style="width: <?= $hv ?>%; background: <?= esc($hc) ?>;"></div>
            </div>
            <div class="hist-val"><?= number_format($hv, 1) ?>%</div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->endSection() ?>
