<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\Suelos\Adjunto;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\EstudioValidacionHistorial;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<int>|null */
    private ?array $dependenciaIdsCache = null;

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
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::ManagementSupport, UserRole::OdsReviewer, UserRole::OdsValidator], true);
    }

    public function canReviewOdsIndicators(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::ManagementSupport, UserRole::SiidManager, UserRole::Reviewer, UserRole::OdsReviewer, UserRole::OdsValidator], true);
    }

    public function isDedicatedOdsReviewer(): bool
    {
        return in_array($this->role, [UserRole::OdsReviewer, UserRole::OdsValidator], true);
    }

    public function mustSeeOnlyAssignedOdsReviews(): bool
    {
        return $this->role === UserRole::OdsReviewer;
    }

    public function canConfirmOdsIndicatorRelations(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::OdsValidator], true);
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

    /**
     * Dependencias (sectores) a las que pertenece el usuario para el reporte mensual sectorial.
     */
    public function dependencias(): BelongsToMany
    {
        return $this->belongsToMany(Dependencia::class)->withTimestamps();
    }

    /**
     * Administración, Gerencia (gerente, apoyo y gestor SIID) y revisores ven todos los sectores.
     */
    public function veTodosLosSectores(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::ManagementSupport, UserRole::SiidManager, UserRole::Reviewer], true);
    }

    /**
     * Crea seguimientos, carga pasivas, ajusta techos, aprueba, devuelve y cierra cortes.
     */
    public function gestionaReporteSectorial(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Manager, UserRole::SiidManager], true);
    }

    /**
     * @return list<int>
     */
    public function dependenciaIdsAsignadas(): array
    {
        return $this->dependenciaIdsCache ??= $this->dependencias()
            ->pluck('dependencias.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function perteneceADependencia(int $dependenciaId): bool
    {
        return in_array($dependenciaId, $this->dependenciaIdsAsignadas(), true);
    }

    public function olvidarDependenciasAsignadas(): void
    {
        $this->dependenciaIdsCache = null;
    }

    public function analisisAreas(): HasMany
    {
        return $this->hasMany(AnalisisArea::class);
    }

    public function estudiosSuelosCargados(): HasMany
    {
        return $this->hasMany(EstudioSuelos::class, 'cargado_por');
    }

    public function estudiosSuelosRevisados(): HasMany
    {
        return $this->hasMany(EstudioSuelos::class, 'revisado_por');
    }

    public function adjuntosSuelos(): HasMany
    {
        return $this->hasMany(Adjunto::class, 'cargado_por');
    }

    public function historialValidacionSuelos(): HasMany
    {
        return $this->hasMany(EstudioValidacionHistorial::class);
    }
}
