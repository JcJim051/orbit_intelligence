<?php

namespace App\Policies;

use App\Models\Proyecto;
use App\Models\User;

class ProyectoPolicy
{
    public function view(User $user, Proyecto $proyecto): bool
    {
        if ($user->veTodosLosSectores()) {
            return true;
        }

        return $proyecto->dependencias()->whereIn('dependencias.id', $user->dependenciaIdsAsignadas())->exists();
    }
}
