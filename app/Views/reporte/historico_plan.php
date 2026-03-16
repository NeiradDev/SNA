<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: reporte/historico_plan.php
 * =========================================================
 * ✅ IMPLEMENTADO:
 * - Preguntas en ORDEN según CONDICIÓN (AFLUENCIA, NORMAL, etc.)
 * - Respuestas SIN formato JSON (solo el valor)
 * - Si falta respuesta => muestra "—"
 * - Rediseño visual con paleta corporativa:
 *   #F20505 #F22E2E #F27272 #F2F2F2 #0D0D0D
 * - Animaciones suaves e interacción visual
 * =========================================================
 */

$scopeInfo      = $scopeInfo ?? ['scope' => 'self'];

/** @var array $users */
$users = (is_array($users ?? null)) ? $users : [];

/** @var array $weeks */
$weeks = (is_array($weeks ?? null)) ? $weeks : [];

$selectedUserId = (int)($selectedUserId ?? 0);
$selectedWeek   = (string)($selectedWeek ?? '');
$historico      = $historico ?? null;
$tasksPack      = $tasksPack ?? null;

// Helper seguro
function e($v)
{
    return esc((string)$v);
}

// Label del scope
$scopeLabel = 'Usuario';
if (($scopeInfo['scope'] ?? '') === 'division') $scopeLabel = 'Jefe de División';
if (($scopeInfo['scope'] ?? '') === 'area')     $scopeLabel = 'Jefe de Área';

/**
 * Helper para fechas
 */
$fmtDateTime = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') return 'N/D';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i', $ts) : $value;
};

/**
 * Helper estado visual
 */
$getTaskStatusClass = static function (array $t): string {
    $estado = strtolower(trim((string)($t['nombre_estado'] ?? '')));
    $done = !empty($t['completed_at']) || str_contains($estado, 'realiz') || str_contains($estado, 'complet');

    return $done ? 'hp-badge-done' : 'hp-badge-pending';
};
?>

<style>
    :root{
        --hp-red-1:#F20505;
        --hp-red-2:#F22E2E;
        --hp-red-3:#F27272;
        --hp-light:#F2F2F2;
        --hp-dark:#0D0D0D;
    }

    .hp-page{
        animation: hpFadePage .35s ease;
    }

    @keyframes hpFadePage{
        from{opacity:0; transform:translateY(10px);}
        to{opacity:1; transform:translateY(0);}
    }

    .hp-title{
        font-weight:900;
        letter-spacing:.3px;
        color:var(--hp-dark);
        margin-bottom:.2rem;
    }

    .hp-subtitle{
        color:rgba(13,13,13,.72);
        font-size:.95rem;
    }

    .hp-card{
        border:1px solid rgba(13,13,13,.10);
        border-radius:18px;
        background:linear-gradient(180deg, #ffffff 0%, var(--hp-light) 100%);
        box-shadow:0 14px 30px -22px rgba(13,13,13,.18);
        transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    .hp-card:hover{
        transform:translateY(-2px);
        box-shadow:0 18px 36px -24px rgba(13,13,13,.24);
        border-color:rgba(242,46,46,.18);
    }

    .hp-top-card{
        position:relative;
        overflow:hidden;
    }

    .hp-top-card::before{
        content:"";
        position:absolute;
        inset:0;
        background:
            radial-gradient(circle at top right, rgba(242,46,46,.10), transparent 34%),
            radial-gradient(circle at bottom left, rgba(242,114,114,.12), transparent 38%);
        pointer-events:none;
    }

    .hp-top-content{
        position:relative;
        z-index:1;
    }

    .hp-chip{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:7px 12px;
        border-radius:999px;
        background:rgba(242,46,46,.10);
        border:1px solid rgba(242,46,46,.20);
        color:var(--hp-dark);
        font-weight:800;
        font-size:.86rem;
    }

    .hp-btn-soft{
        border-radius:12px;
        font-weight:800;
        transition:all .18s ease;
    }

    .hp-btn-soft:hover{
        transform:translateY(-1px);
    }

    .hp-btn-dark-custom{
        background:linear-gradient(135deg, var(--hp-dark) 0%, var(--hp-red-2) 100%);
        border-color:var(--hp-dark);
        color:#fff;
        box-shadow:0 12px 24px -18px rgba(13,13,13,.38);
    }

    .hp-btn-dark-custom:hover{
        color:#fff;
        opacity:.96;
    }

    .hp-btn-light-custom{
        background:linear-gradient(180deg, #ffffff 0%, var(--hp-light) 100%);
        border:1px solid rgba(13,13,13,.12);
        color:var(--hp-dark);
    }

    .hp-kpis{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
    }

    .hp-kpi{
        min-width:145px;
        padding:12px 14px;
        border-radius:14px;
        background:linear-gradient(135deg, var(--hp-dark) 0%, var(--hp-red-2) 100%);
        color:#fff;
        box-shadow:0 14px 28px -20px rgba(13,13,13,.34);
        animation:hpFloat .35s ease;
    }

    .hp-kpi strong{
        display:block;
        font-size:1.08rem;
        line-height:1.1;
    }

    .hp-kpi small{
        display:block;
        margin-top:4px;
        opacity:.88;
    }

    @keyframes hpFloat{
        from{opacity:0; transform:translateY(10px) scale(.98);}
        to{opacity:1; transform:translateY(0) scale(1);}
    }

    .hp-section-title{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:12px 14px;
        border-radius:12px;
        font-weight:900;
        letter-spacing:.3px;
        text-transform:uppercase;
        background:linear-gradient(135deg, #ffffff 0%, var(--hp-light) 100%);
        border:1px solid rgba(13,13,13,.12);
        color:var(--hp-dark);
    }

    .hp-section-title small{
        font-weight:700;
        opacity:.75;
        text-transform:none;
    }

    .hp-filter-card{
        padding:1rem;
    }

    .hp-summary-grid{
        display:grid;
        grid-template-columns:repeat(12, 1fr);
        gap:16px;
    }

    .hp-col-12{grid-column:span 12;}
    .hp-col-6{grid-column:span 6;}
    .hp-col-4{grid-column:span 4;}
    .hp-col-8{grid-column:span 8;}

    .hp-info-grid{
        display:grid;
        grid-template-columns:repeat(12, 1fr);
        gap:12px;
    }

    .hp-info-item{
        grid-column:span 6;
        border:1px solid rgba(13,13,13,.08);
        border-radius:14px;
        padding:12px 14px;
        background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.90) 100%);
        transition:transform .18s ease, border-color .18s ease;
    }

    .hp-info-item:hover{
        transform:translateY(-1px);
        border-color:rgba(242,46,46,.18);
    }

    .hp-info-item small{
        display:block;
        color:rgba(13,13,13,.68);
        margin-bottom:4px;
        font-weight:700;
    }

    .hp-info-item strong{
        color:var(--hp-dark);
    }

    .hp-questions-grid{
        display:grid;
        grid-template-columns:repeat(12, 1fr);
        gap:12px;
    }

    .hp-question-card{
        grid-column:span 6;
        border:1px solid rgba(13,13,13,.08);
        border-radius:14px;
        background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.92) 100%);
        padding:14px;
        box-shadow:0 8px 18px -22px rgba(13,13,13,.25);
        transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        animation:hpItemIn .28s ease;
    }

    .hp-question-card:hover{
        transform:translateY(-1px);
        border-color:rgba(242,46,46,.20);
        box-shadow:0 14px 24px -22px rgba(13,13,13,.22);
    }

    .hp-question-title{
        font-weight:900;
        color:var(--hp-dark);
        margin-bottom:8px;
        line-height:1.35;
    }

    .hp-question-answer{
        color:rgba(13,13,13,.82);
        white-space:pre-wrap;
    }

    .hp-empty{
        border:1px dashed rgba(13,13,13,.16);
        border-radius:14px;
        padding:14px;
        background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.94) 100%);
        color:rgba(13,13,13,.72);
    }

    .hp-task-section-danger{
        background:linear-gradient(180deg, rgba(242,114,114,.10) 0%, rgba(242,242,242,.96) 100%);
        border:1px solid rgba(242,46,46,.22);
    }

    .hp-task-section-danger .hp-section-title{
        background:linear-gradient(135deg, var(--hp-dark) 0%, var(--hp-red-2) 100%);
        color:#fff;
        border-color:rgba(13,13,13,.10);
    }

    .hp-task-section-danger .hp-section-title small{
        color:rgba(242,242,242,.92);
        opacity:1;
    }

    .hp-task-list{
        display:grid;
        gap:10px;
    }

    .hp-task-row{
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:center;
        justify-content:space-between;
        padding:14px;
        border:1px solid rgba(13,13,13,.10);
        border-radius:14px;
        background:linear-gradient(180deg, #ffffff 0%, rgba(242,242,242,.94) 100%);
        box-shadow:0 8px 18px -22px rgba(13,13,13,.25);
        transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        animation:hpItemIn .28s ease;
    }

    .hp-task-row:hover{
        transform:translateY(-1px);
        border-color:rgba(242,46,46,.20);
        box-shadow:0 16px 28px -24px rgba(13,13,13,.22);
    }

    .hp-task-section-danger .hp-task-row{
        background:linear-gradient(180deg, #ffffff 0%, rgba(242,114,114,.08) 100%);
        border-color:rgba(242,46,46,.12);
    }

    @keyframes hpItemIn{
        from{opacity:0; transform:translateY(8px);}
        to{opacity:1; transform:translateY(0);}
    }

    .hp-task-left{
        min-width:240px;
        flex:1 1 360px;
    }

    .hp-task-title{
        font-weight:900;
        margin:0;
        color:var(--hp-dark);
    }

    .hp-task-desc{
        margin:4px 0 0;
        color:rgba(13,13,13,.74);
        font-size:.92rem;
        line-height:1.3rem;
    }

    .hp-task-extra{
        margin-top:6px;
        color:rgba(13,13,13,.72);
        font-size:.86rem;
    }

    .hp-task-meta{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        align-items:center;
        justify-content:flex-end;
        flex:0 0 auto;
    }

    .hp-badge{
        display:inline-flex;
        align-items:center;
        border-radius:999px;
        padding:7px 11px;
        font-size:.82rem;
        font-weight:800;
        border:1px solid rgba(13,13,13,.10);
    }

    .hp-badge-soft{
        background:rgba(13,13,13,.04);
        color:var(--hp-dark);
    }

    .hp-badge-date{
        font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        background:linear-gradient(180deg, #ffffff 0%, var(--hp-light) 100%);
        color:var(--hp-dark);
    }

    .hp-badge-done{
        background:linear-gradient(135deg, var(--hp-dark) 0%, #2c2c2c 100%);
        color:#fff;
    }

    .hp-badge-pending{
        background:linear-gradient(180deg, rgba(242,242,242,.95) 0%, #ffffff 100%);
        color:var(--hp-dark);
    }

    .hp-badge-urgent{
        background:linear-gradient(135deg, var(--hp-dark) 0%, var(--hp-red-2) 100%);
        color:#fff;
    }

    .hp-divider{
        height:1px;
        background:rgba(13,13,13,.10);
        margin:10px 0;
    }

    @media (max-width: 992px){
        .hp-col-8,
        .hp-col-6,
        .hp-col-4{
            grid-column:span 12;
        }

        .hp-question-card{
            grid-column:span 12;
        }
    }

    @media (max-width: 768px){
        .hp-info-item{
            grid-column:span 12;
        }

        .hp-task-left{
            min-width:100%;
        }

        .hp-kpis{
            width:100%;
        }

        .hp-kpi{
            flex:1 1 calc(50% - 10px);
        }
    }
</style>

<div class="container-fluid hp-page">

    <!-- ===================== HEADER ===================== -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h3 class="hp-title">Histórico Plan de Batalla</h3>
            <div class="hp-subtitle">
                Rol detectado: <b><?= e($scopeLabel) ?></b>
                <?php if (!empty($scopeInfo['division'])): ?>
                    — División: <b><?= e($scopeInfo['division']) ?></b>
                <?php endif; ?>
                <?php if (!empty($scopeInfo['area'])): ?>
                    — Área: <b><?= e($scopeInfo['area']) ?></b>
                <?php endif; ?>
            </div>
        </div>

        <div class="hp-kpis">
            <div class="hp-kpi">
                <strong><?= e($scopeLabel) ?></strong>
                <small>Alcance actual</small>
            </div>
            <div class="hp-kpi">
                <strong><?= count($users) ?></strong>
                <small>Usuarios visibles</small>
            </div>
            <div class="hp-kpi">
                <strong><?= count($weeks) ?></strong>
                <small>Semanas disponibles</small>
            </div>
        </div>
    </div>

    <!-- HERO -->
    <div class="card hp-card hp-top-card mb-3">
        <div class="card-body hp-top-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="hp-chip">Consulta histórica</span>
                    <div class="mt-2 text-muted">
                        Visualiza respuestas guardadas, condición, satisfacción y actividades de la semana seleccionada.
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn hp-btn-soft hp-btn-light-custom" href="<?= base_url('reporte/plan_batalla') ?>">
                        Volver
                    </a>
                    <?php if ($selectedWeek !== '' && $selectedUserId > 0): ?>
                        <a class="btn hp-btn-soft hp-btn-dark-custom"
                           target="_blank"
                           rel="noopener noreferrer"
                           href="<?= base_url('reporte/historico-plan/pdf?semana=' . urlencode($selectedWeek) . '&user=' . (int)$selectedUserId) ?>">
                            Generar PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== FILTROS ===================== -->
    <div class="card hp-card hp-filter-card mb-3">
        <div class="hp-section-title mb-3">
            <span>Filtros</span>
            <small>Consulta personalizada</small>
        </div>

        <form method="get" action="<?= base_url('reporte/historico-plan') ?>" class="row g-3">
            <div class="col-12 col-lg-6">
                <label class="form-label fw-bold">Usuario a consultar</label>
                <select name="user" class="form-select" required>
                    <?php foreach ($users as $u): ?>
                        <?php $uid = (int)($u['id_user'] ?? 0); ?>
                        <option value="<?= $uid ?>" <?= ($uid === $selectedUserId ? 'selected' : '') ?>>
                            <?= e($u['label'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-muted small mt-1">
                    <?php if (($scopeInfo['scope'] ?? '') === 'division'): ?>
                        Puedes consultar usuarios dentro de tu división.
                    <?php elseif (($scopeInfo['scope'] ?? '') === 'area'): ?>
                        Puedes consultar usuarios dentro de tu área.
                    <?php else: ?>
                        Solo puedes consultar tu propio histórico.
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-bold">Semana (corte)</label>
                <select name="semana" class="form-select" required>
                    <?php foreach ((array)$weeks as $w): ?>
                        <?php $wk = (string)($w['semana'] ?? ''); ?>
                        <option value="<?= e($wk) ?>" <?= ($wk === $selectedWeek ? 'selected' : '') ?>>
                            <?= e($wk) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-muted small mt-1">
                    Semana guardada en <b>historico.semana</b> (miércoles de corte).
                </div>
            </div>

            <div class="col-12 col-lg-2 d-flex align-items-end">
                <button class="btn hp-btn-soft hp-btn-dark-custom w-100">Buscar</button>
            </div>
        </form>
    </div>

    <!-- ===================== CONTENIDO ===================== -->
    <?php if (!$historico): ?>
        <div class="card hp-card">
            <div class="card-body">
                <div class="hp-empty">
                    <b>No hay histórico para esa semana / usuario.</b>
                    <div class="mt-1">
                        Prueba con otra semana o verifica que el usuario guardó su plan en esa fecha.
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- RESUMEN HISTORICO -->
        <div class="hp-summary-grid mb-3">
            <div class="hp-col-8">
                <div class="card hp-card">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Resumen del histórico</span>
                            <small><?= e($historico['semana'] ?? '') ?></small>
                        </div>

                        <div class="hp-info-grid">
                            <div class="hp-info-item">
                                <small>Usuario</small>
                                <strong><?= e(($historico['apellidos'] ?? '') . ' ' . ($historico['nombres'] ?? '')) ?></strong>
                            </div>

                            <div class="hp-info-item">
                                <small>Cédula</small>
                                <strong><?= e($historico['cedula'] ?? 'N/D') ?></strong>
                            </div>

                            <div class="hp-info-item">
                                <small>División</small>
                                <strong><?= e($historico['division_nombre'] ?? ($historico['nombre_division'] ?? '')) ?></strong>
                            </div>

                            <div class="hp-info-item">
                                <small>Área</small>
                                <strong><?= e($historico['area_nombre'] ?? '') ?></strong>
                            </div>

                            <div class="hp-info-item">
                                <small>Cargo</small>
                                <strong><?= e($historico['cargo_nombre'] ?? '-') ?></strong>
                            </div>

                            <div class="hp-info-item">
                                <small>Jefe inmediato</small>
                                <strong><?= e($historico['jefe_inmediato'] ?? '-') ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hp-col-4">
                <div class="card hp-card">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Indicadores</span>
                            <small>Plan guardado</small>
                        </div>

                        <div class="hp-info-grid">
                            <div class="hp-info-item" style="grid-column:span 12;">
                                <small>Semana</small>
                                <strong><?= e($historico['semana'] ?? '') ?></strong>
                            </div>

                            <div class="hp-info-item" style="grid-column:span 12;">
                                <small>Satisfacción</small>
                                <strong><?= e($historico['satisfaccion'] ?? '0') ?>%</strong>
                            </div>

                            <div class="hp-info-item" style="grid-column:span 12;">
                                <small>Estado</small>
                                <strong><?= e($historico['estado'] ?? '-') ?></strong>
                            </div>

                            <div class="hp-info-item" style="grid-column:span 12;">
                                <small>Condición</small>
                                <strong><?= e($historico['condicion'] ?? '-') ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- PREGUNTAS -->
        <!-- ===================================================== -->
        <div class="card hp-card mb-3">
            <div class="card-body">
                <div class="hp-section-title mb-3">
                    <span>Detalle (Preguntas del Plan)</span>
                    <small>Ordenadas por condición</small>
                </div>

                <?php
                $questionsByCondition = [
                    'AFLUENCIA' => [
                        "ECONOMIZA EN ACTIVIDADES INNECESARIAS QUE NO CONTRIBUYERON A LA AFLUENCIA.",
                        "HAZ QUE TODA ACCION CUENTE Y NO TOMES PARTE EN NINGUNA ACCIÓN INÚTIL.",
                        "CONSOLIDAR LAS GANANCIAS.",
                        "DESCUBRE QUÉ CAUSÓ LA AFLUENCIA Y REFUERZALO.",
                    ],
                    'NORMAL' => [
                        "NO CAMBIAR NADA.",
                        "LA ÉTICA ES MUY POCO SEVERA.",
                        "EXAMINA LAS ESTADÍSTICAS.",
                        "CORRIGE LO QUE EMPEORÓ.",
                    ],
                    'EMERGENCIA' => [
                        "PROMOCIONA Y PRODUCE.",
                        "CAMBIA TU FORMA DE ACTUAR.",
                        "ECONOMIZA.",
                        "PREPÁRATE PARA DAR SERVICIO.",
                    ],
                    'PELIGRO' => [
                        "ROMPE HÁBITOS NORMALES.",
                        "RESUELVE EL PELIGRO.",
                        "AUTODISCIPLINA.",
                        "REORGANIZA TU VIDA.",
                    ],
                    'INEXISTENCIA' => [
                        "ENCUENTRA UNA LÍNEA DE COMUNICACIÓN.",
                        "DASE A CONOCER.",
                        "DESCUBRE LO QUE NECESITAN.",
                        "PRODÚCELO.",
                    ],
                ];

                $cond = strtoupper(trim((string)($historico['condicion'] ?? '')));
                $expectedQuestions = $questionsByCondition[$cond] ?? [];

                $rawAnswers = $historico['preguntas'] ?? [];
                $answersList = [];

                if (is_array($rawAnswers)) {
                    foreach ($rawAnswers as $v) {
                        if (is_array($v)) {
                            $answersList[] = trim(implode(' ', array_map('strval', $v)));
                        } else {
                            $answersList[] = (string)$v;
                        }
                    }
                }
                ?>

                <?php if (empty($expectedQuestions)): ?>
                    <div class="hp-empty">
                        No hay plantilla de preguntas para la condición:
                        <b><?= e($cond !== '' ? $cond : '-') ?></b>.
                    </div>

                    <?php if (!empty($answersList)): ?>
                        <div class="hp-divider"></div>
                        <div class="text-muted small mb-2">
                            Se encontraron respuestas guardadas:
                        </div>

                        <div class="hp-questions-grid">
                            <?php foreach ($answersList as $index => $ans): ?>
                                <div class="hp-question-card">
                                    <div class="hp-question-title">Respuesta <?= $index + 1 ?></div>
                                    <div class="hp-question-answer">
                                        <b><?= e($ans) ?></b>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <div class="text-muted small mb-3">
                        Condición detectada: <b><?= e($cond) ?></b>
                    </div>

                    <div class="hp-questions-grid">
                        <?php foreach ($expectedQuestions as $idx => $questionText): ?>
                            <?php $answer = $answersList[$idx] ?? ''; ?>
                            <div class="hp-question-card">
                                <div class="hp-question-title"><?= e($questionText) ?></div>
                                <div class="hp-question-answer">
                                    <?php if ($answer !== ''): ?>
                                        <b><?= e($answer) ?></b>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <!-- TAREAS DE LA SEMANA -->
        <div class="card hp-card mb-4">
            <div class="card-body">
                <div class="hp-section-title mb-3">
                    <span>Tareas de la semana</span>
                    <small>Rango consultado</small>
                </div>

                <?php
                $urgentes       = $tasksPack['urgentes'] ?? [];
                $pendientes     = $tasksPack['pendientes'] ?? [];
                $ordenesMi      = $tasksPack['ordenesMi'] ?? [];
                $ordenesJuniors = $tasksPack['ordenesJuniors'] ?? [];
                $start          = $tasksPack['start'] ?? '';
                $end            = $tasksPack['end'] ?? '';
                ?>

                <div class="text-muted small mb-3">
                    Rango calculado: <b><?= e($start) ?></b> → <b><?= e($end) ?></b>
                </div>

                <!-- URGENTES -->
                <div class="card hp-card hp-task-section-danger mb-3">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Actividades urgentes</span>
                            <small><?= count($urgentes) ?> registro(s)</small>
                        </div>

                        <?php if (empty($urgentes)): ?>
                            <div class="hp-empty">No hay actividades urgentes en esta semana.</div>
                        <?php else: ?>
                            <div class="hp-task-list">
                                <?php foreach ($urgentes as $t): ?>
                                    <div class="hp-task-row">
                                        <div class="hp-task-left">
                                            <p class="hp-task-title"><?= e($t['titulo'] ?? '') ?></p>
                                            <?php if (!empty($t['descripcion'])): ?>
                                                <p class="hp-task-desc"><?= e($t['descripcion']) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="hp-task-meta">
                                            <span class="hp-badge hp-badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                            <span class="hp-badge <?= e($getTaskStatusClass($t)) ?>"><?= e($t['nombre_estado'] ?? '') ?></span>
                                            <span class="hp-badge hp-badge-urgent">URGENTE</span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_inicio'] ?? '')) ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_fin'] ?? '-')) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PENDIENTES -->
                <div class="card hp-card mb-3">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Actividades pendientes</span>
                            <small><?= count($pendientes) ?> registro(s)</small>
                        </div>

                        <?php if (empty($pendientes)): ?>
                            <div class="hp-empty">No hay actividades pendientes en esta semana.</div>
                        <?php else: ?>
                            <div class="hp-task-list">
                                <?php foreach ($pendientes as $t): ?>
                                    <div class="hp-task-row">
                                        <div class="hp-task-left">
                                            <p class="hp-task-title"><?= e($t['titulo'] ?? '') ?></p>
                                            <?php if (!empty($t['descripcion'])): ?>
                                                <p class="hp-task-desc"><?= e($t['descripcion']) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="hp-task-meta">
                                            <span class="hp-badge hp-badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                            <span class="hp-badge <?= e($getTaskStatusClass($t)) ?>"><?= e($t['nombre_estado'] ?? '') ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_inicio'] ?? '')) ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_fin'] ?? '-')) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ORDENES MI -->
                <div class="card hp-card mb-3">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Órdenes que debo cumplir</span>
                            <small><?= count($ordenesMi) ?> registro(s)</small>
                        </div>

                        <?php if (empty($ordenesMi)): ?>
                            <div class="hp-empty">No hay órdenes asignadas al usuario en esta semana.</div>
                        <?php else: ?>
                            <div class="hp-task-list">
                                <?php foreach ($ordenesMi as $t): ?>
                                    <div class="hp-task-row">
                                        <div class="hp-task-left">
                                            <p class="hp-task-title"><?= e($t['titulo'] ?? '') ?></p>
                                            <?php if (!empty($t['descripcion'])): ?>
                                                <p class="hp-task-desc"><?= e($t['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <div class="hp-task-extra">
                                                Área: <b><?= e($t['nombre_area'] ?? '-') ?></b>
                                            </div>
                                        </div>

                                        <div class="hp-task-meta">
                                            <span class="hp-badge hp-badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                            <span class="hp-badge <?= e($getTaskStatusClass($t)) ?>"><?= e($t['nombre_estado'] ?? '') ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_inicio'] ?? '')) ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_fin'] ?? '-')) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ORDENES JUNIORS -->
                <div class="card hp-card">
                    <div class="card-body">
                        <div class="hp-section-title mb-3">
                            <span>Órdenes que deben realizar sus juniors</span>
                            <small><?= count($ordenesJuniors) ?> registro(s)</small>
                        </div>

                        <?php if (empty($ordenesJuniors)): ?>
                            <div class="hp-empty">No hay órdenes asignadas por este usuario a terceros en esta semana.</div>
                        <?php else: ?>
                            <div class="hp-task-list">
                                <?php foreach ($ordenesJuniors as $t): ?>
                                    <div class="hp-task-row">
                                        <div class="hp-task-left">
                                            <p class="hp-task-title"><?= e($t['titulo'] ?? '') ?></p>
                                            <?php if (!empty($t['descripcion'])): ?>
                                                <p class="hp-task-desc"><?= e($t['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <div class="hp-task-extra">
                                                Asignado a:
                                                <b><?= e(trim(($t['asignado_a_apellidos'] ?? '') . ' ' . ($t['asignado_a_nombres'] ?? ''))) ?></b>
                                                — Área:
                                                <b><?= e($t['nombre_area'] ?? '-') ?></b>
                                            </div>
                                        </div>

                                        <div class="hp-task-meta">
                                            <span class="hp-badge hp-badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                            <span class="hp-badge <?= e($getTaskStatusClass($t)) ?>"><?= e($t['nombre_estado'] ?? '') ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_inicio'] ?? '')) ?></span>
                                            <span class="hp-badge hp-badge-date"><?= e($fmtDateTime($t['fecha_fin'] ?? '-')) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>