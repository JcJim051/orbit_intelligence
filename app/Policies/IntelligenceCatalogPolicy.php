<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IntelligenceCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageIntelligenceCatalogs();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->canManageIntelligenceCatalogs();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->canManageIntelligenceCatalogs();
    }

    public function export(User $user): bool
    {
        return $user->canManageIntelligenceCatalogs();
    }

    public function import(User $user): bool
    {
        return $user->canManageIntelligenceCatalogs();
    }
}
