<?= $this->extend('layouts/main') ?> 

<?= $this->section('styles') ?>
<?= view('pages/_partials/estilos_metricas', ['part' => 'css']) ?>

<style>
.metrics-grid{
  display:grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap:14px;
  align-items:stretch;
}
@media (min-width: 768px){
  .metrics-grid:not(.single){ grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1200px){
  .metrics-grid:not(.single){ grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.metrics-grid.single{ grid-template-columns: 1fr; }

.metric-card{
  background:#fff;
  border:1px solid rgba(0,0,0,.14);
  border-radius:16px;
  overflow:hidden;
  box-shadow:0 10px 20px rgba(0,0,0,.08);
  display:flex;
  flex-direction:column;
  min-height: 420px;
}
.metric-head{
  padding:14px 16px;
  background:#0B0B0B;
  color:#fff;
  border-bottom:3px solid #E10600;
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.metric-title{ margin:0; font-size:15px; font-weight:900; letter-spacing:.2px; }
.metric-sub{ margin-top:4px; font-size:12px; opacity:.85; }
.metric-badge{
  font-size:11px;
  font-weight:800;
  padding:6px 10px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,.25);
  background:rgba(255,255,255,.10);
  white-space:nowrap;
}
.metric-body{
  padding:14px 16px;
  display:flex;
  flex-direction:column;
  gap:10px;
  flex:1;
}

.kpi-row{ display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; }
@media (min-width: 1200px){
  .metrics-grid.single .kpi-row{ grid-template-columns: repeat(4, minmax(0,1fr)); }
}
.kpi{
  background:rgba(0,0,0,.04);
  border:1px solid rgba(0,0,0,.10);
  border-radius:14px;
  padding:10px 12px;
}
.kpi small{ color:rgba(0,0,0,.65); }
.kpi b{ font-size:16px; }

.chart-area{
  position: relative;
  width: 100%;
  height: 260px;
  max-height: 260px;
  border-radius:14px;
  border:1px solid rgba(0,0,0,.10);
  overflow:hidden;
  background:#fff;
}
.metrics-grid.single .chart-area{ height: 380px; max-height: 380px; }
.chart-area canvas{ width:100% !important; height:100% !important; }
.chart-foot{ color:rgba(0,0,0,.55); font-size:12px; }

/* FILTRO compacto */
.filter-card{
  background:#fff;
  border:1px solid rgba(0,0,0,.14);
  border-radius:16px;
  box-shadow:0 10px 20px rgba(0,0,0,.06);
  padding:10px 12px;
}
.filter-title{ font-weight:900; margin:0 0 6px 0; font-size:13px; letter-spacing:.2px; }
.filter-row{ display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
.filter-row .group{ min-width: 150px; }
.filter-card .form-label{ font-size:12px; margin-bottom:4px !important; }
.filter-card .form-control, .filter-card .form-select{
  padding:6px 10px; font-size:13px; border-radius:12px;
}
.filter-actions{ display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
.filter-actions .btn{ padding:6px 10px; font-size:13px; border-radius:12px; }
.filter-help{ margin-top:6px !important; font-size:12px; color:rgba(0,0,0,.55); }

/* PRINT */
@page{ size: landscape; margin: 10mm; }
#printPaper{ display:none; }

@media print{
  /* 1) apagar todo */
  body *{ display:none !important; }

  /* 2) prender SOLO el papel */
  #printPaper{
    display:block !important;
  }

  /* ✅ CLAVE: prender también sus HIJOS */
  #printPaper *{
    display: revert !important; /* si tu navegador no soporta revert, abajo hay fallback */
  }

  /* fallback por si algún navegador no soporta revert */
  #printPaper .paper-wrap,
  #printPaper .paper-head,
  #printPaper .paper-kpis,
  #printPaper .paper-chart{
    display:block !important;
  }
  #printPaper .paper-meta{
    display:flex !important;
  }
  #printPaper .paper-pill{
    display:inline-flex !important;
  }

  #printPaper, #printPaper *{
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .paper-wrap{ width:100% !important; display:flex !important; flex-direction:column !important; gap:10px !important; }

  .paper-head{
    display:flex !important;
    align-items:flex-start !important;
    justify-content:space-between !important;
    gap:12px !important;
    border-bottom: 2px solid #E10600 !important;
    padding-bottom: 8px !important;
  }

  .paper-title{
    margin:0 !important;
    font-size: 18px !important;
    font-weight: 900 !important;
    color:#0B0B0B !important;
  }

  .paper-meta{
    margin-top: 6px !important;
    display:flex !important;
    flex-wrap:wrap !important;
    gap:8px !important;
    align-items:center !important;
    font-size: 12px !important;
    color: rgba(0,0,0,.78) !important;
  }
  .paper-pill{
    display:inline-flex !important;
    align-items:center !important;
    gap:6px !important;
    padding: 5px 10px !important;
    border-radius: 999px !important;
    border: 1px solid rgba(0,0,0,.15) !important;
    background: rgba(0,0,0,.04) !important;
    font-weight: 800 !important;
  }
  .paper-pill b{ font-weight: 900 !important; }

  .paper-badge{
    font-size: 12px !important;
    font-weight: 900 !important;
    padding: 6px 10px !important;
    border-radius: 999px !important;
    border: 1px solid rgba(0,0,0,.15) !important;
    background: rgba(0,0,0,.04) !important;
    white-space: nowrap !important;
  }

  .paper-kpis{
    display:grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap:10px !important;
  }

  .paper-kpi{
    border:1px solid rgba(0,0,0,.12) !important;
    border-radius: 10px !important;
    padding: 8px 10px !important;
  }
  .paper-kpi small{ color: rgba(0,0,0,.65) !important; }
  .paper-kpi b{ font-size: 16px !important; }

  .paper-chart{
    border: 1px solid rgba(0,0,0,.12) !important;
    border-radius: 12px !important;
    overflow:hidden !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    height: 150mm !important;
  }

  .paper-chart img{
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
    display:block !important;
  }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php
$isGerencia           = (bool)($isGerencia ?? false);
$filterFrom           = (string)($filterFrom ?? '');
$filterTo             = (string)($filterTo ?? '');

$selectedDivisionId   = (int)($selectedDivisionId ?? 0);
$selectedDivisionName = trim((string)($selectedDivisionName ?? ''));

$userFullName         = trim((string)($userFullName ?? 'Usuario'));

$allDivisions          = $allDivisions ?? [];
$divisionCards         = $divisionCards ?? [];

// Normal
$avgWeek     = (float)($avgWeek ?? 0);
$bestWeek    = (float)($bestWeek ?? 0);
$worstWeek   = (float)($worstWeek ?? 0);
$weeksCount  = (int)($weeksCount ?? 0);
$chartLabels = $chartLabels ?? [];
$chartValues = $chartValues ?? [];

// Armar indicadores
$indicators = [];
if ($isGerencia) {
  foreach ($divisionCards as $c) {
    $indicators[] = [
      'divisionId' => (int)($c['divisionId'] ?? 0),
      'title'      => 'División: ' . (string)($c['divisionName'] ?? 'N/D'),
      'avgWeek'    => (float)($c['avgWeek'] ?? 0),
      'bestWeek'   => (float)($c['bestWeek'] ?? 0),
      'worstWeek'  => (float)($c['worstWeek'] ?? 0),
      'weeksCount' => (int)($c['weeksCount'] ?? 0),
      'labels'     => $c['chartLabels'] ?? [],
      'values'     => $c['chartValues'] ?? [],
      'divisionName' => (string)($c['divisionName'] ?? ''), // ✅ extra
    ];
  }
} else {
  $indicators[] = [
    'divisionId' => $selectedDivisionId,
    'title'      => 'Mi indicador',
    'avgWeek'    => $avgWeek,
    'bestWeek'   => $bestWeek,
    'worstWeek'  => $worstWeek,
    'weeksCount' => $weeksCount,
    'labels'     => $chartLabels,
    'values'     => $chartValues,
    'divisionName' => $selectedDivisionName, // ✅ extra
  ];
}

$cardsCount = count($indicators);
$isSingle   = ($cardsCount === 1);
$gridClass  = 'metrics-grid' . ($isSingle ? ' single' : '');

$bandLabel = function(float $p): string {
  if ($p <= 20) return 'INEXISTENCIA';
  if ($p <= 39) return 'PELIGRO';
  if ($p <= 69) return 'EMERGENCIA';
  if ($p <= 89) return 'NORMAL';
  return 'AFLUENCIA';
};

// ✅ Mapa PHP: id_division -> nombre_division
$divisionMap = [];
foreach ($allDivisions as $d) {
  $did = (int)($d['id_division'] ?? 0);
  if ($did > 0) $divisionMap[$did] = (string)($d['nombre_division'] ?? '');
}
?>

<div class="container py-3">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h3 class="mb-0 fw-bold">
      <?= $isGerencia ? 'Indicadores por División' : 'Indicador Personal' ?>
    </h3>
  </div>

  <div class="filter-card mb-3">
    <p class="filter-title">Rango de consulta</p>

    <form method="get" action="<?= site_url('home') ?>">
      <div class="filter-row">
        <div class="group">
          <label class="form-label fw-semibold">Desde</label>
          <input type="date" name="from" class="form-control" value="<?= esc($filterFrom) ?>" required>
        </div>

        <div class="group">
          <label class="form-label fw-semibold">Hasta</label>
          <input type="date" name="to" class="form-control" value="<?= esc($filterTo) ?>" required>
        </div>

        <?php if ($isGerencia): ?>
          <div class="group">
            <label class="form-label fw-semibold">División</label>
            <select name="division_id" class="form-select">
              <option value="0" <?= $selectedDivisionId === 0 ? 'selected' : '' ?>>Todas</option>
              <?php foreach ($allDivisions as $d):
                $did = (int)($d['id_division'] ?? 0);
                $dname = (string)($d['nombre_division'] ?? '');
                if ($did <= 0) continue;
              ?>
                <option value="<?= esc($did) ?>" <?= $selectedDivisionId === $did ? 'selected' : '' ?>>
                  <?= esc($dname) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="filter-actions">
          <button type="submit" class="btn btn-dark">Aplicar</button>
          <a class="btn btn-outline-dark" href="<?= site_url('home') ?>">Limpiar</a>
        </div>
      </div>

      <div class="filter-help">
        <?= $isGerencia
          ? 'Se mostrará el promedio semanal por división dentro del rango. (Puedes filtrar una división.)'
          : 'Se mostrará tu historial semanal dentro del rango.' ?>
      </div>
    </form>
  </div>

  <div class="<?= esc($gridClass) ?>">
    <?php foreach ($indicators as $i => $c):

      $title  = (string)($c['title'] ?? ('Indicador #' . ($i+1)));
      $avg    = (float)($c['avgWeek'] ?? 0);
      $best   = (float)($c['bestWeek'] ?? 0);
      $worst  = (float)($c['worstWeek'] ?? 0);
      $weeks  = (int)($c['weeksCount'] ?? 0);
      $labels = $c['labels'] ?? [];
      $values = $c['values'] ?? [];

      $band   = $bandLabel($avg);

      $chartId = 'lineChart_' . $i;
      $jsonId  = $chartId . '_data';

      // ✅ divisionId correcto
      $divisionIdForPrint = (int)($c['divisionId'] ?? 0);
      if ($isGerencia && $selectedDivisionId > 0) {
        $divisionIdForPrint = $selectedDivisionId;
      }

      // ✅ Nombre de división: SIEMPRE resuelto por MATCH (sin historico)
      // 1) si viene del filtro (controlador)
      // 2) si no, por mapa PHP (para Todas)
      // 3) si no, N/D
      $divisionNameForPrint = '';
      if ($isGerencia && $selectedDivisionId > 0 && $selectedDivisionName !== '') {
        $divisionNameForPrint = $selectedDivisionName;
      } else {
        if ($divisionIdForPrint > 0) {
          $divisionNameForPrint = trim((string)($divisionMap[$divisionIdForPrint] ?? ''));
        }
      }
      if ($divisionNameForPrint === '') {
        // fallback si el card trae su nombre
        $divisionNameForPrint = trim((string)($c['divisionName'] ?? ''));
      }
      if ($divisionNameForPrint === '') $divisionNameForPrint = 'N/D';
    ?>
      <div class="metric-card">
        <div class="metric-head">
          <div>
            <h4 class="metric-title"><?= esc($title) ?></h4>
            <div class="metric-sub">Línea de tiempo semanal (%) — Jue → Mié</div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <span class="metric-badge"><?= esc($band) ?></span>

            <button type="button"
                    class="btn btn-sm btn-outline-light js-print-card"
                    data-title="<?= esc($title) ?>"
                    data-user="<?= esc($userFullName) ?>"
                    data-division-id="<?= esc($divisionIdForPrint) ?>"
                    data-division-name="<?= esc($divisionNameForPrint) ?>"
                    data-band="<?= esc($band) ?>"
                    data-avg="<?= esc(number_format($avg, 1)) ?>"
                    data-best="<?= esc(number_format($best, 1)) ?>"
                    data-worst="<?= esc(number_format($worst, 1)) ?>"
                    data-weeks="<?= esc($weeks) ?>"
                    data-canvas="<?= esc($chartId) ?>">
              Imprimir
            </button>
          </div>
        </div>

        <div class="metric-body">
          <div class="kpi-row">
            <div class="kpi"><small>Promedio</small><br><b><?= esc(number_format($avg, 1)) ?>%</b></div>
            <div class="kpi"><small>Mejor semana</small><br><b><?= esc(number_format($best, 1)) ?>%</b></div>
            <div class="kpi"><small>Peor semana</small><br><b><?= esc(number_format($worst, 1)) ?>%</b></div>
            <div class="kpi"><small>Semanas</small><br><b><?= esc($weeks) ?></b></div>
          </div>

          <div class="chart-area">
            <canvas id="<?= esc($chartId) ?>"></canvas>
          </div>

          <div class="chart-foot">
            <?= $weeks > 0 ? 'Datos encontrados dentro del rango.' : 'Sin datos en el rango seleccionado.' ?>
          </div>

          <script type="application/json" id="<?= esc($jsonId) ?>">
            <?= json_encode(['labels'=>$labels, 'values'=>$values], JSON_UNESCAPED_UNICODE) ?>
          </script>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>

<div id="printMount">
  <div id="printPaper">
    <div class="paper-wrap" id="paperWrap"></div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('pages/_partials/estilos_metricas', ['part' => 'js']) ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
window.__WS_CHARTS__ = window.__WS_CHARTS__ || {};

if (!window.__WS_PRINT_BOUND__) {
  window.__WS_PRINT_BOUND__ = true;

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-print-card');
    if (!btn) return;
    e.preventDefault();
    handlePrint(btn);
  });
}

function getBandLabel(p){
  p = Number(p || 0);
  if (p <= 20) return 'INEXISTENCIA';
  if (p <= 39) return 'PELIGRO';
  if (p <= 69) return 'EMERGENCIA';
  if (p <= 89) return 'NORMAL';
  return 'AFLUENCIA';
}
function getBandColor(p){
  p = Number(p || 0);
  if (p <= 20) return 'rgba(225, 6, 0, 0.95)';
  if (p <= 39) return 'rgba(160, 0, 0, 0.95)';
  if (p <= 69) return 'rgba(213, 207, 93, 0.95)';
  if (p <= 89) return 'rgba(4, 157, 191, 0.95)';
  return 'rgba(2, 48, 89, 0.95)';
}
function escapeHtml(str){
  return String(str || '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}
function waitImagesLoaded(root){
  const imgs = Array.from(root.querySelectorAll('img'));
  if (imgs.length === 0) return Promise.resolve();
  return Promise.all(imgs.map(img => {
    if (img.complete) return Promise.resolve();
    return new Promise(res => { img.onload = img.onerror = () => res(); });
  }));
}
function waitFrames(n=2){
  return new Promise(resolve => {
    const step = () => (n-- <= 0) ? resolve() : requestAnimationFrame(step);
    requestAnimationFrame(step);
  });
}

const bandsAndWatermarkPlugin = {
  id: 'bandsAndWatermarkPlugin',
  beforeDraw(chart) {
    const { ctx, chartArea, scales } = chart;
    if (!chartArea || !scales?.y) return;
    const y = scales.y;

    const bands = [
      { from: 0,  to: 20,  fill: 'rgba(225, 6, 0, 0.10)' },
      { from: 20, to: 39,  fill: 'rgba(225, 6, 0, 0.16)' },
      { from: 39, to: 69,  fill: 'rgba(213, 207, 93, 0.18)' },
      { from: 69, to: 89,  fill: 'rgba(4, 157, 191, 0.14)' },
      { from: 89, to: 100, fill: 'rgba(2, 48, 89, 0.12)' }
    ];

    ctx.save();
    bands.forEach(b => {
      const yTop = y.getPixelForValue(b.to);
      const yBot = y.getPixelForValue(b.from);
      ctx.fillStyle = b.fill;
      ctx.fillRect(chartArea.left, yTop, chartArea.right - chartArea.left, yBot - yTop);
    });

    const meta = 80;
    const yMeta = y.getPixelForValue(meta);

    ctx.save();
    ctx.strokeStyle = 'rgba(225, 6, 0, 0.65)';
    ctx.lineWidth = 2;
    ctx.setLineDash([7, 6]);
    ctx.beginPath();
    ctx.moveTo(chartArea.left, yMeta);
    ctx.lineTo(chartArea.right, yMeta);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.restore();

    ctx.save();
    ctx.fillStyle = 'rgba(225, 6, 0, 0.85)';
    ctx.font = '700 12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'bottom';
    ctx.fillText('Meta 80%', chartArea.left + 8, yMeta - 4);
    ctx.restore();

    ctx.restore();
  }
};

function makePointLabelsPlugin(values){
  return {
    id: 'pointLabelsPlugin_' + Math.random().toString(16).slice(2),
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data) return;

      ctx.save();
      meta.data.forEach((point, i) => {
        const v = Number(values[i] ?? 0);
        const text = `${v}%`;

        const x = point.x, y = point.y;

        ctx.font = '800 11px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const paddingX = 6;
        const w = ctx.measureText(text).width + paddingX * 2;
        const h = 18;
        const boxX = x - w / 2;
        const boxY = y - 18 - h;
        const r = 7;

        ctx.fillStyle = 'rgba(255,255,255,0.90)';
        ctx.strokeStyle = 'rgba(0,0,0,0.15)';
        ctx.lineWidth = 1;

        ctx.beginPath();
        ctx.moveTo(boxX + r, boxY);
        ctx.lineTo(boxX + w - r, boxY);
        ctx.quadraticCurveTo(boxX + w, boxY, boxX + w, boxY + r);
        ctx.lineTo(boxX + w, boxY + h - r);
        ctx.quadraticCurveTo(boxX + w, boxY + h, boxX + w - r, boxY + h);
        ctx.lineTo(boxX + r, boxY + h);
        ctx.quadraticCurveTo(boxX, boxY + h, boxX, boxY + h - r);
        ctx.lineTo(boxX, boxY + r);
        ctx.quadraticCurveTo(boxX, boxY, boxX + r, boxY);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = getBandColor(v);
        ctx.fillText(text, x, boxY + h / 2);
      });
      ctx.restore();
    }
  };
}

function renderAllCharts(){
  document.querySelectorAll('canvas[id^="lineChart_"]').forEach((canvas) => {
    const dataEl = document.getElementById(canvas.id + '_data');
    if (!dataEl) return;

    let payload = {};
    try { payload = JSON.parse(dataEl.textContent || '{}'); } catch(e){ payload = {}; }

    const labels = payload.labels || [];
    const values = payload.values || [];

    const pointBorder = values.map(v => getBandColor(v));
    const pointFill   = values.map(() => '#FFFFFF');

    const ctx = canvas.getContext('2d');

    if (window.__WS_CHARTS__[canvas.id]) {
      try { window.__WS_CHARTS__[canvas.id].destroy(); } catch(e){}
      delete window.__WS_CHARTS__[canvas.id];
    }

    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Satisfacción (%)',
          data: values,
          borderColor: '#0511F2',
          borderWidth: 3,
          tension: 0, // ✅ recto
          backgroundColor: 'rgba(5,17,242,0.08)',
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 7,
          pointBorderWidth: 2,
          pointBackgroundColor: pointFill,
          pointBorderColor: pointBorder,
          pointHoverBackgroundColor: pointBorder,
          pointHoverBorderColor: '#FFFFFF',
          segment: { borderColor: (segCtx) => getBandColor(segCtx.p1?.parsed?.y ?? 0) }
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { top: 26 } },
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
          y: {
            min: 0,
            max: 100,
            ticks: { stepSize: 10, callback: v => v + '%' },
            grid: { color: 'rgba(0,0,0,0.08)' }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ` ${c.parsed.y ?? 0}% — ${getBandLabel(c.parsed.y ?? 0)}` } }
        }
      },
      plugins: [bandsAndWatermarkPlugin, makePointLabelsPlugin(values)]
    });

    window.__WS_CHARTS__[canvas.id] = chart;
  });
}

let __printing = false;

async function handlePrint(btn){
  if (__printing) return;
  __printing = true;

  btn.disabled = true;

  const title    = btn.getAttribute('data-title') || 'Indicador';
  const user     = btn.getAttribute('data-user') || 'Usuario';
  const band     = btn.getAttribute('data-band') || '';

  // ✅ ahora viene ya resuelto desde PHP (match directo)
  let divisionName = (btn.getAttribute('data-division-name') || '').trim();
  if (!divisionName) divisionName = 'N/D';

  const avg      = btn.getAttribute('data-avg') || '0';
  const best     = btn.getAttribute('data-best') || '0';
  const worst    = btn.getAttribute('data-worst') || '0';
  const weeks    = btn.getAttribute('data-weeks') || '0';
  const canvasId = btn.getAttribute('data-canvas') || '';

  const chart = window.__WS_CHARTS__[canvasId] || null;

  let imgSrc = '';
  if (chart && chart.canvas){
    try { chart.update('none'); } catch(e){}
    await waitFrames(2);
    try { imgSrc = chart.canvas.toDataURL('image/png', 1.0); } catch(e){ imgSrc = ''; }
  }

  const paper = document.getElementById('printPaper');
  const wrap  = document.getElementById('paperWrap');

  const originalParent = paper.parentNode;
  const originalNext   = paper.nextSibling;

  wrap.innerHTML = `
    <div class="paper-head">
      <div>
        <h1 class="paper-title">${escapeHtml(title)}</h1>

        <div class="paper-meta">
          <span class="paper-pill">Usuario: <b>${escapeHtml(user)}</b></span>
          <span class="paper-pill">División: <b>${escapeHtml(divisionName)}</b></span>
          <span class="paper-pill">Condición: <b>${escapeHtml(band)}</b></span>
        </div>
      </div>

      <div class="paper-badge">${escapeHtml(band)}</div>
    </div>

    <div class="paper-kpis">
      <div class="paper-kpi"><small>Promedio</small><br><b>${escapeHtml(avg)}%</b></div>
      <div class="paper-kpi"><small>Mejor semana</small><br><b>${escapeHtml(best)}%</b></div>
      <div class="paper-kpi"><small>Peor semana</small><br><b>${escapeHtml(worst)}%</b></div>
      <div class="paper-kpi"><small>Semanas</small><br><b>${escapeHtml(weeks)}</b></div>
    </div>

    <div class="paper-chart">
      ${imgSrc ? `<img src="${imgSrc}" alt="Gráfico">`
              : `<div style="padding:10px;color:#666;">No hay gráfico para imprimir</div>`}
    </div>
  `;

  document.body.appendChild(paper);
  paper.style.display = 'block';

  await waitImagesLoaded(wrap);
  await waitFrames(1);

  // ✅ Ocultar query del URL SOLO durante impresión
  const realUrl = window.location.href;
  try { window.history.replaceState(null, document.title, window.location.pathname); } catch(e){}

  window.print();

  try { window.history.replaceState(null, document.title, realUrl); } catch(e){}

  setTimeout(() => cleanupPrint(paper, wrap, originalParent, originalNext, btn), 800);
}

function cleanupPrint(paper, wrap, originalParent, originalNext, btn){
  if (wrap) wrap.innerHTML = '';
  if (paper) paper.style.display = 'none';

  try{
    if (originalParent){
      if (originalNext) originalParent.insertBefore(paper, originalNext);
      else originalParent.appendChild(paper);
    }
  }catch(e){}

  btn.disabled = false;
  __printing = false;
}

window.addEventListener('afterprint', () => {
  const paper = document.getElementById('printPaper');
  const wrap  = document.getElementById('paperWrap');
  const mount = document.getElementById('printMount');

  if (!paper || !wrap || !mount) return;

  wrap.innerHTML = '';
  paper.style.display = 'none';

  if (paper.parentNode !== mount){
    mount.appendChild(paper);
  }

  __printing = false;
});

renderAllCharts();
</script>

<?= $this->endSection() ?>