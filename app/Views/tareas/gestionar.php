<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<div class="container py-3">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <h3 class="mb-0 fw-bold">Gestión de Tareas</h3>

    <div class="d-flex gap-2">
      <a href="<?= site_url('tareas/asignar') ?>" class="btn btn-dark">
        <i class="bi bi-plus-circle me-1"></i> Nueva tarea
      </a>

      <a href="<?= site_url('tareas/calendario') ?>" class="btn btn-outline-dark">
        <i class="bi bi-calendar3 me-1"></i> Calendario
      </a>
    </div>
  </div>

  <!-- ================= MIS TAREAS ================= -->
  <div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Mis tareas
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaMisTareas" class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Título</th>
              <th>Área</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($misTareas as $t):

              $estado = strtolower(trim($t['nombre_estado']));
              $rowClass = match ($estado) {
                'finalizada'  => 'row-finalizada',
                'cancelada'   => 'row-cancelada',
                'pendiente'   => 'row-pendiente',
                'en progreso' => 'row-progreso',
                'bloqueada'   => 'row-bloqueada',
                default       => ''
              };
            ?>
            <tr class="<?= $rowClass ?>">
              <td><?= esc($t['titulo']) ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad']) ?></td>
              <td><?= esc($t['nombre_estado']) ?></td>
              <td data-order="<?= esc($t['fecha_inicio']) ?>">
                <?= date('d/m/Y H:i', strtotime($t['fecha_inicio'])) ?>
              </td>
              <td><?= $t['fecha_fin'] ? date('d/m/Y H:i', strtotime($t['fecha_fin'])) : '-' ?></td>
              <td class="text-end">
                <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                   class="btn btn-sm btn-outline-dark">
                  <i class="bi bi-pencil-square"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= TAREAS ASIGNADAS POR MÍ ================= -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Tareas asignadas por mí
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaAsignadas" class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Título</th>
              <th>Área</th>
              <th>Asignado a</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tareasAsignadas as $t):

              $estado = strtolower(trim($t['nombre_estado']));
              $rowClass = match ($estado) {
                'finalizada'  => 'row-finalizada',
                'cancelada'   => 'row-cancelada',
                'pendiente'   => 'row-pendiente',
                'en progreso' => 'row-progreso',
                'bloqueada'   => 'row-bloqueada',
                default       => ''
              };
            ?>
            <tr class="<?= $rowClass ?>">
              <td><?= esc($t['titulo']) ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad']) ?></td>
              <td><?= esc($t['nombre_estado']) ?></td>
              <td data-order="<?= esc($t['fecha_inicio']) ?>">
                <?= date('d/m/Y H:i', strtotime($t['fecha_inicio'])) ?>
              </td>
              <td class="text-end">
                <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                   class="btn btn-sm btn-outline-dark">
                  <i class="bi bi-pencil-square"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>


<!-- ================= ESTILOS ================= -->
<style>
.row-finalizada { background-color: #e6f4ea !important; }
.row-pendiente  { background-color: #fdecea !important; }
.row-progreso   { background-color: #fff8e1 !important; }
.row-bloqueada  { background-color: #f8d7da !important; }
.row-cancelada  { background-color: #f1f1f1 !important; color: #6c757d; }

/* Botones DataTables negros */
.dt-buttons .btn {
  background-color: #000 !important;
  color: #fff !important;
  border: none !important;
}

.dt-buttons .btn:hover {
  background-color: #222 !important;
}
</style>


<!-- ================= LIBRERÍAS ================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>


<script>
$(document).ready(function () {

  function initDataTable(idTabla, columnaFecha) {
    $(idTabla).DataTable({
      order: [[columnaFecha, 'desc']],
      pageLength: 5,
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excel', text: 'Excel', className: 'btn btn-sm' },
        { extend: 'pdf', text: 'PDF', className: 'btn btn-sm' },
        { extend: 'print', text: 'Imprimir', className: 'btn btn-sm' }
      ],
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        paginate: {
          next: "›",
          previous: "‹"
        }
      }
    });
  }

  initDataTable('#tablaMisTareas', 4);
  initDataTable('#tablaAsignadas', 5);

});
</script>

<?= $this->endSection() ?>
