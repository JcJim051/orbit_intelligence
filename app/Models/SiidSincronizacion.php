<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiidSincronizacion extends Model
{
    protected $table = 'siid_publicacion.sincronizaciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean', 'iniciado_at' => 'datetime', 'finalizado_at' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SiidSincronizacionDetalle::class, 'sincronizacion_id');
    }

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }
}
