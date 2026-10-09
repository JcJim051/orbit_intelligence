<?php

namespace App\Models\InteligenciaGeografica;

use Database\Factories\InteligenciaGeografica\CapaObjetoCacheFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapaObjetoCache extends Model
{
    /** @use HasFactory<CapaObjetoCacheFactory> */
    use HasFactory;

    protected $table = 'inteligencia.capa_objeto_cache';

    protected $fillable = [
        'capa_id',
        'atributos',
        'identificador_origen',
        'fetched_at',
    ];

    protected $hidden = [
        'geom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'atributos' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Capa::class);
    }
}
