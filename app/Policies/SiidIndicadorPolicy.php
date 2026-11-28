<?php

namespace App\Policies;

use App\Models\SiidIndicador;
use App\Models\User;

class SiidIndicadorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewSiidCatalog();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiidIndicador $siidIndicador): bool
    {
        return $user->canViewSiidCatalog();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiidIndicador $siidIndicador): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiidIndicador $siidIndicador): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SiidIndicador $siidIndicador): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SiidIndicador $siidIndicador): bool
    {
        return false;
    }
}
