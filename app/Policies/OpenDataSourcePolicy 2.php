<?php

namespace App\Policies;

use App\Models\OpenDataSource;
use App\Models\User;

class OpenDataSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOpenDataSources() || $user->canApproveSpatialPublication();
    }

    public function view(User $user, OpenDataSource $openDataSource): bool
    {
        return $user->canApproveSpatialPublication()
            || $user->isAdmin()
            || $openDataSource->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canManageOpenDataSources();
    }

    public function update(User $user, OpenDataSource $openDataSource): bool
    {
        return $user->isAdmin() || $openDataSource->owner_id === $user->id;
    }
}
