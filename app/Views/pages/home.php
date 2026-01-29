<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
  <?= view('pages/_partials/estilos_metricas', ['part' => 'css']) ?>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<div class="chart-card" id="chartBox">
  <div class="chart-grid">

    <div class="kpi-card">
      <p class="kpi-title">Cumplimiento semanal (Mié → Mié)</p>
      <p class="kpi-value" id="weeklyPercent">0%</p>
      <div class="kpi-sub" id="weeklyHint">Promedio de las semanas del mes</div>

      <div class="kpi-row">
        <div>
          <small>Mejor semana</small><br>
          <b id="bestWeek">-</b>
        </div>
        <div class="text-end">
          <small>Peor semana</small><br>
          <b id="worstWeek">-</b>
        </div>
      </div>

      <div class="kpi-row">
        <div>
          <small>Semanas</small><br>
          <b id="weeksCount">-</b>
        </div>
        <div class="text-end">
          <small>Meta (demo)</small><br>
          <b>80%</b>
        </div>
      </div>
    </div>

    <div class="chart-area">
      <div class="chart-head">
        <h3>Línea de tiempo semanal (%) — Mié → Mié</h3>
        <div class="month-filter">
          <span>Mes:</span>
          <input type="month" id="monthPicker">
          <button type="button" id="btnThisMonth">Este mes</button>
        </div>
      </div>

      <canvas id="lineChart" height="110"></canvas>
      <small>Demo: Filtra por mes mostrando solo semanas cuyo miércoles final cae dentro del mes.</small>
    </div>

  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <?= view('pages/_partials/estilos_metricas', ['part' => 'js']) ?>
<?= $this->endSection() ?>
