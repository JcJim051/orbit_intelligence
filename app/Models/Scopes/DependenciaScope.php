<?php

namespace App\Models\Scopes;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Compartimenta el reporte sectorial: un usuario de sector solo ve (y por lo tanto solo puede editar)
 * los registros de sus dependencias. Administración, Gerencia y revisores no se filtran.
 *
 * Sin usuario autenticado (consola, colas, seeders) no se filtra: esos procesos son de confianza.
 */
class DependenciaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->veTodosLosSectores()) {
            return;
        }

        $dependenciaIds = $user->dependenciaIdsAsignadas();

        if ($dependenciaIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        /** @var Model&PerteneceADependencia $model */
        $model->filtrarPorDependencias($builder, $dependenciaIds);
    }
}
