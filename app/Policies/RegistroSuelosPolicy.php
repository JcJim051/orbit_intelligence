<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegistroSuelosPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function view(User $user, Model $registro): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function update(User $user, Model $registro): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }

    public function delete(User $user, Model $registro): bool
    {
        return $user->canAccessSpatialGovernance() || $user->isReviewer();
    }
}
