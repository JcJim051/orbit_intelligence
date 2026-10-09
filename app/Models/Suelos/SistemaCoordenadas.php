<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SistemaCoordenadas extends Catalogo
{
    protected $table = 'suelos.sistema_coordenadas';

    protected $fillable = [
        'codigo',
        'nombre',
        'srid',
        'observacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'srid' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function exploraciones(): HasMany
    {
        return $this->hasMany(Exploracion::class);
    }
}
