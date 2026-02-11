<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<section>
<style>
@import url(https://fonts.googleapis.com/css?family=Roboto:400,500,700);

/* ===== Base ===== */
html, body {
  box-sizing: border-box;
  height: 100%;
  width: 100%;
  background: #FFF;
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
}

*, *:before, *:after {
  box-sizing: inherit;
}

/* ===== Layout ===== */
.container-fostrap {
  padding: 1rem;
  text-align: center;
}

/* ===== Card ===== */
.card {
  background-color: #fff;
  border-radius: .5rem;
  border: 1px solid #e9ecef;
  box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
  transition: transform .2s ease, box-shadow .2s ease;
  margin-bottom: 1rem;
}

.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
}

/* ===== Imagen ===== */
.img-card {
  width: 100%;
  height: 180px;
  overflow: hidden;
  border-top-left-radius: .5rem;
  border-top-right-radius: .5rem;
}

.img-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ===== Contenido ===== */
.card-content {
  padding: .75rem .75rem .5rem; /* ⬅️ reducido */
  text-align: left;
}

.card-title {
  margin: 0 0 .25rem;
  font-weight: 600;
  font-size: 1.1rem;
  text-align: center;
}

.card-text {
  font-size: .85rem;
  color: #6c757d;
  margin-bottom: .25rem;
}

/* ===== Botón ===== */
.card-action {
  padding: .25rem .75rem .75rem; /* ⬅️ elimina espacio innecesario */
}

.card-action button {
  width: 100%;
  font-size: .8rem;
  padding: .35rem;
  border-radius: .375rem;
}
</style>
</section>

<section class="wrapper">
  <div class="container-fostrap">
    <h1 class="heading">Divisiones BESTPC SAS</h1>

    <div class="container">
      <div class="row">

        <?php
          // ✅ Aseguramos que exista
          $divisiones = $divisiones ?? [];
        ?>

        <?php if (empty($divisiones)): ?>
          <div class="col-12">
            <div class="alert alert-light border text-center">
              No hay divisiones registradas para mostrar.
            </div>
          </div>
        <?php endif; ?>

        <?php foreach ($divisiones as $division): ?>
          <?php
            // ✅ Variables seguras
            $nombre = (string) ($division['nombre'] ?? '');
            $jefe   = (string) ($division['jefe'] ?? 'No asignado');
            $img    = (string) ($division['img'] ?? 'https://picsum.photos/seed/division-default/900/500');

            // ✅ Áreas
            $areasArr = $division['areas'] ?? [];
            if (!is_array($areasArr)) $areasArr = [];

            // ✅ Texto compacto de áreas (coma)
            $areasTxt = !empty($areasArr) ? implode(', ', array_map('strval', $areasArr)) : 'Sin áreas asignadas';
          ?>

          <div class="col-12 col-sm-4 mb-3">
            <div class="card h-100">

              <div class="img-card">
                <img src="<?= esc($img) ?>" alt="<?= esc($nombre) ?>">
              </div>

              <div class="card-content">
                <h5 class="card-title text-center mb-2">
                  <?= esc($nombre) ?>
                </h5>

                <p class="card-text small text-muted mb-0">
                  <strong>Jefe de división:</strong> <?= esc($jefe) ?><br>
                  <strong>Áreas a cargo:</strong> <?= esc($areasTxt) ?>
                </p>
              </div>

             <div class="card-action">
  <button type="button"
          class="btn btn-outline-primary btn-sm btn-org"
          data-division-id="<?= (int) $division['id_division'] ?>">
    Organigrama
  </button>
</div>



            </div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-org[data-division-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-division-id');
      if (!id) return;

      // ✅ Redirección al organigrama de la división
      window.location.href = "<?= base_url('orgchart/division') ?>/" + encodeURIComponent(id);
    });
  });
});
</script>

<?= $this->endSection() ?>
