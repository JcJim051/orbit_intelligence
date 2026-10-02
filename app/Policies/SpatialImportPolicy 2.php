<?php

namespace App\Policies;

use App\Models\SpatialImport;
use App\Models\User;

class SpatialImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SpatialImport $spatialImport): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SpatialImport $spatialImport): bool
    {
        return $user->isAdmin();
    }
}
