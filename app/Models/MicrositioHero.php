<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioHero extends Model
{
    protected $table = 'micrositio_heroes';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'banner_path',
    ];

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'banner_path' => $this->banner_path,
        ];
    }
}
