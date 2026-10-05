<?php

namespace App\Models;

use App\Enums\EstadoSeguimiento;
use App\Exceptions\CorteCerradoException;
use Database\Factories\SeguimientoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

/**
 * Ejercicio mensual de reporte (corte). Lo crea la Gerencia, que le adjunta las pasivas del PCT;
 * de ellas salen los techos por proyecto, fuente y dependencia. Al cerrarse queda congelado.
 */
class Seguimiento extends Model
{
    /** @use HasFactory<SeguimientoFactory> */
    use HasFactory;

    public const MODO_HISTORICO = 'historico_consolidado';

    public const MODO_OPERATIVO = 'captura_operativa';

    public const MODO_MIXTO = 'mixto';

    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    protected $table = 'seguimientos';

    protected $fillable = [
        'vigencia',
        'mes',
        'fecha_corte',
        'estado',
        'modo_captura',
        'observacion',
        'created_by',
        'cerrado_por',
        'cerrado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigencia' => 'integer',
            'mes' => 'integer',
            'fecha_corte' => 'date',
            'estado' => EstadoSeguimiento::class,
            'cerrado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Seguimiento $seguimiento): void {
            $seguimiento->fecha_corte ??= Carbon::create($seguimiento->vigencia, $seguimiento->mes, 1)->endOfMonth();
            $seguimiento->estado ??= EstadoSeguimiento::Abierto;

            if (Schema::hasColumn('seguimientos', 'modo_captura')) {
                $seguimiento->modo_captura ??= self::MODO_OPERATIVO;
            }
        });

        static::updating(function (Seguimiento $seguimiento): void {
            if ($seguimiento->getOriginal('estado') === EstadoSeguimiento::Cerrado) {
                throw new CorteCerradoException;
            }
        });

        static::deleting(function (Seguimiento $seguimiento): void {
            if ($seguimiento->estaCerrado()) {
                throw new CorteCerradoException;
            }
        });
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function pasivaCargas(): HasMany
    {
        return $this->hasMany(PasivaCarga::class);
    }

    public function pasivaVigente(): HasOne
    {
        return $this->hasOne(PasivaCarga::class)->where('es_vigente', true)->latestOfMany();
    }

    public function pasivaLineas(): HasMany
    {
        return $this->hasMany(PasivaLinea::class);
    }

    public function techos(): HasMany
    {
        return $this->hasMany(Techo::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteProyecto::class);
    }

    public function estaCerrado(): bool
    {
        return $this->estado === EstadoSeguimiento::Cerrado;
    }

    public function etiqueta(): string
    {
        return ucfirst(self::MESES[$this->mes] ?? (string) $this->mes).' '.$this->vigencia;
    }

    /**
     * @return array<string, string>
     */
    public static function modosCaptura(): array
    {
        return [
            self::MODO_HISTORICO => 'Histórico consolidado',
            self::MODO_OPERATIVO => 'Captura operativa',
            self::MODO_MIXTO => 'Mixto',
        ];
    }

    public function modoCapturaLabel(): string
    {
        return self::modosCaptura()[$this->modo_captura] ?? 'Captura operativa';
    }

    public function esHistoricoConsolidado(): bool
    {
        return $this->modo_captura === self::MODO_HISTORICO;
    }

    /**
     * Seguimiento inmediatamente anterior (para precargar la focalización y comparar cortes).
     */
    public function anterior(): ?self
    {
        return self::query()
            ->where(fn (Builder $query) => $query
                ->where('vigencia', '<', $this->vigencia)
                ->orWhere(fn (Builder $mismaVigencia) => $mismaVigencia->where('vigencia', $this->vigencia)->where('mes', '<', $this->mes)))
            ->orderByDesc('vigencia')
            ->orderByDesc('mes')
            ->first();
    }

    public function scopeAbiertos(Builder $query): void
    {
        $query->where('estado', EstadoSeguimiento::Abierto->value);
    }
}
