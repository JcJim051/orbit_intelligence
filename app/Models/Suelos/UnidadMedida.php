<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Catalogo
{
    protected $table = 'suelos.unidad_medida';

    protected $fillable = [
        'codigo',
        'nombre',
        'simbolo',
        'magnitud',
        'factor_a_base',
        'unidad_base_id',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_a_base' => 'decimal:9',
            'activo' => 'boolean',
        ];
    }

    public function unidadBase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unidad_base_id');
    }

    public function derivadas(): HasMany
    {
        return $this->hasMany(self::class, 'unidad_base_id');
    }

    public function tiposEnsayo(): HasMany
    {
        return $this->hasMany(TipoEnsayo::class, 'unidad_id');
    }
}
