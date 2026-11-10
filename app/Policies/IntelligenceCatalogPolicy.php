<?php

namespace App\Policies;

use App\Models\IndicadorProducto;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PoliticaPublicaDepartamental;
use App\Models\TipologiaInversion;
use App\Models\Trazador;
use App\Models\TrazadorCategoria;
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

    public function create(User $user, mixed $subject = null): bool
    {
        return $user->canManageIntelligenceCatalogs()
            && $this->canManageCatalog($user, $subject)
            && (! $this->isTipologia($subject) || $user->isAdmin());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->canManageIntelligenceCatalogs()
            && $this->canManageCatalog($user, $record)
            && (! $record instanceof TipologiaInversion || $user->isAdmin());
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function export(User $user, mixed $subject = null): bool
    {
        return $user->canManageIntelligenceCatalogs()
            && $this->canManageCatalog($user, $subject)
            && (! $this->isTipologia($subject) || $user->isAdmin());
    }

    public function import(User $user, mixed $subject = null): bool
    {
        return $user->canManageIntelligenceCatalogs()
            && $this->canManageCatalog($user, $subject)
            && (! $this->isTipologia($subject) || $user->isAdmin());
    }

    private function isTipologia(mixed $subject): bool
    {
        return $subject === TipologiaInversion::class || $subject instanceof TipologiaInversion;
    }

    private function canManageCatalog(User $user, mixed $subject): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Laravel removes the class name before invoking a policy's create,
        // export or import method. Those abilities therefore arrive without
        // a subject; the route-level middleware restricts the catalogs that
        // the investment reviewer may manage.
        if ($subject === null) {
            return $user->isInvestmentReviewer();
        }

        return in_array($subject, [
            MetaProducto::class,
            IndicadorProducto::class,
            IndicadorResultado::class,
            MetaResultado::class,
            Trazador::class,
            TrazadorCategoria::class,
            PoliticaPublicaDepartamental::class,
        ], true)
            || $subject instanceof MetaProducto
            || $subject instanceof IndicadorProducto
            || $subject instanceof IndicadorResultado
            || $subject instanceof MetaResultado
            || $subject instanceof Trazador
            || $subject instanceof TrazadorCategoria
            || $subject instanceof PoliticaPublicaDepartamental;
    }
}
