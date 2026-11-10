<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioPublicacion extends Model
{
    protected $table = 'micrositio_publicaciones';

    protected $fillable = [
        'tipo',
        'titulo',
        'fecha',
        'resumen',
        'archivo_path',
        'nombre_archivo',
        'es_ejemplo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'es_ejemplo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'fecha' => $this->fecha?->toDateString(),
            'resumen' => $this->resumen,
            'archivo_path' => $this->archivo_path,
            'nombre_archivo' => $this->nombre_archivo,
            'es_ejemplo' => (bool) $this->es_ejemplo,
            'orden' => (int) $this->orden,
        ];
    }
}
