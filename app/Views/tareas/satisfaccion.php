<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
$porcentaje = (float)$data['porcentaje'];

/*
  Convertimos porcentaje a color dinámico:
  0%   → rojo
  50%  → amarillo
  100% → verde
*/
$hue = $porcentaje * 1.2;
$colorStart = "hsl($hue, 85%, 92%)";
$colorEnd   = "hsl($hue, 85%, 75%)";
?>

<style>
.satisfaccion-card{
  border:none;
  border-radius:20px;
}

.circle-wrapper{
  position:relative;
  width:240px;
  height:240px;
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
  font-size:3rem;
  font-weight:900;
  color:#000; /* SIEMPRE NEGRO */
}

.semana-text{
  text-align:center;
  margin-top:15px;
  font-size:.9rem;
  color:#555;
}

.stat-box{
  background:#f8f9fa;
  border-radius:14px;
  padding:18px;
  min-width:130px;
}

.stat-box strong{
  font-size:1.6rem;
}
</style>

<div class="container py-4">

  <h3 class="mb-4 fw-bold text-center">Mi porcentaje de satisfacción</h3>

  <div class="card shadow satisfaccion-card p-4 text-center">

    <!-- CÍRCULO SOLO RELLENO -->
    <div class="circle-wrapper mb-2">

      <svg width="240" height="240">

        <defs>
          <radialGradient id="innerGrad" cx="50%" cy="50%" r="60%">
            <stop offset="0%" stop-color="<?= $colorStart ?>"/>
            <stop offset="100%" stop-color="<?= $colorEnd ?>"/>
          </radialGradient>
        </defs>

        <!-- Círculo sin borde -->
        <circle
          cx="120"
          cy="120"
          r="100"
          fill="url(#innerGrad)"
        />

      </svg>

      <div class="circle-center">
        <div class="circle-percent">
          <?= number_format($porcentaje, 1) ?>%
        </div>
      </div>

    </div>

    <!-- SEMANA FUERA DEL CÍRCULO -->
    <div class="semana-text">
      Semana <?= esc($data['inicio']) ?> → <?= esc($data['fin']) ?>
    </div>

    <!-- ESTADÍSTICAS -->
    <div class="d-flex justify-content-center gap-4 flex-wrap mt-4">

      <div class="stat-box">
        <strong style="color:#28a745;">
          <?= $data['realizadas'] ?>
        </strong><br>
        <small>Realizadas</small>
      </div>

      <div class="stat-box">
        <strong style="color:#dc3545;">
          <?= $data['no_realizadas'] ?>
        </strong><br>
        <small>No realizadas</small>
      </div>

    </div>

  </div>

</div>

<?= $this->endSection() ?>
