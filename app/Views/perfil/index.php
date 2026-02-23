<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: perfil/index.php
 * =========================================================
 * ✅ Cambios implementados:
 * - Agregar helper('form') para asegurar que old() funcione
 * - Form action usando base_url('index.php/...') (ngrok / sin rewrite)
 * - Mantener mensajes flashdata (success/error/info)
 * - Form para editar correo y contraseña
 * =========================================================
 */

// ✅ Asegura disponibilidad de old() en la vista
helper('form');
?>

<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="m-0">Mi Perfil</h5>

        <?php if (isset($activo)) : ?>
            <span class="badge <?= ((int)$activo === 1) ? 'bg-success' : 'bg-secondary' ?>">
                <?= ((int)$activo === 1) ? 'Activo' : 'Inactivo' ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- =========================
         MENSAJES (FLASHDATA)
    ========================== -->
    <?php if (session()->getFlashdata('success')) : ?>
        <div class="alert alert-success py-2"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('info')) : ?>
        <div class="alert alert-info py-2"><?= esc(session()->getFlashdata('info')) ?></div>
    <?php endif; ?>

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

            <!--<div class="col-6 col-md-4">
                <div class="small text-muted">Nivel</div>
                <div class="fw-semibold text-truncate">
                    <? //= //esc($nivel ?? '') ?>
                </div>
            </div>-->

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

    <!-- =========================================================
         NUEVO: EDITAR CORREO Y CONTRASEÑA
    ========================================================== -->
    <div class="card shadow-sm border-0 p-3 mt-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold">Seguridad y contacto</div>
            <small class="text-muted">Puedes actualizar tu correo y contraseña</small>
        </div>

        <form action="<?= base_url('index.php/perfil/update-credentials') ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Correo</label>
                    <input
                        type="email"
                        name="correo"
                        class="form-control"
                        value="<?= esc(old('correo') ?? ($correo ?? '')) ?>"
                        placeholder="ejemplo@correo.com">
                </div>

                <div class="col-12">
                    <hr class="my-2">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">Contraseña actual</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">Nueva contraseña</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">Confirmar nueva contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repite la contraseña">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= site_url('home') ?>" class="btn btn-outline-secondary">Volver</a>
                <button type="submit" class="btn btn-dark">Guardar cambios</button>
            </div>
        </form>

    </div>
</div>

<?= $this->endSection() ?>