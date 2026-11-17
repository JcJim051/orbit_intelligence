<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioMapaSincronizacion extends Model
{
    protected $table = 'micrositio_mapa_sincronizaciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
        ];
    }
}
