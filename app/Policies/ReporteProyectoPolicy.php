<?php

namespace App\Policies;

use App\Enums\EstadoReporteProyecto;
use App\Models\ReporteProyecto;
use App\Models\User;

class ReporteProyectoPolicy
{
    public function view(User $user, ReporteProyecto $reporte): bool
    {
        return $user->veTodosLosSectores() || $user->perteneceADependencia($reporte->dependencia_id);
    }

    /**
     * Diligenciar actividades, ejecución, avance, evidencias y focalización, y enviar: solo el sector dueño,
     * mientras el reporte esté en borrador o devuelto y el seguimiento abierto.
     */
    public function update(User $user, ReporteProyecto $reporte): bool
    {
        return ($user->gestionaReporteSectorial() || $user->perteneceADependencia($reporte->dependencia_id))
            && $reporte->esEditablePorSector();
    }

    public function revisar(User $user, ReporteProyecto $reporte): bool
    {
        return $user->gestionaReporteSectorial()
            && $reporte->estado === EstadoReporteProyecto::Reportado
            && ! $reporte->seguimiento->estaCerrado();
    }
}
