<?php

namespace App\Policies;

use App\Models\User;

class MicrositioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function export(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user, mixed $registro = null): bool
    {
        return $user->isAdmin();
    }
}
