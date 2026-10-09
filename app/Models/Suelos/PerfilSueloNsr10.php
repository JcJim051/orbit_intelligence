<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Límites y nombres por verificar contra la tabla A.2.4-1 de la NSR-10.
 */
class PerfilSueloNsr10 extends Catalogo
{
    protected $table = 'suelos.perfil_suelo_nsr10';

    protected $fillable = [
        'codigo',
        'nombre',
        'vs30_min_m_s',
        'vs30_max_m_s',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vs30_min_m_s' => 'decimal:1',
            'vs30_max_m_s' => 'decimal:1',
            'activo' => 'boolean',
        ];
    }

    public function parametrosDiseno(): HasMany
    {
        return $this->hasMany(ParametrosDiseno::class);
    }
}
