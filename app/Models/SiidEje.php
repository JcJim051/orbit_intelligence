<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SiidEje extends Model
{
    protected $table = 'siid_publicacion.ejes';

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? $this->table : 'ejes';
    }

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
