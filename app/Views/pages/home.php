<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<?= view('pages/_partials/estilos_metricas', ['part' => 'css']) ?>

<style>
/* ===== CONTENEDOR DEL CHART ===== */
.chart-area {
  position: relative;
  height: 260px;        /* 🔒 LÍMITE REAL */
  max-height: 260px;
  width: 100%;
}

.chart-area canvas {
  width: 100% !important;
  height: 100% !important;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<?php
$avgWeek     = $avgWeek     ?? 0;
$bestWeek    = $bestWeek    ?? 0;
$worstWeek   = $worstWeek   ?? 0;
$weeksCount  = $weeksCount  ?? 0;
$chartLabels = $chartLabels ?? [];
$chartValues = $chartValues ?? [];
?>

<div class="chart-card" id="chartBox">
  <div class="chart-grid">

    <!-- ================= KPI ================= -->
    <div class="kpi-card">
      <p class="kpi-title">Cumplimiento semanal</p>

      <p class="kpi-value">
        <?= esc($avgWeek) ?>%
      </p>

      <div class="kpi-sub">
        Promedio de las últimas <?= esc($weeksCount) ?> semanas
      </div>

      <div class="kpi-row">
        <div>
          <small>Mejor semana</small><br>
          <b><?= esc($bestWeek) ?>%</b>
        </div>
        <div class="text-end">
          <small>Peor semana</small><br>
          <b><?= esc($worstWeek) ?>%</b>
        </div>
      </div>

      <div class="kpi-row">
        <div>
          <small>Semanas</small><br>
          <b><?= esc($weeksCount) ?></b>
        </div>
        <div class="text-end">
          <small>Meta</small><br>
          <b>80%</b>
        </div>
      </div>
    </div>
    <!-- =============== FIN KPI =============== -->

    <!-- ================= CHART ================= -->
    <div class="chart-area">
      <div class="chart-head">
        <h3>Línea de tiempo semanal (%) — Mié → Mié</h3>

        <div class="month-filter">
          <span>Mes:</span>
          <input type="month" disabled>
          <button type="button" disabled>Este mes</button>
        </div>
      </div>

      <canvas id="lineChart"></canvas>

      <small class="text-muted">
        Se muestran las últimas 3 semanas registradas del Plan de Batalla.
      </small>
    </div>
    <!-- =============== FIN CHART =============== -->

  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('pages/_partials/estilos_metricas', ['part' => 'js']) ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* ================= DATOS DESDE PHP ================= */
const labels = <?= json_encode($chartLabels) ?>;
const values = <?= json_encode($chartValues) ?>;

/* ================= CHART ================= */
const ctx = document.getElementById('lineChart').getContext('2d');

new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels,
    datasets: [{
      label: 'Satisfacción (%)',
      data: values,
      borderColor: '#0511F2',
      backgroundColor: 'rgba(5,17,242,0.15)',
      tension: 0.35,
      fill: true,
      pointRadius: 4,
      pointHoverRadius: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false, // 🔒 evita crecimiento infinito

    scales: {
      x: {
        grid: { display: false }
      },
      y: {
        min: 0,
        max: 100,
        ticks: {
          stepSize: 20,
          callback: value => value + '%'
        }
      }
    },

    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ctx.parsed.y + '%'
        }
      }
    }
  }
});
</script>

<?= $this->endSection() ?>
