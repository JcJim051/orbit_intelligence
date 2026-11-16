<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioHero extends Model
{
    protected $table = 'micrositio_heroes';

    protected $fillable = [
        'eyebrow',
        'titulo',
        'subtitulo',
        'fuentes_linea',
        'pildoras',
        'banner_path',
        'usar_imagen_fondo',
    ];

    protected $attributes = [
        'usar_imagen_fondo' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pildoras' => 'array',
            'usar_imagen_fondo' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'fuentes_linea' => $this->fuentes_linea,
            'pildoras' => array_values($this->pildoras ?? []),
            'banner_path' => $this->banner_path,
            'usar_imagen_fondo' => (bool) $this->usar_imagen_fondo,
        ];
    }
}
