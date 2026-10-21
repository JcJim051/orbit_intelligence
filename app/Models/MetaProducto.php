<?php

namespace App\Models;

use Database\Factories\MetaProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaProducto extends Model
{
    /** @use HasFactory<MetaProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'metas_producto';

    protected $fillable = [
        'codigo',
        'nombre',
        'indicador_producto',
        'subprograma_id',
        'sector_mga_id',
        'meta_resultado_id',
        'dependencia_id',
        'tipologia_inversion_id',
        'tipologia_observacion',
        'tipologia_clasificada_por',
        'tipologia_clasificada_at',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tipologia_clasificada_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MetaProducto $meta): void {
            if (! $meta->isDirty('tipologia_inversion_id')) {
                return;
            }

            if ($meta->tipologia_inversion_id === null) {
                $meta->tipologia_clasificada_por = null;
                $meta->tipologia_clasificada_at = null;

                return;
            }

            $user = auth()->user();
            $meta->tipologia_clasificada_por = $user?->name ?: $user?->email ?: 'Sistema';
            $meta->tipologia_clasificada_at = now();
        });
    }

    public function subprograma(): BelongsTo
    {
        return $this->belongsTo(PddSubprograma::class, 'subprograma_id');
    }

    public function sectorMga(): BelongsTo
    {
        return $this->belongsTo(SectorMga::class, 'sector_mga_id');
    }

    public function metaResultado(): BelongsTo
    {
        return $this->belongsTo(MetaResultado::class, 'meta_resultado_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function indicadorProducto(): HasOne
    {
        return $this->hasOne(IndicadorProducto::class, 'meta_producto_id');
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'meta_producto_proyecto')
            ->withPivot('regionaliza')
            ->withTimestamps();
    }

    public function tipologiaInversion(): BelongsTo
    {
        return $this->belongsTo(TipologiaInversion::class, 'tipologia_inversion_id');
    }

    public function planIndicativo(): HasMany
    {
        return $this->hasMany(PlanIndicativoMeta::class, 'meta_producto_id');
    }
}
