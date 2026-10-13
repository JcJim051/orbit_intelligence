<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\FrecuenciaActualizacionCapa;
use App\Enums\TipoAccesoCapa;
use App\Models\Concerns\TieneEtiquetaCatalogo;
use Database\Factories\InteligenciaGeografica\CapaFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Capa extends Model
{
    /** @use HasFactory<CapaFactory> */
    use HasFactory, TieneEtiquetaCatalogo;

    protected $table = 'inteligencia.capa';

    protected $fillable = [
        'codigo',
        'nombre',
        'fuente_id',
        'tipo_acceso',
        'url_servicio',
        'layer_id',
        'endpoints',
        'campos_clave',
        'pregunta',
        'licencia',
        'fecha_actualizacion',
        'frecuencia_actualizacion',
        'ttl_horas',
        'fecha_corte_fuente',
        'fecha_ultima_sincronizacion',
        'estado_frescura',
        'cita_fuente',
        'activa',
        'observacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_acceso' => TipoAccesoCapa::class,
            'endpoints' => 'array',
            'campos_clave' => 'array',
            'fecha_actualizacion' => 'date',
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::class,
            'ttl_horas' => 'integer',
            'fecha_corte_fuente' => 'datetime',
            'fecha_ultima_sincronizacion' => 'datetime',
            'estado_frescura' => EstadoFrescuraCapa::class,
            'activa' => 'boolean',
        ];
    }

    /**
     * La sincronización lee la fecha de corte de la fuente antes de descargar.
     * Descarga solo cuando esa fecha es posterior a la almacenada, o cuando alguna todavía no existe.
     */
    public function requiereDescarga(?DateTimeInterface $fechaCorteDetectada): bool
    {
        if ($fechaCorteDetectada === null || $this->fecha_corte_fuente === null) {
            return true;
        }

        return Carbon::parse($fechaCorteDetectada)->greaterThan($this->fecha_corte_fuente);
    }

    /**
     * La copia queda vencida si nunca se sincronizó o si su edad supera ttl_horas.
     * Con ttl 0, vence en cuanto el reloj pasa el instante de la sincronización.
     */
    public function copiaExcedeTtl(?DateTimeInterface $ahora = null): bool
    {
        if ($this->fecha_ultima_sincronizacion === null) {
            return true;
        }

        $referencia = $ahora === null ? Carbon::now() : Carbon::parse($ahora);

        return $this->fecha_ultima_sincronizacion
            ->copy()
            ->addHours((int) $this->ttl_horas)
            ->lessThan($referencia);
    }

    public const AVISO_CONECTOR = 'Esta capa todavía no tiene una copia descargada. Esta fecha no proviene de una consulta a la fuente.';

    /**
     * La tarea programada consulta la fuente solo cuando este instante ya pasó.
     */
    public function revisionCadenciaVencida(?DateTimeInterface $ahora = null): bool
    {
        if ($this->frecuencia_actualizacion === FrecuenciaActualizacionCapa::TiempoReal) {
            return false;
        }

        $proxima = $this->instanteProximaCadencia();

        if ($proxima === null) {
            return true;
        }

        $referencia = $ahora === null ? Carbon::now() : Carbon::parse($ahora);

        return $proxima->lessThanOrEqualTo($referencia);
    }

    public function instanteProximaCadencia(): ?Carbon
    {
        if ($this->frecuencia_actualizacion === FrecuenciaActualizacionCapa::TiempoReal) {
            return $this->fecha_ultima_sincronizacion?->copy();
        }

        $base = $this->fecha_ultima_sincronizacion ?? $this->fecha_corte_fuente;

        if ($base === null) {
            return null;
        }

        return match ($this->frecuencia_actualizacion) {
            FrecuenciaActualizacionCapa::Diaria => $base->copy()->addDay(),
            FrecuenciaActualizacionCapa::Semanal => $base->copy()->addDays(7),
            FrecuenciaActualizacionCapa::Mensual => $base->copy()->addMonth(),
            FrecuenciaActualizacionCapa::PorVersion => $base->copy()->addDays(7),
            FrecuenciaActualizacionCapa::TiempoReal => $base->copy(),
        };
    }

    public function textoProximaCadencia(): string
    {
        if ($this->frecuencia_actualizacion === FrecuenciaActualizacionCapa::TiempoReal) {
            return 'En cada consulta';
        }

        $proxima = $this->instanteProximaCadencia();

        if ($proxima === null) {
            return 'Sin fecha: el conector está pendiente';
        }

        $texto = $proxima->format('d/m/Y H:i');

        if ($this->frecuencia_actualizacion === FrecuenciaActualizacionCapa::PorVersion) {
            return $texto.' (revisión semanal)';
        }

        return $texto;
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(Fuente::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(AnalisisResultado::class);
    }

    public function objetosCache(): HasMany
    {
        return $this->hasMany(CapaObjetoCache::class);
    }

    public function refrescos(): HasMany
    {
        return $this->hasMany(CapaRefresco::class);
    }

    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(CapaSincronizacion::class);
    }

    public function scopeActiva(Builder $query): void
    {
        $query->where($this->qualifyColumn('activa'), true);
    }
}
