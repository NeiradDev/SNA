<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Services\PlanBatallaService;
use App\Services\TareaService;
use Config\Database;

class Reporte extends BaseController
{
    private PlanBatallaService $service;
    private UsuarioModel $usuarioModel;
    private TareaService $tareaService;
    private $db;

    public function __construct()
    {
        $this->service      = new PlanBatallaService();
        $this->usuarioModel = new UsuarioModel();
        $this->tareaService = new TareaService();
        $this->db           = Database::connect();
    }

    // =====================================================
    // CALCULAR SEMANA JUEVES → MIÉRCOLES (SEMANA ACTUAL)
    // =====================================================
    private function getSemanaActual(): array
    {
        $tz = new \DateTimeZone('America/Guayaquil');
        $today = new \DateTime('now', $tz);

        // Si hoy es JUE(4) o más, la semana inicia "este jueves"
        if ((int)$today->format('N') >= 4) {
            $inicio = clone $today;
            $inicio->modify('this thursday');
        } else {
            // Si hoy es LUN(1)-MIÉ(3), la semana inició "el jueves pasado"
            $inicio = clone $today;
            $inicio->modify('last thursday');
        }

        $inicio->setTime(0, 0, 0);

        $fin = clone $inicio;
        $fin->modify('+6 days'); // jueves + 6 = miércoles
        $fin->setTime(23, 59, 59);

        return [
            'inicio' => $inicio,
            'fin'    => $fin
        ];
    }

    // =====================================================
    // CALCULAR SEMANA SIGUIENTE (JUEVES → MIÉRCOLES)
    // =====================================================
    private function getSemanaSiguiente(array $semanaActual = null): array
    {
        // Si no me mandan la semana actual, la calculo
        $semanaActual = $semanaActual ?? $this->getSemanaActual();

        // Clono para no alterar objetos originales
        $inicio = clone $semanaActual['inicio'];
        $fin    = clone $semanaActual['fin'];

        // Siguiente semana = +7 días
        $inicio->modify('+7 days');
        $fin->modify('+7 days');

        // Seguridad: mantener horas correctas
        $inicio->setTime(0, 0, 0);
        $fin->setTime(23, 59, 59);

        return [
            'inicio' => $inicio,
            'fin'    => $fin
        ];
    }

    // =====================================================
    // VERIFICAR SI YA COMPLETÓ PLAN SEMANAL
    // =====================================================
    private function yaCompletoSemana(int $idUser): bool
    {
        $range = $this->service->getCurrentWeekRange();
        $semana = $range['semana'];

        return $this->db->table('public.historico')
            ->where('id_user', $idUser)
            ->where('semana', $semana)
            ->countAllResults() > 0;
    }

    // =====================================================
    // CALCULAR PRÓXIMO MIÉRCOLES 00:00
    // =====================================================
    private function proximoMiercoles(): string
    {
        $tz = new \DateTimeZone('America/Guayaquil');
        $date = new \DateTime('next wednesday', $tz);
        $date->setTime(0, 0, 0);

        // Formato ISO para JS
        return $date->format('Y-m-d H:i:s');
    }

    // =====================================================
    // QUERY BASE CON JOIN A ESTADO
    // =====================================================
    private function baseQueryTareas()
    {
        return $this->db->table('public.tareas t')
            ->select('
                t.*,
                e.nombre_estado,
                e.id_estado_tarea
            ')
            ->join('public.estado_tarea e', 'e.id_estado_tarea = t.id_estado_tarea', 'left');
    }

    // =====================================================
    // FORMATEAR FECHAS
    // =====================================================
    private function formatearFechas(array $tareas): array
    {
        foreach ($tareas as &$t) {
            $t['fecha_inicio_fmt'] = !empty($t['fecha_inicio'])
                ? (new \DateTime($t['fecha_inicio']))->format('d/m/Y')
                : null;

            $t['fecha_fin_fmt'] = !empty($t['fecha_fin'])
                ? (new \DateTime($t['fecha_fin']))->format('d/m/Y')
                : null;
        }

        return $tareas;
    }

    // =====================================================
    // VISTA PLAN DE BATALLA
    // =====================================================
    public function plan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');

        $perfil       = $this->service->getUserProfile($idUser);
        $satisfaccion = $this->tareaService->getSatisfaccionActual($idUser);

        // 🔒 Verificar si ya completó
        $planCompletado = $this->yaCompletoSemana($idUser);
        $proximoMiercoles = $this->proximoMiercoles();

        // ¿Tiene juniors?
        $tieneJuniors = $this->usuarioModel
            ->where('id_supervisor', $idUser)
            ->countAllResults() > 0;

        // =========================
        // RANGOS DE FECHA
        // =========================
        $semana = $this->getSemanaActual();
        $semanaSiguiente = $this->getSemanaSiguiente($semana);

        $inicioSemana = $semana['inicio']->format('Y-m-d H:i:s');
        $finSemana    = $semana['fin']->format('Y-m-d H:i:s');

        $inicioSig = $semanaSiguiente['inicio']->format('Y-m-d H:i:s');
        $finSig    = $semanaSiguiente['fin']->format('Y-m-d H:i:s');

        // =========================
        // ESTA SEMANA (IGUAL QUE YA TENÍAS)
        // =========================
        $urgentes = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.id_prioridad', 4)
            ->where('t.fecha_inicio >=', $inicioSemana)
            ->where('t.fecha_inicio <=', $finSemana)
            ->get()->getResultArray();

        $pendientes = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.asignado_por', $idUser)
            ->where('t.fecha_inicio >=', $inicioSemana)
            ->where('t.fecha_inicio <=', $finSemana)
            ->get()->getResultArray();

        $ordenesMias = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.asignado_por !=', $idUser)
            ->where('t.fecha_inicio >=', $inicioSemana)
            ->where('t.fecha_inicio <=', $finSemana)
            ->get()->getResultArray();

        $ordenesJuniors = [];
        if ($tieneJuniors) {
            $ordenesJuniors = $this->baseQueryTareas()
                ->where('t.asignado_por', $idUser)
                ->where('t.asignado_a !=', $idUser)
                ->where('t.fecha_inicio >=', $inicioSemana)
                ->where('t.fecha_inicio <=', $finSemana)
                ->get()->getResultArray();
        }

        // =========================
        // SIGUIENTE SEMANA (AQUÍ ESTÁ LA CLAVE)
        // =========================
        $urgentesSiguiente = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.id_prioridad', 4)
            ->where('t.fecha_inicio >=', $inicioSig)
            ->where('t.fecha_inicio <=', $finSig)
            ->get()->getResultArray();

        $pendientesSiguiente = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.asignado_por', $idUser)
            ->where('t.fecha_inicio >=', $inicioSig)
            ->where('t.fecha_inicio <=', $finSig)
            ->get()->getResultArray();

        $ordenesMiasSiguiente = $this->baseQueryTareas()
            ->where('t.asignado_a', $idUser)
            ->where('t.asignado_por !=', $idUser)
            ->where('t.fecha_inicio >=', $inicioSig)
            ->where('t.fecha_inicio <=', $finSig)
            ->get()->getResultArray();

        // ✅ ESTA ES LA VARIABLE QUE QUIERES VER EN LA VISTA
        $ordenesJuniorsSiguiente = [];
        if ($tieneJuniors) {
            $ordenesJuniorsSiguiente = $this->baseQueryTareas()
                ->where('t.asignado_por', $idUser)
                ->where('t.asignado_a !=', $idUser)
                ->where('t.fecha_inicio >=', $inicioSig)
                ->where('t.fecha_inicio <=', $finSig)
                ->get()->getResultArray();
        }

        // Formateo fechas (para que la vista use igual estilo)
        $urgentes             = $this->formatearFechas($urgentes);
        $pendientes           = $this->formatearFechas($pendientes);
        $ordenesMias          = $this->formatearFechas($ordenesMias);
        $ordenesJuniors       = $this->formatearFechas($ordenesJuniors);

        $urgentesSiguiente       = $this->formatearFechas($urgentesSiguiente);
        $pendientesSiguiente     = $this->formatearFechas($pendientesSiguiente);
        $ordenesMiasSiguiente    = $this->formatearFechas($ordenesMiasSiguiente);
        $ordenesJuniorsSiguiente = $this->formatearFechas($ordenesJuniorsSiguiente);

        // ✅ Enviar TODO a la vista
        return view('reporte/plan', [
            'perfil'                 => $perfil,
            'satisfaccion'           => $satisfaccion,

            'tieneJuniors'           => $tieneJuniors,

            // Semana actual
            'urgentes'               => $urgentes,
            'pendientes'             => $pendientes,
            'ordenesMias'            => $ordenesMias,
            'ordenesJuniors'         => $ordenesJuniors,
            'semana'                 => $semana,

            // Semana siguiente
            'urgentesSiguiente'       => $urgentesSiguiente,
            'pendientesSiguiente'     => $pendientesSiguiente,
            'ordenesMiasSiguiente'    => $ordenesMiasSiguiente,

            // ✅ ESTA ES LA QUE TE FALTABA
            'ordenesJuniorsSiguiente' => $ordenesJuniorsSiguiente,
            'semanaSiguiente'         => $semanaSiguiente,

            // Control de bloqueo
            'planCompletado'         => $planCompletado,
            'proximoMiercoles'       => $proximoMiercoles
        ]);
    }

    // =====================================================
    // GUARDAR PLAN
    // =====================================================
    public function storePlan()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        $idUser = (int) session()->get('id_user');

        // 🔒 Evitar doble guardado
        if ($this->yaCompletoSemana($idUser)) {
            return redirect()
                ->to(site_url('reporte/plan'))
                ->with('success',
                    'Ya completaste tu Plan de Batalla semanal. Se volverá a habilitar el próximo miércoles desde las 00:00 hasta las 23:59.'
                );
        }

        $post = (array) $this->request->getPost();

        $res = $this->service->savePlanToHistorico($idUser, $post);

        if (!$res['ok']) {
            return redirect()->back()->withInput()
                ->with('errors', $res['errors'] ?? [
                    'general' => 'No se pudo guardar el plan.'
                ]);
        }

        return redirect()
            ->to(site_url('reporte/plan'))
            ->with('success',
                'Has completado tu Plan de Batalla semanal correctamente. Se volverá a habilitar el próximo miércoles desde las 00:00 hasta las 23:59.'
            );
    }
        public function Completado()
    {
        $modelo = new UsuarioModel();
        $data['usuarios'] = $modelo->completado();

        return view('reporte/completado', $data);
    }

    public function Completado()
    {
        $modelo = new UsuarioModel();
        $data['usuarios'] = $modelo->completado();

        return view('reporte/completado', $data);
    }
}
