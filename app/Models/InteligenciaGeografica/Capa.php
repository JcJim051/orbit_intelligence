<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\TipoAccesoCapa;
use App\Models\Concerns\TieneEtiquetaCatalogo;
use Database\Factories\InteligenciaGeografica\CapaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Capa extends Model
{
    /** @use HasFactory<CapaFactory> */
    use HasFactory, TieneEtiquetaCatalogo;

    protected $table = 'inteligencia.capa';

    protected $fillable = [
        'codigo',
        'nombre',
        'fuente_id',
        'tipo_acceso',
        'url_servicio',
        'layer_id',
        'endpoints',
        'campos_clave',
        'pregunta',
        'licencia',
        'fecha_actualizacion',
        'activa',
        'observacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_acceso' => TipoAccesoCapa::class,
            'endpoints' => 'array',
            'campos_clave' => 'array',
            'fecha_actualizacion' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(Fuente::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(AnalisisResultado::class);
    }

    public function objetosCache(): HasMany
    {
        return $this->hasMany(CapaObjetoCache::class);
    }

    public function refrescos(): HasMany
    {
        return $this->hasMany(CapaRefresco::class);
    }

    public function scopeActiva(Builder $query): void
    {
        $query->where($this->qualifyColumn('activa'), true);
    }
}
