<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;

class Metricas extends Controller
{
    public function cumplimiento()
    {
        $session = session();

        // ✅ Solo usuario logueado
        if (!$session->get('logged_in')) {
            return $this->response->setStatusCode(401)->setJSON(['message' => 'No autorizado']);
        }

        // ✅ Usuario desde sesión (NO desde el cliente)
        $userId = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['message' => 'Sesión inválida']);
        }

        // month=YYYY-MM
        $month = (string) ($this->request->getGet('month') ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $this->response->setStatusCode(400)->setJSON(['message' => 'Parámetro month inválido (YYYY-MM)']);
        }

        // ===== DEMO backend: devuelve data "por usuario" (distinta según $userId) =====
        [$yy, $mm] = array_map('intval', explode('-', $month));
        $m = $mm - 1;

        $weds = $this->wednesdaysInMonth($yy, $m);

        $labels = [];
        $values = [];

        foreach ($weds as $endWed) {
            $startWed = $endWed->modify('-7 days');
            $labels[] = $this->fmtWed($startWed) . ' → ' . $this->fmtWed($endWed);

            // DEMO determinístico por fecha + usuario
            $values[] = $this->stablePercentForDateAndUser($endWed, $userId);
        }

        return $this->response->setJSON([
            'labels' => $labels,
            'values' => $values,
        ]);

        // ===== FUTURO BD (comentado) =====
        /*
        // 1) Validar sesión y obtener $userId (ya)
        // 2) Consultar BD filtrando por $userId y el mes (YYYY-MM)
        // 3) Retornar labels/values de ese usuario

        // $model = new \App\Models\MetricasModel();
        // $rows = $model->getCumplimientoSemanalPorUsuario($userId, $month);

        // return $this->response->setJSON([
        //   'labels' => array_column($rows, 'label'),
        //   'values' => array_column($rows, 'value'),
        // ]);
        */
    }

    // ===== Helpers DEMO =====

    private function wednesdaysInMonth(int $y, int $m): array
    {
        $monthStr = str_pad((string)($m + 1), 2, '0', STR_PAD_LEFT);
        $first = new \DateTimeImmutable("$y-$monthStr-01");
        $last  = $first->modify('last day of this month')->setTime(23, 59, 59);

        $targetDow = 3; // Mié (0=Dom)
        $firstDow  = (int) $first->format('w');
        $offset    = ($targetDow - $firstDow + 7) % 7;

        $cur = $first->modify("+$offset days");

        $arr = [];
        while ($cur <= $last) {
            $arr[] = $cur;
            $cur = $cur->modify('+7 days');
        }
        return $arr;
    }

    private function fmtWed(\DateTimeImmutable $d): string
    {
        $WD = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        $MO = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $dow = (int) $d->format('w');
        $day = (int) $d->format('j');
        $mon = (int) $d->format('n') - 1;

        return $WD[$dow] . ' ' . $day . ' ' . $MO[$mon];
    }

    private function stablePercentForDateAndUser(\DateTimeImmutable $d, int $userId): int
    {
        $key = ((int)$d->format('Y')) * 10000 + ((int)$d->format('n')) * 100 + ((int)$d->format('j'));
        $x = ($key ^ 0x9e3779b9) ^ ($userId * 2654435761);
        $x = ($x ^ ($x << 13)) & 0xFFFFFFFF;
        $x = ($x ^ ($x >> 17)) & 0xFFFFFFFF;
        $x = ($x ^ ($x << 5))  & 0xFFFFFFFF;

        return 45 + ($x % 51);
    }
}
