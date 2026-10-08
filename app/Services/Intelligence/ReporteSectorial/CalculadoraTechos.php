<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoRevisionPasiva;
use App\Enums\OrigenCambioTecho;
use App\Exceptions\CorteCerradoException;
use App\Models\Dependencia;
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
use Illuminate\Validation\ValidationException;
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
                ->selectRaw("proyecto_id, fuente_financiacion_id, dependencia_id, SUM({$base}) as total, SUM(compromisos) as total_comprometido, SUM(obligaciones) as total_obligado, SUM(pagos) as total_pagado, COUNT(*) as lineas")
                ->get();

            $vistos = [];

            foreach ($agregados as $agregado) {
                $techo = Techo::sinFiltroSectorial()->withTrashed()->firstOrNew([
                    'seguimiento_id' => $seguimiento->id,
                    'proyecto_id' => $agregado->proyecto_id,
                    'fuente_financiacion_id' => $agregado->fuente_financiacion_id,
                    'dependencia_id' => $agregado->dependencia_id,
                ]);

                $anterior = $this->snapshot($techo);
                $teniaAjuste = $techo->exists && $techo->tieneAjuste();
                $estabaRetirado = $techo->trashed();

                if ($estabaRetirado) {
                    $techo->restore();
                }

                $techo->fill([
                    'valor_pasiva' => round((float) $agregado->total, 2),
                    'valor_ajuste' => null,
                    'comprometido_pasiva' => round((float) $agregado->total_comprometido, 2),
                    'comprometido_ajuste' => null,
                    'obligado_pasiva' => round((float) $agregado->total_obligado, 2),
                    'obligado_ajuste' => null,
                    'pagado_pasiva' => round((float) $agregado->total_pagado, 2),
                    'pagado_ajuste' => null,
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

                if ($anterior === null || $estabaRetirado || $this->cambio($techo, $anterior)) {
                    $motivo = $anterior === null
                        ? 'Techo calculado desde la pasiva '.$carga->nombre_original
                        : 'Techo recalculado por nueva pasiva '.$carga->nombre_original.($teniaAjuste ? ' (se retiró el ajuste manual anterior)' : '');

                    if ($estabaRetirado) {
                        $motivo .= ' (se restauró una fuente que había sido retirada)';
                    }

                    $this->registrar($techo, $anterior, OrigenCambioTecho::CargaPasiva, $motivo, $carga, $usuario);
                }
            }

            Techo::sinFiltroSectorial()
                ->where('seguimiento_id', $seguimiento->id)
                ->whereNotIn('id', $vistos === [] ? [0] : $vistos)
                ->get()
                ->each(function (Techo $techo) use ($carga, $usuario): void {
                    $anterior = $this->snapshot($techo);
                    $techo->fill([
                        'valor_pasiva' => 0,
                        'valor_ajuste' => null,
                        'comprometido_pasiva' => 0,
                        'comprometido_ajuste' => null,
                        'obligado_pasiva' => 0,
                        'obligado_ajuste' => null,
                        'pagado_pasiva' => 0,
                        'pagado_ajuste' => null,
                        'pasiva_carga_id' => $carga->id,
                        'lineas_count' => 0,
                    ])->save();

                    if ($anterior !== null && $this->cambio($techo, $anterior)) {
                        $this->registrar($techo, $anterior, OrigenCambioTecho::CargaPasiva, 'El proyecto y la fuente ya no aparecen en la pasiva '.$carga->nombre_original, $carga, $usuario);
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
            $anterior = $this->snapshot($techo);
            $techo->update(['valor_ajuste' => round($valor, 2)]);
            $this->registrar($techo, $anterior, OrigenCambioTecho::AjusteManual, $motivo, null, $usuario, [
                'soporte_disk' => $disk,
                'soporte_path' => $path,
                'soporte_nombre_original' => $soporte->getClientOriginalName(),
                'soporte_sha256' => $sha256,
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.techo_ajustado', $usuario, ['techo_id' => $techo->id, 'motivo' => $motivo], null, ['valor' => $anterior['valor'] ?? null], ['valor' => (string) $techo->valor]);

            return $techo;
        });
    }

    /**
     * Crea o corrige la fuente y los cuatro valores de un techo. El agregado de la pasiva se conserva
     * y lo digitado queda como ajuste, con motivo y usuario en el historial.
     *
     * @param  array{fuente_financiacion_id: int, asignado: float, comprometido: float, obligado: float, pagado: float, motivo: string}  $datos
     */
    public function corregir(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia, ?Techo $techo, array $datos, User $usuario): Techo
    {
        if ($seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        return DB::transaction(function () use ($seguimiento, $proyecto, $dependencia, $techo, $datos, $usuario): Techo {
            $fuenteId = (int) $datos['fuente_financiacion_id'];
            $conflicto = Techo::sinFiltroSectorial()->withTrashed()
                ->where('seguimiento_id', $seguimiento->id)
                ->where('proyecto_id', $proyecto->id)
                ->where('dependencia_id', $dependencia->id)
                ->where('fuente_financiacion_id', $fuenteId)
                ->when($techo !== null, fn ($query) => $query->whereKeyNot($techo->id))
                ->first();

            if ($conflicto !== null) {
                if ($techo === null && $conflicto->trashed()) {
                    $conflicto->restore();
                    $techo = $conflicto;
                } else {
                    throw ValidationException::withMessages([
                        'fuente_financiacion_id' => 'Esa fuente ya tiene un techo en este proyecto y dependencia.',
                    ]);
                }
            }

            if ($techo === null) {
                $techo = new Techo([
                    'seguimiento_id' => $seguimiento->id,
                    'proyecto_id' => $proyecto->id,
                    'dependencia_id' => $dependencia->id,
                    'fuente_financiacion_id' => $fuenteId,
                    'base' => $this->base(),
                    'lineas_count' => 0,
                    'valor_pasiva' => 0,
                    'comprometido_pasiva' => 0,
                    'obligado_pasiva' => 0,
                    'pagado_pasiva' => 0,
                ]);
            }

            $anterior = $this->snapshot($techo);
            $techo->fill([
                'fuente_financiacion_id' => $fuenteId,
                'valor_ajuste' => round((float) $datos['asignado'], 2),
                'comprometido_ajuste' => round((float) $datos['comprometido'], 2),
                'obligado_ajuste' => round((float) $datos['obligado'], 2),
                'pagado_ajuste' => round((float) $datos['pagado'], 2),
            ])->save();

            $this->registrar($techo, $anterior, OrigenCambioTecho::CorreccionFuente, $datos['motivo'], null, $usuario);
            $this->auditoria->log(null, 'reporte_sectorial.techo_fuente_corregida', $usuario, [
                'techo_id' => $techo->id,
                'motivo' => $datos['motivo'],
                'fuente_financiacion_id' => $fuenteId,
            ], null, $anterior ?? [], $this->snapshot($techo) ?? []);

            return $techo;
        });
    }

    public function eliminar(Techo $techo, string $motivo, User $usuario): void
    {
        if ($techo->seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        DB::transaction(function () use ($techo, $motivo, $usuario): void {
            $anterior = $this->snapshot($techo);
            PasivaLinea::sinFiltroSectorial()->where('techo_id', $techo->id)->update(['techo_id' => null]);

            TechoHistorial::create([
                'techo_id' => $techo->id,
                'seguimiento_id' => $techo->seguimiento_id,
                'dependencia_id' => $techo->dependencia_id,
                'valor_anterior' => $anterior['valor'] ?? null,
                'valor_nuevo' => 0,
                'comprometido_anterior' => $anterior['comprometido'] ?? null,
                'comprometido_nuevo' => 0,
                'obligado_anterior' => $anterior['obligado'] ?? null,
                'obligado_nuevo' => 0,
                'pagado_anterior' => $anterior['pagado'] ?? null,
                'pagado_nuevo' => 0,
                'origen' => OrigenCambioTecho::CorreccionFuente,
                'motivo' => $motivo,
                'user_id' => $usuario->id,
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.techo_fuente_eliminada', $usuario, [
                'techo_id' => $techo->id,
                'motivo' => $motivo,
                'fuente_financiacion_id' => $techo->fuente_financiacion_id,
            ], null, $anterior ?? [], ['valor' => '0', 'comprometido' => '0', 'obligado' => '0', 'pagado' => '0']);

            $techo->delete();
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

    /**
     * @return array{valor: string, comprometido: string, obligado: string, pagado: string}|null
     */
    private function snapshot(Techo $techo): ?array
    {
        if (! $techo->exists) {
            return null;
        }

        return [
            'valor' => (string) $techo->valor,
            'comprometido' => (string) $techo->comprometido,
            'obligado' => (string) $techo->obligado,
            'pagado' => (string) $techo->pagado,
        ];
    }

    /**
     * @param  array{valor: string, comprometido: string, obligado: string, pagado: string}  $anterior
     */
    private function cambio(Techo $techo, array $anterior): bool
    {
        foreach (['valor', 'comprometido', 'obligado', 'pagado'] as $campo) {
            if (! $this->iguales((float) $anterior[$campo], (float) $techo->{$campo})) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{valor: string, comprometido: string, obligado: string, pagado: string}|null  $anterior
     * @param  array<string, mixed>  $extra
     */
    private function registrar(Techo $techo, ?array $anterior, OrigenCambioTecho $origen, string $motivo, ?PasivaCarga $carga, ?User $usuario, array $extra = []): void
    {
        TechoHistorial::create([
            'techo_id' => $techo->id,
            'seguimiento_id' => $techo->seguimiento_id,
            'dependencia_id' => $techo->dependencia_id,
            'valor_anterior' => $anterior['valor'] ?? null,
            'valor_nuevo' => $techo->valor,
            'comprometido_anterior' => $anterior['comprometido'] ?? null,
            'comprometido_nuevo' => $techo->comprometido,
            'obligado_anterior' => $anterior['obligado'] ?? null,
            'obligado_nuevo' => $techo->obligado,
            'pagado_anterior' => $anterior['pagado'] ?? null,
            'pagado_nuevo' => $techo->pagado,
            'origen' => $origen,
            'motivo' => $motivo,
            'pasiva_carga_id' => $carga?->id,
            'user_id' => $usuario?->id,
        ] + $extra);
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
