<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ejecución financiera acumulada a la fecha de corte, por actividad y fuente.
 */
class EjecucionFinanciera extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'ejecuciones_financieras';

    protected $fillable = [
        'reporte_proyecto_id',
        'actividad_id',
        'fuente_financiacion_id',
        'comprometido',
        'obligado',
        'pagado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'comprometido' => 'decimal:2',
            'obligado' => 'decimal:2',
            'pagado' => 'decimal:2',
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

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id');
    }
}
