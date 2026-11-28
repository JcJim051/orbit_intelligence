<?php

namespace App\Models\Suelos;

use App\Enums\GrupoUscs;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionUscs extends Catalogo
{
    protected $table = 'suelos.clasificacion_uscs';

    protected $fillable = [
        'codigo',
        'nombre',
        'grupo',
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
            'grupo' => GrupoUscs::class,
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function estratos(): HasMany
    {
        return $this->hasMany(Estrato::class);
    }
}
