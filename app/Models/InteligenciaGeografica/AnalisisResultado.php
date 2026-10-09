<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\ServidoDesde;
use Database\Factories\InteligenciaGeografica\AnalisisResultadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisResultado extends Model
{
    /** @use HasFactory<AnalisisResultadoFactory> */
    use HasFactory;

    protected $table = 'inteligencia.analisis_resultado';

    protected $fillable = [
        'analisis_area_id',
        'capa_id',
        'conteo',
        'area_m2',
        'longitud_m',
        'resumen',
        'consulted_at',
        'servido_desde',
        'cita_fuente',
        'url_fuente',
        'licencia',
        'fecha_corte',
        'obsoleto',
        'capa_sincronizacion_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conteo' => 'integer',
            'area_m2' => 'decimal:2',
            'longitud_m' => 'decimal:2',
            'resumen' => 'array',
            'consulted_at' => 'datetime',
            'servido_desde' => ServidoDesde::class,
            'fecha_corte' => 'datetime',
            'obsoleto' => 'boolean',
        ];
    }

    public function analisis(): BelongsTo
    {
        return $this->belongsTo(AnalisisArea::class, 'analisis_area_id');
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Capa::class);
    }

    public function sincronizacion(): BelongsTo
    {
        return $this->belongsTo(CapaSincronizacion::class, 'capa_sincronizacion_id');
    }
}
