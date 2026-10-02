<?php

namespace App\Models;

use App\Enums\TipoFocalizacion;
use App\Models\Concerns\PerteneceADependencia;
use Database\Factories\ProyectoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyecto de inversión identificado por su BPIN. Un proyecto puede pertenecer a varias dependencias;
 * una de ellas se marca como responsable principal.
 */
class Proyecto extends Model
{
    /** @use HasFactory<ProyectoFactory> */
    use HasFactory, PerteneceADependencia, SoftDeletes;

    protected $table = 'proyectos';

    protected $fillable = [
        'bpin',
        'nombre',
        'tipo_focalizacion',
        'municipio_id',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_focalizacion' => TipoFocalizacion::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  list<int>  $dependenciaIds
     */
    public function filtrarPorDependencias(Builder $query, array $dependenciaIds): void
    {
        $query->whereHas('dependencias', fn (Builder $dependencias) => $dependencias->whereIn('dependencias.id', $dependenciaIds));
    }

    public function dependencias(): BelongsToMany
    {
        return $this->belongsToMany(Dependencia::class, 'dependencia_proyecto')
            ->withPivot(['es_responsable_principal', 'origen'])
            ->withTimestamps();
    }

    public function metasProducto(): BelongsToMany
    {
        return $this->belongsToMany(MetaProducto::class, 'meta_producto_proyecto')->withTimestamps();
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }

    public function techos(): HasMany
    {
        return $this->hasMany(Techo::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteProyecto::class);
    }

    public function requiereFocalizacionMensual(): bool
    {
        return $this->tipo_focalizacion?->requiereFocalizacionMensual() ?? false;
    }

    public function etiqueta(): string
    {
        return $this->bpin.' — '.$this->nombre;
    }

    /**
     * Proyectos de una dependencia concreta (útil para Gerencia, que no está filtrada por el scope global).
     */
    public function scopeDeDependencia(Builder $query, int $dependenciaId): void
    {
        $query->whereHas('dependencias', fn (Builder $dependencias) => $dependencias->where('dependencias.id', $dependenciaId));
    }
}
