<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorTerritorialAsignacion extends Model
{
    protected $table = 'indicador_territorial_asignaciones';

    protected $fillable = [
        'indicador_territorial_id', 'asignado_a', 'asignado_por', 'grupo_origen', 'motivo', 'asignado_at',
    ];

    protected function casts(): array
    {
        return ['asignado_at' => 'datetime'];
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorTerritorial::class, 'indicador_territorial_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }
}
