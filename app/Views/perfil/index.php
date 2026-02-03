<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="m-0">Mi Perfil</h5>

        <?php if (isset($activo)) : ?>
            <span class="badge <?= ((int)$activo === 1) ? 'bg-success' : 'bg-secondary' ?>">
                <?= ((int)$activo === 1) ? 'Activo' : 'Inactivo' ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0 p-3">
        <!-- Información personal -->
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="fw-semibold">Información personal</div>
            <small class="text-muted">Datos registrados</small>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-6">
                <div class="small text-muted">Nombre</div>
                <div class="fw-semibold">
                    <?= esc(trim(($nombres ?? '') . ' ' . ($apellidos ?? ''))) ?>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="small text-muted">Cédula</div>
                <div class="fw-semibold">
                    <?= esc($cedula ?? '') ?>
                </div>
            </div>
        </div>

        <hr class="my-2">

        <!-- Información laboral -->
        <div class="fw-semibold mb-1">Información laboral</div>

        <div class="row g-2">
            <div class="col-6 col-md-4">
                <div class="small text-muted">Cargo</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_cargo ?? ($cargo_nombre ?? '')) ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">Área</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_area ?? (string)($id_area ?? '')) ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">División</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_division ?? '') ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">Agencia</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_agencia ?? (string)($id_agencias ?? '')) ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">Nivel</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nivel ?? '') ?>
                </div>
            </div>

            <?php if (!empty($supervisor_nombre)) : ?>
                <div class="col-12 col-md-4">
                    <div class="small text-muted">Supervisor</div>
                    <div class="fw-semibold text-truncate">
                        <?= esc($supervisor_nombre) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mensaje -->
        <div class="alert alert-warning py-2 px-2 mt-3 mb-0 small" role="alert">
            <b>Importante:</b> Si algún dato está erróneo, comuníquese con el administrador.
        </div>
    </div>
</div>

<?= $this->endSection() ?>
