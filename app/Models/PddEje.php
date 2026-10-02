<?php

namespace App\Models;

use Database\Factories\PddEjeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PddEje extends Model
{
    /** @use HasFactory<PddEjeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pdd_ejes';

    protected $fillable = [
        'codigo',
        'numeral',
        'nombre',
        'pilar_id',
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

    public function pilar(): BelongsTo
    {
        return $this->belongsTo(PddPilar::class, 'pilar_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PddLinea::class, 'eje_id')->orderBy('codigo');
    }
}
