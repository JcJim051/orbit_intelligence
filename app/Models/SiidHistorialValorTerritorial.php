<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiidHistorialValorTerritorial extends Model
{
    protected $table = 'siid_territorial.historial_valores';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array'];
    }
}
