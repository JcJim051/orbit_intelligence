<?php

namespace App\Models\InteligenciaGeografica;

use Database\Factories\InteligenciaGeografica\CapaVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapaVersion extends Model
{
    /** @use HasFactory<CapaVersionFactory> */
    use HasFactory;

    protected $table = 'inteligencia.capa_version';

    protected $fillable = [
        'capa_id',
        'numero',
        'fecha_corte',
        'cita_fuente',
        'url_fuente',
        'licencia',
        'checksum',
        'conteo',
        'agregados',
        'modificados',
        'eliminados',
        'vigente',
        'sincronizacion_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_corte' => 'datetime',
            'conteo' => 'integer',
            'agregados' => 'integer',
            'modificados' => 'integer',
            'eliminados' => 'integer',
            'vigente' => 'boolean',
        ];
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Capa::class);
    }

    public function sincronizacion(): BelongsTo
    {
        return $this->belongsTo(CapaSincronizacion::class, 'sincronizacion_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(AnalisisResultado::class);
    }
}
