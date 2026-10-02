<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo de soporte de un avance físico, guardado en un disco privado con su hash SHA-256.
 */
class Evidencia extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'evidencias';

    protected $fillable = [
        'avance_fisico_id',
        'disk',
        'path',
        'nombre_original',
        'mime',
        'bytes',
        'sha256',
        'descripcion',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
        ];
    }

    protected function rutaDependencia(): ?string
    {
        return 'avanceFisico.reporte';
    }

    public function seguimientoIdParaCongelamiento(): ?int
    {
        $reporteId = AvanceFisico::sinFiltroSectorial()->whereKey($this->avance_fisico_id)->value('reporte_proyecto_id');

        return ReporteProyecto::sinFiltroSectorial()->whereKey($reporteId)->value('seguimiento_id');
    }

    public function avanceFisico(): BelongsTo
    {
        return $this->belongsTo(AvanceFisico::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
