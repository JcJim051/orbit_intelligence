<?php

namespace App\Models;

use App\Enums\EstadoReporteProyecto;
use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reporte mensual de una dependencia sobre un proyecto en un seguimiento.
 * Flujo: borrador → reportado → aprobado | devuelto (→ reportado). Al cerrar el seguimiento queda congelado.
 */
class ReporteProyecto extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'reportes_proyecto';

    protected $fillable = [
        'seguimiento_id',
        'proyecto_id',
        'dependencia_id',
        'estado',
        'justificacion_focalizacion',
        'reportado_por',
        'reportado_at',
        'revisado_por',
        'revisado_at',
        'observacion_revision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoReporteProyecto::class,
            'reportado_at' => 'datetime',
            'revisado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReporteProyecto $reporte): void {
            $reporte->estado ??= EstadoReporteProyecto::Borrador;
        });
    }

    public function seguimientoIdParaCongelamiento(): ?int
    {
        return $this->seguimiento_id;
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(Seguimiento::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(EjecucionFinanciera::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(AvanceFisico::class);
    }

    public function focalizaciones(): HasMany
    {
        return $this->hasMany(Focalizacion::class);
    }

    public function esEditablePorSector(): bool
    {
        return $this->estado->esEditablePorSector() && ! $this->seguimiento->estaCerrado();
    }

    public function scopeDelSeguimiento(Builder $query, Seguimiento|int $seguimiento): void
    {
        $query->where('seguimiento_id', $seguimiento instanceof Seguimiento ? $seguimiento->id : $seguimiento);
    }

    public function scopeDelSector(Builder $query, int $dependenciaId): void
    {
        $query->where('dependencia_id', $dependenciaId);
    }

    public function scopeEnEstado(Builder $query, EstadoReporteProyecto $estado): void
    {
        $query->where('estado', $estado->value);
    }
}
