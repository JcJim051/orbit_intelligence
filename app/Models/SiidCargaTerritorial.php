<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiidCargaTerritorial extends Model
{
    protected $table = 'siid_territorial.cargas';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aplicado_at' => 'datetime', 'revertido_at' => 'datetime'];
    }

    public function filas(): HasMany
    {
        return $this->hasMany(SiidCargaTerritorialFila::class, 'carga_id');
    }
}
