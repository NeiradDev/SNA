<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    /* Hover suave en filas */
    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,.02);
        cursor: pointer;
    }

    /* Espaciado interno del wrapper DataTables dentro del card */
    .dataTables_wrapper {
        padding: 12px 12px 0 12px;
    }

    /* Evita que el contenedor recorte los botones (sobre todo en mobile) */
    .table-responsive {
        overflow: visible;
    }

    /* Layout de botones DataTables (exportación/columnas) */
    .dataTables_wrapper .dt-buttons{
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-bottom: .5rem;
    }

    /* Botones con bordes definidos (evita “corte” visual dentro del card) */
    .dataTables_wrapper .dt-buttons .btn{
        border-radius: .5rem !important;
        border: 1px solid rgba(0,0,0,.25) !important;
        line-height: 1.2 !important;
        padding: .38rem .75rem !important;
        box-shadow: none !important;
        background-clip: padding-box;
    }

    /* Separación del buscador */
    .dataTables_wrapper .dataTables_filter{
        margin-bottom: .5rem;
    }

    /* Input buscador con look Bootstrap */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: .5rem;
        padding: .375rem .75rem;
        margin-left: .5rem;
    }

    /* Paginación con bordes suaves */
    .dataTables_wrapper .dataTables_paginate .page-link {
        border-radius: .5rem;
        margin: 0 .15rem;
    }

    /* Respiro inferior del info y paginación dentro del card */
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        padding-bottom: 12px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="container-fluid pt-2">

    <?php
        /**
         * Flashdata success:
         * - El Controller redirige a esta vista con ->with('success', '...')
         * - Si existe, mostramos un modal de confirmación.
         */
        $success = session()->getFlashdata('success');
    ?>

    <!-- ============================================================
         MODAL DE ÉXITO (solo aparece si hay mensaje en flashdata)
         ============================================================ -->
    <?php if (!empty($success)): ?>
        <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle me-2"></i>Registro exitoso
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0"><?= esc($success) ?></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-dark px-4" data-bs-dismiss="modal">Perfecto</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         CABECERA + BOTÓN NUEVO USUARIO
         ============================================================ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestión de Personal</h2>
            <p class="text-muted">Listado de usuarios registrados en el sistema</p>
        </div>

        <!-- Enlace a formulario de creación -->
        <a href="<?= base_url('usuarios/nuevo') ?>" class="btn btn-dark px-4 shadow-sm">
            <i class="bi bi-person-plus me-2"></i>Nuevo Usuario
        </a>
    </div>

    <!-- ============================================================
         TABLA (DataTables)
         - ID va oculto (sirve para ordenamiento)
         - no-export: columnas que NO deben ir en exportación (acciones, id oculto)
         ============================================================ -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="bg-light">
                        <tr>
                            <!-- ID oculto (se usa para ordenar desc) -->
                            <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase d-none no-export">ID</th>

                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Nombre Completo</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Documento</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Cargo</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Área</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Supervisor</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Matriz</th>
                            <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Estado</th>

                            <!-- Acciones no exportables -->
                            <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase text-end no-export">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $user): ?>
                                <tr>
                                    <!-- Columna ID (oculta) -->
                                    <td class="px-4 d-none"><?= esc($user['id_user']) ?></td>

                                    <!-- Nombre -->
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= esc($user['nombres'] . ' ' . $user['apellidos']) ?>
                                        </div>
                                    </td>

                                    <!-- Documento (antes Cédula, ahora puede ser alfanumérico) -->
                                    <td><?= esc($user['cedula']) ?></td>

                                    <!-- Cargo -->
                                    <td>
                                        <span class="badge bg-light text-dark border fw-normal">
                                            <?= esc($user['nombre_cargo'] ?? $user['id_cargo']) ?>
                                        </span>
                                    </td>

                                    <!-- Área -->
                                    <td><?= esc($user['nombre_area'] ?? '—') ?></td>

                                    <!-- Supervisor -->
                                    <td><?= esc($user['supervisor_nombre'] ?? '—') ?></td>

                                    <!-- Matriz (Agencia) -->
                                    <td><?= esc($user['nombre_agencia'] ?? '—') ?></td>

                                    <!-- Estado -->
                                    <td>
                                        <?php if (!empty($user['activo'])): ?>
                                            <span class="badge bg-success-subtle text-success px-3">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger px-3">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-4 text-end">
                                        <a href="<?= base_url('usuarios/editar/' . $user['id_user']) ?>" class="btn btn-sm btn-outline-dark border-0">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Estado vacío -->
                            <tr>
                                <td colspan="9" class="text-center py-5">
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- ============================================================
     SCRIPTS DataTables
     Nota: si tu layout ya carga jQuery, elimina la línea de jQuery.
     ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
    /**
     * Inicializa DataTables con:
     * - Responsive
     * - Botones de exportación
     * - Búsqueda
     * - Ordenamiento por ID (col 0) aunque esté oculto
     * - Idioma español
     * - Modal de éxito si existe
     */
    $(function () {
        // 1) DataTable
        $('#usersTable').DataTable({
            responsive: true,

            // Cantidad inicial de filas
            pageLength: 10,

            // Opciones de cantidad de filas
            lengthMenu: [10, 25, 50, 100],

            // Orden por ID oculto (columna 0) DESC
            order: [[0, 'desc']],

            // Layout: botones a la izquierda, buscador a la derecha
            dom:
                "<'row align-items-center g-2'<'col-12 col-md-6 d-flex flex-wrap gap-2'B><'col-12 col-md-6 d-flex justify-content-md-end'f>>" +
                "<'row'<'col-12'tr>>" +
                "<'row align-items-center g-2'<'col-12 col-md-5'i><'col-12 col-md-7 d-flex justify-content-md-end'p>>",

            // Botones de exportación y control de columnas
            buttons: [
                { extend: 'copy',  text: '<i class="bi bi-clipboard me-1"></i>Copiar', className: 'btn btn-sm btn-dark', exportOptions: { columns: ':not(.no-export)' } },
                { extend: 'excel', text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel', className: 'btn btn-sm btn-dark', exportOptions: { columns: ':not(.no-export)' } },
                { extend: 'csv',   text: '<i class="bi bi-filetype-csv me-1"></i>CSV', className: 'btn btn-sm btn-dark', exportOptions: { columns: ':not(.no-export)' } },
                { extend: 'pdf',   text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF', className: 'btn btn-sm btn-dark', exportOptions: { columns: ':not(.no-export)' } },
                { extend: 'print', text: '<i class="bi bi-printer me-1"></i>Imprimir', className: 'btn btn-sm btn-dark', exportOptions: { columns: ':not(.no-export)' } },
                { extend: 'colvis',text: '<i class="bi bi-layout-three-columns me-1"></i>Columnas', className: 'btn btn-sm btn-outline-dark' }
            ],

            // Definición de columnas
            columnDefs: [
                // ID oculto (no visible, no searchable)
                { targets: 0, visible: false, searchable: false },

                // Acciones (última columna): no ordenable, no searchable, no export
                { targets: -1, orderable: false, searchable: false, className: 'no-export' }
            ],

            // Idioma en español
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json",
                search: "Buscar:",
                lengthMenu: "Mostrar _MENU_",
                info: "Mostrando _START_ a _END_ de _TOTAL_",
                infoEmpty: "Mostrando 0 a 0 de 0",
                zeroRecords: "No se encontraron resultados",
                paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
            },

            // Evita cálculos automáticos de ancho que a veces rompen el layout
            autoWidth: false
        });

        // 2) Mostrar modal de éxito si existe en el DOM
        const modalEl = document.getElementById('successModal');
        if (modalEl && window.bootstrap) {
            new bootstrap.Modal(modalEl).show();
        }
    });
  </script>
<?= $this->endSection() ?>