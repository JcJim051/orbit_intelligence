<?php

namespace App\Policies;

use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\User;

class CapaSincronizacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CapaSincronizacion $sincronizacion): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CapaSincronizacion $sincronizacion): bool
    {
        return false;
    }

    public function delete(User $user, CapaSincronizacion $sincronizacion): bool
    {
        return false;
    }
}
