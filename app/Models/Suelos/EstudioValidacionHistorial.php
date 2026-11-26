<?php

namespace App\Models\Suelos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstudioValidacionHistorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'suelos.estudio_validacion_historial';

    protected $fillable = [
        'estudio_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'usuario_id',
        'observacion',
    ];

    public function estudio(): BelongsTo
    {
        return $this->belongsTo(EstudioSuelos::class, 'estudio_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(EstadoValidacion::class, 'estado_anterior_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(EstadoValidacion::class, 'estado_nuevo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
