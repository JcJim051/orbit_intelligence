<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Distribución mensual por municipio (DANE) de un proyecto multimunicipio o departamental.
 */
class Focalizacion extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'focalizaciones';

    protected $fillable = [
        'reporte_proyecto_id',
        'municipio_id',
        'porcentaje',
        'valor',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:4',
            'valor' => 'decimal:2',
            'cantidad' => 'decimal:4',
        ];
    }

    protected function rutaDependencia(): ?string
    {
        return 'reporte';
    }

    public function seguimientoIdParaCongelamiento(): ?int
    {
        return ReporteProyecto::sinFiltroSectorial()->whereKey($this->reporte_proyecto_id)->value('seguimiento_id');
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteProyecto::class, 'reporte_proyecto_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
