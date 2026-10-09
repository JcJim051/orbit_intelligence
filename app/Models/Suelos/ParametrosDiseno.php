<?php

namespace App\Models\Suelos;

use App\Enums\PotencialExpansivo;
use App\Enums\PotencialLicuacion;
use Database\Factories\Suelos\ParametrosDisenoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametrosDiseno extends Model
{
    /** @use HasFactory<ParametrosDisenoFactory> */
    use HasFactory;

    protected $table = 'suelos.parametros_diseno';

    protected $fillable = [
        'estudio_id',
        'exploracion_id',
        'perfil_suelo_nsr10_id',
        'vs30_m_s',
        'capacidad_portante_adm_kpa',
        'profundidad_desplante_m',
        'tipo_cimentacion_id',
        'asentamiento_estimado_cm',
        'cbr_diseno_pct',
        'potencial_expansivo',
        'potencial_licuacion',
        'recomendaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vs30_m_s' => 'decimal:1',
            'capacidad_portante_adm_kpa' => 'decimal:2',
            'profundidad_desplante_m' => 'decimal:2',
            'asentamiento_estimado_cm' => 'decimal:2',
            'cbr_diseno_pct' => 'decimal:2',
            'potencial_expansivo' => PotencialExpansivo::class,
            'potencial_licuacion' => PotencialLicuacion::class,
        ];
    }

    public function estudio(): BelongsTo
    {
        return $this->belongsTo(EstudioSuelos::class, 'estudio_id');
    }

    public function exploracion(): BelongsTo
    {
        return $this->belongsTo(Exploracion::class);
    }

    public function perfilSueloNsr10(): BelongsTo
    {
        return $this->belongsTo(PerfilSueloNsr10::class);
    }

    public function tipoCimentacion(): BelongsTo
    {
        return $this->belongsTo(TipoCimentacion::class);
    }
}
