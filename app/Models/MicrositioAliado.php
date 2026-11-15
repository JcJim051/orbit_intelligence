<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioAliado extends Model
{
    protected $table = 'micrositio_aliados';

    protected $fillable = [
        'nombre',
        'logo_path',
        'url',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'nombre' => $this->nombre,
            'logo_path' => $this->logo_path,
            'url' => $this->url,
            'orden' => (int) $this->orden,
        ];
    }
}
