<?php

namespace App\Models;

use App\Enums\OrientacionIndicador;
use Database\Factories\IndicadorResultadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicadorResultado extends Model
{
    /** @use HasFactory<IndicadorResultadoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'indicadores_resultado';

    protected $fillable = [
        'codigo',
        'nombre',
        'unidad_medida',
        'orientacion',
        'linea_base',
        'linea_base_texto',
        'meta_cuatrienio',
        'fuente_verificacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientacion' => OrientacionIndicador::class,
            'linea_base' => 'decimal:4',
            'meta_cuatrienio' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function metasResultado(): HasMany
    {
        return $this->hasMany(MetaResultado::class, 'indicador_resultado_id');
    }

    public function odsReview(): HasOne
    {
        return $this->hasOne(IndicadorResultadoOdsReview::class, 'indicador_resultado_id');
    }
}
