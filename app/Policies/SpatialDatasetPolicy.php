<?php

namespace App\Policies;

use App\Models\SpatialDataset;
use App\Models\User;

class SpatialDatasetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function view(User $user, SpatialDataset $spatialDataset): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SpatialDataset $spatialDataset): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, SpatialDataset $spatialDataset): bool
    {
        return $user->canApproveSpatialPublication();
    }
}
