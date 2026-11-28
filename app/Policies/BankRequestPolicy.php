<?php

namespace App\Policies;

use App\Models\BankRequest;
use App\Models\User;

class BankRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->puedeRevisarBancos();
    }

    public function view(User $user, BankRequest $bankRequest): bool
    {
        if (! $user->puedeRevisarBancos()) {
            return false;
        }

        if ($user->veTodosLosBancos()) {
            return true;
        }

        return $bankRequest->dependencia_id !== null
            && $user->perteneceADependencia((int) $bankRequest->dependencia_id);
    }

    public function update(User $user, BankRequest $bankRequest): bool
    {
        return $this->view($user, $bankRequest);
    }
}
