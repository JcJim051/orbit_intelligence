<?php

namespace App\Policies;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\User;

class AnalisisAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function view(User $user, AnalisisArea $analisis): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function update(User $user, AnalisisArea $analisis): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AnalisisArea $analisis): bool
    {
        return $user->isAdmin();
    }
}
