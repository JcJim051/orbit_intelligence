<?php

namespace App\Models\Suelos;

use App\Models\Concerns\TieneEtiquetaCatalogo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class Catalogo extends Model
{
    use TieneEtiquetaCatalogo;

    public function scopeActivo(Builder $query): void
    {
        $query->where($this->qualifyColumn('activo'), true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
