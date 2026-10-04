<?php

namespace App\Models;

use Database\Factories\MetaProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaProducto extends Model
{
    /** @use HasFactory<MetaProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'metas_producto';

    protected $fillable = [
        'codigo',
        'nombre',
        'subprograma_id',
        'sector_mga_id',
        'meta_resultado_id',
        'dependencia_id',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
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

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'meta_producto_proyecto')->withTimestamps();
    }

    public function planIndicativo(): HasMany
    {
        return $this->hasMany(PlanIndicativoMeta::class, 'meta_producto_id');
    }
}
