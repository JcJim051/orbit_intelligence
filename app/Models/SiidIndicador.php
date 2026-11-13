<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SiidIndicador extends Model
{
    protected $table = 'siid_publicacion.indicadores_siid';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'es_icet' => 'boolean', 'es_pet' => 'boolean', 'activo' => 'boolean',
            'datos_origen' => 'array', 'sincronizado_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(SiidAreaTematica::class, 'area_tematica_id');
    }

    public function eje(): BelongsTo
    {
        return $this->belongsTo(SiidEje::class, 'eje_id');
    }

    public function publicacion(): MorphOne
    {
        return $this->morphOne(SiidPublicacion::class, 'publicable');
    }

    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }
}
