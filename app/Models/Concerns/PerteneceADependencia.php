<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aplica DependenciaScope al modelo. Por defecto filtra por la columna dependencia_id;
 * los modelos hijos definen rutaDependencia() con la relación (en notación de puntos) que llega
 * a un modelo con dependencia_id, por ejemplo 'reporte' o 'avanceFisico.reporte'.
 */
trait PerteneceADependencia
{
    public static function bootPerteneceADependencia(): void
    {
        static::addGlobalScope(new DependenciaScope);
    }

    /**
     * @param  list<int>  $dependenciaIds
     */
    public function filtrarPorDependencias(Builder $query, array $dependenciaIds): void
    {
        $ruta = $this->rutaDependencia();

        if ($ruta === null) {
            $query->whereIn($this->qualifyColumn('dependencia_id'), $dependenciaIds);

            return;
        }

        $query->whereHas($ruta, fn (Builder $relacionado) => $relacionado->whereIn($relacionado->getModel()->qualifyColumn('dependencia_id'), $dependenciaIds));
    }

    protected function rutaDependencia(): ?string
    {
        return null;
    }

    /**
     * Consulta sin el filtro de sector. Úsese solo en procesos de Gerencia o de sistema.
     */
    public static function sinFiltroSectorial(): Builder
    {
        return static::query()->withoutGlobalScope(DependenciaScope::class);
    }
}
