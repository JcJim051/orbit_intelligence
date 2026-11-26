<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoValidacion extends Catalogo
{
    protected $table = 'suelos.estado_validacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'es_final',
        'visible_en_informe',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_final' => 'boolean',
            'visible_en_informe' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function estudios(): HasMany
    {
        return $this->hasMany(EstudioSuelos::class);
    }
}
