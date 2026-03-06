<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: reporte/historico_plan.php
 * =========================================================
 * ✅ IMPLEMENTADO:
 * - Preguntas en ORDEN según CONDICIÓN (AFLUENCIA, NORMAL, etc.)
 * - Respuestas SIN formato JSON (solo el valor, ej: "66.67%")
 * - Si falta respuesta => muestra "—"
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

// Label del scope (para mostrar arriba)
$scopeLabel = 'Usuario';
if (($scopeInfo['scope'] ?? '') === 'division') $scopeLabel = 'Jefe de División';
if (($scopeInfo['scope'] ?? '') === 'area')     $scopeLabel = 'Jefe de Área';
?>

<style>
    /* (COPIADO / ADAPTADO) de tu plan.php para mantener look & feel */
    .section-title {
        font-weight: 800;
        letter-spacing: .2px;
        color: #0B0B0B;
    }

    .section-title--invert {
        background: #FFFFFF;
        color: #0B0B0B;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(0, 0, 0, .12);
    }

    /* Render horizontal de tareas */
    .task-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border: 1px solid rgba(0, 0, 0, .12);
        border-radius: 12px;
        background: #fff;
    }

    .task-left {
        min-width: 240px;
        flex: 1 1 340px;
    }

    .task-title {
        font-weight: 800;
        margin: 0;
        color: #0B0B0B;
    }

    .task-desc {
        margin: 4px 0 0;
        color: #333;
        font-size: .92rem;
        line-height: 1.2rem;
    }

    .task-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
        flex: 0 0 auto;
    }

    .badge-soft {
        border-radius: 999px;
        padding: 6px 10px;
        font-size: .82rem;
        border: 1px solid rgba(0, 0, 0, .12);
        background: rgba(0, 0, 0, .03);
        font-weight: 700;
    }

    .badge-date {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    .box {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .12);
        border-radius: 14px;
        padding: 12px;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    @media(min-width: 992px) {
        .grid-2 {
            grid-template-columns: 1fr 1fr;
        }
    }

    .hr {
        height: 1px;
        background: rgba(0, 0, 0, .10);
        margin: 10px 0;
    }
</style>

<div class="container-fluid">

    <!-- ===================== HEADER ===================== -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="section-title mb-1">Histórico Plan de Batalla</h3>
            <div class="text-muted">
                Rol detectado: <b><?= e($scopeLabel) ?></b>
                <?php if (!empty($scopeInfo['division'])): ?>
                    — División: <b><?= e($scopeInfo['division']) ?></b>
                <?php endif; ?>
                <?php if (!empty($scopeInfo['area'])): ?>
                    — Área: <b><?= e($scopeInfo['area']) ?></b>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-dark" href="<?= base_url('reporte/plan_batalla') ?>">
                Volver
            </a>
            <?php if ($selectedWeek !== '' && $selectedUserId > 0): ?>
                <a class="btn btn-sm btn-dark"
                    target="_blank"
                    rel="noopener noreferrer"
                    href="<?= base_url('reporte/historico-plan/pdf?semana=' . urlencode($selectedWeek) . '&user=' . (int)$selectedUserId) ?>">
                    Generar PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== FILTROS ===================== -->
    <div class="box mb-3">
        <h5 class="section-title section-title--invert mb-2">Filtros</h5>

        <form method="get" action="<?= base_url('reporte/historico-plan') ?>" class="row g-2">
            <!-- Usuario -->
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

            <!-- Semana -->
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
                <button class="btn btn-dark w-100">Buscar</button>
            </div>
        </form>
    </div>

    <!-- ===================== CONTENIDO ===================== -->
    <?php if (!$historico): ?>
        <div class="box">
            <b>No hay histórico para esa semana / usuario.</b>
            <div class="text-muted mt-1">
                Prueba con otra semana o verifica que el usuario guardó su plan en esa fecha.
            </div>
        </div>
    <?php else: ?>

        <!-- RESUMEN HISTORICO -->
        <div class="box mb-3">
            <h5 class="section-title section-title--invert mb-2">Resumen del Histórico</h5>

            <div class="grid-2">
                <div class="box">
                    <div class="fw-bold mb-1">Usuario</div>
                    <div><?= e(($historico['apellidos'] ?? '') . ' ' . ($historico['nombres'] ?? '')) ?></div>
                    <div class="text-muted small">Cédula: <?= e($historico['cedula'] ?? '') ?></div>
                    <div class="hr"></div>

                    <div class="fw-bold mb-1">Organización</div>
                    <div class="text-muted small">
                        División: <b><?= e($historico['division_nombre'] ?? ($historico['nombre_division'] ?? '')) ?></b>
                        — Área: <b><?= e($historico['area_nombre'] ?? '') ?></b>
                    </div>
                    <div class="text-muted small">
                        Cargo: <?= e($historico['cargo_nombre'] ?? '-') ?>
                    </div>
                    <div class="text-muted small">
                        Jefe inmediato: <?= e($historico['jefe_inmediato'] ?? '-') ?>
                    </div>
                </div>

                <div class="box">
                    <div class="fw-bold mb-1">Semana</div>
                    <div>
                        <span class="badge-soft badge-date"><?= e($historico['semana'] ?? '') ?></span>
                    </div>

                    <div class="hr"></div>

                    <div class="fw-bold mb-1">Satisfacción</div>
                    <div style="font-size:1.1rem;">
                        <b><?= e($historico['satisfaccion'] ?? '0') ?>%</b>
                    </div>

                    <div class="hr"></div>

                    <div class="fw-bold mb-1">Estado / Condición</div>
                    <div class="text-muted">
                        Estado: <b><?= e($historico['estado'] ?? '-') ?></b> —
                        Condición: <b><?= e($historico['condicion'] ?? '-') ?></b>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- PREGUNTAS (SIN JSON) + ORDEN POR CONDICIÓN ✅ -->
        <!-- ===================================================== -->
        <div class="box mb-3">
            <h5 class="section-title section-title--invert mb-2">Detalle (Preguntas del Plan)</h5>

            <?php
            // -----------------------------
            // Preguntas por condición (orden fijo)
            // -----------------------------
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

            // -----------------------------
            // Condición actual
            // -----------------------------
            $cond = strtoupper(trim((string)($historico['condicion'] ?? '')));
            $expectedQuestions = $questionsByCondition[$cond] ?? [];

            // -----------------------------
            // Respuestas guardadas (preguntas_json ya decodificado en service)
            // Queremos SOLO valores, SIN JSON
            // -----------------------------
            $rawAnswers = $historico['preguntas'] ?? [];
            $answersList = [];

            if (is_array($rawAnswers)) {
                foreach ($rawAnswers as $v) {
                    // Si viene array dentro => lo aplanamos como texto
                    if (is_array($v)) {
                        $answersList[] = trim(implode(' ', array_map('strval', $v)));
                    } else {
                        $answersList[] = (string)$v;
                    }
                }
            }
            ?>

            <?php if (empty($expectedQuestions)): ?>
                <div class="text-muted">
                    No hay plantilla de preguntas para la condición:
                    <b><?= e($cond !== '' ? $cond : '-') ?></b>.
                </div>

                <?php if (!empty($answersList)): ?>
                    <div class="hr"></div>
                    <div class="text-muted small">
                        Se encontraron respuestas guardadas, mostrando solo valores:
                    </div>
                    <ul class="mt-2">
                        <?php foreach ($answersList as $ans): ?>
                            <li><b><?= e($ans) ?></b></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            <?php else: ?>

                <div class="text-muted small mb-2">
                    Condición: <b><?= e($cond) ?></b>
                </div>

                <div class="row g-2">
                    <?php foreach ($expectedQuestions as $idx => $questionText): ?>
                        <?php $answer = $answersList[$idx] ?? ''; ?>
                        <div class="col-12 col-lg-6">
                            <div class="box">
                                <div class="fw-bold mb-1"><?= e($questionText) ?></div>
                                <div class="text-muted" style="white-space:pre-wrap;">
                                    <?php if ($answer !== ''): ?>
                                        <b><?= e($answer) ?></b>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

        <!-- TAREAS DE LA SEMANA -->
        <div class="box mb-4">
            <h5 class="section-title section-title--invert mb-2">Tareas de la semana</h5>

            <?php
            $urgentes       = $tasksPack['urgentes'] ?? [];
            $pendientes     = $tasksPack['pendientes'] ?? [];
            $ordenesMi      = $tasksPack['ordenesMi'] ?? [];
            $ordenesJuniors = $tasksPack['ordenesJuniors'] ?? [];
            $start          = $tasksPack['start'] ?? '';
            $end            = $tasksPack['end'] ?? '';
            ?>

            <div class="text-muted small mb-2">
                Rango calculado: <b><?= e($start) ?></b> → <b><?= e($end) ?></b>
            </div>

            <!-- Secciones como tu Plan -->
            <div class="mb-3">
                <h6 class="section-title mb-2">ACTIVIDADES URGENTES</h6>
                <?php if (empty($urgentes)): ?>
                    <div class="text-muted">No hay actividades urgentes en esta semana.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($urgentes as $t): ?>
                            <div class="task-row">
                                <div class="task-left">
                                    <p class="task-title mb-0"><?= e($t['titulo'] ?? '') ?></p>
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <p class="task-desc"><?= e($t['descripcion']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="task-meta">
                                    <span class="badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                    <span class="badge-soft"><?= e($t['nombre_estado'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_inicio'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_fin'] ?? '-') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <h6 class="section-title mb-2">ACTIVIDADES PENDIENTES</h6>
                <?php if (empty($pendientes)): ?>
                    <div class="text-muted">No hay actividades pendientes en esta semana.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($pendientes as $t): ?>
                            <div class="task-row">
                                <div class="task-left">
                                    <p class="task-title mb-0"><?= e($t['titulo'] ?? '') ?></p>
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <p class="task-desc"><?= e($t['descripcion']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="task-meta">
                                    <span class="badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                    <span class="badge-soft"><?= e($t['nombre_estado'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_inicio'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_fin'] ?? '-') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <h6 class="section-title mb-2">ÓRDENES QUE DEBO CUMPLIR</h6>
                <?php if (empty($ordenesMi)): ?>
                    <div class="text-muted">No hay órdenes asignadas al usuario en esta semana.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($ordenesMi as $t): ?>
                            <div class="task-row">
                                <div class="task-left">
                                    <p class="task-title mb-0"><?= e($t['titulo'] ?? '') ?></p>
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <p class="task-desc"><?= e($t['descripcion']) ?></p>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1">
                                        Área: <b><?= e($t['nombre_area'] ?? '-') ?></b>
                                    </div>
                                </div>
                                <div class="task-meta">
                                    <span class="badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                    <span class="badge-soft"><?= e($t['nombre_estado'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_inicio'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_fin'] ?? '-') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h6 class="section-title mb-2">ÓRDENES QUE DEBEN REALIZAR SUS JUNIORS</h6>
                <?php if (empty($ordenesJuniors)): ?>
                    <div class="text-muted">No hay órdenes asignadas por este usuario a terceros en esta semana.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($ordenesJuniors as $t): ?>
                            <div class="task-row">
                                <div class="task-left">
                                    <p class="task-title mb-0"><?= e($t['titulo'] ?? '') ?></p>
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <p class="task-desc"><?= e($t['descripcion']) ?></p>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1">
                                        Asignado a: <b><?= e(trim(($t['asignado_a_apellidos'] ?? '') . ' ' . ($t['asignado_a_nombres'] ?? ''))) ?></b>
                                        — Área: <b><?= e($t['nombre_area'] ?? '-') ?></b>
                                    </div>
                                </div>
                                <div class="task-meta">
                                    <span class="badge-soft"><?= e($t['nombre_prioridad'] ?? '') ?></span>
                                    <span class="badge-soft"><?= e($t['nombre_estado'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_inicio'] ?? '') ?></span>
                                    <span class="badge-soft badge-date"><?= e($t['fecha_fin'] ?? '-') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>