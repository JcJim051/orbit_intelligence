<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCimentacion extends Catalogo
{
    protected $table = 'suelos.tipo_cimentacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    public function parametrosDiseno(): HasMany
    {
        return $this->hasMany(ParametrosDiseno::class);
    }
}
