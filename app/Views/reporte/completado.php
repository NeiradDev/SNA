<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: reporte/usuarios_pendientes.php
 * =========================================================
 * ✅ IMPLEMENTADO:
 * - DataTables con búsqueda, paginado y ordenamiento
 * - Exportación Excel / PDF / CSV / impresión
 * - WhatsApp individual por teléfono
 * - Mensaje predeterminado EDITABLE
 * - Si cambias el mensaje, se envía ese mensaje actualizado
 * - Control por número: si ya se abrió WhatsApp, se bloquea 30 min
 * - Rehabilitación automática luego de 30 min
 * =========================================================
 */
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
  :root{
    --ws-red-1:#F20505;
    --ws-red-2:#F22E2E;
    --ws-red-3:#F27272;
    --ws-light:#F2F2F2;
    --ws-dark:#0D0D0D;
  }

  .pending-page{
    animation: fadeInPending .35s ease;
  }

  @keyframes fadeInPending{
    from{opacity:0; transform:translateY(8px);}
    to{opacity:1; transform:translateY(0);}
  }

  .pending-title{
    font-weight:900;
    letter-spacing:.3px;
    color:var(--ws-dark);
    margin-bottom:.2rem;
  }

  .pending-subtitle{
    color:rgba(13,13,13,.72);
    font-size:.95rem;
  }

  .pending-card{
    border:1px solid rgba(13,13,13,.10);
    border-radius:18px;
    background:linear-gradient(180deg, #ffffff 0%, var(--ws-light) 100%);
    box-shadow:0 14px 30px -22px rgba(13,13,13,.18);
  }

  .pending-hero{
    position:relative;
    overflow:hidden;
  }

  .pending-hero::before{
    content:"";
    position:absolute;
    inset:0;
    background:
      radial-gradient(circle at top right, rgba(242,46,46,.10), transparent 34%),
      radial-gradient(circle at bottom left, rgba(242,114,114,.12), transparent 38%);
    pointer-events:none;
  }

  .pending-hero-content{
    position:relative;
    z-index:1;
  }

  .pending-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(242,46,46,.10);
    border:1px solid rgba(242,46,46,.20);
    color:var(--ws-dark);
    font-weight:800;
    font-size:.86rem;
  }

  .pending-kpis{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }

  .pending-kpi{
    min-width:145px;
    padding:12px 14px;
    border-radius:14px;
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%);
    color:#fff;
    box-shadow:0 14px 28px -20px rgba(13,13,13,.34);
  }

  .pending-kpi strong{
    display:block;
    font-size:1.08rem;
    line-height:1.1;
  }

  .pending-kpi small{
    display:block;
    margin-top:4px;
    opacity:.88;
  }

  .pending-table-wrap{
    border:1px solid rgba(13,13,13,.10);
    border-radius:18px;
    overflow:hidden;
    background:#fff;
  }

  .pending-message-box{
    border:1px solid rgba(13,13,13,.10);
    border-radius:16px;
    background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.95) 100%);
    padding:16px;
    margin-bottom:16px;
  }

  .pending-message-title{
    font-weight:900;
    color:var(--ws-dark);
    margin-bottom:8px;
  }

  .pending-message-textarea{
    width:100%;
    min-height:140px;
    border:1px solid rgba(13,13,13,.12);
    border-radius:14px;
    padding:12px 14px;
    resize:vertical;
    outline:none;
    transition:border-color .18s ease, box-shadow .18s ease;
    background:#fff;
    color:var(--ws-dark);
    line-height:1.5;
  }

  .pending-message-textarea:focus{
    border-color:rgba(242,46,46,.40);
    box-shadow:0 0 0 .2rem rgba(242,46,46,.12);
  }

  table.dataTable thead th{
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%) !important;
    color:#fff !important;
    border-bottom:none !important;
    vertical-align:middle;
    font-weight:900;
  }

  table.dataTable tbody tr{
    transition:background .18s ease, transform .18s ease;
  }

  table.dataTable tbody tr:hover{
    background:rgba(242,114,114,.08) !important;
  }

  .pending-phone-link{
    color:var(--ws-red-1);
    text-decoration:none;
    font-weight:800;
    transition:all .18s ease;
  }

  .pending-phone-link:hover{
    color:var(--ws-dark);
    text-decoration:underline;
  }

  .pending-phone-link.is-disabled{
    pointer-events:none;
    color:rgba(13,13,13,.45);
    text-decoration:none;
    cursor:not-allowed;
  }

  .pending-status-badge{
    display:inline-flex;
    align-items:center;
    padding:7px 11px;
    border-radius:999px;
    font-size:.78rem;
    font-weight:900;
    border:1px solid rgba(13,13,13,.10);
    background:linear-gradient(180deg, rgba(242,114,114,.15) 0%, rgba(242,46,46,.08) 100%);
    color:var(--ws-dark);
  }

  .pending-status-badge.sent{
    background:linear-gradient(135deg, var(--ws-dark) 0%, var(--ws-red-2) 100%);
    color:#fff;
  }

  .pending-status-badge.waiting{
    background:linear-gradient(180deg, rgba(242,242,242,.95) 0%, #ffffff 100%);
    color:var(--ws-dark);
  }

  .pending-status-badge.ready{
    background:linear-gradient(180deg, rgba(242,114,114,.15) 0%, rgba(242,46,46,.08) 100%);
    color:var(--ws-dark);
  }

  .pending-status-text{
    display:block;
    font-size:.78rem;
    color:rgba(13,13,13,.72);
    margin-top:4px;
    font-weight:700;
  }

  .dataTables_wrapper .dataTables_filter input{
    border-radius:10px !important;
    border:1px solid rgba(13,13,13,.12) !important;
    margin-left:.5rem !important;
  }

  .dataTables_wrapper .dataTables_length select{
    border-radius:10px !important;
    border:1px solid rgba(13,13,13,.12) !important;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button{
    border-radius:10px !important;
    margin:0 2px !important;
  }

  .dt-buttons .btn{
    border-radius:10px !important;
    font-weight:800 !important;
    margin-right:6px !important;
  }

  .pending-alert{
    border-radius:14px;
  }

  @media (max-width: 768px){
    .pending-kpis{
      width:100%;
    }

    .pending-kpi{
      flex:1 1 calc(50% - 10px);
    }
  }
</style>

<div class="container py-3 pending-page">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h2 class="pending-title">Usuarios pendientes (semana actual)</h2>
      <div class="pending-subtitle">
        Consulta rápida de usuarios que aún no completan el Plan de Batalla.
      </div>
    </div>

    <div class="pending-kpis">
      <div class="pending-kpi">
        <strong><?= !empty($usuarios) ? count($usuarios) : 0 ?></strong>
        <small>Total pendientes</small>
      </div>
    </div>
  </div>

  <div class="card pending-card pending-hero mb-3">
    <div class="card-body pending-hero-content d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <span class="pending-chip">Seguimiento semanal</span>
        <div class="mt-2 text-muted">
          Haz clic en el teléfono para abrir WhatsApp con el recordatorio preparado. Si ya se abrió, ese número quedará en espera por 30 minutos antes de poder reenviar.
        </div>
      </div>
    </div>
  </div>

  <div class="pending-message-box">
    <div class="pending-message-title">Mensaje predeterminado editable</div>
    <textarea id="messageEditor" class="pending-message-textarea">⚠️ *Recordatorio importante*

Estimado/a,

Se registra que aún no ha completado el *SNA Plan de Batalla* correspondiente a la semana actual.

Por favor, le solicitamos realizar su llenado a la brevedad posible, ya que este proceso es de carácter importante y requiere atención inmediata.

Muchas gracias por su atención y pronta gestión.</textarea>
  </div>

  <?php if (!empty($usuarios)) : ?>
    <div class="pending-table-wrap">
      <div class="table-responsive p-3">
        <table id="tablaPendientes" class="table table-sm table-striped table-bordered align-middle mb-0 w-100">
          <thead>
            <tr>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Teléfono</th>
              <th style="width:170px;">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u) : ?>
              <?php
                $nombres        = trim((string)($u->nombres ?? ''));
                $apellidos      = trim((string)($u->apellidos ?? ''));
                $telefonoRaw    = trim((string)($u->telefono ?? ''));
                $telefonoDigits = preg_replace('/\D+/', '', $telefonoRaw);
              ?>
              <tr data-phone="<?= esc($telefonoDigits) ?>">
                <td><?= esc($nombres) ?></td>
                <td><?= esc($apellidos) ?></td>

                <td>
                  <?php if ($telefonoDigits !== ''): ?>
                    <a
                      href="#"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="pending-phone-link js-wa-link"
                      data-phone="<?= esc($telefonoDigits) ?>"
                      data-phone-raw="<?= esc($telefonoRaw) ?>"
                    >
                      <?= esc($telefonoRaw) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">Sin teléfono</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if ($telefonoDigits !== ''): ?>
                    <span class="pending-status-badge ready js-status-badge" data-phone="<?= esc($telefonoDigits) ?>">
                      Listo para enviar
                    </span>
                    <span class="pending-status-text js-status-text" data-phone="<?= esc($telefonoDigits) ?>">
                      No se ha enviado recordatorio recientemente.
                    </span>
                  <?php else: ?>
                    <span class="pending-status-badge waiting">
                      Sin teléfono
                    </span>
                    <span class="pending-status-text">
                      No disponible para WhatsApp.
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4">
                Total pendientes: <?= count($usuarios) ?>
              </th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  <?php else : ?>
    <div class="alert alert-success pending-alert" role="alert">
      No hay usuarios pendientes esta semana.
    </div>
  <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const messageEditor = document.getElementById('messageEditor');

    const LOCK_MINUTES = 30;
    const LOCK_MS = LOCK_MINUTES * 60 * 1000;
    const STORAGE_PREFIX = 'plan_batalla_last_sent_';

    function normalizePhone(phone) {
      return String(phone || '').replace(/\D+/g, '');
    }

    function getStorageKey(phone) {
      return STORAGE_PREFIX + normalizePhone(phone);
    }

    function getLastSent(phone) {
      const raw = localStorage.getItem(getStorageKey(phone));
      const timestamp = raw ? parseInt(raw, 10) : 0;
      return Number.isFinite(timestamp) ? timestamp : 0;
    }

    function setLastSent(phone) {
      localStorage.setItem(getStorageKey(phone), String(Date.now()));
    }

    function getRemainingMs(phone) {
      const lastSent = getLastSent(phone);
      if (!lastSent) return 0;

      const diff = Date.now() - lastSent;
      const remaining = LOCK_MS - diff;

      return remaining > 0 ? remaining : 0;
    }

    function formatRemaining(ms) {
      const totalSeconds = Math.ceil(ms / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;

      const mm = String(minutes).padStart(2, '0');
      const ss = String(seconds).padStart(2, '0');

      return `${mm}:${ss}`;
    }

    function getCurrentMessage() {
      const value = messageEditor ? messageEditor.value.trim() : '';
      return value !== '' ? value : '⚠️ Recordatorio importante';
    }

    function buildWhatsAppLink(phone) {
      return 'https://wa.me/' + normalizePhone(phone) + '?text=' + encodeURIComponent(getCurrentMessage());
    }

    function updatePhoneState(phone) {
      const normalizedPhone = normalizePhone(phone);
      if (!normalizedPhone) return;

      const linkEl = document.querySelector('.js-wa-link[data-phone="' + normalizedPhone + '"]');
      const badgeEl = document.querySelector('.js-status-badge[data-phone="' + normalizedPhone + '"]');
      const textEl = document.querySelector('.js-status-text[data-phone="' + normalizedPhone + '"]');

      if (!linkEl || !badgeEl || !textEl) return;

      const remaining = getRemainingMs(normalizedPhone);

      if (remaining > 0) {
        linkEl.classList.add('is-disabled');
        linkEl.removeAttribute('href');

        badgeEl.classList.remove('ready', 'sent');
        badgeEl.classList.add('waiting');
        badgeEl.textContent = 'Reenvío en espera';

        textEl.textContent = 'Podrás reenviar el recordatorio en ' + formatRemaining(remaining) + '.';
      } else {
        linkEl.classList.remove('is-disabled');
        linkEl.setAttribute('href', buildWhatsAppLink(normalizedPhone));

        const lastSent = getLastSent(normalizedPhone);

        badgeEl.classList.remove('waiting');
        badgeEl.classList.add(lastSent ? 'sent' : 'ready');

        badgeEl.textContent = lastSent ? 'Disponible nuevamente' : 'Listo para enviar';
        textEl.textContent = lastSent
          ? 'Ya puedes reenviar el recordatorio a este número.'
          : 'No se ha enviado recordatorio recientemente.';
      }
    }

    function updateAllPhoneStates() {
      document.querySelectorAll('.js-wa-link').forEach(link => {
        updatePhoneState(link.dataset.phone);
      });
    }

    document.addEventListener('click', function (e) {
      const link = e.target.closest('.js-wa-link');
      if (!link) return;

      const phone = normalizePhone(link.dataset.phone);
      if (!phone) return;

      const remaining = getRemainingMs(phone);

      if (remaining > 0) {
        e.preventDefault();
        updatePhoneState(phone);
        return;
      }

      setLastSent(phone);
      updatePhoneState(phone);
      link.setAttribute('href', buildWhatsAppLink(phone));
    });

    if (messageEditor) {
      messageEditor.addEventListener('input', function () {
        document.querySelectorAll('.js-wa-link').forEach(link => {
          const phone = normalizePhone(link.dataset.phone);
          if (!phone) return;

          if (getRemainingMs(phone) <= 0) {
            link.setAttribute('href', buildWhatsAppLink(phone));
          }
        });
      });
    }

    $('#tablaPendientes').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
      order: [[0, 'asc'], [1, 'asc']],
      dom:
        "<'row align-items-center mb-3'<'col-md-6'B><'col-md-6'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
      language: {
        decimal: "",
        emptyTable: "No hay datos disponibles en la tabla",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(filtrado de _MAX_ registros totales)",
        infoPostFix: "",
        thousands: ",",
        lengthMenu: "Mostrar _MENU_ registros",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        search: "Buscar:",
        zeroRecords: "No se encontraron coincidencias",
        paginate: {
          first: "Primero",
          last: "Último",
          next: "Siguiente",
          previous: "Anterior"
        },
        aria: {
          sortAscending: ": activar para ordenar la columna ascendente",
          sortDescending: ": activar para ordenar la columna descendente"
        }
      },
      buttons: [
        {
          extend: 'excelHtml5',
          text: 'Excel',
          className: 'btn btn-light',
          exportOptions: {
            columns: [0, 1, 2, 3]
          },
          title: 'Usuarios_pendientes_plan_batalla'
        },
        {
          extend: 'pdfHtml5',
          text: 'PDF',
          className: 'btn btn-light',
          exportOptions: {
            columns: [0, 1, 2, 3]
          },
          title: 'Usuarios pendientes plan de batalla',
          orientation: 'landscape',
          pageSize: 'A4'
        },
        {
          extend: 'csvHtml5',
          text: 'CSV',
          className: 'btn btn-light',
          exportOptions: {
            columns: [0, 1, 2, 3]
          },
          title: 'Usuarios_pendientes_plan_batalla'
        },
        {
          extend: 'print',
          text: 'Imprimir',
          className: 'btn btn-light',
          exportOptions: {
            columns: [0, 1, 2, 3]
          },
          title: 'Usuarios pendientes plan de batalla'
        }
      ],
      drawCallback: function () {
        updateAllPhoneStates();
      }
    });

    updateAllPhoneStates();
    setInterval(updateAllPhoneStates, 1000);
  });
</script>

<?= $this->endSection() ?>