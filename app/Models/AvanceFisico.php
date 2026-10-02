<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Avance físico de una actividad en un reporte. Si la cantidad es mayor que cero exige al menos una evidencia.
 */
class AvanceFisico extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'avances_fisicos';

    protected $fillable = [
        'reporte_proyecto_id',
        'actividad_id',
        'cantidad',
        'fecha_ejecucion',
        'descripcion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'fecha_ejecucion' => 'date',
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

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    public function scopePendientesEvidencia(Builder $query): void
    {
        $query->where('cantidad', '>', 0)->whereDoesntHave('evidencias');
    }
}
