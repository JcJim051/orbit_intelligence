<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrazadorCategoria extends Model
{
    use SoftDeletes;

    protected $table = 'trazador_categorias';

    protected $fillable = ['trazador_id', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function trazador(): BelongsTo
    {
        return $this->belongsTo(Trazador::class);
    }

    public function asociaciones(): HasMany
    {
        return $this->hasMany(ProyectoTrazadorCategoria::class);
    }
}
