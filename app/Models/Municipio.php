<?php

namespace App\Models;

use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\LimiteMunicipio;
use Database\Factories\MunicipioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function estudiosSuelos(): HasMany
    {
        return $this->hasMany(EstudioSuelos::class);
    }

    public function limiteSuelos(): HasOne
    {
        return $this->hasOne(LimiteMunicipio::class);
    }
}
