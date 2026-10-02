<?php

namespace App\Policies;

use App\Models\Seguimiento;
use App\Models\User;

class SeguimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->veTodosLosSectores() || $user->dependenciaIdsAsignadas() !== [];
    }

    public function view(User $user, Seguimiento $seguimiento): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->gestionaReporteSectorial();
    }

    public function update(User $user, Seguimiento $seguimiento): bool
    {
        return $user->gestionaReporteSectorial() && ! $seguimiento->estaCerrado();
    }

    public function cargarPasiva(User $user, Seguimiento $seguimiento): bool
    {
        return $user->gestionaReporteSectorial() && ! $seguimiento->estaCerrado();
    }

    public function cerrar(User $user, Seguimiento $seguimiento): bool
    {
        return $user->gestionaReporteSectorial() && ! $seguimiento->estaCerrado();
    }
}
