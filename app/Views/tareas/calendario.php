<?= $this->extend('layouts/main') ?> 

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<style>
:root{
  --border:#e5e5e5;
  --danger:#dc2626;
  --success:#16a34a;

  /* Corporativos (WHOLPHINSYS) */
  --c1:#0511F2; /* primary */
  --c2:#023059; /* navy */
  --c3:#049DBF; /* accent */
  --c4:#2B94B2; /* accent2 */
  --c5:#0D0D0D; /* black */
}

/* KPI */
.kpi-box{
  background:#fff;
  border:1px solid var(--border);
  border-radius:12px;
  padding:10px;
  text-align:center;
  box-shadow:0 4px 12px rgba(0,0,0,.04);
}
.kpi-box h4{ font-weight:900; font-size:1rem; margin:0; }
.kpi-box span{
  font-size:.65rem;
  font-weight:600;
  text-transform:uppercase;
  color:#666;
}

/* CALENDAR */
.cal-card{
  background:#fff;
  border:1px solid var(--border);
  border-radius:14px;
  padding:8px;
  box-shadow:0 6px 18px rgba(0,0,0,.05);
}

.fc{ font-size:.78rem; color:#111; }

/* BOTONES */
.fc .fc-button{
  background:#000 !important;
  border:none !important;
  font-size:.68rem !important;
  padding:3px 8px !important;
}
.fc .fc-button:hover{ background:#222 !important; }

/* TITULO */
.fc .fc-toolbar-title{
  font-weight:900;
  font-size:.95rem;
  text-transform:uppercase;
}

/* HEADERS DIAS */
.fc-col-header-cell-cushion{
  color:#000 !important;
  font-weight:900;
  text-decoration:none !important;
  text-transform:uppercase;
  font-size:.72rem;
}

/* NUMEROS */
.fc-daygrid-day-number{
  color:#000 !important;
  font-weight:900;
  text-decoration:none !important;
}

/* ====== CLAVE: EN MES NO QUEREMOS BARRAS MULTI-DÍA ======
   (porque nosotros lo renderizamos como chips dentro del día) */
.fc-dayGridMonth-view .fc-daygrid-block-event{
  display:none !important;
}

/* Quitamos estilo default */
.fc-event{
  background:transparent !important;
  border:none !important;
  padding:0 !important;
}

/* Puntico del list-item: lo ocultamos */
.fc-daygrid-dot-event .fc-daygrid-event-dot{
  display:none !important;
}

/* ====== LISTA DENTRO DEL DÍA (ESPACIADO) ====== */
.fc-daygrid-day-events{
  margin-top:6px;
  display:flex;
  flex-direction:column;
  gap:6px;
}

/* “+X más” */
.fc .fc-more-link{
  font-weight:900;
  text-transform:uppercase;
  text-decoration:none;
  cursor:pointer;
}

/* Para que no se vea aplastado */
.fc-daygrid-day-frame{
  min-height:120px;
}

/* ====== CHIP CORPORATIVO + FECHAS + 2 LÍNEAS TÍTULO ====== */
.task-chip{
  width:100%;
  border-radius:12px;
  padding:6px 10px;
  color:#fff;
  box-shadow:0 6px 14px rgba(0,0,0,.14);
  box-sizing:border-box;
  overflow:hidden;
}

.task-chip .task-title{
  font-weight:900;
  font-size:.72rem;
  line-height:1.15;

  white-space:normal;
  overflow:hidden;
  text-overflow:ellipsis;
  display:-webkit-box;
  -webkit-box-orient:vertical;
  -webkit-line-clamp:2; /* 2 líneas */
  line-clamp:2;

  word-break:break-word;
}

.task-chip .task-dates{
  margin-top:3px;
  font-size:.62rem;
  font-weight:900;
  opacity:.92;
  letter-spacing:.2px;
}

/* Estados */
.task-done{
  background: linear-gradient(135deg, #0D0D0D, #1f2937) !important;
  text-decoration:line-through;
  opacity:.95;
}
.task-overdue{
  background: linear-gradient(135deg, #dc2626, #ef4444) !important;
}

/* Hover */
.fc-daygrid-event-harness:hover .task-chip{
  filter:brightness(1.06);
  transform: translateY(-1px);
  transition: all .12s ease;
}

/* ====== MODAL LISTA DEL DÍA ====== */
.day-modal-title{
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.5px;
}
.day-list{
  display:flex;
  flex-direction:column;
  gap:10px;
}
.day-list .task-chip{
  border-radius:14px;
}
.day-list-wrap{
  max-height:60vh;
  overflow:auto;
  padding-right:4px;
}
</style>
<?= $this->endSection() ?>


<?= $this->section('contenido') ?>
<div class="container py-3">

<h5 class="fw-bold mb-3">MI CALENDARIO DE ACTIVIDADES</h5>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="kpi-box">
      <h4 id="kpiTotal">0</h4>
      <span>Total Semana</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-box">
      <h4 id="kpiPendientes">0</h4>
      <span>Pendientes</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-box">
      <h4 id="kpiVencidas" style="color:var(--danger)">0</h4>
      <span>Vencidas</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-box">
      <h4 id="kpiRealizadas" style="color:var(--success)">0</h4>
      <span>Realizadas</span>
    </div>
  </div>
</div>

<div class="cal-card">
  <div id="calendar"></div>
</div>

<input type="hidden" id="csrfName" value="<?= csrf_token() ?>">
<input type="hidden" id="csrfHash" value="<?= csrf_hash() ?>">

</div>

<!-- ===== MODAL: LISTA COMPLETA DEL DÍA ===== -->
<div class="modal fade" id="dayTasksModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-header">
        <h5 class="modal-title day-modal-title" id="dayTasksModalTitle">Tareas del día</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="day-list-wrap">
          <div id="dayTasksModalList" class="day-list"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
window.addEventListener('load',()=>{

let stats = { total:0, pendientes:0, vencidas:0, realizadas:0 };

/* ===== SEMANA JUEVES → MIÉRCOLES ===== */
function getBusinessWeekRange(){
  const hoy = new Date();
  const dia = hoy.getDay() === 0 ? 7 : hoy.getDay(); // Domingo=7
  const diff = (dia >= 4) ? (dia - 4) : (dia + 3);

  const inicio = new Date(hoy);
  inicio.setDate(hoy.getDate() - diff);
  inicio.setHours(0,0,0,0);

  const fin = new Date(inicio);
  fin.setDate(inicio.getDate() + 6);
  fin.setHours(23,59,59,999);

  return { inicio, fin };
}
const semana = getBusinessWeekRange();

/* ====== GRADIENTES CORPORATIVOS (LLAMATIVOS) ====== */
function generateCorporateGradient(id){
  const gradients = [
    "linear-gradient(135deg, #0511F2, #049DBF)",
    "linear-gradient(135deg, #023059, #0511F2)",
    "linear-gradient(135deg, #049DBF, #2B94B2)",
    "linear-gradient(135deg, #111827, #0511F2)",
    "linear-gradient(135deg, #2B94B2, #0511F2)"
  ];
  return gradients[id % gradients.length];
}

/* Helper: sumar días */
function addDays(dateInput, days){
  const d = new Date(dateInput);
  d.setDate(d.getDate() + days);
  return d;
}

/* Helper: comparar día (local) */
function sameDay(a, b){
  return a.getFullYear()===b.getFullYear() &&
         a.getMonth()===b.getMonth() &&
         a.getDate()===b.getDate();
}

/* Helper: formateo ES bonito */
function fmtDayTitle(dateObj){
  return new Intl.DateTimeFormat('es-ES', {
    weekday:'long', day:'2-digit', month:'long', year:'numeric'
  }).format(dateObj);
}
function fmtShort(dateObj){
  return new Intl.DateTimeFormat('es-ES', { day:'2-digit', month:'short' }).format(dateObj);
}

/* ====== Construir chip (reutilizable para calendario y modal) ====== */
function buildTaskChip(event){
  const p = event.extendedProps || {};

  // Fechas reales para mostrar (guardadas desde el fetch)
  const realStart = p._startDate ? new Date(p._startDate) : event.start;
  let realEnd = null;

  if(p._endDate){
    const tmp = new Date(p._endDate);
    tmp.setDate(tmp.getDate() - 1); // exclusivo -> inclusivo visual
    realEnd = tmp;
  }else{
    realEnd = realStart;
  }

  const startLabel = fmtShort(realStart);
  const endLabel   = fmtShort(realEnd);

  const chip = document.createElement("div");
  chip.classList.add("task-chip");

  if(Number(p.id_estado_tarea) === 3){
    chip.classList.add("task-done");
  }else if(p.overdue){
    chip.classList.add("task-overdue");
  }else{
    chip.style.background = generateCorporateGradient(Number(event.id) || 0);
  }

  const title = document.createElement("div");
  title.classList.add("task-title");
  title.textContent = event.title;

  const dates = document.createElement("div");
  dates.classList.add("task-dates");
  dates.textContent = (startLabel === endLabel)
    ? `📅 ${startLabel}`
    : `📅 ${startLabel} → ${endLabel}`;

  chip.title = `${event.title}\n${dates.textContent}`;

  chip.appendChild(title);
  chip.appendChild(dates);

  return chip;
}

/* ====== Modal helpers ====== */
function openDayModal(dateObj, events){
  const titleEl = document.getElementById('dayTasksModalTitle');
  const listEl  = document.getElementById('dayTasksModalList');

  titleEl.textContent = `Tareas del día: ${fmtDayTitle(dateObj)}`;

  listEl.innerHTML = "";
  if(!events.length){
    const empty = document.createElement("div");
    empty.className = "text-muted fw-bold";
    empty.textContent = "No hay tareas para este día.";
    listEl.appendChild(empty);
  }else{
    // Ordena por hora de inicio (si viene con hora)
    events.sort((a,b)=> new Date(a.start) - new Date(b.start));
    events.forEach(ev=>{
      listEl.appendChild(buildTaskChip(ev));
    });
  }

  // Bootstrap Modal (asumido en tu layout)
  if(window.bootstrap && window.bootstrap.Modal){
    const modalEl = document.getElementById('dayTasksModal');
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }else{
    alert("Tu layout no tiene Bootstrap JS cargado. Carga bootstrap.bundle.min.js para ver el modal.");
  }
}

const calendarEl = document.getElementById('calendar');

const calendar = new FullCalendar.Calendar(calendarEl,{
  locale: 'es',
  firstDay: 1,

  initialView:'dayGridMonth',
  height:'auto',
  fixedWeekCount:false,

  headerToolbar:{
    left:'prev,next today',
    center:'title',
    right:'dayGridMonth,timeGridWeek'
  },

  buttonText:{
    today:'Hoy',
    month:'Mes',
    week:'Semana'
  },

  moreLinkText: function(n){
    return `+${n} más`;
  },

  views:{
    dayGridMonth:{
      eventDisplay: 'list-item',
      dayMaxEventRows: 4,

      /* ====== AQUÍ: en vez de popover, abrimos MODAL con la lista completa ====== */
      moreLinkClick: function(arg){
        // arg.date = fecha del día donde se hizo click
        const dateClicked = arg.date;

        // Tomamos eventos cargados y filtramos por ese día (start del evento)
        const dayEvents = calendar.getEvents().filter(ev => sameDay(new Date(ev.start), dateClicked));

        openDayModal(dateClicked, dayEvents);

        // Evita comportamiento por defecto
        return "none";
      }
    },
    timeGridWeek:{
      eventDisplay: 'block'
    }
  },

  events:(info, success)=>{
    fetch(`<?= site_url('tareas/events') ?>`)
      .then(r=>r.json())
      .then(data=>{

        stats = { total:0, pendientes:0, vencidas:0, realizadas:0 };

        const viewType = calendar.view.type;

        const normalized = data.map(ev=>{
          const inicioTarea = new Date(ev.start);

          let finTarea = ev.end ? new Date(ev.end) : null;
          if(finTarea) finTarea.setDate(finTarea.getDate()-1);

          // Guardamos fechas reales para pintarlas siempre
          ev.extendedProps = ev.extendedProps || {};
          ev.extendedProps._startDate = ev.start;
          ev.extendedProps._endDate   = ev.end;

          const dentroSemana = (inicioTarea >= semana.inicio && inicioTarea <= semana.fin);

          if(dentroSemana){
            stats.total++;

            if(Number(ev.extendedProps.id_estado_tarea) === 3){
              stats.realizadas++;
            }else{
              stats.pendientes++;
            }

            const hoy = new Date();
            if(finTarea && hoy > finTarea && Number(ev.extendedProps.id_estado_tarea) !== 3){
              stats.vencidas++;
              ev.extendedProps.overdue = true;
            }
          }

          /* SOLO EN MES: forzar 1 día para que NO salga barra extendida */
          if(viewType === 'dayGridMonth'){
            ev.allDay = true;
            ev.display = 'list-item';

            // end = start + 1 día (end exclusivo)
            ev.end = addDays(ev.start, 1).toISOString();
          }

          return ev;
        });

        updateKPI();
        success(normalized);
      });
  },

  eventContent(info){
    // Usamos la misma construcción de chip
    const chip = buildTaskChip(info.event);
    return { domNodes:[chip] };
  },

  eventClick(info){
    const p = info.event.extendedProps || {};

    const inicio = new Date(info.event.start);
    let fin = info.event.end ? new Date(info.event.end) : null;
    if(fin) fin.setDate(fin.getDate()-1);

    const hoy = new Date();

    const dentroSemana = (inicio >= semana.inicio && inicio <= semana.fin);
    const dentroRangoFecha = fin ? (hoy >= inicio && hoy <= fin) : (hoy >= inicio);

    const puedeMarcar =
      Number(p.id_estado_tarea) === 1 &&
      dentroSemana &&
      dentroRangoFecha;

    if(puedeMarcar){
      if(confirm("¿Marcar como hecha esta tarea?")){
        marcarComoHecha(info.event.id);
      }
    }
  }
});

calendar.render();

function updateKPI(){
  document.getElementById('kpiTotal').textContent=stats.total;
  document.getElementById('kpiPendientes').textContent=stats.pendientes;
  document.getElementById('kpiVencidas').textContent=stats.vencidas;
  document.getElementById('kpiRealizadas').textContent=stats.realizadas;
}

function marcarComoHecha(id){
  const body = new URLSearchParams();
  body.append(
    document.getElementById('csrfName').value,
    document.getElementById('csrfHash').value
  );

  fetch(`<?= site_url('tareas/completar') ?>/${id}`,{
    method:'POST',
    headers:{
      'X-Requested-With':'XMLHttpRequest',
      'Content-Type':'application/x-www-form-urlencoded'
    },
    body:body.toString()
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.success){
      calendar.refetchEvents();
    }else{
      alert(data.error ?? "No se pudo marcar la tarea");
    }
  });
}

});
</script>
<?= $this->endSection() ?>