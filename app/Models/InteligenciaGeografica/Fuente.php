<?php

namespace App\Models\InteligenciaGeografica;

use App\Models\Concerns\TieneEtiquetaCatalogo;
use Database\Factories\InteligenciaGeografica\FuenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fuente extends Model
{
    /** @use HasFactory<FuenteFactory> */
    use HasFactory, TieneEtiquetaCatalogo;

    protected $table = 'inteligencia.fuente';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function capas(): HasMany
    {
        return $this->hasMany(Capa::class);
    }

    public function scopeActivo(Builder $query): void
    {
        $query->where($this->qualifyColumn('activo'), true);
    }
}
