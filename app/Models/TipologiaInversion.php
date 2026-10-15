<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\TipologiaInversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipologiaInversion extends Model
{
    use HasFactory;

    protected static function newFactory(): TipologiaInversionFactory
    {
        return TipologiaInversionFactory::new();
    }
    protected $table = 'tipologias_inversion';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function metasProducto(): HasMany
    {
        return $this->hasMany(MetaProducto::class, 'tipologia_inversion_id');
    }

    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }
}
