<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
  :root {
    --sna-red: #c1121f;
    --sna-black: #111827;
    --sna-green: #16a34a;
    /* verde hecha */
    --sna-border: #e5e7eb;
    --sna-muted: #6b7280;
  }

  .cal-card {
    background: #fff;
    border: 1px solid var(--sna-border);
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
    padding: 8px;
  }

  /* FullCalendar compacto */
  .fc {
    --fc-page-bg-color: transparent;
    --fc-border-color: var(--sna-border);
    --fc-neutral-bg-color: #f9fafb;
    --fc-today-bg-color: rgba(193, 18, 31, .06);
  }

  /* Toolbar súper compacta */
  .fc .fc-toolbar.fc-header-toolbar {
    margin-bottom: 6px;
    padding: 4px 4px 0;
  }

  .fc .fc-toolbar-title {
    font-size: .95rem;
    font-weight: 800;
    color: var(--sna-black);
  }

  .fc .fc-button {
    border-radius: 10px !important;
    padding: .28rem .45rem !important;
    font-weight: 700;
    border: 1px solid var(--sna-border) !important;
    background: #fff !important;
    color: var(--sna-black) !important;
    box-shadow: none !important;
    font-size: .80rem !important;
    line-height: 1 !important;
  }

  .fc .fc-button:hover {
    border-color: rgba(193, 18, 31, .35) !important;
    background: rgba(193, 18, 31, .05) !important;
  }

  .fc .fc-button-primary:not(:disabled).fc-button-active {
    background: rgba(193, 18, 31, .08) !important;
    border-color: rgba(193, 18, 31, .35) !important;
  }

  /* Encabezado días */
  .fc .fc-col-header-cell-cushion {
    color: var(--sna-muted);
    font-weight: 800;
    text-decoration: none;
    font-size: .78rem;
  }

  /* Números de día */
  .fc .fc-daygrid-day-number {
    color: var(--sna-black);
    text-decoration: none;
    font-weight: 700;
    font-size: .80rem;
    padding: 4px;
  }

  /* Celdas más pequeñas */
  .fc .fc-daygrid-day-frame {
    min-height: 72px;
  }

  .fc .fc-daygrid-day-top {
    justify-content: flex-end;
  }

  /* Eventos súper compactos */
  .fc .fc-event {
    border-radius: 10px;
    padding: 1px 6px;
    margin: 2px 3px;
    border: 1px solid transparent;
  }

  .fc .fc-event-title {
    font-weight: 800;
    font-size: .74rem;
  }

  .fc .fc-event-time {
    font-size: .70rem;
  }

  /* Colores por estado / prioridad */
  .ev-normal {
    background: var(--sna-black) !important;
    border-color: var(--sna-black) !important;
    color: #fff !important;
  }

  .ev-urgent {
    background: var(--sna-red) !important;
    border-color: var(--sna-red) !important;
    color: #fff !important;
  }

  .ev-done {
    background: var(--sna-green) !important;
    border-color: var(--sna-green) !important;
    color: #fff !important;
    text-decoration: none;
  }

  /* Leyenda mini */
  .legend {
    font-size: .82rem;
    color: var(--sna-muted);
  }

  .legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
  }

  /* Modal limpio */
  .modal-content {
    border-radius: 14px;
  }

  .task-desc {
    background: #f9fafb;
    border: 1px solid var(--sna-border);
    border-radius: 12px;
    padding: 10px;
    min-height: 70px;
    color: var(--sna-black);
  }
</style>
<?= $this->endSection() ?>


<?= $this->section('contenido') ?>

<div class="container py-3">

  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
    <div>
      <h6 class="mb-0" style="font-weight:900;color:var(--sna-black);">Calendario de Tareas</h6>
      <div class="legend mt-1">
        <span class="me-3"><span class="legend-dot" style="background:#111827;"></span>Normal</span>
        <span class="me-3"><span class="legend-dot" style="background:#c1121f;"></span>Urgente</span>
        <span><span class="legend-dot" style="background:#16a34a;"></span>Hecha</span>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <select id="scope" class="form-select form-select-sm" style="max-width:220px">
        <option value="mine">Mis tareas</option>
        <option value="assigned">Asignadas por mí</option>
      </select>

      <a class="btn btn-sm btn-outline-danger" href="<?= site_url('tareas/asignar') ?>">
        <i class="bi bi-plus-circle me-1"></i> Asignar
      </a>
    </div>
  </div>

  <div class="cal-card">
    <div id="calendar"></div>
  </div>

  <input type="hidden" id="csrfName" value="<?= esc(csrf_token()) ?>">
  <input type="hidden" id="csrfHash" value="<?= esc(csrf_hash()) ?>">
</div>

<!-- Modal detalle -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="taskTitle" style="font-weight:900;">Tarea</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6"><b>Área:</b> <span id="taskArea"></span></div>
          <div class="col-md-6"><b>Prioridad:</b> <span id="taskPriority"></span></div>
          <div class="col-md-6"><b>Estado:</b> <span id="taskState"></span></div>
          <div class="col-md-6"><b>Fechas:</b> <span id="taskDates"></span></div>
          <div class="col-md-6"><b>Asignado a:</b> <span id="taskTo"></span></div>
          <div class="col-md-6"><b>Asignado por:</b> <span id="taskBy"></span></div>

          <div class="col-12 mt-2">
            <b>Descripción</b>
            <div id="taskDesc" class="task-desc"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button id="btnDone" class="btn btn-success btn-sm d-none">
          <i class="bi bi-check2-circle me-1"></i> Marcar como hecha
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
  window.addEventListener('load', function() {
    if (!window.FullCalendar) return console.error('FullCalendar no cargó');
    if (!window.bootstrap) return console.error('Bootstrap no cargó');

    const currentUserId = <?= (int) session()->get('id_user') ?>;

    const scopeEl = document.getElementById('scope');
    const calendarEl = document.getElementById('calendar');

    const modalEl = document.getElementById('taskModal');
    const modal = new bootstrap.Modal(modalEl);

    const taskTitle = document.getElementById('taskTitle');
    const taskArea = document.getElementById('taskArea');
    const taskPriority = document.getElementById('taskPriority');
    const taskState = document.getElementById('taskState');
    const taskDates = document.getElementById('taskDates');
    const taskTo = document.getElementById('taskTo');
    const taskBy = document.getElementById('taskBy');
    const taskDesc = document.getElementById('taskDesc');
    const btnDone = document.getElementById('btnDone');

    const csrfNameEl = document.getElementById('csrfName');
    const csrfHashEl = document.getElementById('csrfHash');

    let selectedTaskId = null;

    function fmtDate(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      return d.toLocaleString();
    }

    function loadEvents(info, successCallback, failureCallback) {
      const scope = scopeEl.value || 'mine';

      fetch(`<?= site_url('tareas/events') ?>?scope=${encodeURIComponent(scope)}`, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(r => r.json())
        .then(data => {
          if (data && data.error) {
            console.error('Eventos error:', data.error);
            successCallback([]);
            return;
          }
          successCallback(data);
        })
        .catch(err => {
          console.error('Error cargando eventos:', err);
          if (failureCallback) failureCallback(err);
        });
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'es',
      height: 'auto',
      firstDay: 1,
      nowIndicator: true,
      dayMaxEvents: 2,

      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek'
      },
      buttonText: {
        today: 'Hoy',
        month: 'Mes',
        week: 'Semana'
      },

      events: loadEvents,

      eventDidMount: function(info) {
        const p = info.event.extendedProps || {};
        const prioridad = (p.prioridad || '').toLowerCase();
        const estado = (p.estado || '').toLowerCase();

        info.el.classList.remove('ev-normal', 'ev-urgent', 'ev-done');

        if (estado === 'hecha') {
          info.el.classList.add('ev-done');
          return;
        }
        if (prioridad === 'urgente') {
          info.el.classList.add('ev-urgent');
          return;
        }
        info.el.classList.add('ev-normal');
      },

      eventClick: function(arg) {
        const ev = arg.event;
        const p = ev.extendedProps || {};

        selectedTaskId = ev.id;

        taskTitle.textContent = ev.title || 'Tarea';
        taskArea.textContent = p.nombre_area || '';
        taskPriority.textContent = p.prioridad || '';
        taskState.textContent = p.estado || '';
        taskDates.textContent = `${fmtDate(ev.startStr)} ${ev.endStr ? '→ ' + fmtDate(ev.endStr) : ''}`;
        taskTo.textContent = p.asignado_a_nombre || '';
        taskBy.textContent = p.asignado_por_nombre || '';
        taskDesc.textContent = (p.descripcion && p.descripcion.trim() !== '') ? p.descripcion : 'Sin descripción';

        const canDone = (Number(p.asignado_a) === Number(currentUserId)) && (p.estado === 'Pendiente');
        btnDone.classList.toggle('d-none', !canDone);

        modal.show();
      }
    });

    calendar.render();

    scopeEl.addEventListener('change', () => calendar.refetchEvents());

    btnDone.addEventListener('click', async () => {
      if (!selectedTaskId) return;

      const csrfName = csrfNameEl.value;
      const csrfHash = csrfHashEl.value;

      const body = new URLSearchParams();
      body.append(csrfName, csrfHash);

      const res = await fetch(`<?= site_url('tareas/completar') ?>/${selectedTaskId}`, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: body.toString()
      });

      const data = await res.json();

      if (data.success) {
        modal.hide();
        calendar.refetchEvents();
      } else {
        alert(data.error || 'No se pudo marcar como hecha.');
      }
    });
  });
</script>
<?= $this->endSection() ?>