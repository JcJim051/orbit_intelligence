<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
        ];
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isReviewer(): bool
    {
        return in_array($this->role, [UserRole::Reviewer, UserRole::Admin], true);
    }

    public function canApproveSpatialPublication(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::ManagementSupport], true);
    }

    public function canAccessSpatialGovernance(): bool
    {
        return $this->isAdmin() || $this->canApproveSpatialPublication() || $this->role === UserRole::SiidManager;
    }

    public function canManageDashboards(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::SiidManager], true);
    }

    public function canApproveDashboards(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function canManageOpenDataSources(): bool
    {
        return $this->isAdmin() || $this->role === UserRole::SiidManager;
    }

    public function canAccessManagementGoals(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::ManagementSupport], true);
    }

    public function canAccessPlatformAdministration(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'management' && $this->active;
    }

    public function canManageIntelligenceCatalogs(): bool
    {
        return $this->isAdmin();
    }
}
