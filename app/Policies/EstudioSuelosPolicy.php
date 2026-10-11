<?php

namespace App\Policies;

use App\Models\Suelos\EstudioSuelos;
use App\Models\User;

class EstudioSuelosPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function view(User $user, EstudioSuelos $estudio): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function update(User $user, EstudioSuelos $estudio): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function delete(User $user, EstudioSuelos $estudio): bool
    {
        return $user->isAdmin();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isAdmin();
    }

    public function validar(User $user, EstudioSuelos $estudio): bool
    {
        return $user->isReviewer() && $estudio->cargado_por !== $user->id;
    }
}
