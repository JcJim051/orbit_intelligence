<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnaliticaSincronizacion extends Model
{
    protected $table = 'analitica_sincronizaciones';

    protected $fillable = [
        'pasiva_carga_id',
        'corte',
        'sha256',
        'estado',
        'rubros',
        'origen',
        'mensaje',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'corte' => 'date',
            'rubros' => 'integer',
        ];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(PasivaCarga::class, 'pasiva_carga_id');
    }
}
