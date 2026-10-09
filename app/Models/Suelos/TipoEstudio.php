<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEstudio extends Catalogo
{
    protected $table = 'suelos.tipo_estudio';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    public function estudios(): HasMany
    {
        return $this->hasMany(EstudioSuelos::class);
    }
}
