<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProgramacionEnlaceModel;
use App\Services\ServicioHorarioEnlace;
use Config\Database;

/**
 * Controlador para UN SOLO HORARIO:
 * - index: muestra vista
 * - save: guarda horario (y deja solo 1 activo)
 * - status: devuelve enabled true/false (ruta interna, consulta BD)
 */
class HorarioPlan extends BaseController
{
    protected ProgramacionEnlaceModel $scheduleModel;
    protected ServicioHorarioEnlace $scheduleService;

    public function __construct()
    {
        $this->scheduleModel = new ProgramacionEnlaceModel();
        $this->scheduleService = new ServicioHorarioEnlace($this->scheduleModel);
    }

    public function index()
    {
        $row = $this->scheduleModel
            ->where('active', true)
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$row) {
            $row = $this->scheduleModel
                ->orderBy('updated_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();
        }

        return view('reporte/horario_plan', ['row' => $row]);
    }

    public function status()
    {
        $enabledNow = $this->scheduleService->isPlanEnabledNow();

        return $this->response->setJSON([
            'ok'      => true,
            'enabled' => $enabledNow,
            'ts'      => date('c'),
        ]);
    }

    public function save()
    {
        $payload = $this->request->getPost();
        $json = $this->request->getJSON(true);
        if (is_array($json) && !empty($json)) {
            $payload = array_merge($payload, $json);
        }

        $rules = [
            'enable_dow'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[7]',
            'enable_time'  => 'required',
            'disable_dow'  => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[7]',
            'disable_time' => 'required',
            'timezone'     => 'required|string',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'       => false,
                'message'  => 'Validación fallida',
                'errors'   => $this->validator->getErrors(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $data = [
            'id'           => !empty($payload['id']) ? (int)$payload['id'] : null,
            'enable_dow'   => (int)$payload['enable_dow'],
            'enable_time'  => (string)$payload['enable_time'],
            'disable_dow'  => (int)$payload['disable_dow'],
            'disable_time' => (string)$payload['disable_time'],
            'timezone'     => (string)$payload['timezone'],
            'active'       => !empty($payload['active']) ? true : false,
            'updated_at'   => date('Y-m-d H:i:sP'),
        ];

        $this->scheduleModel->save($data);

        $id = $data['id'] ?: (int)$this->scheduleModel->getInsertID();
        if (!$id) {
            $last = $this->scheduleModel->orderBy('id', 'DESC')->first();
            $id = $last ? (int)$last['id'] : 0;
        }

        // ✅ dejar un solo horario: desactivar los demás
        $db = Database::connect();
        $db->table('link_sna_schedule')
            ->where('id !=', $id)
            ->update([
                'active'     => false,
                'updated_at' => date('Y-m-d H:i:sP'),
            ]);

        $enabledNow = $this->scheduleService->isPlanEnabledNow();

        return $this->response->setJSON([
            'ok'       => true,
            'message'  => 'Horario guardado correctamente',
            'enabled'  => $enabledNow,
            'id'       => $id,
            'csrfHash' => csrf_hash(),
        ]);
    }
}
