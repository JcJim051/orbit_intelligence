<?php

namespace App\Models;

use Database\Factories\MunicipioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    /** @use HasFactory<MunicipioFactory> */
    use HasFactory;

    protected $table = 'municipios';

    protected $fillable = [
        'codigo_dane',
        'nombre',
        'subregion',
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
}
