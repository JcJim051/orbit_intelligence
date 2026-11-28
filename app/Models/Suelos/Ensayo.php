<?php

namespace App\Models\Suelos;

use Database\Factories\Suelos\EnsayoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ensayo extends Model
{
    /** @use HasFactory<EnsayoFactory> */
    use HasFactory;

    protected $table = 'suelos.ensayo';

    protected $fillable = [
        'exploracion_id',
        'estrato_id',
        'muestra',
        'profundidad_desde_m',
        'profundidad_hasta_m',
        'tipo_ensayo_id',
        'valor',
        'valor_original',
        'unidad_original_id',
        'es_rechazo',
        'observacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profundidad_desde_m' => 'decimal:2',
            'profundidad_hasta_m' => 'decimal:2',
            'valor' => 'decimal:4',
            'valor_original' => 'decimal:4',
            'es_rechazo' => 'boolean',
        ];
    }

    public function exploracion(): BelongsTo
    {
        return $this->belongsTo(Exploracion::class);
    }

    public function estrato(): BelongsTo
    {
        return $this->belongsTo(Estrato::class);
    }

    public function tipoEnsayo(): BelongsTo
    {
        return $this->belongsTo(TipoEnsayo::class);
    }

    public function unidadOriginal(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_original_id');
    }
}
