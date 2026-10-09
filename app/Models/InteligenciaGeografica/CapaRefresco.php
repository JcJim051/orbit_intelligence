<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\EstadoRefrescoCapa;
use Database\Factories\InteligenciaGeografica\CapaRefrescoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapaRefresco extends Model
{
    /** @use HasFactory<CapaRefrescoFactory> */
    use HasFactory;

    protected $table = 'inteligencia.capa_refresco';

    protected $fillable = [
        'capa_id',
        'iniciado_at',
        'finalizado_at',
        'estado',
        'registros',
        'mensaje',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'estado' => EstadoRefrescoCapa::class,
            'registros' => 'integer',
        ];
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Capa::class);
    }
}
