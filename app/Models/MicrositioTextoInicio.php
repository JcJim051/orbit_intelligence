<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioTextoInicio extends Model
{
    protected $table = 'micrositio_textos_inicio';

    /** @var array<string, string> */
    public const DEFECTO = [
        'ultimos_titulo' => 'Lo último de los indicadores',
        'ultimos_bajada' => 'Cifras oficiales más recientes para el departamento del Meta.',
        'ultimos_enlace' => 'Ver todos los indicadores →',
        'destacados_titulo' => 'Indicadores destacados por área temática',
        'destacados_bajada' => 'Una selección de los indicadores más relevantes del SIID.',
        'destacados_enlace' => 'Ver catálogo de indicadores →',
        'publicaciones_titulo' => 'Publicaciones',
        'publicaciones_bajada' => 'Boletines y estudios de la Gerencia.',
        'publicaciones_enlace' => 'Ver todas →',
        'aliados_titulo' => 'Aliados',
        'aliados_bajada' => 'Entidades que producen o comparten información con el SIID.',
    ];

    protected $fillable = [
        'ultimos_titulo',
        'ultimos_bajada',
        'ultimos_enlace',
        'destacados_titulo',
        'destacados_bajada',
        'destacados_enlace',
        'publicaciones_titulo',
        'publicaciones_bajada',
        'publicaciones_enlace',
        'aliados_titulo',
        'aliados_bajada',
    ];

    protected $attributes = self::DEFECTO;

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        $salida = [];

        foreach (array_keys(self::DEFECTO) as $clave) {
            $salida[$clave] = $this->{$clave};
        }

        return $salida;
    }
}
