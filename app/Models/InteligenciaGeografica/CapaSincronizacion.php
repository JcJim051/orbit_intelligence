<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use Database\Factories\InteligenciaGeografica\CapaSincronizacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapaSincronizacion extends Model
{
    /** @use HasFactory<CapaSincronizacionFactory> */
    use HasFactory;

    protected $table = 'inteligencia.capa_sincronizacion';

    protected $fillable = [
        'capa_id',
        'iniciado_at',
        'finalizado_at',
        'fecha_corte_detectada',
        'cambio',
        'entidades_agregadas',
        'entidades_actualizadas',
        'entidades_eliminadas',
        'estado',
        'mensaje_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'fecha_corte_detectada' => 'datetime',
            'cambio' => 'boolean',
            'entidades_agregadas' => 'integer',
            'entidades_actualizadas' => 'integer',
            'entidades_eliminadas' => 'integer',
            'estado' => EstadoSincronizacionCapa::class,
        ];
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Capa::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(AnalisisResultado::class);
    }
}
