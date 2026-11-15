<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MicrositioUltimo extends Model
{
    protected $table = 'micrositio_ultimos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'capa_codigo',
        'valor',
        'unidad',
        'periodo',
        'delta_texto',
        'delta_tipo',
        'fuente',
        'url',
        'es_ejemplo',
        'orden',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tarjeta): void {
            if (static::query()->count() >= 4) {
                throw ValidationException::withMessages([
                    'codigo' => 'Puede haber máximo 4 tarjetas en «Lo último de los indicadores».',
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_ejemplo' => 'boolean',
            'orden' => 'integer',
        ];
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
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'capa_codigo' => $this->capa_codigo,
            'valor' => $this->valor,
            'unidad' => $this->unidad,
            'periodo' => $this->periodo,
            'delta_texto' => $this->delta_texto,
            'delta_tipo' => $this->delta_tipo ?: 'neutro',
            'fuente' => $this->fuente,
            'url' => $this->url,
            'es_ejemplo' => (bool) $this->es_ejemplo,
            'orden' => (int) $this->orden,
        ];
    }
}
