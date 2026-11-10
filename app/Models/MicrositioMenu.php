<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioMenu extends Model
{
    protected $table = 'micrositio_menus';

    protected $fillable = [
        'texto',
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
            'texto' => $this->texto,
            'url' => $this->url,
            'orden' => (int) $this->orden,
        ];
    }
}
