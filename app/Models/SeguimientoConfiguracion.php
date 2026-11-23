<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoConfiguracion extends Model
{
    protected $table = 'seguimiento_configuraciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reporte_indicadores_activo' => 'boolean',
            'edicion_validadores_informacion_activa' => 'boolean',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
