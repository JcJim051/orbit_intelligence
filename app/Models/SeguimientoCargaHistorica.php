<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoCargaHistorica extends Model
{
    protected $table = 'seguimiento_cargas_historicas';

    protected $fillable = [
        'seguimiento_id',
        'disk',
        'path',
        'nombre_original',
        'sha256',
        'proyectos_path',
        'proyectos_nombre_original',
        'proyectos_sha256',
        'uploaded_by',
        'status',
        'filas_total',
        'filas_validas',
        'filas_bloqueadas',
        'filas_importadas',
        'filas_actualizadas',
        'diagnostico',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'diagnostico' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(Seguimiento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
