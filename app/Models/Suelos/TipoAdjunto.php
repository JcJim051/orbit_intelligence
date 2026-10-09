<?php

namespace App\Models\Suelos;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAdjunto extends Catalogo
{
    protected $table = 'suelos.tipo_adjunto';

    protected $fillable = [
        'codigo',
        'nombre',
        'extensiones_permitidas',
        'activo',
    ];

    public function adjuntos(): HasMany
    {
        return $this->hasMany(Adjunto::class);
    }
}
