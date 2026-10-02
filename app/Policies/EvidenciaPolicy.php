<?php

namespace App\Policies;

use App\Models\Evidencia;
use App\Models\User;

class EvidenciaPolicy
{
    public function view(User $user, Evidencia $evidencia): bool
    {
        return $user->veTodosLosSectores() || $user->perteneceADependencia($evidencia->avanceFisico->reporte->dependencia_id);
    }

    public function delete(User $user, Evidencia $evidencia): bool
    {
        $reporte = $evidencia->avanceFisico->reporte;

        return $user->perteneceADependencia($reporte->dependencia_id) && $reporte->esEditablePorSector();
    }
}
