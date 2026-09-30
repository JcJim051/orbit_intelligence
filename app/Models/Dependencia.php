<?php

namespace App\Models;

use App\Enums\TipoDependencia;
use Database\Factories\DependenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dependencia extends Model
{
    /** @use HasFactory<DependenciaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'dependencias';

    protected $fillable = [
        'codigo',
        'nombre',
        'sigla',
        'tipo',
        'hoja_matriz',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDependencia::class,
            'activo' => 'boolean',
        ];
    }

    public function reglasPasiva(): HasMany
    {
        return $this->hasMany(DependenciaReglaPasiva::class);
    }

    public function metasProducto(): HasMany
    {
        return $this->hasMany(MetaProducto::class);
    }
}
