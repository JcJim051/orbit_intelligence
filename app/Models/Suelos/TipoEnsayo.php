<?php

namespace App\Models\Suelos;

use App\Enums\AmbitoEnsayo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEnsayo extends Catalogo
{
    protected $table = 'suelos.tipo_ensayo';

    protected $fillable = [
        'codigo',
        'nombre',
        'ambito',
        'unidad_id',
        'rango_min',
        'rango_max',
        'norma_referencia',
        'descripcion',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ambito' => AmbitoEnsayo::class,
            'rango_min' => 'decimal:4',
            'rango_max' => 'decimal:4',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_id');
    }

    public function ensayos(): HasMany
    {
        return $this->hasMany(Ensayo::class);
    }
}
