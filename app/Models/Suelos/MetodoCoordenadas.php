<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoCoordenadas extends Catalogo
{
    protected $table = 'suelos.metodo_coordenadas';

    protected $fillable = [
        'codigo',
        'nombre',
        'precision_estimada_m',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precision_estimada_m' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function exploraciones(): HasMany
    {
        return $this->hasMany(Exploracion::class);
    }
}
