<?php

namespace App\Policies;

use App\Models\Proyecto;
use App\Models\User;

class ProyectoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessManagementGoals();
    }

    public function create(User $user): bool
    {
        return $user->canEditInvestmentCatalogs();
    }

    public function view(User $user, Proyecto $proyecto): bool
    {
        if ($user->veTodosLosSectores() || $user->role === \App\Enums\UserRole::InvestmentReviewer) {
            return true;
        }

        return $proyecto->dependencias()->whereIn('dependencias.id', $user->dependenciaIdsAsignadas())->exists();
    }

    public function update(User $user, Proyecto $proyecto): bool
    {
        return $user->canEditInvestmentCatalogs();
    }

    public function delete(User $user, Proyecto $proyecto): bool
    {
        return $user->isAdmin();
    }
}
