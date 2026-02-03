<?php

namespace App\Services;

use App\Models\ProgramacionEnlaceModel;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Servicio (UN SOLO HORARIO):
 * - usa el ÚLTIMO registro activo de la BD
 * - calcula si el Plan está habilitado AHORA
 * - usa timezone del registro; fallback America/Guayaquil
 *
 * DOW ISO: 1=Lun ... 7=Dom
 */
class ServicioHorarioEnlace
{
    protected ProgramacionEnlaceModel $scheduleModel;

    public function __construct(?ProgramacionEnlaceModel $scheduleModel = null)
    {
        $this->scheduleModel = $scheduleModel ?? new ProgramacionEnlaceModel();
    }

    /**
     * Retorna true si Plan está habilitado ahora.
     */
    public function isPlanEnabledNow(): bool
    {
        $row = $this->getSingleActiveRow();
        if (!$row) return false;

        return $this->isNowWithinScheduleRow($row);
    }

    /**
     * Trae el único horario activo (más reciente).
     */
    public function getSingleActiveRow(): ?array
    {
        $row = $this->scheduleModel
            ->where('active', true)
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return $row ?: null;
    }

    /**
     * ✅ Función dedicada: timezone segura.
     */
    protected function getTimezoneFromRow(array $row): DateTimeZone
    {
        $tzName = (string)($row['timezone'] ?? 'America/Guayaquil');
        $tzName = trim($tzName) !== '' ? $tzName : 'America/Guayaquil';

        try {
            return new DateTimeZone($tzName);
        } catch (Exception $e) {
            return new DateTimeZone('America/Guayaquil');
        }
    }

    /**
     * Evalúa la ventana semanal:
     * (enable_dow+enable_time) <= NOW < (disable_dow+disable_time)
     */
    protected function isNowWithinScheduleRow(array $row): bool
    {
        $tz = $this->getTimezoneFromRow($row);
        $now = new DateTimeImmutable('now', $tz);

        $enableDow = $this->normalizeIsoDow((int)($row['enable_dow'] ?? 1));
        $disableDow = $this->normalizeIsoDow((int)($row['disable_dow'] ?? 1));

        $enableMin = $this->timeToMinutes((string)($row['enable_time'] ?? '00:00:00'));
        $disableMin = $this->timeToMinutes((string)($row['disable_time'] ?? '00:00:00'));

        $enablePoint = (($enableDow - 1) * 1440) + $enableMin;
        $disablePoint = (($disableDow - 1) * 1440) + $disableMin;

        // Cruza semana
        if ($disablePoint <= $enablePoint) {
            $disablePoint += 7 * 1440;
        }

        $nowDow = (int)$now->format('N'); // 1..7
        $nowMin = ((int)$now->format('H') * 60) + (int)$now->format('i');
        $nowPoint = (($nowDow - 1) * 1440) + $nowMin;

        // Ajuste para comparar si la ventana cruza semana
        if ($nowPoint < $enablePoint) {
            $nowPoint += 7 * 1440;
        }

        return ($enablePoint <= $nowPoint) && ($nowPoint < $disablePoint);
    }

    protected function normalizeIsoDow(int $dow): int
    {
        return ($dow >= 1 && $dow <= 7) ? $dow : 1;
    }

    protected function timeToMinutes(string $time): int
    {
        $time = trim($time);
        if ($time === '') return 0;

        $parts = explode(':', $time);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;

        $h = max(0, min(23, $h));
        $m = max(0, min(59, $m));

        return ($h * 60) + $m;
    }
}
