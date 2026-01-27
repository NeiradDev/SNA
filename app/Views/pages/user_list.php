<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="container-fluid pt-2">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestión de Personal</h2>
            <p class="text-muted">Listado de usuarios registrados en el sistema</p>
        </div>
        <a href="<?= base_url('usuarios/nuevo') ?>" class="btn btn-dark px-4 shadow-sm">
            <i class="bi bi-person-plus me-2"></i>Nuevo Usuario
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0"> <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase">ID</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Nombre Completo</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Cédula</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Cargo / Área</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Estado</th>
                            <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($usuarios)): ?>
                            <?php foreach($usuarios as $user): ?>
                            <tr>
                                <td class="px-4"><?= $user['id_user'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= $user['nombres'] . ' ' . $user['apellidos'] ?></div>
                                    <div class="small text-muted">ID: <?= $user['id_user'] ?></div>
                                </td>
                                <td><?= $user['cedula'] ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal"><?= $user['id_cargo'] ?></span>
                                </td>
                                <td>
                                    <?php if($user['activo']): ?>
                                        <span class="badge bg-success-subtle text-success px-3">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger px-3">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-end">
                                    <a href="<?= base_url('usuarios/editar/'.$user['id_user']) ?>" class="btn btn-sm btn-outline-dark border-0">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-people text-muted display-4"></i>
                                    <p class="mt-3 text-muted">No hay usuarios registrados actualmente.</p>
                                    <a href="<?= base_url('usuarios/nuevo') ?>" class="btn btn-sm btn-dark">Registrar el primero</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    /* Efecto suave al pasar el mouse por la fila */
    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,.02);
        cursor: pointer;
    }
</style>
<?= $this->endSection() ?>