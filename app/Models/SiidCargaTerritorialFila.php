<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiidCargaTerritorialFila extends Model
{
    protected $table = 'siid_territorial.carga_filas';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['datos_recibidos' => 'array', 'datos_normalizados' => 'array', 'datos_anteriores' => 'array'];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(SiidCargaTerritorial::class, 'carga_id');
    }
}
