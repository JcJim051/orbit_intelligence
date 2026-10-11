<?php

namespace App\Policies;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\User;

class CapaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Capa $capa): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Capa $capa): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Capa $capa): bool
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

    public function sincronizar(User $user, Capa $capa): bool
    {
        return $user->isAdmin();
    }
}
