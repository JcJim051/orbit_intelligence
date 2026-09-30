<?php

namespace App\Models;

use Database\Factories\PddLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PddLinea extends Model
{
    /** @use HasFactory<PddLineaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pdd_lineas';

    protected $fillable = [
        'codigo',
        'numeral',
        'nombre',
        'eje_id',
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

    public function eje(): BelongsTo
    {
        return $this->belongsTo(PddEje::class, 'eje_id');
    }

    public function programas(): HasMany
    {
        return $this->hasMany(PddPrograma::class, 'linea_id')->orderBy('codigo');
    }
}
