<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Techo por seguimiento × proyecto × fuente × dependencia, derivado de la pasiva vigente.
 * Cada uno de los cuatro valores (asignado, comprometido, obligado y pagado) usa
 * el ajuste trazado cuando existe y, si no, el agregado de la pasiva.
 */
class Techo extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento, SoftDeletes;

    protected $table = 'techos';

    protected $fillable = [
        'seguimiento_id',
        'proyecto_id',
        'fuente_financiacion_id',
        'dependencia_id',
        'valor_pasiva',
        'valor_ajuste',
        'valor',
        'comprometido_pasiva',
        'comprometido_ajuste',
        'comprometido',
        'obligado_pasiva',
        'obligado_ajuste',
        'obligado',
        'pagado_pasiva',
        'pagado_ajuste',
        'pagado',
        'base',
        'pasiva_carga_id',
        'lineas_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_pasiva' => 'decimal:2',
            'valor_ajuste' => 'decimal:2',
            'valor' => 'decimal:2',
            'comprometido_pasiva' => 'decimal:2',
            'comprometido_ajuste' => 'decimal:2',
            'comprometido' => 'decimal:2',
            'obligado_pasiva' => 'decimal:2',
            'obligado_ajuste' => 'decimal:2',
            'obligado' => 'decimal:2',
            'pagado_pasiva' => 'decimal:2',
            'pagado_ajuste' => 'decimal:2',
            'pagado' => 'decimal:2',
            'lineas_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Techo $techo): void {
            $techo->valor = $techo->valor_ajuste ?? $techo->valor_pasiva ?? 0;
            $techo->comprometido = $techo->comprometido_ajuste ?? $techo->comprometido_pasiva ?? 0;
            $techo->obligado = $techo->obligado_ajuste ?? $techo->obligado_pasiva ?? 0;
            $techo->pagado = $techo->pagado_ajuste ?? $techo->pagado_pasiva ?? 0;
        });
    }

    public function tieneAjuste(): bool
    {
        return $this->valor_ajuste !== null
            || $this->comprometido_ajuste !== null
            || $this->obligado_ajuste !== null
            || $this->pagado_ajuste !== null;
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

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PasivaLinea::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TechoHistorial::class)->latest('id');
    }

    public function scopeDelSeguimiento(Builder $query, Seguimiento|int $seguimiento): void
    {
        $query->where('seguimiento_id', $seguimiento instanceof Seguimiento ? $seguimiento->id : $seguimiento);
    }
}
