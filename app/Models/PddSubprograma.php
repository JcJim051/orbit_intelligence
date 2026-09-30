<?php

namespace App\Models;

use Database\Factories\PddSubprogramaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PddSubprograma extends Model
{
    /** @use HasFactory<PddSubprogramaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'pdd_subprogramas';

    protected $fillable = [
        'codigo',
        'numeral',
        'nombre',
        'programa_id',
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

    public function programa(): BelongsTo
    {
        return $this->belongsTo(PddPrograma::class, 'programa_id');
    }

    public function metasProducto(): HasMany
    {
        return $this->hasMany(MetaProducto::class, 'subprograma_id');
    }

    public function metasResultado(): HasMany
    {
        return $this->hasMany(MetaResultado::class, 'subprograma_id');
    }
}
