<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="container-fluid">

    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm pt-2">
                <div class="card-body p-4">
                    <form action="<?= base_url('usuarios/guardar') ?>" method="POST">
                        <?= csrf_field() ?>
                        
                        <h5 class="mb-4 text-muted"><i class="bi bi-person-badge me-2"></i>Información Personal</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Nombres</label>
                                <input type="text" name="nombres" class="form-control bg-light border-0" placeholder="Ej. Juan Carlos" required maxlength="32">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Apellidos</label>
                                <input type="text" name="apellidos" class="form-control bg-light border-0" placeholder="Ej. Armas" required maxlength="32">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Cédula (ID)</label>
                                <input type="number" name="cedula" class="form-control bg-light border-0" placeholder="Solo números" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Contraseña</label>
                                <input type="password" name="password" class="form-control bg-light border-0" required>
                            </div>

                            <div class="col-12 my-4">
                                <h5 class="text-muted"><i class="bi bi-building me-2"></i>Asignación y Estructura</h5>
                                <hr class="opacity-25">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Agencia</label>
                                <select name="id_agencias" class="form-select bg-light border-0">
                                    <option value="">Seleccione...</option>
                                    </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Área</label>
                                <select name="id_area" class="form-select bg-light border-0">
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Cargo</label>
                                <select name="id_cargo" class="form-select bg-light border-0">
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Supervisor</label>
                                <select name="id_supervisor" class="form-select bg-light border-0">
                                    <option value="0">Sin Supervisor</option>
                                </select>
                            </div>

                            <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
                                    <label class="form-check-label" for="activo">Usuario Activo</label>
                                </div>
                                <div>
                                    <a href="<?= base_url('/home') ?>" class="btn btn-light px-4 me-2">Cancelar</a>
                                    <button type="submit" class="btn btn-dark px-5 shadow-sm">
                                        Registrar Usuario
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-3">
            <div class="card border-0 bg-dark text-white shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Ayuda</h6>
                    <p class="small opacity-75">Asegúrese de que la <strong>Cédula</strong> no esté registrada previamente. Los campos con IDs son obligatorios para la estructura de reportes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>