<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * Vista: tareas/gestionar.php
 *
 * Secciones:
 * - Pendientes de revisión (estado 6) (solo si hay)
 * - Mis tareas
 * - Tareas asignadas por mí (no autoasignadas)
 * - Tareas de mi equipo (solo jefaturas)
 *
 * Nota:
 * - Esta vista usa DataTables en modo “cliente”.
 * - Acciones rápidas:
 *   - Usuario normal (scope=self): NO cierra directo, envía a revisión (estado 6).
 *   - Jefaturas/supervisor/asignador/gerencia: puede cerrar directo (estado 3/4).
 */

$assignScope = $assignScope ?? ['mode' => 'self'];
$assignMode  = (string)($assignScope['mode'] ?? 'self');

/**
 * Jefaturas pueden ver equipo (super/division/area)
 */
$canSeeTeam = in_array($assignMode, ['super', 'division', 'area'], true);

/**
 * Estado cerrado si: Realizada(3) / No realizada(4) / Cancelada(5)
 */
$isClosedState = function(int $estadoId): bool {
  return in_array($estadoId, [3,4,5], true);
};

/**
 * Estado bloqueado para acciones rápidas:
 * - Cerrados (3/4/5)
 * - En revisión (6) (ya está solicitada)
 */
$isLockedForActions = function(int $estadoId): bool {
  return in_array($estadoId, [3,4,5,6], true);
};

/**
 * Clases por estado (filas)
 */
$rowClassByState = function(int $estadoId): string {
  return match ($estadoId) {
    3 => 'row-finalizada',
    4 => 'row-norealizada',
    5 => 'row-cancelada',
    6 => 'row-revision',
    default => ''
  };
};
?>

<div class="container py-3">

  <!-- ================= CABECERA ================= -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h3 class="mb-0 fw-bold">Gestión de Actividades</h3>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('tareas/asignar') ?>" class="btn btn-dark">
        <i class="bi bi-plus-circle me-1"></i> Nueva Actividad
      </a>

      <a href="<?= site_url('tareas/calendario') ?>" class="btn btn-outline-dark">
        <i class="bi bi-calendar3 me-1"></i> Calendario de Actividades
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
  <?php endif; ?>

  <!-- ================= FILTRO CLIENTE ================= -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Desde</label>
          <input type="date" id="fDesde" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Hasta</label>
          <input type="date" id="fHasta" class="form-control">
        </div>
        <div class="col-md-6 d-flex gap-2 flex-wrap">
          <button type="button" id="btnAplicarFiltro" class="btn btn-dark">
            <i class="bi bi-funnel me-1"></i> Aplicar
          </button>
          <button type="button" id="btnLimpiarFiltro" class="btn btn-outline-dark">
            <i class="bi bi-x-circle me-1"></i> Limpiar
          </button>
          <div class="ms-auto text-muted small">
            Filtro por <b>fecha inicio</b> (cliente).
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= PENDIENTES DE REVISIÓN ================= -->
  <?php if (!empty($pendientesRevision)): ?>
    <div class="card shadow-sm mb-4 border-0">
      <div class="card-header bg-dark text-white fw-bold d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          Pendientes de revisión
          <div class="small fw-normal opacity-75">
            Tareas enviadas por usuarios (estado <b>En revisión</b>).
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="button" id="btnApproveBatch" class="btn btn-sm btn-outline-light">
            <i class="bi bi-check2-circle me-1"></i> Aprobar
          </button>
          <button type="button" id="btnRejectBatch" class="btn btn-sm btn-outline-light">
            <i class="bi bi-x-circle me-1"></i> Rechazar
          </button>
        </div>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table id="tablaRevision" class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:40px;">
                  <input type="checkbox" id="chkAllRevision">
                </th>
                <th>Título</th>
                <th>Área</th>
                <th>Asignado a</th>
                <th>Solicitado</th>
                <th>Fecha solicitud</th>
                <th>Inicio</th>
                <th>Fin</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendientesRevision as $t):
                $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                $rowClass = $rowClassByState($estadoId);

                $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                $finRaw    = (string)($t['fecha_fin'] ?? '');
                $revAtRaw  = (string)($t['review_requested_at'] ?? '');
              ?>
                <tr class="<?= esc($rowClass) ?>">
                  <td>
                    <input type="checkbox" class="chkRevision" value="<?= (int)$t['id_tarea'] ?>">
                  </td>
                  <td><?= esc($t['titulo'] ?? '-') ?></td>
                  <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                  <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                  <td>
                    <span class="badge bg-warning text-dark">
                      <?= esc($t['nombre_estado_solicitado'] ?? 'Solicitud') ?>
                    </span>
                  </td>
                  <td><?= $revAtRaw ? date('d/m/Y H:i', strtotime($revAtRaw)) : '-' ?></td>

                  <td data-order="<?= esc($inicioRaw) ?>" class="td-inicio">
                    <?= $inicioRaw ? date('d/m/Y H:i', strtotime($inicioRaw)) : '-' ?>
                  </td>
                  <td><?= $finRaw ? date('d/m/Y H:i', strtotime($finRaw)) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="text-muted small mt-2">
          Selecciona tareas y usa <b>Aprobar</b> o <b>Rechazar</b>.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ================= MIS TAREAS ================= -->
  <div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Mis Actividades
      <div class="small fw-normal opacity-75">
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaMisTareas" class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:120px;">Marcar</th>
              <th>Título</th>
              <th>Área</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th class="text-end" style="width:90px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($misTareas ?? []) as $t):

              $estadoId = (int)($t['id_estado_tarea'] ?? 0);
              $rowClass = $rowClassByState($estadoId);

              $locked   = $isLockedForActions($estadoId);
              $closed   = $isClosedState($estadoId);

              $inicioRaw = (string)($t['fecha_inicio'] ?? '');
              $finRaw    = (string)($t['fecha_fin'] ?? '');
            ?>
            <tr class="<?= esc($rowClass) ?>">
              <td>
                <?php if ($locked): ?>
                  <?php if ($estadoId === 6): ?>
                    <span class="badge bg-warning text-dark">En revisión</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Cerrada</span>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="d-flex gap-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-dark action-state"
                            data-id="<?= (int)$t['id_tarea'] ?>"
                            data-state="3"
                            title="Marcar como realizada">
                      <i class="bi bi-check2-square"></i>
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-dark action-state"
                            data-id="<?= (int)$t['id_tarea'] ?>"
                            data-state="4"
                            title="Marcar como no realizada">
                      <i class="bi bi-x-square"></i>
                    </button>
                  </div>
                <?php endif; ?>
              </td>

              <td><?= esc($t['titulo'] ?? '-') ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
              <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

              <td data-order="<?= esc($inicioRaw) ?>" class="td-inicio">
                <?= $inicioRaw ? date('d/m/Y H:i', strtotime($inicioRaw)) : '-' ?>
              </td>

              <td><?= $finRaw ? date('d/m/Y H:i', strtotime($finRaw)) : '-' ?></td>

              <td class="text-end">
                <?php if ($closed || $estadoId === 6): ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                    <i class="bi bi-lock"></i>
                  </button>
                <?php else: ?>
                  <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                     class="btn btn-sm btn-outline-dark"
                     title="Editar">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= TAREAS ASIGNADAS POR MÍ ================= -->
  <div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Actividades asignadas por mí
      <div class="small fw-normal opacity-75">
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaAsignadas" class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:120px;">Marcar</th>
              <th>Título</th>
              <th>Área</th>
              <th>Asignado a</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th class="text-end" style="width:90px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($tareasAsignadas ?? []) as $t):

              $estadoId = (int)($t['id_estado_tarea'] ?? 0);
              $rowClass = $rowClassByState($estadoId);

              $locked = $isLockedForActions($estadoId);
              $closed = $isClosedState($estadoId);

              $inicioRaw = (string)($t['fecha_inicio'] ?? '');
              $finRaw    = (string)($t['fecha_fin'] ?? '');
            ?>
            <tr class="<?= esc($rowClass) ?>">
              <td>
                <?php if ($locked): ?>
                  <?php if ($estadoId === 6): ?>
                    <span class="badge bg-warning text-dark">En revisión</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Cerrada</span>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="d-flex gap-1">
                    <button type="button"
        class="btn btn-sm btn-outline-dark action-state"
        data-id="<?= (int)$t['id_tarea'] ?>"
        data-state="3">

    <img src="<?= base_url('assets/img/icons/report.svg') ?>"
         alt="Marcar como hecha"
         class="icon-svg">

</button>


                    <button type="button"
                            class="btn btn-sm btn-outline-dark action-state"
                            data-id="<?= (int)$t['id_tarea'] ?>"
                            data-state="4">
                      <i class="bi bi-x-square"></i>
                    </button>
                  </div>
                <?php endif; ?>
              </td>

              <td><?= esc($t['titulo'] ?? '-') ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
              <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

              <td data-order="<?= esc($inicioRaw) ?>" class="td-inicio">
                <?= $inicioRaw ? date('d/m/Y H:i', strtotime($inicioRaw)) : '-' ?>
              </td>

              <td><?= $finRaw ? date('d/m/Y H:i', strtotime($finRaw)) : '-' ?></td>

              <td class="text-end">
                <?php if ($closed || $estadoId === 6): ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="bi bi-lock"></i>
                  </button>
                <?php else: ?>
                  <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                     class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= TAREAS DE MI EQUIPO ================= -->
  <?php if ($canSeeTeam): ?>
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Tareas de mi equipo
      <div class="small fw-normal opacity-75">
        Control de subordinados (autoasignaciones de ellos).
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaEquipo" class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:120px;">Marcar</th>
              <th>Título</th>
              <th>Área</th>
              <th>Asignado a</th>
              <th>Asignado por</th>
              <th>Prioridad</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th class="text-end" style="width:90px;">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($tareasEquipo ?? []) as $t):

              $estadoId = (int)($t['id_estado_tarea'] ?? 0);
              $rowClass = $rowClassByState($estadoId);

              $locked = $isLockedForActions($estadoId);
              $closed = $isClosedState($estadoId);

              $inicioRaw = (string)($t['fecha_inicio'] ?? '');
              $finRaw    = (string)($t['fecha_fin'] ?? '');
            ?>
            <tr class="<?= esc($rowClass) ?>">
              <td>
                <?php if ($locked): ?>
                  <?php if ($estadoId === 6): ?>
                    <span class="badge bg-warning text-dark">En revisión</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Cerrada</span>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="d-flex gap-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-dark action-state"
                            data-id="<?= (int)$t['id_tarea'] ?>"
                            data-state="3">
                      <i class="bi bi-check2-square"></i>
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-dark action-state"
                            data-id="<?= (int)$t['id_tarea'] ?>"
                            data-state="4">
                      <i class="bi bi-x-square"></i>
                    </button>
                  </div>
                <?php endif; ?>
              </td>

              <td><?= esc($t['titulo'] ?? '-') ?></td>
              <td><?= esc($t['nombre_area'] ?? '-') ?></td>
              <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
              <td><?= esc($t['asignado_por_nombre'] ?? '-') ?></td>
              <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
              <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

              <td data-order="<?= esc($inicioRaw) ?>" class="td-inicio">
                <?= $inicioRaw ? date('d/m/Y H:i', strtotime($inicioRaw)) : '-' ?>
              </td>

              <td><?= $finRaw ? date('d/m/Y H:i', strtotime($finRaw)) : '-' ?></td>

              <td class="text-end">
                <?php if ($closed || $estadoId === 6): ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="bi bi-lock"></i>
                  </button>
                <?php else: ?>
                  <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                     class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ================= ESTILOS (sin neón) ================= -->
<style>
/* Colores suaves (no neón) para leer rápido */
.row-finalizada { background-color: #e6f4ea !important; } /* verde suave */
.row-norealizada{ background-color: #fdecea !important; } /* rojo suave */
.row-cancelada  { background-color: #f1f1f1 !important; color: #6c757d; }
.row-revision   { background-color: #fff3cd !important; } /* amarillo suave */

/* Botones DataTables negros */
.dt-buttons .btn{
  background-color:#000 !important;
  color:#fff !important;
  border:none !important;
}
.dt-buttons .btn:hover{ background-color:#222 !important; }
</style>

<!-- ================= LIBRERÍAS ================= -->
<!-- Nota: Si tu layout YA incluye jQuery/DataTables, elimina estas líneas para evitar doble carga -->
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

  // -------------------------------------------------------------
  // Contexto de alcance (self / area / division / super)
  // Lo usamos SOLO para mensajes al usuario (confirmaciones).
  // La autorización real SIEMPRE la valida el backend (Service).
  // -------------------------------------------------------------
  const assignMode = <?= json_encode($assignMode) ?>;

  // =========================================================
  // 1) DataTables init (reutilizable)
  // =========================================================
  function initDataTable(idTabla, columnaOrdenInicio) {
    $(idTabla).DataTable({
      order: [[columnaOrdenInicio, 'desc']],
      pageLength: 5,
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excel', text: 'Excel', className: 'btn btn-sm' },
        { extend: 'pdf',   text: 'PDF',   className: 'btn btn-sm' },
        { extend: 'print', text: 'Imprimir', className: 'btn btn-sm' }
      ],
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        paginate: { next: "›", previous: "‹" }
      }
    });
  }

  // Orden por columna "Inicio"
  initDataTable('#tablaMisTareas', 5);
  initDataTable('#tablaAsignadas', 6);
  if ($('#tablaEquipo').length) initDataTable('#tablaEquipo', 7);
  if ($('#tablaRevision').length) initDataTable('#tablaRevision', 6);

  // =========================================================
  // 2) Filtro por rango (cliente) usando DataTables ext.search
  // =========================================================
  const columnMap = {
    tablaMisTareas: 5,
    tablaAsignadas: 6,
    tablaEquipo:    7,
    tablaRevision:  6
  };

  /**
   * Extrae YYYY-mm-dd desde un timestamp (data-order)
   */
  function parseDateKey(raw) {
    if (!raw) return '';
    return raw.substring(0,10);
  }

  /**
   * Hook global de DataTables: decide si una fila pasa el filtro
   */
  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    const tableId = settings.nTable.getAttribute('id');
    const colIdx  = columnMap[tableId];
    if (typeof colIdx === 'undefined') return true;

    const desde = $('#fDesde').val() || '';
    const hasta = $('#fHasta').val() || '';
    if (!desde && !hasta) return true;

    const api   = new $.fn.dataTable.Api(settings);
    const node  = api.row(dataIndex).node();

    // Tomamos el "data-order" de la celda "Inicio"
    const td    = $(node).find('td').eq(colIdx);
    const order = td.attr('data-order') || '';
    const key   = parseDateKey(order);

    if (!key) return false;
    if (desde && key < desde) return false;
    if (hasta && key > hasta) return false;

    return true;
  });

  $('#btnAplicarFiltro').on('click', function(){
    $('#tablaMisTareas').DataTable().draw();
    $('#tablaAsignadas').DataTable().draw();
    if ($('#tablaEquipo').length) $('#tablaEquipo').DataTable().draw();
    if ($('#tablaRevision').length) $('#tablaRevision').DataTable().draw();
  });

  $('#btnLimpiarFiltro').on('click', function(){
    $('#fDesde').val('');
    $('#fHasta').val('');
    $('#tablaMisTareas').DataTable().draw();
    $('#tablaAsignadas').DataTable().draw();
    if ($('#tablaEquipo').length) $('#tablaEquipo').DataTable().draw();
    if ($('#tablaRevision').length) $('#tablaRevision').DataTable().draw();
  });

  // =========================================================
  // 3) CSRF (CI4)
  // =========================================================
  let csrfName = <?= json_encode(csrf_token()) ?>;
  let csrfHash = <?= json_encode(csrf_hash()) ?>;

  // =========================================================
  // 4) Acciones rápidas: Realizada / No realizada
  // Endpoint real según tu Routes:
  // POST tareas/estado/{id}
  //
  // IMPORTANTE:
  // - El backend (según Service) espera el campo "estado" (3/4)
  // - Aquí enviamos "estado" (y también "id_estado_tarea" por compatibilidad)
  // =========================================================
  async function postEstado(taskId, estadoId){
    const url = <?= json_encode(site_url('tareas/estado')) ?> + '/' + taskId;

    const body = new URLSearchParams();
    body.append(csrfName, csrfHash);

    // ✅ Campo correcto para el backend actual:
    body.append('estado', String(estadoId));

    // ✅ Compatibilidad por si en algún punto leías id_estado_tarea:
    body.append('id_estado_tarea', String(estadoId));

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    });

    const data = await res.json();

    // Si el backend devuelve csrfHash, lo actualizamos
    if (data && data.csrfHash) csrfHash = data.csrfHash;

    return data;
  }

  $(document).on('click', '.action-state', async function(){
    const taskId  = Number($(this).data('id'));
    const stateId = Number($(this).data('state'));
    if (!taskId || !stateId) return;

    const isSelf = (assignMode === 'self');

    const confirmMsg = isSelf
      ? ((stateId === 3)
          ? '¿Solicitar marcar como REALIZADA? (Se enviará a revisión de tu supervisor)'
          : '¿Solicitar marcar como NO REALIZADA? (Se enviará a revisión de tu supervisor)')
      : ((stateId === 3)
          ? '¿Marcar esta tarea como REALIZADA? (Se cerrará y ya no podrá editarse)'
          : '¿Marcar esta tarea como NO REALIZADA? (Se cerrará y ya no podrá editarse)');

    if (!confirm(confirmMsg)) return;

    try {
      const r = await postEstado(taskId, stateId);

      if (!r || !r.success) {
        alert((r && r.error) ? r.error : 'No se pudo actualizar el estado.');
        return;
      }

      if (r.message) alert(r.message);

      window.location.reload();

    } catch (e) {
      alert('Error de red al actualizar el estado.');
    }
  });

  // =========================================================
  // 5) Revisión masiva (approve / reject)
  //
  // Ruta real:
  // POST tareas/revisar-lote
  //
  // Campos esperados por el controller:
  // - action: approve|reject
  // - ids[] : lista de id_tarea
  // =========================================================
  function getSelectedReviewIds(){
    const ids = [];
    $('.chkRevision:checked').each(function(){
      ids.push(Number($(this).val()));
    });
    return ids.filter(x => x > 0);
  }

  async function postReviewBatch(action, ids){
    const url = <?= json_encode(site_url('tareas/revisar-lote')) ?>;

    const body = new URLSearchParams();
    body.append(csrfName, csrfHash);
    body.append('action', action);

    // ✅ Nombre correcto: ids[]
    ids.forEach(id => body.append('ids[]', String(id)));

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    });

    const data = await res.json();

    // Si el backend devuelve csrfHash, lo actualizamos
    if (data && data.csrfHash) csrfHash = data.csrfHash;

    return data;
  }

  $('#chkAllRevision').on('change', function(){
    $('.chkRevision').prop('checked', $(this).is(':checked'));
  });

  $('#btnApproveBatch').on('click', async function(){
    const ids = getSelectedReviewIds();
    if (!ids.length) return alert('Selecciona tareas para aprobar.');

    if (!confirm('¿Aprobar las tareas seleccionadas?')) return;

    const r = await postReviewBatch('approve', ids);
    if (!r || !r.success) return alert((r && r.error) ? r.error : 'No se pudo aprobar.');

    window.location.reload();
  });

  $('#btnRejectBatch').on('click', async function(){
    const ids = getSelectedReviewIds();
    if (!ids.length) return alert('Selecciona tareas para rechazar.');

    if (!confirm('¿Rechazar las tareas seleccionadas? (Se marcarán como No realizada)')) return;

    const r = await postReviewBatch('reject', ids);
    if (!r || !r.success) return alert((r && r.error) ? r.error : 'No se pudo rechazar.');

    window.location.reload();
  });

});
</script>

<?= $this->endSection() ?>
