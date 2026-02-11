<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
:root{
  --sna-red:#c1121f;
  --sna-black:#111827;
  --sna-green:#16a34a;
  --sna-border:#e5e7eb;
  --sna-muted:#6b7280;
}

.cal-card{
  background:#fff;
  border:1px solid var(--sna-border);
  border-radius:12px;
  box-shadow:0 6px 18px rgba(0,0,0,.06);
  padding:6px;
}

.fc .fc-daygrid-day-frame{min-height:56px}
.fc .fc-event{border-radius:8px;padding:0 4px;margin:1px 2px}

.ev-normal{background:var(--sna-black)!important;color:#fff}
.ev-urgent{background:var(--sna-red)!important;color:#fff}
.ev-done{background:var(--sna-green)!important;color:#fff}

.task-desc{
  background:#f9fafb;
  border:1px solid var(--sna-border);
  border-radius:10px;
  padding:8px;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="container py-2">

  <div class="d-flex justify-content-between mb-2">
    <div>
      <h6 class="fw-bold mb-0">Calendario de Tareas</h6>
    </div>

    <div class="d-flex gap-2">
      <select id="scope" class="form-select form-select-sm">
        <option value="mine">Mis tareas</option>
        <option value="assigned">Asignadas por mí</option>
      </select>

      <a class="btn btn-sm btn-outline-danger" href="<?= site_url('tareas/asignar') ?>">
        <i class="bi bi-plus-circle"></i> Asignar
      </a>
    </div>
  </div>

  <div class="cal-card">
    <div id="calendar"></div>
  </div>

  <input type="hidden" id="csrfName" value="<?= csrf_token() ?>">
  <input type="hidden" id="csrfHash" value="<?= csrf_hash() ?>">
</div>

<!-- MODAL -->
<div class="modal fade" id="taskModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header py-2">
        <h6 class="modal-title" id="taskTitle"></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body py-2">
        <div class="row g-1">
          <div class="col-md-6"><b>Área:</b> <span id="taskArea"></span></div>
          <div class="col-md-6"><b>Prioridad:</b> <span id="taskPriority"></span></div>
          <div class="col-md-6"><b>Estado:</b> <span id="taskState"></span></div>
          <div class="col-md-6"><b>Fechas:</b> <span id="taskDates"></span></div>
          <div class="col-md-6"><b>Asignado a:</b> <span id="taskTo"></span></div>
          <div class="col-md-6"><b>Asignado por:</b> <span id="taskBy"></span></div>

          <div class="col-12 mt-1">
            <b>Descripción</b>
            <div id="taskDesc" class="task-desc"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer py-2">
        <button id="btnDone" class="btn btn-success btn-sm d-none">
          <i class="bi bi-check2-circle"></i> Marcar como hecha
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>

    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
window.addEventListener('load',()=>{

  const currentUserId = <?= (int)session()->get('id_user') ?>;
  const modal = new bootstrap.Modal(document.getElementById('taskModal'));
  const btnDone = document.getElementById('btnDone');
  const scopeEl = document.getElementById('scope');

  let selectedTaskId = null;

  const calendar = new FullCalendar.Calendar(
    document.getElementById('calendar'),
    {
      locale:'es',
      firstDay:1,
      initialView:'dayGridMonth',
      height:'auto',
      dayMaxEvents:1,
      fixedWeekCount:false,

      events:(info,success)=>{
        fetch(`<?= site_url('tareas/events') ?>?scope=${scopeEl.value}`)
          .then(r=>r.json())
          .then(success);
      },

      eventDidMount(info){
        const p = info.event.extendedProps;
        info.el.classList.add(
          p.id_estado_tarea === 3 ? 'ev-done' :
          p.prioridad === 'Urgente' ? 'ev-urgent' : 'ev-normal'
        );
      },

      eventClick(info){
        const p = info.event.extendedProps;
        selectedTaskId = info.event.id;

        taskTitle.textContent = info.event.title;
        taskArea.textContent = p.area;
        taskPriority.textContent = p.prioridad;
        taskState.textContent = p.estado;
        taskDates.textContent = info.event.start.toLocaleString();
        taskTo.textContent = p.asignado_a_nombre;
        taskBy.textContent = p.asignado_por_nombre;
        taskDesc.textContent = p.descripcion || 'Sin descripción';

        const puedeMarcar =
          Number(p.asignado_a) === currentUserId &&
          Number(p.id_estado_tarea) === 1;

        btnDone.classList.toggle('d-none', !puedeMarcar);
        modal.show();
      }
    }
  );

  calendar.render();
  scopeEl.addEventListener('change',()=>calendar.refetchEvents());

  btnDone.addEventListener('click', async () => {

  const body = new URLSearchParams();
  body.append(
    document.getElementById('csrfName').value,
    document.getElementById('csrfHash').value
  );

  const res = await fetch(
    `<?= site_url('tareas/completar') ?>/${selectedTaskId}`,
    {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: body.toString()
    }
  );

  // 🔒 Seguridad extra
  if (!res.ok) {
    alert('Error de comunicación con el servidor');
    return;
  }

  const data = await res.json();

  if (data.success === true) {
    modal.hide();
    calendar.refetchEvents();
  } else {
    alert(data.error ?? 'No se pudo marcar la tarea');
  }
});

});
</script>
<?= $this->endSection() ?>
