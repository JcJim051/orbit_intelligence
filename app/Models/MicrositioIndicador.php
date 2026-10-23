<?php

namespace App\Models;

use Database\Factories\MicrositioIndicadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrositioIndicador extends Model
{
    /** @use HasFactory<MicrositioIndicadorFactory> */
    use HasFactory;

    protected $table = 'micrositio_indicadores';

    protected $fillable = [
        'codigo',
        'nombre',
        'micrositio_tema_id',
        'dashboard_uuid',
        'descripcion',
        'es_publico',
        'es_ejemplo',
        'conjunto_datos',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_publico' => 'boolean',
            'es_ejemplo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(MicrositioTema::class, 'micrositio_tema_id');
    }

    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        $this->loadMissing('tema');

        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tema_slug' => $this->tema?->slug,
            'tema_nombre' => $this->tema?->nombre,
            'dashboard_uuid' => $this->dashboard_uuid,
            'descripcion' => $this->descripcion,
            'es_publico' => (bool) $this->es_publico,
            'es_ejemplo' => (bool) $this->es_ejemplo,
            'conjunto_datos' => $this->conjunto_datos,
            'orden' => (int) $this->orden,
        ];
    }
}
