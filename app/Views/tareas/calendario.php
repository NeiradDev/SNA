<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<style>
  :root {
    --border: #e5e5e5;
    --danger: #dc2626;
    --success: #16a34a;

    --c1: #0511F2;
    --c2: #023059;
    --c3: #049DBF;
    --c4: #2B94B2;
    --c5: #0D0D0D;
  }

  .kpi-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .04);
  }

  .kpi-box h4 {
    font-weight: 900;
    font-size: 1rem;
    margin: 0;
  }

  .kpi-box span {
    font-size: .65rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #666;
  }

  .cal-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 8px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .05);
  }

  .fc {
    font-size: .78rem;
    color: #111;
  }

  .fc .fc-button {
    background: #000 !important;
    border: none !important;
    font-size: .68rem !important;
    padding: 3px 8px !important;
  }

  .fc .fc-button:hover {
    background: #222 !important;
  }

  .fc .fc-toolbar-title {
    font-weight: 900;
    font-size: .95rem;
    text-transform: uppercase;
  }

  .fc-col-header-cell-cushion {
    color: #000 !important;
    font-weight: 900;
    text-decoration: none !important;
    text-transform: uppercase;
    font-size: .72rem;
  }

  .fc-daygrid-day-number {
    color: #000 !important;
    font-weight: 900;
    text-decoration: none !important;
  }

  .fc-dayGridMonth-view .fc-daygrid-block-event {
    display: none !important;
  }

  .fc-event {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
  }

  .fc-daygrid-dot-event .fc-daygrid-event-dot {
    display: none !important;
  }

  .fc-daygrid-day-events {
    margin-top: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .fc .fc-more-link {
    font-weight: 900;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
  }

  .fc-daygrid-day-frame {
    min-height: 120px;
  }

  .task-chip {
    width: 100%;
    border-radius: 12px;
    padding: 6px 10px;
    color: #fff;
    box-shadow: 0 6px 14px rgba(0, 0, 0, .14);
    box-sizing: border-box;
    overflow: hidden;
  }

  .task-chip .task-title {
    font-weight: 900;
    font-size: .72rem;
    line-height: 1.15;
    white-space: normal;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    word-break: break-word;
  }

  .task-chip .task-dates {
    margin-top: 3px;
    font-size: .62rem;
    font-weight: 900;
    opacity: .92;
    letter-spacing: .2px;
  }

  .task-done {
    background: linear-gradient(135deg, #0D0D0D, #1f2937) !important;
    text-decoration: line-through;
    opacity: .95;
  }

  .task-overdue {
    background: linear-gradient(135deg, #dc2626, #ef4444) !important;
  }

  .fc-daygrid-event-harness:hover .task-chip {
    filter: brightness(1.06);
    transform: translateY(-1px);
    transition: all .12s ease;
  }

  .day-modal-title {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .5px;
  }

  .day-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .day-list .task-chip {
    border-radius: 14px;
  }

  .day-list-wrap {
    max-height: 60vh;
    overflow: auto;
    padding-right: 4px;
  }

  .task-detail-box {
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 12px;
    background: #fafafa;
  }

  .task-detail-label {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #666;
    margin-bottom: 2px;
  }

  .task-detail-value {
    font-size: .90rem;
    font-weight: 600;
    color: #111;
    word-break: break-word;
  }

  .task-modal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .task-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #f2f4f7;
    border: 1px solid #e5e7eb;
    font-size: .75rem;
    font-weight: 700;
  }

  .ws-note {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(4, 157, 191, .08);
    border: 1px solid rgba(4, 157, 191, .16);
    font-size: .82rem;
    color: #0D0D0D;
  }

  #calendarActionEvidenceWrap {
    display: none;
  }

  @media (max-width: 768px) {
    .fc-daygrid-day-frame {
      min-height: 90px;
    }
  }
</style>
<?= $this->endSection() ?>


<?= $this->section('contenido') ?>
<div class="container py-3">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h5 class="fw-bold mb-1">MI CALENDARIO DE ACTIVIDADES</h5>
      <div class="text-muted small">
        Aquí puedes revisar tus actividades y, si tienes personal a cargo, también las de tu equipo.
      </div>
    </div>

    <div style="min-width:260px; max-width:320px; width:100%;">
      <label for="calendarScope" class="form-label fw-bold mb-1">Ver calendario</label>
      <select id="calendarScope" class="form-select form-select-sm">
        <option value="mine">Solo mis actividades</option>
        <option value="team">Solo actividades de mi equipo</option>
        <option value="all">Mis actividades + mi equipo</option>
        <option value="assigned">Actividades asignadas por mí</option>
      </select>
    </div>
  </div>

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

<!-- MODAL: LISTA COMPLETA DEL DÍA -->
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

<!-- MODAL: DETALLE / ACCIONES DE LA ACTIVIDAD -->
<div class="modal fade" id="calendarTaskActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="calendarTaskActionTitle">Detalle de actividad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="task-detail-box mb-3">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="task-detail-label">Actividad</div>
              <div class="task-detail-value" id="detailTitulo">-</div>
            </div>

            <div class="col-md-6">
              <div class="task-detail-label">Área</div>
              <div class="task-detail-value" id="detailArea">-</div>
            </div>

            <div class="col-md-6">
              <div class="task-detail-label">Asignado a</div>
              <div class="task-detail-value" id="detailAsignadoA">-</div>
            </div>

            <div class="col-md-6">
              <div class="task-detail-label">Asignado por</div>
              <div class="task-detail-value" id="detailAsignadoPor">-</div>
            </div>

            <div class="col-md-6">
              <div class="task-detail-label">Inicio</div>
              <div class="task-detail-value" id="detailInicio">-</div>
            </div>

            <div class="col-md-6">
              <div class="task-detail-label">Fin</div>
              <div class="task-detail-value" id="detailFin">-</div>
            </div>

            <div class="col-12">
              <div class="task-detail-label">Descripción</div>
              <div class="task-detail-value" id="detailDescripcion">-</div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
          <div class="task-meta-chip" id="detailEstadoChip">Estado: -</div>
          <div class="task-meta-chip" id="detailPrioridadChip">Prioridad: -</div>
        </div>

        <div class="ws-note" id="calendarTaskActionHelp">
          Aquí puedes revisar la actividad. Si todavía está activa y te corresponde actuar, podrás marcarla desde este mismo calendario.
        </div>

        <div id="calendarActionEvidenceWrap" class="mt-3">
          <div class="border rounded-4 p-3 bg-light">
            <div class="fw-bold mb-2">Evidencia de la actividad</div>

            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="calendarHasEvidenceCheck">
              <label class="form-check-label fw-semibold" for="calendarHasEvidenceCheck">
                ¿La actividad tiene evidencia?
              </label>
            </div>

            <div id="calendarEvidenceFields" style="display:none;">
              <div class="mb-3">
                <label for="calendarEvidenceUrl" class="form-label fw-semibold">Enlace de evidencia</label>
                <input type="url" id="calendarEvidenceUrl" class="form-control" placeholder="https://drive.google.com/...">
              </div>

              <div class="mb-0">
                <label for="calendarEvidenceNote" class="form-label fw-semibold">Observación de evidencia</label>
                <textarea id="calendarEvidenceNote" class="form-control" rows="3" placeholder="Describe brevemente la evidencia"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="task-modal-actions" id="calendarTaskActions">
          <button type="button" class="btn btn-success" id="btnCalendarMarkDone" style="display:none;">
            Marcar como realizada
          </button>
          <button type="button" class="btn btn-outline-danger" id="btnCalendarMarkNotDone" style="display:none;">
            Marcar como no realizada
          </button>
        </div>

        <div class="d-flex gap-2">
          <a href="#" class="btn btn-outline-dark" id="btnCalendarGoManage" target="_self">Ir a gestionar</a>
          <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
  window.addEventListener('load', () => {

    let stats = {
      total: 0,
      pendientes: 0,
      vencidas: 0,
      realizadas: 0
    };
    let currentCalendarEvent = null;
    let currentRequestedState = 0;

    function getBusinessWeekRange() {
      const hoy = new Date();
      const dia = hoy.getDay() === 0 ? 7 : hoy.getDay();
      const diff = (dia >= 4) ? (dia - 4) : (dia + 3);

      const inicio = new Date(hoy);
      inicio.setDate(hoy.getDate() - diff);
      inicio.setHours(0, 0, 0, 0);

      const fin = new Date(inicio);
      fin.setDate(inicio.getDate() + 6);
      fin.setHours(23, 59, 59, 999);

      return {
        inicio,
        fin
      };
    }
    const semana = getBusinessWeekRange();

    function generateCorporateGradient(id) {
      const gradients = [
        "linear-gradient(135deg, #0511F2, #049DBF)",
        "linear-gradient(135deg, #023059, #0511F2)",
        "linear-gradient(135deg, #049DBF, #2B94B2)",
        "linear-gradient(135deg, #111827, #0511F2)",
        "linear-gradient(135deg, #2B94B2, #0511F2)"
      ];
      return gradients[id % gradients.length];
    }

    function addDays(dateInput, days) {
      const d = new Date(dateInput);
      d.setDate(d.getDate() + days);
      return d;
    }

    function getRawDateKey(rawValue) {
      const raw = String(rawValue || '').trim();
      if (!raw) return '';

      const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
      return match ? match[1] : '';
    }

    function getDateKeyFromDateObj(dateObj) {
      if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) return '';
      const y = dateObj.getFullYear();
      const m = String(dateObj.getMonth() + 1).padStart(2, '0');
      const d = String(dateObj.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }

    function sameDayByKey(dateKeyA, dateKeyB) {
      return String(dateKeyA || '') === String(dateKeyB || '');
    }

    function fmtDayTitle(dateObj) {
      return new Intl.DateTimeFormat('es-ES', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
      }).format(dateObj);
    }

    function fmtShort(dateObj) {
      return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: 'short'
      }).format(dateObj);
    }

    function fmtDateTimeLocal(raw) {
      const value = String(raw || '').trim();
      if (!value) return '-';

      const dt = new Date(value);
      if (!isNaN(dt.getTime())) {
        return new Intl.DateTimeFormat('es-EC', {
          timeZone: 'America/Guayaquil',
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
          hour12: false
        }).format(dt).replace(',', '');
      }

      return value;
    }

    function buildTaskChip(event) {
      const p = event.extendedProps || {};

      const realStart = p._startDate ? new Date(p._startDate) : event.start;
      let realEnd = null;

      if (p._endDate) {
        const tmp = new Date(p._endDate);
        tmp.setDate(tmp.getDate() - 1);
        realEnd = tmp;
      } else {
        realEnd = realStart;
      }

      const startLabel = fmtShort(realStart);
      const endLabel = fmtShort(realEnd);

      const chip = document.createElement("div");
      chip.classList.add("task-chip");

      if (Number(p.id_estado_tarea) === 3) {
        chip.classList.add("task-done");
      } else if (p.overdue) {
        chip.classList.add("task-overdue");
      } else {
        chip.style.background = generateCorporateGradient(Number(event.id) || 0);
      }

      const title = document.createElement("div");
      title.classList.add("task-title");
      title.textContent = event.title;

      const dates = document.createElement("div");
      dates.classList.add("task-dates");
      dates.textContent = (startLabel === endLabel) ?
        `📅 ${startLabel}` :
        `📅 ${startLabel} → ${endLabel}`;

      chip.title = `${event.title}\n${dates.textContent}`;

      chip.appendChild(title);
      chip.appendChild(dates);

      const assignedName = (p.asignado_a_nombre || '').trim();
      if (assignedName !== '') {
        const owner = document.createElement("div");
        owner.classList.add("task-dates");
        owner.textContent = `👤 ${assignedName}`;
        chip.appendChild(owner);
      }

      return chip;
    }

    function openDayModal(dateObj, events) {
      const titleEl = document.getElementById('dayTasksModalTitle');
      const listEl = document.getElementById('dayTasksModalList');

      titleEl.textContent = `Tareas del día: ${fmtDayTitle(dateObj)}`;

      listEl.innerHTML = "";

      if (!events.length) {
        const empty = document.createElement("div");
        empty.className = "text-muted fw-bold";
        empty.textContent = "No hay tareas para este día.";
        listEl.appendChild(empty);
      } else {
        events.sort((a, b) => {
          const aRaw = a.extendedProps?._startDate || a.startStr || '';
          const bRaw = b.extendedProps?._startDate || b.startStr || '';
          return new Date(aRaw) - new Date(bRaw);
        });

        events.forEach(ev => {
          const wrapper = document.createElement('div');
          wrapper.style.cursor = 'pointer';
          wrapper.appendChild(buildTaskChip(ev));
          wrapper.addEventListener('click', () => {
            const modalEl = document.getElementById('dayTasksModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();
            setTimeout(() => openTaskActionModal(ev), 250);
          });
          listEl.appendChild(wrapper);
        });
      }

      const modalEl = document.getElementById('dayTasksModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }

    function resetActionModalEvidence() {
      currentRequestedState = 0;
      document.getElementById('calendarHasEvidenceCheck').checked = false;
      document.getElementById('calendarEvidenceUrl').value = '';
      document.getElementById('calendarEvidenceNote').value = '';
      document.getElementById('calendarEvidenceFields').style.display = 'none';
      document.getElementById('calendarActionEvidenceWrap').style.display = 'none';
    }

    function canUserActOnEvent(event) {
      const p = event.extendedProps || {};

      const estadoId = Number(p.id_estado_tarea || 0);
      if (![1, 2].includes(estadoId)) {
        return false;
      }

      const realStartRaw = p._startDate || event.startStr || '';
      const realEndRaw = p._endDate || event.endStr || '';

      const inicio = realStartRaw ? new Date(realStartRaw) : null;
      let fin = realEndRaw ? new Date(realEndRaw) : null;

      if (fin) {
        fin.setDate(fin.getDate() - 1);
      }

      const hoy = new Date();

      const dentroSemana = inicio ?
        (inicio >= semana.inicio && inicio <= semana.fin) :
        false;

      const dentroRangoFecha = inicio ?
        (fin ? (hoy >= inicio && hoy <= fin) : (hoy >= inicio)) :
        false;

      return dentroSemana && dentroRangoFecha;
    }

    function openTaskActionModal(event) {
      currentCalendarEvent = event;
      resetActionModalEvidence();

      const p = event.extendedProps || {};

      document.getElementById('detailTitulo').textContent = event.title || '-';
      document.getElementById('detailArea').textContent = p.area || '-';
      document.getElementById('detailAsignadoA').textContent = p.asignado_a_nombre || '-';
      document.getElementById('detailAsignadoPor').textContent = p.asignado_por_nombre || '-';
      document.getElementById('detailInicio').textContent = fmtDateTimeLocal(p._startDate || event.startStr || '');
      document.getElementById('detailFin').textContent = fmtDateTimeLocal(p._endDate || event.endStr || '');
      document.getElementById('detailDescripcion').textContent = p.descripcion || '-';
      document.getElementById('detailEstadoChip').textContent = `Estado: ${p.estado || '-'}`;
      document.getElementById('detailPrioridadChip').textContent = `Prioridad: ${p.prioridad || '-'}`;
      document.getElementById('btnCalendarGoManage').href = `<?= site_url('tareas/gestionar') ?>`;

      const btnDone = document.getElementById('btnCalendarMarkDone');
      const btnNotDone = document.getElementById('btnCalendarMarkNotDone');
      const help = document.getElementById('calendarTaskActionHelp');

      if (canUserActOnEvent(event)) {
        btnDone.style.display = '';
        btnNotDone.style.display = '';
        help.innerHTML = 'Esta actividad todavía está activa y puedes gestionarla desde aquí. Al marcarla, se abrirá el bloque para registrar evidencia si corresponde.';
      } else {
        btnDone.style.display = 'none';
        btnNotDone.style.display = 'none';
        help.innerHTML = 'Esta actividad solo se puede marcar mientras esté activa, dentro de la semana y dentro de su rango de fechas.';
      }

      const modalEl = document.getElementById('calendarTaskActionModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }

    async function sendCalendarStateChange(taskId, stateId) {
      const csrfName = document.getElementById('csrfName').value;
      const csrfHash = document.getElementById('csrfHash').value;

      const hasEvidence = document.getElementById('calendarHasEvidenceCheck').checked;
      const evidenceUrl = document.getElementById('calendarEvidenceUrl').value.trim();
      const evidenceNote = document.getElementById('calendarEvidenceNote').value.trim();

      if (hasEvidence && evidenceUrl === '') {
        alert('Debes ingresar el enlace de evidencia.');
        return;
      }

      const body = new URLSearchParams();
      body.append(csrfName, csrfHash);
      body.append('id_estado_tarea', String(stateId));
      body.append('estado', String(stateId));
      body.append('has_evidence', hasEvidence ? '1' : '0');
      body.append('evidence_url', evidenceUrl);
      body.append('evidence_note', evidenceNote);

      const response = await fetch(`<?= site_url('tareas/estado') ?>/${taskId}`, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      const data = await response.json();

      if (data && data.csrfHash) {
        document.getElementById('csrfHash').value = data.csrfHash;
      }

      if (!data.success) {
        alert(data.error || 'No se pudo actualizar la actividad.');
        return;
      }

      const modalEl = document.getElementById('calendarTaskActionModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.hide();

      calendar.refetchEvents();
    }

    document.getElementById('calendarHasEvidenceCheck').addEventListener('change', function() {
      document.getElementById('calendarEvidenceFields').style.display = this.checked ? '' : 'none';

      if (!this.checked) {
        document.getElementById('calendarEvidenceUrl').value = '';
        document.getElementById('calendarEvidenceNote').value = '';
      }
    });

    document.getElementById('btnCalendarMarkDone').addEventListener('click', function() {
      if (!currentCalendarEvent) return;

      currentRequestedState = 3;
      document.getElementById('calendarActionEvidenceWrap').style.display = '';
      document.getElementById('calendarTaskActionHelp').innerHTML = 'Antes de enviar esta acción, indica si la actividad tiene evidencia. Si eres usuario normal, esto puede enviarse a revisión según tu rol.';
      this.style.display = 'none';
      document.getElementById('btnCalendarMarkNotDone').style.display = 'none';

      const footerActions = document.getElementById('calendarTaskActions');
      if (!document.getElementById('btnCalendarConfirmSend')) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btnCalendarConfirmSend';
        btn.className = 'btn btn-primary';
        btn.textContent = 'Confirmar envío';
        btn.addEventListener('click', async function() {
          if (!currentCalendarEvent || !currentRequestedState) return;
          await sendCalendarStateChange(currentCalendarEvent.id, currentRequestedState);
        });
        footerActions.appendChild(btn);
      }
    });

    document.getElementById('btnCalendarMarkNotDone').addEventListener('click', function() {
      if (!currentCalendarEvent) return;

      currentRequestedState = 4;
      document.getElementById('calendarActionEvidenceWrap').style.display = '';
      document.getElementById('calendarTaskActionHelp').innerHTML = 'Si lo necesitas, puedes adjuntar evidencia o una observación antes de enviarla como no realizada.';
      this.style.display = 'none';
      document.getElementById('btnCalendarMarkDone').style.display = 'none';

      const footerActions = document.getElementById('calendarTaskActions');
      if (!document.getElementById('btnCalendarConfirmSend')) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btnCalendarConfirmSend';
        btn.className = 'btn btn-primary';
        btn.textContent = 'Confirmar envío';
        btn.addEventListener('click', async function() {
          if (!currentCalendarEvent || !currentRequestedState) return;
          await sendCalendarStateChange(currentCalendarEvent.id, currentRequestedState);
        });
        footerActions.appendChild(btn);
      }
    });

    document.getElementById('calendarTaskActionModal').addEventListener('hidden.bs.modal', function() {
      currentCalendarEvent = null;
      currentRequestedState = 0;
      resetActionModalEvidence();

      const btnDone = document.getElementById('btnCalendarMarkDone');
      const btnNotDone = document.getElementById('btnCalendarMarkNotDone');
      const btnConfirm = document.getElementById('btnCalendarConfirmSend');

      btnDone.style.display = '';
      btnNotDone.style.display = '';

      if (btnConfirm) {
        btnConfirm.remove();
      }

      document.getElementById('calendarTaskActionHelp').innerHTML = 'Aquí puedes revisar la actividad. Si todavía está activa y te corresponde actuar, podrás marcarla desde este mismo calendario.';
    });

    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'es',
      firstDay: 1,
      initialView: 'dayGridMonth',
      height: 'auto',
      fixedWeekCount: false,

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

      moreLinkText: function(n) {
        return `+${n} más`;
      },

      views: {
        dayGridMonth: {
          eventDisplay: 'list-item',
          dayMaxEventRows: 4,

          moreLinkClick: function(arg) {
            const dateClicked = arg.date;
            const dateClickedKey = getDateKeyFromDateObj(dateClicked);

            const dayEvents = calendar.getEvents().filter(ev => {
              const p = ev.extendedProps || {};
              const originalKey = p._startDateKey || getRawDateKey(p._startDate || ev.startStr || '');
              return sameDayByKey(originalKey, dateClickedKey);
            });

            openDayModal(dateClicked, dayEvents);
            return "none";
          }
        },

        timeGridWeek: {
          eventDisplay: 'block'
        }
      },

      events: (info, success) => {
        const selectedScope = document.getElementById('calendarScope')?.value || 'mine';

        fetch(`<?= site_url('tareas/events') ?>?scope=${encodeURIComponent(selectedScope)}`)
          .then(r => r.json())
          .then(data => {

            stats = {
              total: 0,
              pendientes: 0,
              vencidas: 0,
              realizadas: 0
            };

            const viewType = calendar.view.type;

            const normalized = data.map(ev => {
              const originalStartRaw = String(ev.start || '').trim();
              const originalEndRaw = String(ev.end || '').trim();

              const inicioTarea = originalStartRaw ? new Date(originalStartRaw) : null;
              let finTarea = originalEndRaw ? new Date(originalEndRaw) : null;

              if (finTarea) {
                finTarea.setDate(finTarea.getDate() - 1);
              }

              ev.extendedProps = ev.extendedProps || {};
              ev.extendedProps._startDate = originalStartRaw;
              ev.extendedProps._endDate = originalEndRaw;
              ev.extendedProps._startDateKey = getRawDateKey(originalStartRaw);
              ev.extendedProps._endDateKey = getRawDateKey(originalEndRaw);

              const dentroSemana = inicioTarea ?
                (inicioTarea >= semana.inicio && inicioTarea <= semana.fin) :
                false;

              if (dentroSemana) {
                stats.total++;

                if (Number(ev.extendedProps.id_estado_tarea) === 3) {
                  stats.realizadas++;
                } else {
                  stats.pendientes++;
                }

                const hoy = new Date();
                if (finTarea && hoy > finTarea && Number(ev.extendedProps.id_estado_tarea) !== 3) {
                  stats.vencidas++;
                  ev.extendedProps.overdue = true;
                }
              }

              if (viewType === 'dayGridMonth') {
                ev.allDay = true;
                ev.display = 'list-item';

                if (originalStartRaw) {
                  ev.end = addDays(originalStartRaw, 1).toISOString();
                }
              }

              return ev;
            });

            updateKPI();
            success(normalized);
          })
          .catch(() => {
            stats = {
              total: 0,
              pendientes: 0,
              vencidas: 0,
              realizadas: 0
            };
            updateKPI();
            success([]);
          });
      },

      eventContent(info) {
        const chip = buildTaskChip(info.event);
        return {
          domNodes: [chip]
        };
      },

      eventClick(info) {
        openTaskActionModal(info.event);
      }
    });

    calendar.render();

    const calendarScopeEl = document.getElementById('calendarScope');
    if (calendarScopeEl) {
      calendarScopeEl.addEventListener('change', function() {
        calendar.refetchEvents();
      });
    }

    function updateKPI() {
      document.getElementById('kpiTotal').textContent = stats.total;
      document.getElementById('kpiPendientes').textContent = stats.pendientes;
      document.getElementById('kpiVencidas').textContent = stats.vencidas;
      document.getElementById('kpiRealizadas').textContent = stats.realizadas;
    }

  });
</script>
<?= $this->endSection() ?>