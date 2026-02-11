<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<style>
:root{
  --black:#111;
  --border:#e5e5e5;
  --danger:#dc2626;
  --success:#16a34a;
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
.kpi-box h4{
  font-weight:900;
  font-size:1rem;
  margin:0;
}
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

.fc{
  font-size:.75rem;
  text-transform:uppercase;
  color:#111;
}

/* BOTONES */
.fc .fc-button{
  background:#000 !important;
  border:none !important;
  font-size:.68rem !important;
  padding:3px 8px !important;
}
.fc .fc-button:hover{
  background:#222 !important;
}

/* TITULO */
.fc .fc-toolbar-title{
  font-weight:800;
  font-size:.9rem;
}

/* NUMEROS NEGROS */
.fc-daygrid-day-number,
.fc-col-header-cell-cushion{
  color:#000 !important;
  font-weight:700;
  text-decoration:none !important;
}

/* BANDERAS */
.fc-event{
  background:transparent !important;
  border:none !important;
  padding:0 !important;
}

.flag{
  position:relative;
  display:inline-block;
  padding:2px 8px 2px 10px;
  font-size:.65rem;
  font-weight:700;
  color:#fff;
  border-radius:4px;
}

.flag::before{
  content:"";
  position:absolute;
  left:-6px;
  top:0;
  border-top:8px solid transparent;
  border-bottom:8px solid transparent;
  border-right:6px solid currentColor;
}

.flag-done{
  background:#000 !important;
  text-decoration:line-through;
}
.flag-done::after{
  content:" ✖";
}

.flag-overdue{
  background:var(--danger) !important;
}

.modal-content{
  border-radius:14px;
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

  let diff = dia >= 4 ? dia - 4 : dia + 3;

  const inicio = new Date(hoy);
  inicio.setDate(hoy.getDate() - diff);
  inicio.setHours(0,0,0,0);

  const fin = new Date(inicio);
  fin.setDate(inicio.getDate() + 6);
  fin.setHours(23,59,59,999);

  return { inicio, fin };
}

const semana = getBusinessWeekRange();

/* COLOR POR ID */
function generateColor(id){
  const colors=[
    "#111827","#374151","#4b5563",
    "#3b82f6","#8b5cf6","#10b981"
  ];
  return colors[id % colors.length];
}

const calendar = new FullCalendar.Calendar(
document.getElementById('calendar'),
{
  locale:'es',
  firstDay:1,
  initialView:'dayGridMonth',
  height:'auto',
  dayMaxEvents:2,
  fixedWeekCount:false,

  headerToolbar:{
    left:'prev,next today',
    center:'title',
    right:'dayGridMonth,timeGridWeek'
  },

  buttonText:{
    today:'HOY',
    month:'MES',
    week:'SEMANA'
  },

  events:(info,success)=>{
    fetch(`<?= site_url('tareas/events') ?>`)
    .then(r=>r.json())
    .then(data=>{

      stats = { total:0, pendientes:0, vencidas:0, realizadas:0 };

      data.forEach(ev=>{

        const inicioTarea = new Date(ev.start);
        let finTarea = ev.end ? new Date(ev.end) : null;
        if(finTarea) finTarea.setDate(finTarea.getDate()-1);

        const dentroSemana =
          inicioTarea >= semana.inicio &&
          inicioTarea <= semana.fin;

        if(dentroSemana){

          stats.total++;

          if(ev.extendedProps.id_estado_tarea == 3){
            stats.realizadas++;
          }else{
            stats.pendientes++;
          }

          const hoy = new Date();
          if(finTarea && hoy > finTarea && ev.extendedProps.id_estado_tarea !=3){
            stats.vencidas++;
            ev.extendedProps.overdue=true;
          }
        }

      });

      updateKPI();
      success(data);
    });
  },

  eventContent(info){
    const p = info.event.extendedProps;
    const div=document.createElement("div");
    div.classList.add("flag");

    if(p.id_estado_tarea == 3){
      div.classList.add("flag-done");
    }
    else if(p.overdue){
      div.classList.add("flag-overdue");
    }
    else{
      div.style.background=generateColor(Number(info.event.id));
    }

    div.textContent="🚩 "+info.event.title;
    return { domNodes:[div] };
  },

  eventClick(info){

    const p = info.event.extendedProps;
    const inicio = new Date(info.event.start);
    let fin = info.event.end ? new Date(info.event.end) : null;
    if(fin) fin.setDate(fin.getDate()-1);

    const hoy = new Date();

    const dentroSemana =
      inicio >= semana.inicio &&
      inicio <= semana.fin;

    const dentroRangoFecha =
      fin ? hoy >= inicio && hoy <= fin : hoy >= inicio;

    const puedeMarcar =
      Number(p.id_estado_tarea) === 1 &&
      dentroSemana &&
      dentroRangoFecha;

    if(puedeMarcar){
      if(confirm("¿Marcar como hecha esta tarea?")){
        marcarComoHecha(info.event.id);
      }
    }else{
      alert("Solo puedes marcar tareas dentro de la semana actual (JUEVES → MIÉRCOLES).");
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
