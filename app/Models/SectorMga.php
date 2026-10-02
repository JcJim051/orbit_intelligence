<?php

namespace App\Models;

use Database\Factories\SectorMgaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SectorMga extends Model
{
    /** @use HasFactory<SectorMgaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'sectores_mga';

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

    public function metasProducto(): HasMany
    {
        return $this->hasMany(MetaProducto::class, 'sector_mga_id');
    }
}
