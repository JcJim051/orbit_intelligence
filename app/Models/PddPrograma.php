<?php

namespace App\Models;

use Database\Factories\PddProgramaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class PddPrograma extends Model
{
    /** @use HasFactory<PddProgramaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pdd_programas';

    protected $fillable = [
        'codigo',
        'numeral',
        'nombre',
        'linea_id',
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

    public function linea(): BelongsTo
    {
        return $this->belongsTo(PddLinea::class, 'linea_id');
    }

    public function subprogramas(): HasMany
    {
        return $this->hasMany(PddSubprograma::class, 'programa_id')->orderBy('codigo');
    }

    /**
     * Metas de producto de los subprogramas vigentes del programa.
     */
    public function metasProducto(): HasManyThrough
    {
        return $this->hasManyThrough(MetaProducto::class, PddSubprograma::class, 'programa_id', 'subprograma_id');
    }

    public function metasResultado(): HasMany
    {
        return $this->hasMany(MetaResultado::class, 'programa_id');
    }
}
