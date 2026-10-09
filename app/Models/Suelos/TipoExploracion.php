<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoExploracion extends Catalogo
{
    protected $table = 'suelos.tipo_exploracion';

    protected $fillable = [
        'codigo',
        'nombre',
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
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function exploraciones(): HasMany
    {
        return $this->hasMany(Exploracion::class);
    }
}
