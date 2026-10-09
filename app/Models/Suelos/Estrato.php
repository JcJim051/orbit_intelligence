<?php

namespace App\Models\Suelos;

use App\Enums\CompacidadEstrato;
use App\Enums\ConsistenciaEstrato;
use App\Enums\HumedadVisualEstrato;
use Database\Factories\Suelos\EstratoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estrato extends Model
{
    /** @use HasFactory<EstratoFactory> */
    use HasFactory;

    protected $table = 'suelos.estrato';

    protected $fillable = [
        'exploracion_id',
        'profundidad_desde_m',
        'profundidad_hasta_m',
        'clasificacion_uscs_id',
        'clasificacion_aashto_id',
        'descripcion',
        'color',
        'humedad_visual',
        'consistencia',
        'compacidad',
        'es_relleno',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profundidad_desde_m' => 'decimal:2',
            'profundidad_hasta_m' => 'decimal:2',
            'humedad_visual' => HumedadVisualEstrato::class,
            'consistencia' => ConsistenciaEstrato::class,
            'compacidad' => CompacidadEstrato::class,
            'es_relleno' => 'boolean',
        ];
    }

    public function exploracion(): BelongsTo
    {
        return $this->belongsTo(Exploracion::class);
    }

    public function clasificacionUscs(): BelongsTo
    {
        return $this->belongsTo(ClasificacionUscs::class);
    }

    public function clasificacionAashto(): BelongsTo
    {
        return $this->belongsTo(ClasificacionAashto::class);
    }

    public function ensayos(): HasMany
    {
        return $this->hasMany(Ensayo::class);
    }
}
