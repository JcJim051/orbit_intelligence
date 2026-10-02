<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Techo por seguimiento × proyecto × fuente × dependencia, derivado de la pasiva vigente.
 * valor = valor_ajuste (si la Gerencia hizo un ajuste trazado) o valor_pasiva.
 */
class Techo extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    protected $table = 'techos';

    protected $fillable = [
        'seguimiento_id',
        'proyecto_id',
        'fuente_financiacion_id',
        'dependencia_id',
        'valor_pasiva',
        'valor_ajuste',
        'valor',
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
            'lineas_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Techo $techo): void {
            $techo->valor = $techo->valor_ajuste ?? $techo->valor_pasiva ?? 0;
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
