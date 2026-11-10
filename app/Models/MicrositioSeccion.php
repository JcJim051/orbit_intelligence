<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioSeccion extends Model
{
    protected $table = 'micrositio_secciones';

    protected $fillable = [
        'clave',
        'publicado',
        'publicado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado' => 'array',
            'publicado_en' => 'datetime',
        ];
    }
}
