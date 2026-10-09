<?php

namespace App\Models\Suelos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjunto extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'suelos.adjunto';

    protected $fillable = [
        'estudio_id',
        'tipo_adjunto_id',
        'nombre_archivo',
        'ruta_storage',
        'mime_type',
        'tamano_bytes',
        'sha256',
        'cargado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
        ];
    }

    public function estudio(): BelongsTo
    {
        return $this->belongsTo(EstudioSuelos::class, 'estudio_id');
    }

    public function tipoAdjunto(): BelongsTo
    {
        return $this->belongsTo(TipoAdjunto::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por');
    }
}
