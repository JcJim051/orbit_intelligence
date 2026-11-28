<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiidSincronizacionDetalle extends Model
{
    protected $table = 'siid_publicacion.sincronizacion_detalles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['datos_recibidos' => 'array'];
    }

    public function sincronizacion(): BelongsTo
    {
        return $this->belongsTo(SiidSincronizacion::class, 'sincronizacion_id');
    }
}
