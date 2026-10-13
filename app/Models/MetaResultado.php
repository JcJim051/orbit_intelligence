<?php

namespace App\Models;

use Database\Factories\MetaResultadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaResultado extends Model
{
    /** @use HasFactory<MetaResultadoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'metas_resultado';

    protected $fillable = [
        'codigo',
        'codigo_provisional',
        'descripcion',
        'programa_id',
        'subprograma_id',
        'indicador_resultado_id',
        'linea_base',
        'meta_cuatrienio',
        'observacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linea_base' => 'decimal:4',
            'meta_cuatrienio' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(PddPrograma::class, 'programa_id');
    }

    public function subprograma(): BelongsTo
    {
        return $this->belongsTo(PddSubprograma::class, 'subprograma_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultado::class, 'indicador_resultado_id');
    }

    public function metasProducto(): HasMany
    {
        return $this->hasMany(MetaProducto::class, 'meta_resultado_id');
    }

    public function construccion(): HasOne
    {
        return $this->hasOne(MetaResultadoConstruccion::class, 'meta_resultado_id');
    }
}
