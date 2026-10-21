<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seguimiento4mMeta extends Model
{
    protected $table = 'seguimiento_4m_metas';

    protected $fillable = [
        'meta_producto_id', 'codigo_meta_producto', 'vigencia', 'responsable', 'orientacion',
        'programado_fisico', 'ejecutado_fisico', 'asignado', 'comprometido', 'obligado', 'pagado',
        'estado_obligado', 'estado_pagado', 'observaciones', 'fuente', 'fila_fuente',
    ];

    protected function casts(): array
    {
        return ['vigencia' => 'integer', 'programado_fisico' => 'decimal:4', 'ejecutado_fisico' => 'decimal:4',
            'asignado' => 'decimal:2', 'comprometido' => 'decimal:2', 'obligado' => 'decimal:2', 'pagado' => 'decimal:2'];
    }

    public function metaProducto(): BelongsTo
    {
        return $this->belongsTo(MetaProducto::class, 'meta_producto_id');
    }

    public function getPorcentajeFisicoAttribute(): ?float
    {
        if ($this->programado_fisico === null || (float) $this->programado_fisico == 0 || $this->ejecutado_fisico === null) {
            return null;
        }

        return min(100, ((float) $this->ejecutado_fisico / (float) $this->programado_fisico) * 100);
    }
}
