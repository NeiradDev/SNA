<?php

use App\Services\ServicioHorarioEnlace;

/**
 * Visibilidad inicial del link (consulta BD).
 */
function isPlanEnabled(): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $service = new ServicioHorarioEnlace();
    $cached = $service->isPlanEnabledNow();

    return $cached;
}
