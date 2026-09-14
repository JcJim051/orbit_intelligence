<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return $user->isAdmin() || $meeting->user_id === $user->id || ($user->isReviewer() && $meeting->reviewer_id === $user->id);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && $user->role !== UserRole::Member || $meeting->user_id === $user->id;
    }

    public function approve(User $user, Meeting $meeting): bool
    {
        return $user->isAdmin() || ($user->isReviewer() && $meeting->reviewer_id === $user->id);
    }
}
