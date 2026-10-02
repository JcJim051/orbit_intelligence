<?php

namespace App\Models;

use App\Enums\EstadoRevisionPasiva;
use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila hoja (con fuente) de una pasiva. Se asigna a una dependencia con las reglas de pasiva
 * y a un proyecto por el BPIN; si algo no se resuelve queda pendiente de revisión, nunca se descarta.
 */
class PasivaLinea extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'pasiva_lineas';

    protected $fillable = [
        'pasiva_carga_id',
        'seguimiento_id',
        'fila',
        'identificacion_presupuestal',
        'unidad_pct',
        'rubro',
        'codigo_fuente',
        'concepto',
        'bpin',
        'nombre_proyecto',
        'es_inversion',
        'fuente_financiacion_id',
        'dependencia_id',
        'proyecto_id',
        'techo_id',
        'apropiacion_inicial',
        'modificaciones',
        'apropiacion_definitiva',
        'cdp',
        'compromisos',
        'obligaciones',
        'pagos',
        'estado_revision',
        'motivos_revision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fila' => 'integer',
            'es_inversion' => 'boolean',
            'apropiacion_inicial' => 'decimal:2',
            'modificaciones' => 'decimal:2',
            'apropiacion_definitiva' => 'decimal:2',
            'cdp' => 'decimal:2',
            'compromisos' => 'decimal:2',
            'obligaciones' => 'decimal:2',
            'pagos' => 'decimal:2',
            'estado_revision' => EstadoRevisionPasiva::class,
            'motivos_revision' => 'array',
        ];
    }

    public function seguimientoIdParaCongelamiento(): ?int
    {
        return $this->seguimiento_id;
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(PasivaCarga::class, 'pasiva_carga_id');
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(Seguimiento::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function techo(): BelongsTo
    {
        return $this->belongsTo(Techo::class);
    }

    public function scopeVigentes(Builder $query): void
    {
        $query->whereHas('carga', fn (Builder $carga) => $carga->where('es_vigente', true));
    }

    public function scopePendientesRevision(Builder $query): void
    {
        $query->where('estado_revision', EstadoRevisionPasiva::Pendiente->value);
    }
}
