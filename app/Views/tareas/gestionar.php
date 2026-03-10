<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: tareas/gestionar.php
 * =========================================================
 * ✅ Implementado:
 * 1) FIX CRÍTICO TZ: mostrar timestamptz (-05) sin descuadrar hora.
 * 2) Cancelación con motivo -> modal -> POST /tareas/cancelar/{id}.
 * 3) Pendientes de revisión: Acción, Motivo, Fin solicitado + detalle modal.
 * 4) ✅ NUEVO: Pestaña "Mis actividades diarias" SOLO tareas Diarias (recurrence_uid),
 *    y SOLO las del DÍA correspondiente (hoy en America/Guayaquil).
 *    - Las Diarias NO se muestran en "Activas" (solo ahí van las NO Diarias).
 * 5) ✅ NUEVO: En "Activas" ordenar desde HOY hacia futuro (asc).
 * 6) ✅ NUEVO: Colores por caducidad (según fecha_fin):
 *    - vence HOY o ya vencida => rojo
 *    - vence MAÑANA => amarillo
 *    - vence después => verde
 * 7) ✅ DataTables: selector filas 5/10/25/50/100 + "Todos".
 *
 * ⚠️ Nota importante:
 * Para que "tareas diarias" detecte Diarias, tu SELECT debe traer `t.recurrence_uid`.
 * Si aún no lo estás devolviendo en getTasksForManagement(), agrégalo al SELECT.
 */

/* =========================================================
   ✅ TIMEZONE ÚNICO PARA MOSTRAR EN LA UI
========================================================= */
$uiTz = new DateTimeZone('America/Guayaquil');

/**
 * ✅ Formatea timestamps SIN descuadrar hora (timestamptz).
 */
$fmtUiDateTime = function (?string $raw) use ($uiTz): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '-';

  try {
    $dt = new DateTime($raw);
    $dt->setTimezone($uiTz);
    return $dt->format('d/m/Y H:i');
  } catch (Throwable $e) {
    $ts = strtotime($raw);
    if ($ts === false) return $raw;
    $dt = (new DateTime('@' . $ts))->setTimezone($uiTz);
    return $dt->format('d/m/Y H:i');
  }
};

/**
 * ✅ Data-order estable (ISO) para DataTables + filtro.
 */
$fmtOrder = function (?string $raw) use ($uiTz): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '';
  try {
    $dt = new DateTime($raw);
    $dt->setTimezone($uiTz);
    return $dt->format('Y-m-d H:i:sP');
  } catch (Throwable $e) {
    return $raw;
  }
};

/**
 * ✅ Fecha "hoy" en TZ Ecuador.
 */
$todayKey = (new DateTimeImmutable('now', $uiTz))->format('Y-m-d');

/**
 * ✅ Extrae YYYY-mm-dd desde una fecha con o sin offset.
 */
$getDayKeyFromRaw = function(?string $raw) use ($uiTz): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '';
  try {
    $dt = new DateTime($raw);
    $dt->setTimezone($uiTz);
    return $dt->format('Y-m-d');
  } catch (Throwable $e) {
    return substr($raw, 0, 10);
  }
};

/**
 * ✅ Clase visual por "caducidad" (fecha_fin vs hoy).
 * - rojo: hoy o vencida
 * - amarillo: mañana
 * - verde: después
 */
$getDueClass = function(?string $finRaw) use ($uiTz): string {
  $finRaw = trim((string)$finRaw);
  if ($finRaw === '') return '';

  try {
    $end = new DateTime($finRaw);
    $end->setTimezone($uiTz);

    $today0 = new DateTime('now', $uiTz);
    $today0->setTime(0,0,0);

    $end0 = clone $end;
    $end0->setTime(0,0,0);

    $diffDays = (int) floor(($end0->getTimestamp() - $today0->getTimestamp()) / 86400);

    if ($diffDays <= 0) return 'due-red';
    if ($diffDays === 1) return 'due-yellow';
    return 'due-green';
  } catch (Throwable $e) {
    return '';
  }
};

/**
 * ✅ Detecta si una tarea es repetida:
 * - por columna recurrence_uid (uuid) si viene en el array
 */
$isRecurring = function(array $t): bool {
  $uid = trim((string)($t['recurrence_uid'] ?? ''));
  return ($uid !== '');
};

$assignScope = $assignScope ?? ['mode' => 'self'];
$assignMode  = (string)($assignScope['mode'] ?? 'self');
$canSeeTeam  = in_array($assignMode, ['super', 'division', 'area'], true);

$currentUserId = (int) session()->get('id_user');

/**
 * Estados cerrados (no acciones)
 */
$isClosedState = function(int $estadoId): bool {
  return in_array($estadoId, [3,4,5], true);
};

/**
 * Bloquea botones si está cerrada o en revisión
 */
$isLockedForActions = function(int $estadoId): bool {
  return in_array($estadoId, [3,4,5,6], true);
};

/**
 * Clase visual por estado
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

/**
 * Activas = no cerradas y no en revisión
 */
$isActiveState = function(int $estadoId): bool {
  return !in_array($estadoId, [3,4,5,6], true);
};

/**
 * En revisión
 */
$isReviewState = function(int $estadoId): bool {
  return $estadoId === 6;
};

/**
 * Cerradas = realizada / no realizada / cancelada
 */
$isClosedTabState = function(int $estadoId): bool {
  return in_array($estadoId, [3,4,5], true);
};

/**
 * Filtra tareas por tipo de pestaña (active/review/closed)
 */
$filterTasksByTab = function(array $tasks, string $tab) use ($isActiveState, $isReviewState, $isClosedTabState): array {
  return array_values(array_filter($tasks, function($t) use ($tab, $isActiveState, $isReviewState, $isClosedTabState) {
    $estadoId = (int)($t['id_estado_tarea'] ?? 0);

    return match ($tab) {
      'active' => $isActiveState($estadoId),
      'review' => $isReviewState($estadoId),
      'closed' => $isClosedTabState($estadoId),
      default  => true,
    };
  }));
};

/**
 * ✅ NUEVO: separar "Mis tareas" en:
 * - NO Diarias (para "Activas" normales)
 * - Diarias SOLO de HOY (para "Mis actividades diarias")
 *
 * Además:
 * - cerradas: pueden incluir Diarias y no Diarias (historial completo)
 */
$misTareasAll       = $misTareas ?? [];
$tareasAsignadasAll = $tareasAsignadas ?? [];
$tareasEquipoAll    = $tareasEquipo ?? [];

/**
 * Helper: filtrar mis Diarias de HOY (por fecha_inicio)
 * - SOLO muestra las del día correspondiente (hoy)
 * - NO muestra futuras (aunque sean Diarias)
 */
$misRecurringToday = array_values(array_filter($misTareasAll, function($t) use ($isRecurring, $getDayKeyFromRaw, $todayKey, $isClosedTabState) {
  if (!$isRecurring($t)) return false;

  // Solo del día (por fecha_inicio)
  $day = $getDayKeyFromRaw((string)($t['fecha_inicio'] ?? ''));
  if ($day !== $todayKey) return false;

  // Si está cerrada, NO va aquí (se ve en "Cerradas" como historial)
  $estadoId = (int)($t['id_estado_tarea'] ?? 0);
  if ($isClosedTabState($estadoId)) return false;

  return true;
}));

/**
 * ✅ Mis NO Diarias (todas) para tabs normales (active/review/closed),
 * PERO: en "Activas" y "En revisión" NO queremos Diarias.
 */
$misNonRecurring = array_values(array_filter($misTareasAll, function($t) use ($isRecurring) {
  return !$isRecurring($t);
}));

// Tabs para "Mis Actividades" (solo NO Diarias)
$misActive   = $filterTasksByTab($misNonRecurring, 'active');
$misReview   = $filterTasksByTab($misNonRecurring, 'review');
$misClosed   = $filterTasksByTab($misTareasAll, 'closed'); // historial completo (incluye Diarias)

// Tabs para "Asignadas por mí" (aquí NO movemos nada a diarias, es solo “mis diarias”)
$asigActive  = $filterTasksByTab($tareasAsignadasAll, 'active');
$asigReview  = $filterTasksByTab($tareasAsignadasAll, 'review');
$asigClosed  = $filterTasksByTab($tareasAsignadasAll, 'closed');

// Tabs para "Equipo"
$teamActive  = $filterTasksByTab($tareasEquipoAll, 'active');
$teamReview  = $filterTasksByTab($tareasEquipoAll, 'review');
$teamClosed  = $filterTasksByTab($tareasEquipoAll, 'closed');

// Contadores principales
$misMainCount   = count($misActive)  + count($misReview);
$dailyMainCount = count($misRecurringToday);
$asigMainCount  = count($asigActive) + count($asigReview);
$teamMainCount  = count($teamActive) + count($teamReview);

$reviewActionLabel = function(array $t): string {
  $reviewAction = (string)($t['review_action'] ?? '');
  $requestedState = (int)($t['review_requested_state'] ?? 0);

  if ($reviewAction === 'cancel') return 'Cancelación';
  if ($reviewAction === 'date_change') return 'Cambio de fecha';

  if ($reviewAction === 'state') {
    return match ($requestedState) {
      3 => 'Marcar como realizada',
      4 => 'Marcar como no realizada',
      5 => 'Cancelación',
      default => 'Cambio de estado',
    };
  }

  return match ($requestedState) {
    3 => 'Marcar como realizada',
    4 => 'Marcar como no realizada',
    5 => 'Cancelación',
    default => 'Solicitud',
  };
};

$reviewRequestedStateLabel = function(array $t): string {
  $requestedStateName = trim((string)($t['nombre_estado_solicitado'] ?? ''));
  if ($requestedStateName !== '') return $requestedStateName;

  $requestedState = (int)($t['review_requested_state'] ?? 0);

  return match ($requestedState) {
    3 => 'Realizada',
    4 => 'No realizada',
    5 => 'Cancelada',
    default => '-',
  };
};

/**
 * Permiso UI para mostrar botón Cancelar
 */
$canCancelRow = function(array $t) use ($assignMode, $currentUserId): bool {
  $estadoId = (int)($t['id_estado_tarea'] ?? 0);

  if (in_array($estadoId, [3,4,5,6], true)) return false;

  $isAssignee         = ((int)($t['asignado_a'] ?? 0) === $currentUserId);
  $assignedSupervisor = (int)($t['asignado_a_supervisor'] ?? 0);
  $isDirectSupervisor = ($assignedSupervisor > 0 && $assignedSupervisor === $currentUserId);
  $isSuperUser        = ($assignMode === 'super');

  return ($isAssignee || $isDirectSupervisor || $isSuperUser);
};

// Flash messages
$flashError     = $error   ?? session()->getFlashdata('error');
$flashSuccess   = $success ?? session()->getFlashdata('success');
$dueAlerts      = $dueAlerts ?? [];
$expiredUpdated = (int)($expiredUpdated ?? 0);

/**
 * ✅ NUEVO: "notificaciones" de decisión (aprobado/rechazado)
 * - Esta vista soporta que el controller le pase:
 *   $decisionNotifications = [
 *      ['id_tarea'=>..., 'titulo'=>..., 'decision'=>'approved|rejected', 'action'=>'cancel|date_change|state', 'approved_at'=>..., 'approved_by_nombre'=>..., 'detalle'=>...],
 *      ...
 *   ];
 *
 * Si no lo pasas, no rompe: solo no muestra modal.
 */
$decisionNotifications = $decisionNotifications ?? [];
?>

<style>
  /* =========================================================
     ICONOS
  ========================================================== */
  .icon-btn{ width:18px; height:18px; display:inline-block; vertical-align:middle; }
  .icon-btn svg{ width:18px; height:18px; display:block; }
  .icon-ok svg { fill:#198754; }
  .icon-no svg { fill:#dc3545; }
  .icon-ed svg { fill:#0B0B0B; }
  .icon-lk svg { fill:#6c757d; }
  .icon-cancel svg { fill:#dc3545; }

  .icon-svg{ width:18px; height:18px; display:block; filter: contrast(1.2) brightness(.9); }

  /* =========================================================
     FILAS POR ESTADO
  ========================================================== */
  .row-finalizada { background-color:#e6f4ea !important; }
  .row-norealizada{ background-color:#fdecea !important; }
  .row-cancelada  { background-color:#f1f1f1 !important; color:#6c757d; }
  .row-revision   { background-color:#fff3cd !important; }

  /* =========================================================
     ✅ NUEVO: CADUCIDAD (por fecha_fin)
  ========================================================== */
  .due-red    { background: rgba(220,53,69,.12) !important; }
  .due-yellow { background: rgba(255,193,7,.14) !important; }
  .due-green  { background: rgba(25,135,84,.10) !important; }

  /* =========================================================
     BOTONES DATATABLES
  ========================================================== */
  .dt-buttons .btn{ background-color:#000 !important; color:#fff !important; border:none !important; }
  .dt-buttons .btn:hover{ background-color:#222 !important; }

  /* =========================================================
     BOTÓN CANCELAR
  ========================================================== */
  .btn-cancel{ border-color:#dc3545 !important; color:#dc3545 !important; }
  .btn-cancel:hover{ background:#dc3545 !important; color:#fff !important; }

  /* =========================================================
     MODAL
  ========================================================== */
  .modal-header.bg-dark{ border-bottom:3px solid #0B0B0B; }
  .td-motivo{ max-width:360px; white-space:normal; word-break:break-word; }

  /* =========================================================
     PESTAÑAS PRINCIPALES
  ========================================================== */
  .main-task-tabs{ gap:10px; }
  .main-task-tabs .nav-link{
    border-radius:12px; font-weight:700; color:#111;
    background:#f1f3f5; border:1px solid rgba(0,0,0,.08); padding:10px 16px;
  }
  .main-task-tabs .nav-link.active{
    background:#111; color:#fff; box-shadow:0 8px 18px rgba(0,0,0,.15);
  }

  /* =========================================================
     PESTAÑAS ANIDADAS
  ========================================================== */
  .nested-task-tabs{ border-bottom:1px solid rgba(0,0,0,.08); margin-bottom:16px; }
  .nested-task-tabs .nav-link{ color:#111; font-weight:600; border:none; border-bottom:3px solid transparent; }
  .nested-task-tabs .nav-link.active{ color:#111; background:transparent; border-bottom:3px solid #111; }

  .tab-pane .table-responsive{ border-radius:14px; overflow:hidden; }

  .badge-due-today{ background:#dc3545 !important; color:#fff !important; }
  .badge-due-tomorrow{ background:#fd7e14 !important; color:#111 !important; }
</style>

<div class="container py-3">

  <!-- ================= CABECERA ================= -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h3 class="mb-0 fw-bold">Gestión de Actividades</h3>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= site_url('tareas/asignar') ?>" class="btn btn-dark">
        <span class="icon-btn me-1">
          <svg viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 1.5a.75.75 0 0 1 .75.75v5h5a.75.75 0 0 1 0 1.5h-5v5a.75.75 0 0 1-1.5 0v-5h-5a.75.75 0 0 1 0-1.5h5v-5A.75.75 0 0 1 8 1.5z"/>
          </svg>
        </span>
        Nueva Actividad
      </a>

      <a href="<?= site_url('tareas/calendario') ?>" class="btn btn-outline-dark">
        <span class="icon-btn me-1">
          <svg viewBox="0 0 16 16" aria-hidden="true">
            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h.5A1.5 1.5 0 0 1 15 2.5v11A1.5 1.5 0 0 1 13.5 15h-11A1.5 1.5 0 0 1 1 13.5v-11A1.5 1.5 0 0 1 2.5 1H3V.5a.5.5 0 0 1 .5-.5zM2.5 2A.5.5 0 0 0 2 2.5V4h12V2.5a.5.5 0 0 0-.5-.5h-11zM14 5H2v8.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V5z"/>
          </svg>
        </span>
        Calendario de Actividades
      </a>
    </div>
  </div>

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
            <span class="icon-btn me-1">
              <svg viewBox="0 0 16 16" aria-hidden="true">
                <path d="M1.5 2a.5.5 0 0 1 .4-.49h12.2a.5.5 0 0 1 .39.81L10 8.5V13a.5.5 0 0 1-.79.41l-2-1.333A.5.5 0 0 1 7 11.667V8.5L1.51 2.32A.5.5 0 0 1 1.5 2z"/>
              </svg>
            </span>
            Aplicar
          </button>

          <button type="button" id="btnLimpiarFiltro" class="btn btn-outline-dark">
            <span class="icon-btn me-1 icon-no">
              <svg viewBox="0 0 16 16" aria-hidden="true">
                <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
              </svg>
            </span>
            Limpiar
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
            Actividades enviadas por usuarios (estado <b>En revisión</b>).
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="button" id="btnApproveBatch" class="btn btn-sm btn-outline-light">
            <span class="icon-btn me-1 icon-ok">
              <svg viewBox="0 0 16 16" aria-hidden="true">
                <path d="M13.485 1.929a.75.75 0 0 1 .086 1.056l-7.25 9a.75.75 0 0 1-1.097.073l-3.75-3.5a.75.75 0 1 1 1.023-1.096l3.16 2.95 6.74-8.36a.75.75 0 0 1 1.088-.123z"/>
              </svg>
            </span>
            Aprobar
          </button>

          <button type="button" id="btnRejectBatch" class="btn btn-sm btn-outline-light">
            <span class="icon-btn me-1 icon-no">
              <svg viewBox="0 0 16 16" aria-hidden="true">
                <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
              </svg>
            </span>
            Rechazar
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
                <th>Nombre de la actividad</th>
                <th>Área</th>
                <th>Asignado a</th>
                <th>Solicitado por</th>
                <th>Acción</th>
                <th>Estado solicitado</th>
                <th>Motivo</th>
                <th>Fecha solicitud</th>
                <th>Inicio</th>
                <th>Fin actual</th>
                <th>Fin solicitado</th>
                <th class="text-end" style="width:140px;">Detalle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendientesRevision as $t):
                $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                $rowClass = $rowClassByState($estadoId);

                $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                $finRaw    = (string)($t['fecha_fin'] ?? '');
                $revAtRaw  = (string)($t['review_requested_at'] ?? '');

                $revReason = (string)($t['review_reason'] ?? '');
                $reqFin    = (string)($t['review_requested_fecha_fin'] ?? '');

                $accionLabel          = $reviewActionLabel($t);
                $estadoSolicitadoText = $reviewRequestedStateLabel($t);
              ?>
                <tr class="<?= esc($rowClass) ?>">
                  <td>
                    <input type="checkbox" class="chkRevision" value="<?= (int)$t['id_tarea'] ?>">
                  </td>

                  <td><?= esc($t['titulo'] ?? '-') ?></td>
                  <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                  <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                  <td><?= esc($t['review_requested_by_nombre'] ?? '-') ?></td>

                  <td><span class="badge bg-warning text-dark"><?= esc($accionLabel) ?></span></td>
                  <td><span class="badge bg-secondary"><?= esc($estadoSolicitadoText) ?></span></td>

                  <td class="td-motivo">
                    <?= $revReason ? esc($revReason) : '<span class="text-muted">-</span>' ?>
                  </td>

                  <td><?= $revAtRaw ? esc($fmtUiDateTime($revAtRaw)) : '-' ?></td>

                  <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                    <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                  </td>

                  <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                  <td><?= $reqFin ? esc($fmtUiDateTime($reqFin)) : '-' ?></td>

                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-outline-dark btn-view-review"
                            data-title="<?= esc((string)($t['titulo'] ?? '-'), 'attr') ?>"
                            data-area="<?= esc((string)($t['nombre_area'] ?? '-'), 'attr') ?>"
                            data-asignado="<?= esc((string)($t['asignado_a_nombre'] ?? '-'), 'attr') ?>"
                            data-solicitado-por="<?= esc((string)($t['review_requested_by_nombre'] ?? '-'), 'attr') ?>"
                            data-accion="<?= esc($accionLabel, 'attr') ?>"
                            data-estado-solicitado="<?= esc($estadoSolicitadoText, 'attr') ?>"
                            data-motivo="<?= esc((string)($revReason ?: '-'), 'attr') ?>"
                            data-fecha-solicitud="<?= esc((string)($revAtRaw ? $fmtUiDateTime($revAtRaw) : '-'), 'attr') ?>"
                            data-inicio="<?= esc((string)($inicioRaw ? $fmtUiDateTime($inicioRaw) : '-'), 'attr') ?>"
                            data-fin-actual="<?= esc((string)($finRaw ? $fmtUiDateTime($finRaw) : '-'), 'attr') ?>"
                            data-fin-solicitado="<?= esc((string)($reqFin ? $fmtUiDateTime($reqFin) : '-'), 'attr') ?>">
                      Ver solicitud
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="text-muted small mt-2">
          Selecciona actividades y usa <b>Aprobar</b> o <b>Rechazar</b>.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ================= PESTAÑAS PRINCIPALES + ANIDADAS ================= -->
  <div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-dark text-white fw-bold">
      Gestión agrupada de actividades
      <div class="small fw-normal opacity-75">
        Organizado por pestañas principales y subpestañas por estado.
      </div>
    </div>

    <div class="card-body">

      <!-- ================= TABS PRINCIPALES ================= -->
      <ul class="nav nav-pills main-task-tabs mb-4" id="mainTaskTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active"
                  id="tab-mis-main"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-mis-main"
                  type="button"
                  role="tab">
            Mis Actividades
            <span class="badge rounded-pill bg-light text-dark ms-1"><?= $misMainCount ?></span>
          </button>
        </li>

        <!-- ✅ NUEVO: Mis actividades diarias (SOLO Diarias / HOY) -->
        <li class="nav-item" role="presentation">
          <button class="nav-link"
                  id="tab-diarias-main"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-diarias-main"
                  type="button"
                  role="tab">
            Mis actividades diarias
            <span class="badge rounded-pill bg-light text-dark ms-1"><?= $dailyMainCount ?></span>
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button class="nav-link"
                  id="tab-asignadas-main"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-asignadas-main"
                  type="button"
                  role="tab">
            Actividades asignadas por mí
            <span class="badge rounded-pill bg-light text-dark ms-1"><?= $asigMainCount ?></span>
          </button>
        </li>

        <?php if ($canSeeTeam): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    id="tab-equipo-main"
                    data-bs-toggle="pill"
                    data-bs-target="#pane-equipo-main"
                    type="button"
                    role="tab">
              Actividades de mi equipo
              <span class="badge rounded-pill bg-light text-dark ms-1"><?= $teamMainCount ?></span>
            </button>
          </li>
        <?php endif; ?>
      </ul>

      <div class="tab-content" id="mainTaskTabsContent">

        <!-- ========================================================= -->
        <!-- 1) MIS ACTIVIDADES (NO Diarias en Activas/Revisión) -->
        <!-- ========================================================= -->
        <div class="tab-pane fade show active" id="pane-mis-main" role="tabpanel" aria-labelledby="tab-mis-main">

          <ul class="nav nav-tabs nested-task-tabs" id="misNestedTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active"
                      id="mis-active-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#mis-active-pane"
                      type="button"
                      role="tab">
                Activas
                <span class="badge rounded-pill bg-dark ms-1"><?= count($misActive) ?></span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="mis-review-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#mis-review-pane"
                      type="button"
                      role="tab">
                En revisión
                <span class="badge rounded-pill bg-warning text-dark ms-1"><?= count($misReview) ?></span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="mis-closed-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#mis-closed-pane"
                      type="button"
                      role="tab">
                Cerradas (Historial)
                <span class="badge rounded-pill bg-secondary ms-1"><?= count($misClosed) ?></span>
              </button>
            </li>
          </ul>

          <div class="tab-content">

            <!-- MIS ACTIVAS (NO Diarias) -->
            <div class="tab-pane fade show active" id="mis-active-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaMisTareasActivas" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Marcar</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($misActive as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);

                      $locked   = $isLockedForActions($estadoId);
                      $closed   = $isClosedState($estadoId);

                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                      $canCancel = $canCancelRow($t);

                      // ✅ NUEVO: color por caducidad (solo si NO está cerrada)
                      $dueClass = (!$closed && $estadoId !== 6) ? $getDueClass($finRaw) : '';
                    ?>
                      <tr class="<?= esc(trim($rowClass . ' ' . $dueClass)) ?>">
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
                                <span class="icon-btn icon-ok">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M13.485 1.929a.75.75 0 0 1 .086 1.056l-7.25 9a.75.75 0 0 1-1.097.073l-3.75-3.5a.75.75 0 1 1 1.023-1.096l3.16 2.95 6.74-8.36a.75.75 0 0 1 1.088-.123z"/>
                                  </svg>
                                </span>
                              </button>

                              <button type="button"
                                      class="btn btn-sm btn-outline-dark action-state"
                                      data-id="<?= (int)$t['id_tarea'] ?>"
                                      data-state="4"
                                      title="Marcar como no realizada">
                                <span class="icon-btn icon-no">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
                                  </svg>
                                </span>
                              </button>
                            </div>
                          <?php endif; ?>
                        </td>

                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>

                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>

                        <td class="text-end">
                          <?php if ($closed || $estadoId === 6): ?>
                            <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                              <span class="icon-btn icon-lk">
                                <svg viewBox="0 0 16 16" aria-hidden="true">
                                  <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                                </svg>
                              </span>
                            </button>
                          <?php else: ?>
                            <div class="d-flex justify-content-end gap-1">
                              <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                                 class="btn btn-sm btn-outline-dark"
                                 title="Editar">
                                <span class="icon-btn icon-ed">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.5 9.5L3 14l.646-2.646 9.5-9.5zM11.207 2.5 4 9.707V11h1.293L12.5 3.793 11.207 2.5z"/>
                                    <path d="M1 13.5V16h2.5l-.5-2H1.5l-.5-.5z"/>
                                  </svg>
                                </span>
                              </a>

                              <?php if ($canCancel): ?>
                                <form action="<?= site_url('tareas/cancelar/' . (int)$t['id_tarea']) ?>"
                                      method="post"
                                      class="m-0 form-cancel-task d-inline">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id_estado_tarea" value="5">
                                  <input type="hidden" name="review_reason" value="">

                                  <button type="button"
                                          class="btn btn-sm btn-outline-danger btn-cancel btn-cancel-task"
                                          data-task="<?= (int)$t['id_tarea'] ?>"
                                          title="Solicitar cancelación">
                                    <span class="icon-btn icon-cancel">
                                      <svg viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm3.354 9.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 1 1 .708-.708L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646z"/>
                                      </svg>
                                    </span>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- MIS EN REVISIÓN (NO Diarias) -->
            <div class="tab-pane fade" id="mis-review-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaMisTareasRevision" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Marcar</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($misReview as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);
                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                    ?>
                      <tr class="<?= esc($rowClass) ?>">
                        <td><span class="badge bg-warning text-dark">En revisión</span></td>
                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>
                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                            <span class="icon-btn icon-lk">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                              </svg>
                            </span>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- MIS CERRADAS (Historial completo: Diarias + no Diarias) -->
            <div class="tab-pane fade" id="mis-closed-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaMisTareasCerradas" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Estado</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($misClosed as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);
                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                      $isRec = $isRecurring($t);
                    ?>
                      <tr class="<?= esc($rowClass) ?>">
                        <td>
                          <span class="badge bg-secondary">Cerrada</span>
                          <?php if ($isRec): ?>
                            <span class="badge bg-info text-dark ms-1">Repetida</span>
                          <?php endif; ?>
                        </td>
                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>
                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                            <span class="icon-btn icon-lk">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                              </svg>
                            </span>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================= -->
        <!-- ✅ NUEVO: 1.5) MIS TAREAS DIARIAS (solo Diarias de HOY) -->
        <!-- ========================================================= -->
        <div class="tab-pane fade" id="pane-diarias-main" role="tabpanel" aria-labelledby="tab-diarias-main">
          <div class="alert alert-info">
            Aquí se muestran <b>solo</b> las actividades <b>Diarias</b> del día <b><?= esc($todayKey) ?></b>.
          </div>

          <div class="table-responsive">
            <table id="tablaMisTareasDiarias" class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:120px;">Marcar</th>
                  <th>Nombre de la actividad</th>
                  <th>Área</th>
                  <th>Prioridad</th>
                  <th>Estado</th>
                  <th>Inicio</th>
                  <th>Fin</th>
                  <th class="text-end" style="width:140px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($misRecurringToday as $t):
                  $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                  $rowClass = $rowClassByState($estadoId);

                  $locked   = $isLockedForActions($estadoId);
                  $closed   = $isClosedState($estadoId);

                  $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                  $finRaw    = (string)($t['fecha_fin'] ?? '');
                  $canCancel = $canCancelRow($t);

                  $dueClass = (!$closed && $estadoId !== 6) ? $getDueClass($finRaw) : '';
                ?>
                  <tr class="<?= esc(trim($rowClass . ' ' . $dueClass)) ?>">
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
                            <span class="icon-btn icon-ok">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M13.485 1.929a.75.75 0 0 1 .086 1.056l-7.25 9a.75.75 0 0 1-1.097.073l-3.75-3.5a.75.75 0 1 1 1.023-1.096l3.16 2.95 6.74-8.36a.75.75 0 0 1 1.088-.123z"/>
                              </svg>
                            </span>
                          </button>

                          <button type="button"
                                  class="btn btn-sm btn-outline-dark action-state"
                                  data-id="<?= (int)$t['id_tarea'] ?>"
                                  data-state="4"
                                  title="Marcar como no realizada">
                            <span class="icon-btn icon-no">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
                              </svg>
                            </span>
                          </button>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?= esc($t['titulo'] ?? '-') ?>
                      <span class="badge bg-info text-dark ms-1">Repetida</span>
                    </td>
                    <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                    <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                    <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

                    <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                      <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                    </td>

                    <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>

                    <td class="text-end">
                      <?php if ($closed || $estadoId === 6): ?>
                        <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                          <span class="icon-btn icon-lk">
                            <svg viewBox="0 0 16 16" aria-hidden="true">
                              <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                            </svg>
                          </span>
                        </button>
                      <?php else: ?>
                        <div class="d-flex justify-content-end gap-1">
                          <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                             class="btn btn-sm btn-outline-dark"
                             title="Editar">
                            <span class="icon-btn icon-ed">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.5 9.5L3 14l.646-2.646 9.5-9.5zM11.207 2.5 4 9.707V11h1.293L12.5 3.793 11.207 2.5z"/>
                                <path d="M1 13.5V16h2.5l-.5-2H1.5l-.5-.5z"/>
                              </svg>
                            </span>
                          </a>

                          <?php if ($canCancel): ?>
                            <form action="<?= site_url('tareas/cancelar/' . (int)$t['id_tarea']) ?>"
                                  method="post"
                                  class="m-0 form-cancel-task d-inline">
                              <?= csrf_field() ?>
                              <input type="hidden" name="id_estado_tarea" value="5">
                              <input type="hidden" name="review_reason" value="">

                              <button type="button"
                                      class="btn btn-sm btn-outline-danger btn-cancel btn-cancel-task"
                                      data-task="<?= (int)$t['id_tarea'] ?>"
                                      title="Solicitar cancelación">
                                <span class="icon-btn icon-cancel">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm3.354 9.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 1 1 .708-.708L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646z"/>
                                  </svg>
                                </span>
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if (empty($misRecurringToday)): ?>
            <div class="text-muted small mt-2">
              No tienes actividades Diarias programadas para hoy.
            </div>
          <?php endif; ?>
        </div>

        <!-- ========================================================= -->
        <!-- 2) ACTIVIDADES ASIGNADAS POR MÍ -->
        <!-- ========================================================= -->
        <div class="tab-pane fade" id="pane-asignadas-main" role="tabpanel" aria-labelledby="tab-asignadas-main">

          <ul class="nav nav-tabs nested-task-tabs" id="asignadasNestedTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active"
                      id="asig-active-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#asig-active-pane"
                      type="button"
                      role="tab">
                Activas
                <span class="badge rounded-pill bg-dark ms-1"><?= count($asigActive) ?></span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="asig-review-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#asig-review-pane"
                      type="button"
                      role="tab">
                En revisión
                <span class="badge rounded-pill bg-warning text-dark ms-1"><?= count($asigReview) ?></span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="asig-closed-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#asig-closed-pane"
                      type="button"
                      role="tab">
                Cerradas
                <span class="badge rounded-pill bg-secondary ms-1"><?= count($asigClosed) ?></span>
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <!-- ASIGNADAS ACTIVAS -->
            <div class="tab-pane fade show active" id="asig-active-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaAsignadasActivas" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Marcar</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Asignado a</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($asigActive as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);
                      $locked = $isLockedForActions($estadoId);
                      $closed = $isClosedState($estadoId);

                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                      $canCancel = $canCancelRow($t);

                      $dueClass = (!$closed && $estadoId !== 6) ? $getDueClass($finRaw) : '';
                    ?>
                      <tr class="<?= esc(trim($rowClass . ' ' . $dueClass)) ?>">
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
                                <img src="<?= base_url('assets/img/icons/report.svg') ?>"
                                     alt="Marcar como hecha"
                                     class="icon-svg">
                              </button>

                              <button type="button"
                                      class="btn btn-sm btn-outline-dark action-state"
                                      data-id="<?= (int)$t['id_tarea'] ?>"
                                      data-state="4"
                                      title="Marcar como no realizada">
                                <span class="icon-btn icon-no">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
                                  </svg>
                                </span>
                              </button>
                            </div>
                          <?php endif; ?>
                        </td>

                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>

                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>

                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>

                        <td class="text-end">
                          <?php if ($closed || $estadoId === 6): ?>
                            <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                              <span class="icon-btn icon-lk">
                                <svg viewBox="0 0 16 16" aria-hidden="true">
                                  <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                                </svg>
                              </span>
                            </button>
                          <?php else: ?>
                            <div class="d-flex justify-content-end gap-1">
                              <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                                 class="btn btn-sm btn-outline-dark"
                                 title="Editar">
                                <span class="icon-btn icon-ed">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.5 9.5L3 14l.646-2.646 9.5-9.5zM11.207 2.5 4 9.707V11h1.293L12.5 3.793 11.207 2.5z"/>
                                    <path d="M1 13.5V16h2.5l-.5-2H1.5l-.5-.5z"/>
                                  </svg>
                                </span>
                              </a>

                              <?php if ($canCancel): ?>
                                <form action="<?= site_url('tareas/cancelar/' . (int)$t['id_tarea']) ?>"
                                      method="post"
                                      class="m-0 form-cancel-task d-inline">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id_estado_tarea" value="5">
                                  <input type="hidden" name="review_reason" value="">
                                  <button type="button"
                                          class="btn btn-sm btn-outline-danger btn-cancel btn-cancel-task"
                                          data-task="<?= (int)$t['id_tarea'] ?>"
                                          title="Solicitar cancelación">
                                    <span class="icon-btn icon-cancel">
                                      <svg viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm3.354 9.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 1 1 .708-.708L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646z"/>
                                      </svg>
                                    </span>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ASIGNADAS EN REVISIÓN -->
            <div class="tab-pane fade" id="asig-review-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaAsignadasRevision" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Marcar</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Asignado a</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($asigReview as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);
                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                    ?>
                      <tr class="<?= esc($rowClass) ?>">
                        <td><span class="badge bg-warning text-dark">En revisión</span></td>
                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>
                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                            <span class="icon-btn icon-lk">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                              </svg>
                            </span>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ASIGNADAS CERRADAS -->
            <div class="tab-pane fade" id="asig-closed-pane" role="tabpanel">
              <div class="table-responsive">
                <table id="tablaAsignadasCerradas" class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:120px;">Estado</th>
                      <th>Nombre de la actividad</th>
                      <th>Área</th>
                      <th>Asignado a</th>
                      <th>Prioridad</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th class="text-end" style="width:140px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($asigClosed as $t):
                      $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                      $rowClass = $rowClassByState($estadoId);
                      $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                      $finRaw    = (string)($t['fecha_fin'] ?? '');
                    ?>
                      <tr class="<?= esc($rowClass) ?>">
                        <td><span class="badge bg-secondary">Cerrada</span></td>
                        <td><?= esc($t['titulo'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                        <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                        <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                        <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                          <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                        </td>
                        <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                            <span class="icon-btn icon-lk">
                              <svg viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                              </svg>
                            </span>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================= -->
        <!-- 3) TAREAS DE MI EQUIPO -->
        <!-- ========================================================= -->
        <?php if ($canSeeTeam): ?>
          <div class="tab-pane fade" id="pane-equipo-main" role="tabpanel" aria-labelledby="tab-equipo-main">

            <ul class="nav nav-tabs nested-task-tabs" id="equipoNestedTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active"
                        id="team-active-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#team-active-pane"
                        type="button"
                        role="tab">
                  Activas
                  <span class="badge rounded-pill bg-dark ms-1"><?= count($teamActive) ?></span>
                </button>
              </li>

              <li class="nav-item" role="presentation">
                <button class="nav-link"
                        id="team-review-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#team-review-pane"
                        type="button"
                        role="tab">
                  En revisión
                  <span class="badge rounded-pill bg-warning text-dark ms-1"><?= count($teamReview) ?></span>
                </button>
              </li>

              <li class="nav-item" role="presentation">
                <button class="nav-link"
                        id="team-closed-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#team-closed-pane"
                        type="button"
                        role="tab">
                  Cerradas
                  <span class="badge rounded-pill bg-secondary ms-1"><?= count($teamClosed) ?></span>
                </button>
              </li>
            </ul>

            <div class="tab-content">
              <!-- EQUIPO ACTIVAS -->
              <div class="tab-pane fade show active" id="team-active-pane" role="tabpanel">
                <div class="table-responsive">
                  <table id="tablaEquipoActivas" class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th style="width:120px;">Marcar</th>
                        <th>Nombre de la actividad</th>
                        <th>Área</th>
                        <th>Asignado a</th>
                        <th>Asignado por</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th class="text-end" style="width:140px;">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($teamActive as $t):
                        $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                        $rowClass = $rowClassByState($estadoId);
                        $locked = $isLockedForActions($estadoId);
                        $closed = $isClosedState($estadoId);

                        $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                        $finRaw    = (string)($t['fecha_fin'] ?? '');
                        $canCancel = $canCancelRow($t);

                        $dueClass = (!$closed && $estadoId !== 6) ? $getDueClass($finRaw) : '';
                      ?>
                        <tr class="<?= esc(trim($rowClass . ' ' . $dueClass)) ?>">
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
                                  <span class="icon-btn icon-ok">
                                    <svg viewBox="0 0 16 16" aria-hidden="true">
                                      <path d="M13.485 1.929a.75.75 0 0 1 .086 1.056l-7.25 9a.75.75 0 0 1-1.097.073l-3.75-3.5a.75.75 0 1 1 1.023-1.096l3.16 2.95 6.74-8.36a.75.75 0 0 1 1.088-.123z"/>
                                    </svg>
                                  </span>
                                </button>

                                <button type="button"
                                        class="btn btn-sm btn-outline-dark action-state"
                                        data-id="<?= (int)$t['id_tarea'] ?>"
                                        data-state="4"
                                        title="Marcar como no realizada">
                                  <span class="icon-btn icon-no">
                                    <svg viewBox="0 0 16 16" aria-hidden="true">
                                      <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L9.06 8l3.22 3.22a.75.75 0 1 1-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
                                    </svg>
                                  </span>
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

                          <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                            <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                          </td>

                          <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>

                          <td class="text-end">
                            <?php if ($closed || $estadoId === 6): ?>
                              <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                                <span class="icon-btn icon-lk">
                                  <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                                  </svg>
                                </span>
                              </button>
                            <?php else: ?>
                              <div class="d-flex justify-content-end gap-1">
                                <a href="<?= site_url('tareas/editar/' . (int)$t['id_tarea']) ?>"
                                   class="btn btn-sm btn-outline-dark"
                                   title="Editar">
                                  <span class="icon-btn icon-ed">
                                    <svg viewBox="0 0 16 16" aria-hidden="true">
                                      <path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.5 9.5L3 14l.646-2.646 9.5-9.5zM11.207 2.5 4 9.707V11h1.293L12.5 3.793 11.207 2.5z"/>
                                      <path d="M1 13.5V16h2.5l-.5-2H1.5l-.5-.5z"/>
                                    </svg>
                                  </span>
                                </a>

                                <?php if ($canCancel): ?>
                                  <form action="<?= site_url('tareas/cancelar/' . (int)$t['id_tarea']) ?>"
                                        method="post"
                                        class="m-0 form-cancel-task d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_estado_tarea" value="5">
                                    <input type="hidden" name="review_reason" value="">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger btn-cancel btn-cancel-task"
                                            data-task="<?= (int)$t['id_tarea'] ?>"
                                            title="Solicitar cancelación">
                                      <span class="icon-btn icon-cancel">
                                        <svg viewBox="0 0 16 16" aria-hidden="true">
                                          <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm3.354 9.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 1 1 .708-.708L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646z"/>
                                        </svg>
                                      </span>
                                    </button>
                                  </form>
                                <?php endif; ?>
                              </div>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- EQUIPO EN REVISIÓN -->
              <div class="tab-pane fade" id="team-review-pane" role="tabpanel">
                <div class="table-responsive">
                  <table id="tablaEquipoRevision" class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th style="width:120px;">Marcar</th>
                        <th>Nombre de la actividad</th>
                        <th>Área</th>
                        <th>Asignado a</th>
                        <th>Asignado por</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th class="text-end" style="width:140px;">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($teamReview as $t):
                        $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                        $rowClass = $rowClassByState($estadoId);
                        $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                        $finRaw    = (string)($t['fecha_fin'] ?? '');
                      ?>
                        <tr class="<?= esc($rowClass) ?>">
                          <td><span class="badge bg-warning text-dark">En revisión</span></td>
                          <td><?= esc($t['titulo'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                          <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                          <td><?= esc($t['asignado_por_nombre'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                          <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                            <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                          </td>
                          <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                          <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                              <span class="icon-btn icon-lk">
                                <svg viewBox="0 0 16 16" aria-hidden="true">
                                  <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                                </svg>
                              </span>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- EQUIPO CERRADAS -->
              <div class="tab-pane fade" id="team-closed-pane" role="tabpanel">
                <div class="table-responsive">
                  <table id="tablaEquipoCerradas" class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th style="width:120px;">Estado</th>
                        <th>Nombre de la actividad</th>
                        <th>Área</th>
                        <th>Asignado a</th>
                        <th>Asignado por</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th class="text-end" style="width:140px;">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($teamClosed as $t):
                        $estadoId = (int)($t['id_estado_tarea'] ?? 0);
                        $rowClass = $rowClassByState($estadoId);
                        $inicioRaw = (string)($t['fecha_inicio'] ?? '');
                        $finRaw    = (string)($t['fecha_fin'] ?? '');
                      ?>
                        <tr class="<?= esc($rowClass) ?>">
                          <td><span class="badge bg-secondary">Cerrada</span></td>
                          <td><?= esc($t['titulo'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_area'] ?? '-') ?></td>
                          <td><?= esc($t['asignado_a_nombre'] ?? '-') ?></td>
                          <td><?= esc($t['asignado_por_nombre'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_prioridad'] ?? '-') ?></td>
                          <td><?= esc($t['nombre_estado'] ?? '-') ?></td>
                          <td data-order="<?= esc($fmtOrder($inicioRaw)) ?>" class="td-inicio">
                            <?= $inicioRaw ? esc($fmtUiDateTime($inicioRaw)) : '-' ?>
                          </td>
                          <td><?= $finRaw ? esc($fmtUiDateTime($finRaw)) : '-' ?></td>
                          <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" disabled title="No editable">
                              <span class="icon-btn icon-lk">
                                <svg viewBox="0 0 16 16" aria-hidden="true">
                                  <path d="M8 1a3 3 0 0 0-3 3v3H4a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2h-1V4a3 3 0 0 0-3-3zm2 6H6V4a2 2 0 1 1 4 0v3z"/>
                                </svg>
                              </span>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>

<!-- =========================================================
     MODALS
========================================================= -->

<!-- Modal INFO -->
<div class="modal fade" id="modalInfo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="modalInfoTitle">Mensaje</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalInfoBody">...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal" id="modalInfoOk">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal CONFIRM -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="modalConfirmTitle">Confirmar</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalConfirmBody">¿Seguro?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal" id="modalConfirmCancel">Cancelar</button>
        <button type="button" class="btn btn-dark" id="modalConfirmOk">Sí, continuar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal MOTIVO CANCELACIÓN -->
<div class="modal fade" id="modalCancelReason" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Solicitar cancelación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning mb-3">
          Esta cancelación <b>NO</b> se aplicará de inmediato.<br>
          Se enviará a <b>revisión</b> de tu supervisor.
        </div>

        <label class="form-label fw-semibold">Motivo (obligatorio)</label>
        <textarea id="cancelReasonText" class="form-control" rows="4" placeholder="Escribe el motivo..."></textarea>
        <small class="text-muted">El supervisor verá este motivo para aprobar o rechazar.</small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Volver</button>
        <button type="button" class="btn btn-dark" id="btnSendCancelReason">Enviar a revisión</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal ALERTAS VENCIMIENTO -->
<div class="modal fade" id="modalDueAlerts" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Alertas de vencimiento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning">
          Tienes actividades que vencen <b>hoy</b> o <b>mañana</b>. Revisa y actúa a tiempo.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Nombre de la actividad</th>
                <th>Área</th>
                <th>Vence</th>
                <th>Fin</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody id="dueAlertsBody"></tbody>
          </table>
        </div>

        <div class="text-muted small mt-2">
          * Estas alertas se calculan sobre tus actividades.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal DETALLE SOLICITUD -->
<div class="modal fade" id="modalReviewDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Detalle de solicitud</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre de la actividad</label>
            <div class="form-control bg-light" id="reviewDetailTitle">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Área</label>
            <div class="form-control bg-light" id="reviewDetailArea">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Asignado a</label>
            <div class="form-control bg-light" id="reviewDetailAsignado">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Solicitado por</label>
            <div class="form-control bg-light" id="reviewDetailRequestedBy">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Acción solicitada</label>
            <div class="form-control bg-light" id="reviewDetailAction">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Estado solicitado</label>
            <div class="form-control bg-light" id="reviewDetailRequestedState">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Fecha de la solicitud</label>
            <div class="form-control bg-light" id="reviewDetailRequestedAt">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Fecha Inicial actual</label>
            <div class="form-control bg-light" id="reviewDetailInicio">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Fecha final actual</label>
            <div class="form-control bg-light" id="reviewDetailFinActual">-</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Fecha final solicitada</label>
            <div class="form-control bg-light" id="reviewDetailFinSolicitado">-</div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Motivo</label>
            <div class="form-control bg-light" style="min-height:100px; white-space:pre-wrap;" id="reviewDetailReason">-</div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ NUEVO: Modal de decisiones (aprobadas/rechazadas) -->
<div class="modal fade" id="modalDecisionNotifications" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Resultado de tus solicitudes</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          Aquí verás si una solicitud de <b>cancelación</b> o <b>cambio de fecha</b> fue aprobada o rechazada.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Actividad</th>
                <th>Decisión</th>
                <th>Acción</th>
                <th>Fecha decisión</th>
                <th>Supervisor</th>
                <th class="text-end">Ir</th>
              </tr>
            </thead>
            <tbody id="decisionNotificationsBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<!-- ================= LIBRERÍAS ================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

  // =========================================================
  // Helpers MODALS
  // =========================================================
  const modalInfoEl = document.getElementById('modalInfo');
  const modalInfo   = new bootstrap.Modal(modalInfoEl, { backdrop: 'static' });

  const modalConfirmEl = document.getElementById('modalConfirm');
  const modalConfirm   = new bootstrap.Modal(modalConfirmEl, { backdrop: 'static' });

  const modalCancelReasonEl = document.getElementById('modalCancelReason');
  const modalCancelReason   = new bootstrap.Modal(modalCancelReasonEl, { backdrop: 'static' });

  const modalReviewDetailEl = document.getElementById('modalReviewDetail');
  const modalReviewDetail   = new bootstrap.Modal(modalReviewDetailEl, { backdrop: 'static' });

  const modalDueAlertsEl = document.getElementById('modalDueAlerts');
  const modalDueAlerts   = new bootstrap.Modal(modalDueAlertsEl, { backdrop: 'static' });

  const modalDecisionEl = document.getElementById('modalDecisionNotifications');
  const modalDecision   = new bootstrap.Modal(modalDecisionEl, { backdrop: 'static' });

  let confirmCallback = null;

  function escapeHtml(str){
    return String(str)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function showInfoModal(title, body, onClose = null){
    $('#modalInfoTitle').text(title || 'Mensaje');
    $('#modalInfoBody').html(body || '');
    $(modalInfoEl).off('hidden.bs.modal').on('hidden.bs.modal', function(){
      if (typeof onClose === 'function') onClose();
    });
    modalInfo.show();
  }

  function showConfirmModal(title, body, onOk){
    $('#modalConfirmTitle').text(title || 'Confirmar');
    $('#modalConfirmBody').html(body || '');
    confirmCallback = (typeof onOk === 'function') ? onOk : null;
    modalConfirm.show();
  }

  $('#modalConfirmOk').on('click', function(){
    modalConfirm.hide();
    if (confirmCallback) confirmCallback();
    confirmCallback = null;
  });

  // =========================================================
  // Flash messages
  // =========================================================
  const flashError   = <?= json_encode((string)($flashError ?? '')) ?>;
  const flashSuccess = <?= json_encode((string)($flashSuccess ?? '')) ?>;

  if (flashError) {
    showInfoModal('Error', '<div class="alert alert-danger mb-0">'+escapeHtml(flashError)+'</div>');
  } else if (flashSuccess) {
    showInfoModal('Listo', '<div class="alert alert-success mb-0">'+escapeHtml(flashSuccess)+'</div>');
  }

  // =========================================================
  // ✅ Aviso de auto vencidas + modal de vencimientos
  // =========================================================
  const expiredUpdated = <?= json_encode((int)$expiredUpdated) ?>;
  const dueAlerts      = <?= json_encode($dueAlerts, JSON_UNESCAPED_UNICODE) ?>;

  function fmtDate(dt){
    if (!dt) return '-';
    const raw = String(dt).trim();
    const normalized = raw.replace(' ', 'T');
    const d = new Date(normalized);
    if (isNaN(d.getTime())) return raw;

    return d.toLocaleString('es-EC', {
      timeZone: 'America/Guayaquil',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }).replace(',', '');
  }

  if (!flashError && !flashSuccess && expiredUpdated > 0) {
    showInfoModal(
      'Actualización automática',
      `<div class="alert alert-info mb-0">
        Se marcaron automáticamente <b>${expiredUpdated}</b> actividades como <b>No realizada</b> porque su fecha fin ya venció.
      </div>`
    );
  }

  if (Array.isArray(dueAlerts) && dueAlerts.length > 0) {
    const $body = $('#dueAlertsBody');
    $body.empty();

    dueAlerts.forEach(function(a){
      const title   = escapeHtml(a.titulo || '-');
      const area    = escapeHtml(a.nombre_area || '-');
      const dueLbl  = (a.due_label || '').toLowerCase();
      const badge   = (dueLbl === 'hoy')
        ? '<span class="badge badge-due-today">HOY</span>'
        : '<span class="badge badge-due-tomorrow">MAÑANA</span>';

      const fin     = fmtDate(a.fecha_fin || '');
      const editUrl = <?= json_encode(site_url('tareas/editar/')) ?> + String(a.id_tarea || '');

      $body.append(`
        <tr>
          <td>${title}</td>
          <td>${area}</td>
          <td>${badge}</td>
          <td>${escapeHtml(fin)}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-dark" href="${editUrl}">
              Ver / Editar
            </a>
          </td>
        </tr>
      `);
    });

    if (!flashError && !flashSuccess) {
      modalDueAlerts.show();
    }
  }

  // =========================================================
  // ✅ NUEVO: Notificaciones de decisión (aprobado/rechazado)
  // =========================================================
  const decisionNotifications = <?= json_encode($decisionNotifications, JSON_UNESCAPED_UNICODE) ?>;

  function showDecisionNotificationsIfAny(){
    if (!Array.isArray(decisionNotifications) || decisionNotifications.length === 0) return;

    const $b = $('#decisionNotificationsBody');
    $b.empty();

    decisionNotifications.forEach(function(n){
      const titulo = escapeHtml(n.titulo || '-');
      const decision = (n.decision || '').toLowerCase();
      const action = escapeHtml(n.action || '-');
      const approvedAt = escapeHtml(n.approved_at || '-');
      const supervisor = escapeHtml(n.approved_by_nombre || '-');

      const badge = (decision === 'approved')
        ? '<span class="badge bg-success">APROBADA</span>'
        : (decision === 'rejected')
          ? '<span class="badge bg-danger">RECHAZADA</span>'
          : '<span class="badge bg-secondary">PROCESADA</span>';

      const goUrl = <?= json_encode(site_url('tareas/editar/')) ?> + String(n.id_tarea || '');

      $b.append(`
        <tr>
          <td>${titulo}</td>
          <td>${badge}</td>
          <td>${action}</td>
          <td>${approvedAt}</td>
          <td>${supervisor}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-dark" href="${goUrl}">Ver</a>
          </td>
        </tr>
      `);
    });

    modalDecision.show();
  }

  // =========================================================
  // PERSISTENCIA SEGURA Y MÍNIMA
  // =========================================================
  const UI_KEY = 'tareas_gestionar_ui_safe_v2';

  function getUiState(){
    try{ return JSON.parse(sessionStorage.getItem(UI_KEY)) || {}; }
    catch(e){ return {}; }
  }

  function setUiState(patch){
    const current = getUiState();
    const next = Object.assign({}, current, patch);
    sessionStorage.setItem(UI_KEY, JSON.stringify(next));
  }

  function saveTabsState(){
    const mainActiveBtn = document.querySelector('.main-task-tabs .nav-link.active');
    const nestedActiveMis = document.querySelector('#misNestedTabs .nav-link.active');
    const nestedActiveAsig = document.querySelector('#asignadasNestedTabs .nav-link.active');
    const nestedActiveTeam = document.querySelector('#equipoNestedTabs .nav-link.active');

    setUiState({
      mainTab: mainActiveBtn ? mainActiveBtn.getAttribute('data-bs-target') : '#pane-mis-main',
      misTab: nestedActiveMis ? nestedActiveMis.getAttribute('data-bs-target') : '#mis-active-pane',
      asigTab: nestedActiveAsig ? nestedActiveAsig.getAttribute('data-bs-target') : '#asig-active-pane',
      teamTab: nestedActiveTeam ? nestedActiveTeam.getAttribute('data-bs-target') : '#team-active-pane'
    });
  }

  function restoreTabsState(){
    const state = getUiState();

    if (state.mainTab) {
      const btn = document.querySelector('.main-task-tabs .nav-link[data-bs-target="' + state.mainTab + '"]');
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    if (state.misTab) {
      const btn = document.querySelector('#misNestedTabs .nav-link[data-bs-target="' + state.misTab + '"]');
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    if (state.asigTab) {
      const btn = document.querySelector('#asignadasNestedTabs .nav-link[data-bs-target="' + state.asigTab + '"]');
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    if (state.teamTab) {
      const btn = document.querySelector('#equipoNestedTabs .nav-link[data-bs-target="' + state.teamTab + '"]');
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
  }

  function saveDateFilterState(){
    setUiState({ fDesde: $('#fDesde').val() || '', fHasta: $('#fHasta').val() || '' });
  }

  function restoreDateFilterState(){
    const state = getUiState();
    $('#fDesde').val(state.fDesde || '');
    $('#fHasta').val(state.fHasta || '');
  }

  function saveRevisionChecksState(){
    const ids = [];
    $('.chkRevision:checked').each(function(){
      const id = Number($(this).val());
      if (id > 0) ids.push(id);
    });
    setUiState({ reviewChecks: ids });
  }

  function restoreRevisionChecksState(){
    const state = getUiState();
    const ids = Array.isArray(state.reviewChecks) ? state.reviewChecks : [];

    $('.chkRevision').each(function(){
      const id = Number($(this).val());
      $(this).prop('checked', ids.includes(id));
    });

    const total = $('.chkRevision').length;
    const checked = $('.chkRevision:checked').length;
    $('#chkAllRevision').prop('checked', total > 0 && total === checked);
  }

  function saveScrollState(){ setUiState({ scrollY: window.scrollY || 0 }); }

  function restoreScrollState(){
    const state = getUiState();
    const y = Number(state.scrollY || 0);
    if (y > 0) setTimeout(function(){ window.scrollTo(0, y); }, 120);
  }

  function saveUiContext(){
    saveTabsState();
    saveDateFilterState();
    saveRevisionChecksState();
    saveScrollState();
  }

  // Restaurar estado antes de DT
  restoreDateFilterState();

  // =========================================================
  // DataTables
  // =========================================================
  const assignMode = <?= json_encode($assignMode) ?>;

  function initDataTable(idTabla, columnaOrdenInicio, ordenAsc = false) {
    if (!$(idTabla).length) return;
    if ($.fn.DataTable.isDataTable(idTabla)) return;

    $(idTabla).DataTable({
      order: [[columnaOrdenInicio, ordenAsc ? 'asc' : 'desc']],
      pageLength: 10,
      lengthMenu: [[5,10,25,50,100,-1],[5,10,25,50,100,'Todos']],
      dom: 'lBfrtip',
      autoWidth: false,

      stateSave: true,
      stateDuration: -1,

      buttons: [
        { extend: 'excel', text: 'Excel', className: 'btn btn-sm' },
        { extend: 'pdf',   text: 'PDF',   className: 'btn btn-sm' },
        { extend: 'print', text: 'Imprimir', className: 'btn btn-sm' }
      ],
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Mostrando 0 a 0 de 0",
        zeroRecords: "No se encontraron registros",
        emptyTable: "No hay datos disponibles",
        paginate: { next: "›", previous: "‹" }
      }
    });
  }

  // Tabla revisión (por fecha solicitud desc)
  if ($('#tablaRevision').length) initDataTable('#tablaRevision', 7, false);

  // ✅ Activas: ordenar desde HOY hacia futuro (asc por inicio)
  initDataTable('#tablaMisTareasActivas', 5, true);
  initDataTable('#tablaMisTareasDiarias', 5, true);

  // Revisión y cerradas: desc
  initDataTable('#tablaMisTareasRevision', 5, false);
  initDataTable('#tablaMisTareasCerradas', 5, false);

  // Asignadas y equipo (activas asc, cerradas desc)
  initDataTable('#tablaAsignadasActivas', 6, true);
  initDataTable('#tablaAsignadasRevision', 6, false);
  initDataTable('#tablaAsignadasCerradas', 6, false);

  initDataTable('#tablaEquipoActivas', 7, true);
  initDataTable('#tablaEquipoRevision', 7, false);
  initDataTable('#tablaEquipoCerradas', 7, false);

  // Restaurar tabs / checks / scroll después de DT
  restoreTabsState();
  setTimeout(function(){
    restoreRevisionChecksState();
    restoreScrollState();
  }, 120);

  // =========================================================
  // Filtro cliente por fecha inicio
  // =========================================================
  const columnMap = {
    tablaRevision:           7,

    tablaMisTareasActivas:   5,
    tablaMisTareasDiarias:   5,
    tablaMisTareasRevision:  5,
    tablaMisTareasCerradas:  5,

    tablaAsignadasActivas:   6,
    tablaAsignadasRevision:  6,
    tablaAsignadasCerradas:  6,

    tablaEquipoActivas:      7,
    tablaEquipoRevision:     7,
    tablaEquipoCerradas:     7
  };

  function parseDateKey(raw) {
    if (!raw) return '';
    return raw.substring(0,10);
  }

  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    const tableId = settings.nTable.getAttribute('id');
    const colIdx  = columnMap[tableId];
    if (typeof colIdx === 'undefined') return true;

    const desde = $('#fDesde').val() || '';
    const hasta = $('#fHasta').val() || '';
    if (!desde && !hasta) return true;

    const api   = new $.fn.dataTable.Api(settings);
    const node  = api.row(dataIndex).node();

    const td    = $(node).find('td').eq(colIdx);
    const order = td.attr('data-order') || '';
    const key   = parseDateKey(order);

    if (!key) return false;
    if (desde && key < desde) return false;
    if (hasta && key > hasta) return false;

    return true;
  });

  function drawAllTables(){
    const tables = [
      '#tablaRevision',

      '#tablaMisTareasActivas',
      '#tablaMisTareasDiarias',
      '#tablaMisTareasRevision',
      '#tablaMisTareasCerradas',

      '#tablaAsignadasActivas',
      '#tablaAsignadasRevision',
      '#tablaAsignadasCerradas',

      '#tablaEquipoActivas',
      '#tablaEquipoRevision',
      '#tablaEquipoCerradas'
    ];

    tables.forEach(function(id){
      if ($(id).length && $.fn.DataTable.isDataTable(id)) {
        $(id).DataTable().draw(false);
      }
    });
  }

  setTimeout(function(){ drawAllTables(); }, 80);

  $('#btnAplicarFiltro').on('click', function(){
    saveDateFilterState();
    saveScrollState();
    drawAllTables();
  });

  $('#btnLimpiarFiltro').on('click', function(){
    $('#fDesde').val('');
    $('#fHasta').val('');
    saveDateFilterState();
    saveScrollState();
    drawAllTables();
  });

  $('#fDesde, #fHasta').on('change', function(){ saveDateFilterState(); });

  // Guardar pestañas
  $(document).on('shown.bs.tab', '.main-task-tabs .nav-link', function(){ saveTabsState(); });
  $(document).on('shown.bs.tab', '.nested-task-tabs .nav-link', function(){ saveTabsState(); });

  // =========================================================
  // CSRF para fetch
  // =========================================================
  let csrfName = <?= json_encode(csrf_token()) ?>;
  let csrfHash = <?= json_encode(csrf_hash()) ?>;

  async function postEstado(taskId, estadoId){
    const url = <?= json_encode(site_url('tareas/estado')) ?> + '/' + taskId;

    const body = new URLSearchParams();
    body.append(csrfName, csrfHash);
    body.append('estado', String(estadoId));
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
    if (data && data.csrfHash) csrfHash = data.csrfHash;
    return data;
  }

  // =========================================================
  // Marcar (Realizada / No realizada)
  // =========================================================
  $(document).on('click', '.action-state', function(){
    const taskId  = Number($(this).data('id'));
    const stateId = Number($(this).data('state'));
    if (!taskId || !stateId) return;

    saveUiContext();

    const isSelf = (assignMode === 'self');

    const confirmMsg = isSelf
      ? ((stateId === 3)
          ? '¿Solicitar marcar como <b>REALIZADA</b>?<br><small class="text-muted">Se enviará a revisión de tu supervisor.</small>'
          : '¿Solicitar marcar como <b>NO REALIZADA</b>?<br><small class="text-muted">Se enviará a revisión de tu supervisor.</small>')
      : ((stateId === 3)
          ? '¿Marcar esta actividad como <b>REALIZADA</b>?<br><small class="text-muted">Se cerrará y ya no podrá editarse.</small>'
          : '¿Marcar esta actividad como <b>NO REALIZADA</b>?<br><small class="text-muted">Se cerrará y ya no podrá editarse.</small>');

    showConfirmModal('Confirmar acción', confirmMsg, async function(){
      try {
        const r = await postEstado(taskId, stateId);

        if (!r || !r.success) {
          showInfoModal('Error', '<div class="alert alert-danger mb-0">'+escapeHtml((r && r.error) ? r.error : 'No se pudo actualizar el estado.')+'</div>');
          return;
        }

        const msg = (r.message ? r.message : 'Estado actualizado correctamente.');
        showInfoModal('Listo', '<div class="alert alert-success mb-0">'+escapeHtml(msg)+'</div>', function(){
          saveUiContext();
          window.location.reload();
        });

      } catch (e) {
        showInfoModal('Error', '<div class="alert alert-danger mb-0">Error de red al actualizar el estado.</div>');
      }
    });
  });

  // =========================================================
  // Cancelar -> pedir motivo
  // =========================================================
  let cancelTargetForm = null;

  $(document).on('click', '.btn-cancel-task', function(){
    const form = $(this).closest('form.form-cancel-task')[0];
    if (!form) return;

    saveUiContext();

    cancelTargetForm = form;
    $('#cancelReasonText').val('');

    showConfirmModal(
      'Solicitar cancelación',
      '¿Deseas solicitar la <b>CANCELACIÓN</b> de esta actividad?<br><small class="text-muted">Se enviará a revisión del supervisor.</small>',
      function(){
        modalCancelReason.show();
      }
    );
  });

  $('#btnSendCancelReason').on('click', function(){
    if (!cancelTargetForm) return;

    const motivo = String($('#cancelReasonText').val() || '').trim();

    if (!motivo) {
      showInfoModal('Aviso', '<div class="alert alert-warning mb-0">Debes escribir el motivo.</div>');
      return;
    }

    const input = cancelTargetForm.querySelector('input[name="review_reason"]');
    if (input) input.value = motivo;

    saveUiContext();
    cancelTargetForm.submit();
  });

  // =========================================================
  // Revisión por lote
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
    if (data && data.csrfHash) csrfHash = data.csrfHash;
    return data;
  }

  $('#chkAllRevision').on('change', function(){
    $('.chkRevision').prop('checked', $(this).is(':checked'));
    saveRevisionChecksState();
  });

  $(document).on('change', '.chkRevision', function(){
    const total   = $('.chkRevision').length;
    const checked = $('.chkRevision:checked').length;
    $('#chkAllRevision').prop('checked', total > 0 && total === checked);
    saveRevisionChecksState();
  });

  $('#btnApproveBatch').on('click', function(){
    const ids = getSelectedReviewIds();
    if (!ids.length) {
      showInfoModal('Aviso', '<div class="alert alert-warning mb-0">Selecciona actividades para aprobar.</div>');
      return;
    }

    saveUiContext();

    showConfirmModal('Aprobar actividades', '¿Aprobar las solicitudes seleccionadas?', async function(){
      const r = await postReviewBatch('approve', ids);
      if (!r || !r.success) {
        showInfoModal('Error', '<div class="alert alert-danger mb-0">'+escapeHtml((r && r.error) ? r.error : 'No se pudo aprobar.')+'</div>');
        return;
      }
      showInfoModal('Listo', '<div class="alert alert-success mb-0">Aprobadas correctamente.</div>', function(){
        saveUiContext();
        window.location.reload();
      });
    });
  });

  $('#btnRejectBatch').on('click', function(){
    const ids = getSelectedReviewIds();
    if (!ids.length) {
      showInfoModal('Aviso', '<div class="alert alert-warning mb-0">Selecciona actividades para rechazar.</div>');
      return;
    }

    saveUiContext();

    showConfirmModal(
      'Rechazar actividades',
      '¿Rechazar las solicitudes seleccionadas?<br><small class="text-muted">Volverán al estado anterior y se limpiará la revisión.</small>',
      async function(){
        const r = await postReviewBatch('reject', ids);
        if (!r || !r.success) {
          showInfoModal('Error', '<div class="alert alert-danger mb-0">'+escapeHtml((r && r.error) ? r.error : 'No se pudo rechazar.')+'</div>');
          return;
        }
        showInfoModal('Listo', '<div class="alert alert-success mb-0">Rechazadas correctamente.</div>', function(){
          saveUiContext();
          window.location.reload();
        });
      }
    );
  });

  // =========================================================
  // Guardar scroll al salir
  // =========================================================
  $(window).on('beforeunload', function(){ saveUiContext(); });

  // =========================================================
  // Ver detalle de solicitud de revisión
  // =========================================================
  $(document).on('click', '.btn-view-review', function(){
    const $btn = $(this);

    $('#reviewDetailTitle').text($btn.data('title') || '-');
    $('#reviewDetailArea').text($btn.data('area') || '-');
    $('#reviewDetailAsignado').text($btn.data('asignado') || '-');
    $('#reviewDetailRequestedBy').text($btn.data('solicitado-por') || '-');
    $('#reviewDetailAction').text($btn.data('accion') || '-');
    $('#reviewDetailRequestedState').text($btn.data('estado-solicitado') || '-');
    $('#reviewDetailRequestedAt').text($btn.data('fecha-solicitud') || '-');
    $('#reviewDetailInicio').text($btn.data('inicio') || '-');
    $('#reviewDetailFinActual').text($btn.data('fin-actual') || '-');
    $('#reviewDetailFinSolicitado').text($btn.data('fin-solicitado') || '-');
    $('#reviewDetailReason').text($btn.data('motivo') || '-');

    modalReviewDetail.show();
  });


  // =========================================================
  // ✅ NUEVO: Bloquear acciones si la actividad inicia mañana o después
  // Requisito: en pestañas "Activas" no permitir marcar/cancelar/editar
  // si la fecha INICIO es futura (comparación por día, TZ America/Guayaquil)
  // =========================================================
  const todayKey = <?= json_encode($todayKey) ?>;

  function disableFutureRows(tableSelector, inicioColSelector = 'td.td-inicio'){
    if (!$(tableSelector).length) return;

    $(tableSelector).find('tbody tr').each(function(){
      const $tr = $(this);
      const $inicioTd = $tr.find(inicioColSelector).first();
      const order = String($inicioTd.attr('data-order') || '').trim();
      const startKey = order ? order.substring(0, 10) : '';

      if (!startKey) return;

      // Si inicia mañana o después => bloquear
      if (startKey > todayKey) {
        // 1) Botones de marcar
        $tr.find('.action-state').prop('disabled', true).addClass('disabled').attr('title', 'Esta actividad inicia en una fecha futura.');

        // 2) Botón cancelar (es button type=button dentro de form)
        $tr.find('.btn-cancel-task').prop('disabled', true).addClass('disabled').attr('title', 'Esta actividad inicia en una fecha futura.');

        // 3) Link editar
        $tr.find('a[href*="tareas/editar"]').each(function(){
          $(this)
            .addClass('disabled')
            .attr('aria-disabled', 'true')
            .attr('tabindex', '-1')
            .attr('title', 'Esta actividad inicia en una fecha futura.')
            .on('click', function(e){ e.preventDefault(); });
        });

        // 4) Señal visual: reemplazar contenido de la primera columna (Marcar) por badge "Programada"
        const $firstTd = $tr.find('td').first();
        if ($firstTd.length) {
          $firstTd.html('<span class="badge bg-info text-dark">Programada</span>');
        }

        // 5) Señal visual extra: deshabilitar cualquier otro botón de acción (columna final)
        $tr.find('td').last().find('button, input[type="button"]').prop('disabled', true).addClass('disabled');
      }
    });
  }

  // Ejecutar una vez al cargar (y otra vez después de filtros/redraw por DataTables)
  disableFutureRows('#tablaMisTareasActivas');
  disableFutureRows('#tablaAsignadasActivas');
  disableFutureRows('#tablaEquipoActivas');

  // Cuando DataTables redibuja, re-aplicar
  ['#tablaMisTareasActivas','#tablaAsignadasActivas','#tablaEquipoActivas'].forEach(function(sel){
    if ($(sel).length && $.fn.DataTable.isDataTable(sel)) {
      $(sel).on('draw.dt', function(){ disableFutureRows(sel); });
    }
  });

  // =========================================================
  // Mostrar notificaciones de decisión (si existen)
  // =========================================================
  // Se muestra al cargar, si el backend envía data.
  showDecisionNotificationsIfAny();

});
</script>

<?= $this->endSection() ?>
