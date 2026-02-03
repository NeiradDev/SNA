<?php

namespace App\Filters;

use App\Services\ServicioHorarioEnlace;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Bloquea /reporte/plan fuera de horario (consulta BD).
 */
class FiltroHorarioPlan implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $service = new ServicioHorarioEnlace();

        if (!$service->isPlanEnabledNow()) {
            $html = Services::renderer()->render('errores/horario_no_disponible');

            return Services::response()
                ->setStatusCode(403)
                ->setBody($html);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
