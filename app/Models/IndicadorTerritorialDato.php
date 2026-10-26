<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorTerritorialDato extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_OBSERVADO = 'observado';

    public const ESTADOS = [
        'pendiente' => 'Pendiente de validación',
        'aprobado' => 'Aprobado',
        'observado' => 'Con observaciones',
    ];

    protected $table = 'indicador_territorial_datos';

    protected $fillable = [
        'indicador_territorial_id', 'periodo', 'periodo_inicio', 'periodo_fin', 'valor', 'fecha_corte',
        'fuente', 'enlace_evidencia', 'observacion', 'estado', 'origen', 'archivo_fuente',
        'fila_fuente', 'reportado_por', 'validado_por', 'validado_at',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'fecha_corte' => 'date',
            'valor' => 'decimal:6',
            'validado_at' => 'datetime',
        ];
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorTerritorial::class, 'indicador_territorial_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
