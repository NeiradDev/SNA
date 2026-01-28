<?= $this->extend('layouts/main_login') ?> 

<?= $this->section('titulo') ?>
    Bestpc SNA
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<section class="vh-100" style="background-color: #f4f7f6;"> 
  <div class="container py-3 h-100">
    <div class="row d-flex justify-content-center align-items-center h-100">
      <div class="col col-xl-9">
        <div class="card shadow-lg" style="border-radius: 1rem; border: none;">
          <div class="row g-0">

            <!-- Imagen Lateral -->
            <div class="col-md-5 col-lg-5 d-none d-md-block">
              <img src="<?= base_url('assets/img/img-login.png') ?>"
                alt="BPC Login" class="img-fluid" 
                style="border-radius: 1rem 0 0 1rem; height: 100%; object-fit: cover;" />
            </div>

            <div class="col-md-7 col-lg-7 d-flex align-items-center">
              <div class="card-body p-4 p-xl-5 text-black">

                <!-- ERROR -->
                <?php if (!empty($error)): ?>
                  <div class="alert alert-danger py-2" role="alert">
                    <?= esc($error) ?>
                  </div>
                <?php endif; ?>

                <!-- ✅ FORM FUNCIONAL -->
                <form method="post" action="<?= site_url('auth/attempt') ?>">
                  <?= function_exists('csrf_field') ? csrf_field() : '' ?>

                  <!-- Logo -->
                  <div class="d-flex align-items-center mb-4">
                    <img src="<?= base_url('assets/img/logo-bpc.png') ?>" 
                         alt="Logo" style="width: 360px; height: auto;">
                  </div>

                  <h5 class="fw-bold mb-3 pb-1" style="letter-spacing: -0.5px; color: #1a1a1a;">
                    Ingreso al Sistema
                  </h5>

                  <!-- ✅ Cédula (antes decía email/usuario) -->
                  <div class="form-outline mb-3">
                    <label class="form-label small fw-bold" for="cedula">Cédula</label>
                    <input
                      type="text"
                      id="cedula"
                      name="cedula"
                      class="form-control"
                      required
                      value="<?= esc($old['cedula'] ?? '') ?>"
                      placeholder="Ingrese su cédula"
                    />
                  </div>

                  <div class="form-outline mb-2">
                    <label class="form-label small fw-bold" for="password">Contraseña</label>
                    <input
                      type="password"
                      id="password"
                      name="password"
                      class="form-control"
                      required
                      placeholder="••••••••"
                    />
                  </div>

                  <div class="text-end mb-4">
                    <a class="small text-muted" href="#!">¿Olvidaste tu contraseña?</a>
                  </div>

                  <!-- ✅ Botón SUBMIT (no <a>) -->
                  <div class="mb-3">
                    <button
                      type="submit"
                      class="btn btn-dark btn-md w-100 shadow-sm"
                      style="padding: 10px; border-radius: 0.5rem; font-weight: 600;"
                    >
                      Iniciar Sesión
                    </button>
                  </div>

                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?= $this->endSection() ?>