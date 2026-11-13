<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiidTerritorio extends Model
{
    protected $table = 'siid_territorial.territorios';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
