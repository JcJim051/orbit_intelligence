<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GeoViewer;
use App\Models\User;

class GeoViewerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSpatialGovernance();
    }

    public function view(User $user, GeoViewer $geoViewer): bool
    {
        if ($user->role !== UserRole::SiidManager) {
            return $user->canAccessSpatialGovernance();
        }

        return $geoViewer->owner_id === $user->id
            || $geoViewer->collaborators()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->canManageOpenDataSources();
    }

    public function update(User $user, GeoViewer $geoViewer): bool
    {
        return $geoViewer->canBeEditedBy($user);
    }

    public function publish(User $user, GeoViewer $geoViewer): bool
    {
        return $user->canApproveSpatialPublication();
    }
}
