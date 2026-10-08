@extends('layouts.app', ['title' => $proyecto->bpin.' · Reporte mensual · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))
@php($rutaBase = [$seguimiento, $proyecto, 'dependencia' => $reporte->dependencia_id])
@php($rubrosFinancieros = [
    'asignado' => ['label' => 'Asignado', 'techo' => 'techo_asignado_pasiva'],
    'comprometido' => ['label' => 'Comprometido', 'techo' => 'techo_comprometido_pasiva'],
    'obligado' => ['label' => 'Obligado', 'techo' => 'techo_obligado_pasiva'],
    'pagado' => ['label' => 'Pagado', 'techo' => 'techo_pagado_pasiva'],
])
@php($techoRubro = fn ($techo, string $campo): float => (float) ($techo->{$campo} ?? 0))
@php($totalTechoPorRubro = collect($rubrosFinancieros)->mapWithKeys(fn (array $rubro, string $clave): array => [$clave => (float) $techos->sum(fn ($techo) => $techoRubro($techo, $rubro['techo']))]))
@php($totalProgramadoFinanciero = (float) $actividades->sum(fn ($actividad) => (float) $actividad->programaciones->whereIn('fuente_financiacion_id', $techos->pluck('fuente_financiacion_id'))->sum('valor_asignado')))
@php($totalComprometido = (float) $ejecuciones->sum('comprometido'))
@php($totalObligado = (float) $ejecuciones->sum('obligado'))
@php($totalPagado = (float) $ejecuciones->sum('pagado'))
@php($totalReportadoPorRubro = [
    'asignado' => $totalProgramadoFinanciero,
    'comprometido' => $totalComprometido,
    'obligado' => $totalObligado,
    'pagado' => $totalPagado,
])
@php($diferenciasTechoReporte = collect($rubrosFinancieros)->mapWithKeys(fn (array $rubro, string $clave): array => [$clave => [
    'label' => $rubro['label'],
    'diferencia' => (float) ($totalTechoPorRubro[$clave] ?? 0) - (float) ($totalReportadoPorRubro[$clave] ?? 0),
]])->filter(fn (array $dato): bool => abs($dato['diferencia']) > 0.01))
@php($porcentajeEjecucionTecho = (float) ($totalTechoPorRubro['asignado'] ?? 0) > 0 ? ((float) ($totalTechoPorRubro['comprometido'] ?? 0) / (float) $totalTechoPorRubro['asignado']) * 100 : 0)
@php($porcentajeEjecucionReportada = $totalProgramadoFinanciero > 0 ? ($totalComprometido / $totalProgramadoFinanciero) * 100 : 0)

@section('content')
<div class="space-y-4">
    @if($filamentEmbedded ?? false)
        @if(session('status'))
            <div class="flash-message is-success" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="flash-message is-error" role="alert">
                <strong>No fue posible guardar. Revise estos valores:</strong>
                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
    @endif

    <header class="sticky top-16 z-20 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div class="min-w-0 pr-2">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Reporte mensual · {{ $seguimiento->etiqueta() }}</p>
                    <span class="status {{ $reporte->estado->cssClass() }}">{{ $reporte->estado->label() }}</span>
                </div>
                <h1 class="mt-1 text-[15px] font-black leading-snug text-slate-950 sm:text-base" title="{{ $proyecto->etiqueta() }}">{{ $proyecto->bpin }} — {{ $proyecto->nombre }}</h1>
                <p class="mt-1 text-xs leading-snug text-slate-500">
                    {{ $reporte->dependencia->etiqueta() }} · Focalización: {{ $proyecto->tipo_focalizacion?->label() ?? 'por definir' }}
                    @if($proyecto->tipo_focalizacion === App\Enums\TipoFocalizacion::Municipal && $proyecto->municipio) · {{ $proyecto->municipio->codigo_dane }} — {{ $proyecto->municipio->nombre }} @endif
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-2 xl:justify-end">
            @if($dependencias->count() > 1)
                <form method="get" class="flex items-end gap-2">
                    <label class="field"><span>Dependencia</span>
                        <select name="dependencia" onchange="this.form.submit()">
                            @foreach($dependencias as $dependencia)
                                <option value="{{ $dependencia->id }}" @selected($dependencia->id === $reporte->dependencia_id)>{{ $dependencia->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            @endif
            <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'seguimiento', 'record' => $seguimiento->getRouteKey(), 'dependencia' => $reporte->dependencia_id]) }}">Volver</a>
            @if($puedeEditar)
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.enviar', $rutaBase) }}">
                    @csrf
                    <button class="btn-primary" @disabled($pendientes['mensajes'] !== [])>Enviar reporte</button>
                </form>
            @endif
            </div>
        </div>
        @if($reporte->observacion_revision)
            <details class="mt-2 text-xs text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">Ver observación de devolución</summary>
                <p class="mt-1 rounded-xl bg-amber-50 p-3 text-amber-900">{{ $reporte->observacion_revision }}</p>
            </details>
        @endif
    </header>

    @if($pendientes['mensajes'] !== [] && $puedeEditar)
        <section class="panel border-amber-200 bg-amber-50">
            <h2 class="text-base font-semibold text-amber-900">Pendientes para enviar</h2>
            <ul class="mt-2 list-disc pl-5 text-sm text-amber-900">
                @foreach($pendientes['mensajes'] as $mensaje)<li>{{ $mensaje }}</li>@endforeach
            </ul>
        </section>
    @endif

    @if($puedeRevisar)
        <section class="panel">
            <h2 class="text-lg font-semibold">Revisión de la Gerencia</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.aprobar', $rutaBase) }}" class="space-y-2">
                    @csrf
                    <label class="field"><span>Observación (opcional)</span><textarea name="observacion" rows="2"></textarea></label>
                    <button class="btn-primary">Aprobar</button>
                </form>
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.devolver', $rutaBase) }}" class="space-y-2">
                    @csrf
                    <label class="field"><span>Motivo de la devolución</span><textarea name="observacion" rows="2" required></textarea></label>
                    <button class="btn-danger">Devolver al sector</button>
                </form>
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-0.5 border-b border-slate-100 px-3 py-1.5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-950">Techos por fuente</h2>
                <p class="text-slate-500" style="font-size: 11px;">Valores de referencia derivados de la pasiva PCT vigente.</p>
            </div>
            <p class="text-slate-400" style="font-size: 11px;">{{ number_format($techos->count(), 0, ',', '.') }} fuente(s)</p>
        </div>
        <div class="p-2">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <div style="min-width: 920px;">
                    <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-3 py-1.5 font-semibold uppercase tracking-wide text-slate-500"
                         style="grid-template-columns: minmax(220px, 1.8fr) repeat(4, minmax(100px, 1fr)) minmax(90px, .7fr) 70px; font-size: 10px;">
                        <span>Fuente de financiación</span>
                        @foreach($rubrosFinancieros as $rubro)
                            <span>{{ $rubro['label'] }}</span>
                        @endforeach
                        <span>% ejecución</span>
                        <span class="text-right">Acciones</span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($techos as $techo)
                            @php($asignadoTechoFuente = $techoRubro($techo, 'techo_asignado_pasiva'))
                            @php($comprometidoTechoFuente = $techoRubro($techo, 'techo_comprometido_pasiva'))
                            @php($porcentajeTechoFuente = $asignadoTechoFuente > 0 ? ($comprometidoTechoFuente / $asignadoTechoFuente) * 100 : 0)
                            <div class="grid items-center gap-3 px-3 py-1.5"
                                 style="grid-template-columns: minmax(220px, 1.8fr) repeat(4, minmax(100px, 1fr)) minmax(90px, .7fr) 70px;">
                            <div class="min-w-0">
                                <div class="min-w-0">
                                    <div class="font-semibold leading-tight text-slate-900" style="font-size: 11px;">{{ $techo->fuente->etiqueta() }}</div>
                                    <div class="flex flex-wrap items-center gap-1.5 text-slate-500" style="font-size: 9px;">
                                        <span>{{ number_format((int) $techo->lineas_count, 0, ',', '.') }} línea(s) de pasiva</span>
                                        @if($techo->valor_ajuste !== null)<span class="status status-pending_review">Ajustado</span>@endif
                                    </div>
                                </div>
                            </div>

                                @foreach($rubrosFinancieros as $rubro)
                                    @php($techoValor = $techoRubro($techo, $rubro['techo']))
                                    <strong class="whitespace-nowrap text-slate-950" style="font-size: 12px;">{{ $pesos($techoValor) }}</strong>
                                @endforeach

                                <strong class="whitespace-nowrap text-slate-950" style="font-size: 12px;">{{ number_format($porcentajeTechoFuente, 1, ',', '.') }}%</strong>

                                <div class="flex shrink-0 items-center justify-end gap-1.5">
                                    @if($techo->historial->count() > 0)
                                        <button type="button"
                                                class="count-detail-trigger inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-indigo-700 shadow-sm hover:bg-indigo-50"
                                                data-count-detail-url="{{ route('intelligence.reporte-mensual.proyectos.detalle', $rutaBase + ['relacion' => 'historial_techos', 'techo' => $techo->id]) }}"
                                                aria-haspopup="dialog"
                                                aria-controls="count-detail-dialog"
                                                title="Ver historial del techo">
                                            <span class="sr-only">Ver historial del techo</span>
                                            <span class="text-xs font-black">{{ $techo->historial->count() }}</span>
                                        </button>
                                    @else
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-400" title="Sin historial">0</span>
                                    @endif
                                    @can('ajustar', $techo)
                                        <button type="button"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100"
                                                data-adjustment-modal-target="ajuste-techo-{{ $techo->id }}"
                                                title="Ajuste excepcional">
                                            <span class="sr-only">Ajuste excepcional</span>
                                            ✎
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-slate-500">Este proyecto no tiene techos por fuente registrados.</p>
                        @endforelse
                    </div>

                    @if($techos->isNotEmpty())
                        <div class="grid items-center gap-3 border-t border-slate-200 bg-slate-50 px-3 py-1.5"
                             style="grid-template-columns: minmax(220px, 1.8fr) repeat(4, minmax(100px, 1fr)) minmax(90px, .7fr) 70px; font-size: 11px;">
                            <strong class="text-slate-950">Total del techo</strong>
                            @foreach($rubrosFinancieros as $clave => $rubro)
                                <strong class="whitespace-nowrap text-slate-950">{{ $pesos((float) ($totalTechoPorRubro[$clave] ?? 0)) }}</strong>
                            @endforeach
                            <strong class="whitespace-nowrap text-slate-950">{{ number_format($porcentajeEjecucionTecho, 1, ',', '.') }}%</strong>
                            <span></span>
                        </div>
                        <div class="grid items-center gap-3 border-t border-slate-200 bg-indigo-50/60 px-3 py-1.5"
                             style="grid-template-columns: minmax(220px, 1.8fr) repeat(4, minmax(100px, 1fr)) minmax(90px, .7fr) 70px; font-size: 11px;">
                            <strong class="text-indigo-950">Total reportado por metas</strong>
                            <strong class="whitespace-nowrap text-indigo-950">{{ $pesos($totalProgramadoFinanciero) }}</strong>
                            <strong class="whitespace-nowrap text-indigo-950">{{ $pesos($totalComprometido) }}</strong>
                            <strong class="whitespace-nowrap text-indigo-950">{{ $pesos($totalObligado) }}</strong>
                            <strong class="whitespace-nowrap text-indigo-950">{{ $pesos($totalPagado) }}</strong>
                            <strong class="whitespace-nowrap text-indigo-950">{{ number_format($porcentajeEjecucionReportada, 1, ',', '.') }}%</strong>
                            <span></span>
                        </div>
                        @if($diferenciasTechoReporte->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-amber-200 bg-amber-50 px-3 py-2 text-amber-900" role="alert" style="font-size: 10px;">
                                <strong class="shrink-0">⚠ El reporte por metas no coincide con el techo.</strong>
                                @foreach($diferenciasTechoReporte as $dato)
                                    <span>
                                        <strong>{{ $dato['label'] }}:</strong>
                                        {{ $dato['diferencia'] > 0 ? 'faltan' : 'excede por' }}
                                        {{ $pesos(abs($dato['diferencia'])) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @foreach($techos as $techo)
            @can('ajustar', $techo)
                <dialog id="ajuste-techo-{{ $techo->id }}" class="w-full max-w-xl rounded-3xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50">
                    <form method="post" action="{{ route('intelligence.reporte-mensual.techos.ajustar', $techo) }}" enctype="multipart/form-data" class="bg-white">
                        @csrf
                        <div class="border-b border-slate-100 px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="eyebrow">Ajuste excepcional</p>
                                    <h3 class="text-xl font-bold text-slate-950">{{ $techo->fuente->etiqueta() }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">Use esta opción solo si el techo calculado desde la pasiva requiere corrección soportada.</p>
                                </div>
                                <button type="button" class="btn-small" data-adjustment-modal-close>Cerrar</button>
                            </div>
                        </div>
                        <div class="space-y-4 px-6 py-5">
                            <label class="field"><span>Nuevo techo</span><input type="number" step="0.01" min="0" name="valor" value="{{ old('valor', $techo->valor) }}" required></label>
                            <label class="field"><span>Motivo</span><textarea name="motivo" rows="3" required minlength="10">{{ old('motivo') }}</textarea></label>
                            <label class="field"><span>Soporte</span><input type="file" name="soporte" required></label>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-secondary" data-adjustment-modal-close>Cancelar</button>
                                <button class="btn-primary">Guardar ajuste</button>
                            </div>
                        </div>
                    </form>
                </dialog>
            @endcan
        @endforeach
    </section>

    <section class="panel space-y-3" style="padding: 12px;">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-bold text-slate-950">Reporte de Ejecución por meta producto</h2>
            <span class="text-slate-500" style="font-size: 11px;">Guarde cada meta por separado. El sistema controla los techos acumulados por fuente.</span>
        </div>
        @if($actividades->isEmpty())
            <p class="text-sm text-slate-500">Primero vincule el proyecto con sus metas producto desde la carga masiva de relaciones.</p>
        @else
            <div class="space-y-3">
                @php($fila = 0)
                @foreach($actividades as $actividad)
                        @php($avance = $avances->get($actividad->id))
                        @php($cantidadProgramadaFisica = (float) ($actividad->cantidad_programada ?? 0))
                        @php($avanceActualFisico = $avance ? (float) $avance->cantidad : 0.0)
                        @php($porcentajeFisico = $cantidadProgramadaFisica > 0 ? ($avanceActualFisico / $cantidadProgramadaFisica) * 100 : 0)
                        @php($formatoFisicoResumen = fn (float $valor): string => rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',') ?: '0')
                        @php($programadoActividad = (float) $actividad->programaciones->whereIn('fuente_financiacion_id', $techos->pluck('fuente_financiacion_id'))->sum('valor_asignado'))
                        @php($comprometidoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->comprometido ?? 0)))
                        @php($obligadoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->obligado ?? 0)))
                        @php($pagadoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->pagado ?? 0)))
                        @php($porcentajeFinanciero = $programadoActividad > 0 ? ($comprometidoActividad / $programadoActividad) * 100 : 0)
                        @php($metaFinancieraGuardada = $techos->every(fn ($techo): bool => $ejecuciones->has($actividad->id.'-'.$techo->fuente_financiacion_id)))
                        <details class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-3 py-2 marker:hidden">
                                <div class="min-w-0">
                                    <h3 class="font-semibold leading-snug text-slate-900" style="font-size: 13px;" title="{{ $actividad->metaProducto?->codigo ? $actividad->metaProducto->codigo.' — '.$actividad->metaProducto->nombre : $actividad->etiqueta() }}">
                                        {{ $actividad->metaProducto?->codigo ? $actividad->metaProducto->codigo.' — '.$actividad->metaProducto->nombre : $actividad->etiqueta() }}
                                    </h3>
                                    <p class="mt-0.5 text-slate-500" style="font-size: 10px;">
                                        Avance financiero {{ number_format($porcentajeFinanciero, 1, ',', '.') }}% · Meta física {{ number_format($porcentajeFisico, 1, ',', '.') }}%
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="status {{ $metaFinancieraGuardada ? 'status-approved' : 'status-pending_review' }}">{{ $metaFinancieraGuardada ? 'Financiero guardado' : 'Financiero pendiente' }}</span>
                                    <button type="button" class="btn-small" data-financial-toggle>
                                        {{ $puedeEditar ? 'Reportar avance financiero' : 'Ver avance financiero' }}
                                    </button>
                                    <button type="button" class="btn-small" data-physical-modal-target="meta-fisica-{{ $actividad->id }}">
                                        {{ $puedeEditar ? 'Reportar avance físico' : 'Ver avance físico' }}
                                    </button>
                                </div>
                            </summary>
                            <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.ejecucion.update', $rutaBase) }}" class="p-3">
                                @csrf
                                @method('PUT')
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <div style="min-width: 850px;">
                                        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 font-semibold uppercase tracking-wide text-slate-500"
                                             style="grid-template-columns: minmax(230px, 1.6fr) repeat(4, minmax(125px, 1fr)); font-size: 10px;">
                                            <span>Fuente de financiación</span>
                                            <span>Programado</span>
                                            <span>Comprometido</span>
                                            <span>Obligado</span>
                                            <span>Pagado</span>
                                        </div>
                                        <div class="divide-y divide-slate-100">
                                            @foreach($techos as $techo)
                                                @php($ejecucion = $ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id))
                                                @php($programado = $actividad->programaciones->firstWhere('fuente_financiacion_id', $techo->fuente_financiacion_id))
                                                @php($programadoValor = (float) ($programado?->valor_asignado ?? 0))
                                                <div class="grid items-center gap-3 px-3 py-2"
                                                     style="grid-template-columns: minmax(230px, 1.6fr) repeat(4, minmax(125px, 1fr));">
                                                    <div class="min-w-0">
                                                        <p class="font-semibold leading-snug text-slate-900" style="font-size: 11px;" title="{{ $techo->fuente->etiqueta() }}">{{ $techo->fuente->etiqueta() }}</p>
                                                        <input type="hidden" name="ejecucion[{{ $fila }}][actividad_id]" value="{{ $actividad->id }}">
                                                        <input type="hidden" name="ejecucion[{{ $fila }}][fuente_financiacion_id]" value="{{ $techo->fuente_financiacion_id }}">
                                                    </div>
                                                    <label>
                                                        <span class="sr-only">Programado</span>
                                                        <input class="w-full rounded-lg border border-slate-300 bg-white px-2 text-right text-slate-950"
                                                               style="height: 32px; font-size: 12px;"
                                                               type="number"
                                                               step="0.01"
                                                               min="0"
                                                               name="ejecucion[{{ $fila }}][programado]"
                                                               value="{{ old("ejecucion.$fila.programado", $programadoValor) }}"
                                                               @disabled(! $puedeEditar)>
                                                    </label>
                                                    @foreach(['comprometido' => 'Comprometido', 'obligado' => 'Obligado', 'pagado' => 'Pagado'] as $campo => $label)
                                                        <label>
                                                            <span class="sr-only">{{ $label }}</span>
                                                            <input class="w-full rounded-lg border border-slate-300 bg-white px-2 text-right text-slate-950"
                                                                   style="height: 32px; font-size: 12px;"
                                                                   type="number"
                                                                   step="0.01"
                                                                   min="0"
                                                                   name="ejecucion[{{ $fila }}][{{ $campo }}]"
                                                                   value="{{ old("ejecucion.$fila.$campo", $ejecucion?->{$campo} ?? 0) }}"
                                                                   @disabled(! $puedeEditar)>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @php($fila++)
                                            @endforeach
                                        </div>
                                        <div class="grid items-center gap-3 border-t border-slate-200 bg-slate-50 px-3 py-2"
                                             style="grid-template-columns: minmax(230px, 1.6fr) repeat(4, minmax(125px, 1fr)); font-size: 11px;">
                                            <strong class="text-slate-950">Total meta producto · {{ number_format($porcentajeFinanciero, 1, ',', '.') }}%</strong>
                                            <strong class="whitespace-nowrap">{{ $pesos($programadoActividad) }}</strong>
                                            <strong class="whitespace-nowrap">{{ $pesos($comprometidoActividad) }}</strong>
                                            <strong class="whitespace-nowrap">{{ $pesos($obligadoActividad) }}</strong>
                                            <strong class="whitespace-nowrap">{{ $pesos($pagadoActividad) }}</strong>
                                        </div>
                                    </div>
                                </div>
                                @if($puedeEditar)
                                    <div class="mt-2 flex justify-end">
                                        <button class="btn-primary">Guardar esta meta producto</button>
                                    </div>
                                @endif
                            </form>
                        </details>
                @endforeach
            </div>
        @endif
    </section>

    @foreach($actividades as $actividad)
        @php($avance = $avances->get($actividad->id))
        @php($cantidadProgramadaFisica = (float) ($actividad->cantidad_programada ?? 0))
        @php($avanceActualFisico = $avance ? (float) $avance->cantidad : 0.0)
        @php($saldoFisico = max(0, $cantidadProgramadaFisica - $avanceActualFisico))
        @php($formatoFisico = fn (float $valor): string => rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',') ?: '0')
        <dialog id="meta-fisica-{{ $actividad->id }}"
                class="w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/55"
                style="position: fixed; inset: 0; margin: auto; width: min(920px, calc(100vw - 32px)); max-height: calc(100vh - 32px);">
            <div class="flex max-h-[calc(100vh-32px)] flex-col bg-white">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-bold uppercase tracking-[0.16em] text-emerald-700" style="font-size: 9px;">Reporte de avance físico</p>
                        <h2 class="mt-0.5 font-bold leading-snug text-slate-950" style="font-size: 14px;">
                            {{ $actividad->metaProducto?->codigo ? $actividad->metaProducto->codigo.' — '.$actividad->metaProducto->nombre : $actividad->etiqueta() }}
                        </h2>
                    </div>
                    <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 text-lg font-bold text-slate-500 hover:bg-slate-50" data-physical-modal-close aria-label="Cerrar">×</button>
                </div>

                <div class="space-y-3 overflow-y-auto p-4">
                    <dl class="grid rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                        style="grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 12px;">
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500" style="font-size: 9px;">Programado físico</dt><dd class="mt-0.5 font-bold text-slate-950" style="font-size: 12px;">{{ $formatoFisico($cantidadProgramadaFisica) }} {{ $actividad->unidad_medida }}</dd></div>
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500" style="font-size: 9px;">Reportado</dt><dd class="mt-0.5 font-bold text-slate-950" style="font-size: 12px;">{{ $formatoFisico($avanceActualFisico) }} {{ $actividad->unidad_medida }}</dd></div>
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500" style="font-size: 9px;">Saldo</dt><dd class="mt-0.5 font-bold text-slate-950" style="font-size: 12px;">{{ $formatoFisico($saldoFisico) }} {{ $actividad->unidad_medida }}</dd></div>
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500" style="font-size: 9px;">Fecha del reporte</dt><dd class="mt-0.5 font-bold text-slate-950" style="font-size: 12px;">{{ $avance?->fecha_ejecucion?->format('d/m/Y') ?? 'Sin reporte' }}</dd></div>
                    </dl>

                    @if($avance?->descripcion)
                        <div class="rounded-xl border border-slate-200 px-3 py-2 text-slate-700" style="font-size: 11px;"><strong>Descripción:</strong> {{ $avance->descripcion }}</div>
                    @endif

                    @if($avance && $avance->evidencias->isNotEmpty())
                        <section class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-1.5">
                                <h3 class="font-semibold uppercase tracking-wide text-slate-600" style="font-size: 10px;">Archivos cargados</h3>
                                <span class="font-semibold text-slate-500" style="font-size: 10px;">{{ $avance->evidencias->count() }} archivo(s)</span>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                @foreach($avance->evidencias as $evidencia)
                                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900" style="font-size: 11px;" title="{{ $evidencia->nombre_original }}">{{ $evidencia->nombre_original }}</p>
                                            <p class="text-slate-500" style="font-size: 9px;">{{ number_format($evidencia->bytes / 1024, 0, ',', '.') }} KB · {{ $evidencia->subidoPor?->name }} · {{ $evidencia->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            <a class="btn-small" href="{{ route('intelligence.reporte-mensual.evidencias.preview', $evidencia) }}" target="_blank" rel="noopener">Ver archivo</a>
                                            <a class="btn-small" href="{{ route('intelligence.reporte-mensual.evidencias.show', $evidencia) }}">Descargar</a>
                                            @can('delete', $evidencia)
                                                <form method="post" action="{{ route('intelligence.reporte-mensual.evidencias.destroy', $evidencia) }}">@csrf @method('DELETE')<button class="btn-small">Quitar</button></form>
                                            @endcan
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @elseif($avance && (float) $avance->cantidad > 0 && $actividad->exigeEvidencia())
                        <p class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-red-700" style="font-size: 11px;">Falta la evidencia de este avance.</p>
                    @elseif($avance && (float) $avance->cantidad > 0)
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700" style="font-size: 11px;">Avance histórico validado; no requiere evidencia.</p>
                    @endif

                    @if($puedeEditar)
                        <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $proyecto, $actividad, 'dependencia' => $reporte->dependencia_id]) }}" enctype="multipart/form-data" class="space-y-2 border-t border-slate-200 pt-3">
                            @csrf
                            <div class="grid gap-2" style="grid-template-columns: minmax(140px, .8fr) minmax(160px, .9fr) minmax(300px, 2fr);">
                                <label class="field"><span>Cantidad del periodo</span><input type="number" step="0.0001" min="0" name="cantidad" value="{{ $avance ? (float) $avance->cantidad : 0 }}" required></label>
                                <label class="field"><span>Fecha de ejecución</span><input type="date" name="fecha_ejecucion" value="{{ $avance?->fecha_ejecucion?->toDateString() }}" max="{{ $seguimiento->fecha_corte->toDateString() }}"></label>
                                <label class="field"><span>Descripción del avance</span><input type="text" name="descripcion" value="{{ $avance?->descripcion }}"></label>
                            </div>
                            <div class="grid items-end gap-2" style="grid-template-columns: minmax(280px, 1.5fr) minmax(220px, 1fr) auto;">
                                <label class="field">
                                    <span>Adjuntar evidencia {{ $actividad->exigeEvidencia() ? '' : '(opcional)' }}</span>
                                    <input type="file" name="evidencias[]" multiple>
                                </label>
                                <label class="field"><span>Descripción de la evidencia</span><input type="text" name="descripcion_evidencia"></label>
                                <button class="btn-primary whitespace-nowrap">Guardar avance físico</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </dialog>
    @endforeach

    @if($proyecto->requiereFocalizacionMensual())
        <section class="panel space-y-4">
            <div class="panel-head"><h2>Focalización por municipio</h2><span>Debe sumar 100 %. Se precarga el corte anterior; si cambia, justifique.</span></div>
            <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.focalizacion.update', $rutaBase) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="max-h-[60vh] overflow-y-auto">
                    <table data-siid-datatable class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2">Municipio</th><th class="px-3 py-2">% </th><th class="px-3 py-2">Valor</th><th class="px-3 py-2">Cantidad</th><th class="px-3 py-2">Corte anterior</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($municipios as $indice => $municipio)
                                @php($actual = $focalizacionActual->get($municipio->id))
                                @php($previa = $focalizacionPrevia[$municipio->id] ?? null)
                                <tr>
                                    <td class="px-3 py-2">{{ $municipio->codigo_dane }} — {{ $municipio->nombre }}<input type="hidden" name="focalizacion[{{ $indice }}][municipio_id]" value="{{ $municipio->id }}"></td>
                                    <td class="px-3 py-2"><input class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.0001" min="0" max="100" name="focalizacion[{{ $indice }}][porcentaje]" value="{{ $actual ? (float) $actual->porcentaje : ($previa['porcentaje'] ?? '') }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2"><input class="w-36 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.01" min="0" name="focalizacion[{{ $indice }}][valor]" value="{{ $actual?->valor !== null ? (float) $actual->valor : '' }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2"><input class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.0001" min="0" name="focalizacion[{{ $indice }}][cantidad]" value="{{ $actual?->cantidad !== null ? (float) $actual->cantidad : '' }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2 text-slate-500">{{ $previa ? $previa['porcentaje'].' %' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-sm font-bold text-slate-900">
                            <tr>
                                <td class="px-3 py-3">Total focalización</td>
                                <td class="px-3 py-3 text-right">{{ number_format((float) $focalizacionActual->sum('porcentaje'), 4, ',', '.') }} %</td>
                                <td class="px-3 py-3 text-right">{{ $pesos((float) $focalizacionActual->sum('valor')) }}</td>
                                <td class="px-3 py-3 text-right">{{ rtrim(rtrim(number_format((float) $focalizacionActual->sum('cantidad'), 4, ',', '.'), '0'), ',') }}</td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <label class="field"><span>Justificación del cambio frente al corte anterior</span><textarea name="justificacion_focalizacion" rows="2" @disabled(! $puedeEditar)>{{ $reporte->justificacion_focalizacion }}</textarea></label>
                @if($puedeEditar)<button class="btn-primary">Guardar focalización</button>@endif
            </form>
        </section>
    @endif
</div>

<script>
    document.querySelectorAll('[data-physical-modal-target]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const dialog = document.getElementById(button.dataset.physicalModalTarget);
            if (!dialog) return;
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
        });
    });

    document.querySelectorAll('[data-physical-modal-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('[data-financial-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const details = button.closest('details');
            if (details) details.open = true;
        });
    });

    document.querySelectorAll('[data-adjustment-modal-target]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const dialog = document.getElementById(button.dataset.adjustmentModalTarget);
            if (!dialog) return;
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
        });
    });

    document.querySelectorAll('[data-adjustment-modal-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
</script>

@include('intelligence.catalogs.partials.count-detail-dialog', ['catalog' => new class { public function title(): string { return 'Reporte mensual de metas'; } }])
@endsection
