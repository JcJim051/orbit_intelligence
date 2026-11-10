<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoliticaPublicaDepartamental extends Model
{
    use SoftDeletes;

    protected $table = 'politicas_publicas_departamentales';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function proyectosTrazadores(): HasMany
    {
        return $this->hasMany(ProyectoTrazador::class);
    }
}
