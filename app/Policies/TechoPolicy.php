<?php

namespace App\Policies;

use App\Models\Techo;
use App\Models\User;

class TechoPolicy
{
    public function view(User $user, Techo $techo): bool
    {
        return $user->puedeAccederDependenciaSeguimiento($techo->dependencia_id);
    }

    /**
     * El techo sale de la pasiva. Solo la Gerencia puede hacer un ajuste excepcional, con motivo y soporte.
     */
    public function ajustar(User $user, Techo $techo): bool
    {
        return $user->puedeRevisarDependenciaSeguimiento($techo->dependencia_id) && ! $techo->seguimiento->estaCerrado();
    }
}
