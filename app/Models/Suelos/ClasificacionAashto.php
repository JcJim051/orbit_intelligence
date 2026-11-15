<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionAashto extends Catalogo
{
    protected $table = 'suelos.clasificacion_aashto';

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

    public function estratos(): HasMany
    {
        return $this->hasMany(Estrato::class);
    }
}
