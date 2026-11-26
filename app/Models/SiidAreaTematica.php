<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiidAreaTematica extends Model
{
    protected $table = 'siid_publicacion.areas_tematicas';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    public function eje(): BelongsTo
    {
        return $this->belongsTo(SiidEje::class, 'eje_id');
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(SiidIndicador::class, 'area_tematica_id');
    }
}
