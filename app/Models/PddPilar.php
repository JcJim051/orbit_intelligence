<?php

namespace App\Models;

use Database\Factories\PddPilarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PddPilar extends Model
{
    /** @use HasFactory<PddPilarFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pdd_pilares';

    protected $fillable = [
        'codigo',
        'numeral',
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

    public function ejes(): HasMany
    {
        return $this->hasMany(PddEje::class, 'pilar_id')->orderBy('codigo');
    }
}
