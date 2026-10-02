<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoRevisionPasiva;
use App\Enums\OrigenCambioTecho;
use App\Exceptions\CorteCerradoException;
use App\Models\PasivaCarga;
use App\Models\PasivaLinea;
use App\Models\Proyecto;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Models\TechoHistorial;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Techo(seguimiento, proyecto, fuente, dependencia) = Σ de la columna base (por defecto apropiación definitiva)
 * de las líneas asignadas de la pasiva vigente. Cada cambio queda en techo_historial.
 */
class CalculadoraTechos
{
    public const BASES = ['apropiacion_definitiva', 'apropiacion_inicial', 'cdp', 'compromisos'];

    public function __construct(private readonly AuditLogger $auditoria) {}

    public function base(): string
    {
        $base = (string) config('reporte_sectorial.base_techo', 'apropiacion_definitiva');

        if (! in_array($base, self::BASES, true)) {
            throw new InvalidArgumentException("Base de techo no admitida: {$base}");
        }

        return $base;
    }

    public function recalcular(Seguimiento $seguimiento, PasivaCarga $carga, ?User $usuario): void
    {
        $base = $carga->base_techo;

        if (! in_array($base, self::BASES, true)) {
            throw new InvalidArgumentException("Base de techo no admitida: {$base}");
        }

        DB::transaction(function () use ($seguimiento, $carga, $usuario, $base): void {
            $agregados = PasivaLinea::sinFiltroSectorial()
                ->where('pasiva_carga_id', $carga->id)
                ->where('estado_revision', EstadoRevisionPasiva::Asignada->value)
                ->groupBy('proyecto_id', 'fuente_financiacion_id', 'dependencia_id')
                ->selectRaw("proyecto_id, fuente_financiacion_id, dependencia_id, SUM({$base}) as total, COUNT(*) as lineas")
                ->get();

            $vistos = [];

            foreach ($agregados as $agregado) {
                $techo = Techo::sinFiltroSectorial()->firstOrNew([
                    'seguimiento_id' => $seguimiento->id,
                    'proyecto_id' => $agregado->proyecto_id,
                    'fuente_financiacion_id' => $agregado->fuente_financiacion_id,
                    'dependencia_id' => $agregado->dependencia_id,
                ]);

                $anterior = $techo->exists ? (string) $techo->valor : null;
                $teniaAjuste = $techo->valor_ajuste !== null;
                $techo->fill([
                    'valor_pasiva' => round((float) $agregado->total, 2),
                    'valor_ajuste' => null,
                    'base' => $base,
                    'pasiva_carga_id' => $carga->id,
                    'lineas_count' => (int) $agregado->lineas,
                ])->save();

                $vistos[] = $techo->id;

                PasivaLinea::sinFiltroSectorial()
                    ->where('pasiva_carga_id', $carga->id)
                    ->where('estado_revision', EstadoRevisionPasiva::Asignada->value)
                    ->where('proyecto_id', $agregado->proyecto_id)
                    ->where('fuente_financiacion_id', $agregado->fuente_financiacion_id)
                    ->where('dependencia_id', $agregado->dependencia_id)
                    ->update(['techo_id' => $techo->id]);

                if ($anterior === null || ! $this->iguales((float) $anterior, (float) $techo->valor)) {
                    $this->registrar($techo, $anterior, $carga, $usuario, $anterior === null
                        ? 'Techo calculado desde la pasiva '.$carga->nombre_original
                        : 'Techo recalculado por nueva pasiva '.$carga->nombre_original.($teniaAjuste ? ' (se retiró el ajuste manual anterior)' : ''));
                }
            }

            Techo::sinFiltroSectorial()
                ->where('seguimiento_id', $seguimiento->id)
                ->whereNotIn('id', $vistos === [] ? [0] : $vistos)
                ->get()
                ->each(function (Techo $techo) use ($carga, $usuario): void {
                    $anterior = (string) $techo->valor;
                    $techo->fill(['valor_pasiva' => 0, 'valor_ajuste' => null, 'pasiva_carga_id' => $carga->id, 'lineas_count' => 0])->save();

                    if (! $this->iguales((float) $anterior, 0.0)) {
                        $this->registrar($techo, $anterior, $carga, $usuario, 'El proyecto y la fuente ya no aparecen en la pasiva '.$carga->nombre_original);
                    }
                });

            $this->vincularDependencias($seguimiento);
        });
    }

    /**
     * Ajuste manual excepcional de la Gerencia: exige motivo y soporte, y queda trazado.
     */
    public function ajustar(Techo $techo, float $valor, string $motivo, UploadedFile $soporte, User $usuario): Techo
    {
        if ($techo->seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        $disk = (string) config('reporte_sectorial.disk');
        $sha256 = hash_file('sha256', (string) $soporte->getRealPath());
        $path = $soporte->store('reporte-sectorial/soportes-techo/'.$techo->seguimiento_id, $disk);

        return DB::transaction(function () use ($techo, $valor, $motivo, $usuario, $disk, $sha256, $path, $soporte): Techo {
            $anterior = (string) $techo->valor;
            $techo->update(['valor_ajuste' => round($valor, 2)]);

            TechoHistorial::create([
                'techo_id' => $techo->id,
                'seguimiento_id' => $techo->seguimiento_id,
                'dependencia_id' => $techo->dependencia_id,
                'valor_anterior' => $anterior,
                'valor_nuevo' => $techo->valor,
                'origen' => OrigenCambioTecho::AjusteManual,
                'motivo' => $motivo,
                'soporte_disk' => $disk,
                'soporte_path' => $path,
                'soporte_nombre_original' => $soporte->getClientOriginalName(),
                'soporte_sha256' => $sha256,
                'user_id' => $usuario->id,
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.techo_ajustado', $usuario, ['techo_id' => $techo->id, 'motivo' => $motivo], null, ['valor' => $anterior], ['valor' => (string) $techo->valor]);

            return $techo;
        });
    }

    public function techo(Seguimiento $seguimiento, int $proyectoId, int $fuenteId, int $dependenciaId): float
    {
        return (float) Techo::sinFiltroSectorial()
            ->where('seguimiento_id', $seguimiento->id)
            ->where('proyecto_id', $proyectoId)
            ->where('fuente_financiacion_id', $fuenteId)
            ->where('dependencia_id', $dependenciaId)
            ->value('valor');
    }

    private function iguales(float $primero, float $segundo): bool
    {
        return abs($primero - $segundo) < 0.005;
    }

    private function registrar(Techo $techo, ?string $anterior, PasivaCarga $carga, ?User $usuario, string $motivo): void
    {
        TechoHistorial::create([
            'techo_id' => $techo->id,
            'seguimiento_id' => $techo->seguimiento_id,
            'dependencia_id' => $techo->dependencia_id,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $techo->valor,
            'origen' => OrigenCambioTecho::CargaPasiva,
            'motivo' => $motivo,
            'pasiva_carga_id' => $carga->id,
            'user_id' => $usuario?->id,
        ]);
    }

    /**
     * Relaciona cada proyecto con las dependencias a las que la pasiva le asignó recursos.
     * La dependencia con mayor techo queda como responsable principal si el proyecto aún no tiene una.
     */
    private function vincularDependencias(Seguimiento $seguimiento): void
    {
        $totales = Techo::sinFiltroSectorial()
            ->where('seguimiento_id', $seguimiento->id)
            ->where('valor', '>', 0)
            ->groupBy('proyecto_id', 'dependencia_id')
            ->selectRaw('proyecto_id, dependencia_id, SUM(valor) as total')
            ->orderByDesc('total')
            ->get()
            ->groupBy('proyecto_id');

        foreach ($totales as $proyectoId => $porDependencia) {
            $proyecto = Proyecto::sinFiltroSectorial()->find($proyectoId);

            if ($proyecto === null) {
                continue;
            }

            $tienePrincipal = $proyecto->dependencias()->wherePivot('es_responsable_principal', true)->exists();
            $existentes = $proyecto->dependencias()->pluck('dependencias.id')->all();

            foreach ($porDependencia->values() as $indice => $fila) {
                if (in_array($fila->dependencia_id, $existentes)) {
                    continue;
                }

                $proyecto->dependencias()->attach($fila->dependencia_id, [
                    'es_responsable_principal' => ! $tienePrincipal && $indice === 0,
                    'origen' => 'pasiva',
                ]);
            }
        }
    }
}
