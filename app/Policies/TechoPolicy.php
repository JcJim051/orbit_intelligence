<?php

namespace App\Policies;

use App\Models\Dependencia;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Models\User;

class TechoPolicy
{
    public function view(User $user, Techo $techo): bool
    {
        return $user->veTodosLosSectores() || $user->perteneceADependencia($techo->dependencia_id);
    }

    /**
     * El techo sale de la pasiva. Solo la Gerencia puede hacer un ajuste excepcional, con motivo y soporte.
     */
    public function ajustar(User $user, Techo $techo): bool
    {
        return $user->gestionaReporteSectorial() && ! $techo->seguimiento->estaCerrado();
    }

    /**
     * El administrador técnico y el enlace del sector corrigen la fuente y sus cuatro valores.
     */
    public function crearFuente(User $user, Seguimiento $seguimiento, Dependencia $dependencia): bool
    {
        return $this->puedeCorregir($user, $dependencia->id, $seguimiento);
    }

    public function corregirFuente(User $user, Techo $techo): bool
    {
        return $this->puedeCorregir($user, $techo->dependencia_id, $techo->seguimiento);
    }

    private function puedeCorregir(User $user, int $dependenciaId, Seguimiento $seguimiento): bool
    {
        if ($seguimiento->estaCerrado() || ! $user->corrigeFuentesDeTecho()) {
            return false;
        }

        return $user->isAdmin() || $user->perteneceADependencia($dependenciaId);
    }
}
