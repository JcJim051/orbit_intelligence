<?php

namespace App\Models;

use App\Jobs\SincronizarPasivaAnalitica;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use App\Services\Analitica\SincronizadorPasivaVigente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

/**
 * Archivo de pasiva (ejecución presupuestal del PCT) adjunto a un seguimiento. Solo la Gerencia lo ve.
 */
class PasivaCarga extends Model
{
    use SeCongelaAlCerrarSeguimiento;

    protected $table = 'pasiva_cargas';

    protected $fillable = [
        'seguimiento_id',
        'disk',
        'path',
        'nombre_original',
        'mime',
        'bytes',
        'sha256',
        'base_techo',
        'es_vigente',
        'lineas_total',
        'lineas_inversion',
        'lineas_pendientes',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'es_vigente' => 'boolean',
            'lineas_total' => 'integer',
            'lineas_inversion' => 'integer',
            'lineas_pendientes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $despachar = function (PasivaCarga $carga): void {
            if (! $carga->es_vigente) {
                return;
            }

            if (config('queue.default') === 'sync') {
                try {
                    app(SincronizadorPasivaVigente::class)->sincronizar($carga);
                } catch (Throwable $exception) {
                    app(ImportadorPasivaAnalitica::class)->registrarError($carga, 'No se pudo sincronizar la pasiva a analitica.');
                    report($exception);
                }

                return;
            }

            SincronizarPasivaAnalitica::dispatch($carga->id)->afterCommit();
        };

        static::created($despachar);

        static::updated(function (PasivaCarga $carga) use ($despachar): void {
            if ($carga->wasChanged('es_vigente')) {
                $despachar($carga);
            }
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

    public function lineas(): HasMany
    {
        return $this->hasMany(PasivaLinea::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
