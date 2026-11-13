<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiidEje extends Model
{
    protected $table = 'siid_publicacion.ejes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(SiidAreaTematica::class, 'eje_id')->orderBy('orden');
    }
}
