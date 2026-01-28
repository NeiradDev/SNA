<?= $this->extend('layouts/main') ?>

<?= $this->section('titulo') ?>
    Bestpc SNA - Agencias
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="container-fluid pt-2">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestión de Agencias</h2>
            <p class="text-muted">Listado de agencias registradas en el sistema</p>
        </div>
        <a href="<?= site_url('agencias/crear') ?>" class="btn btn-dark px-4 shadow-sm">
            <i class="bi bi-building-add me-2"></i>Nueva Agencia
        </a>
    </div>

    <!-- Mostrar mensajes flash -->
    <?php if(session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div><?= session()->getFlashdata('success') ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <div><?= session()->getFlashdata('error') ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Nombre Agencia</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Dirección</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Ciudad</th>
                            <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($agencias) && is_array($agencias)): ?>
                            <?php foreach($agencias as $agencia): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= esc($agencia->nombre_agencia) ?></div>
                                </td>
                                <td><?= esc($agencia->direccion) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal"><?= esc($agencia->ciudad) ?></span>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="btn-group" role="group">
                                        <a href="<?= site_url('agencias/editar/' . $agencia->id_agencias) ?>" 
                                           class="btn btn-sm btn-outline-dark border-0">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-buildings text-muted display-4"></i>
                                    <p class="mt-3 text-muted">No hay agencias registradas actualmente.</p>
                                    <a href="<?= site_url('agencias/crear') ?>" class="btn btn-sm btn-dark">Registrar la primera</a>
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
    /* Estilo para los botones de acción */
    .btn-group .btn {
        border-radius: 4px !important;
        margin-right: 4px;
    }
    .btn-group .btn:last-child {
        margin-right: 0;
    }
</style>

<?= $this->endSection() ?>