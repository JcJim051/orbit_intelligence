@extends('layouts.app', ['title' => 'Analítica de metas producto · '.$seguimiento->etiqueta().' · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))
@php($porcentaje = fn ($valor) => number_format((float) $valor, 1, ',', '.').' %')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Analítica de seguimiento</p>
            <h1 class="page-title">Metas producto · {{ $seguimiento->etiqueta() }}</h1>
            <p class="page-subtitle max-w-3xl">
                Cruce de metas producto, BPIN, avance físico y ejecución financiera cargada para el corte.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver al seguimiento</a>
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.analitica.download', [$seguimiento, ...request()->query()]) }}">Descargar CSV</a>
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Metas producto</p>
            <p class="mt-3 text-4xl font-black text-slate-950">{{ number_format($resumen['metas'], 0, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ number_format($resumen['proyectos'], 0, ',', '.') }} BPIN asociados.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Comprometido</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $pesos($resumen['comprometido']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Asignado: {{ $pesos($resumen['asignado']) }}</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ejecución financiera</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $porcentaje($resumen['ejecucion_financiera']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Comprometido / asignado.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avance físico promedio</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $porcentaje($resumen['avance_fisico_promedio']) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ number_format($resumen['cumplidas'], 0, ',', '.') }} cumplidas · {{ number_format($resumen['sin_avance'], 0, ',', '.') }} sin avance.</p>
        </article>
    </section>

    @if($filas->isEmpty() && $ultimaCargaHistorica)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Carga histórica detectada</p>
                    <h2 class="mt-1 text-lg font-bold">La analítica aún no tiene registros consolidados visibles.</h2>
                    <p class="mt-2 text-sm text-amber-900">
                        Última carga: <strong>{{ $ultimaCargaHistorica->nombre_original }}</strong>,
                        estado <strong>{{ $ultimaCargaHistorica->status }}</strong>,
                        {{ number_format($ultimaCargaHistorica->filas_validas, 0, ',', '.') }} válidas,
                        {{ number_format($ultimaCargaHistorica->filas_bloqueadas, 0, ',', '.') }} bloqueadas,
                        {{ number_format($ultimaCargaHistorica->filas_importadas, 0, ',', '.') }} importadas
                        y {{ number_format($ultimaCargaHistorica->filas_actualizadas, 0, ',', '.') }} actualizadas.
                    </p>
                    @if($ultimaCargaHistorica->status === 'diagnosticado' && $ultimaCargaHistorica->filas_validas > 0)
                        <p class="mt-2 text-sm text-amber-900">
                            El archivo ya fue diagnosticado, pero falta ejecutar <strong>Importar filas válidas</strong>. Mientras no se importe, “Ver analítica” seguirá sin datos.
                        </p>
                    @elseif($ultimaCargaHistorica->status !== 'importado')
                        <p class="mt-2 text-sm text-amber-900">
                            Revise la carga histórica: todavía no aparece como importada correctamente.
                        </p>
                    @else
                        <p class="mt-2 text-sm text-amber-900">
                            La carga aparece importada, pero no hay reportes visibles con los filtros actuales. Pruebe limpiar filtros o revise dependencias/BPIN importados.
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.historicas.index', $seguimiento) }}">Ver carga histórica</a>
                    @if($filtros['dependencia'] || $filtros['q'] || $filtros['estado'])
                        <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.analitica.index', $seguimiento) }}">Limpiar filtros</a>
                    @endif
                </div>
            </div>
        </section>
    @elseif($filas->isEmpty() && $reportesConsolidados === 0)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-700">
            <p class="font-semibold text-slate-950">Todavía no hay reportes consolidados para este seguimiento.</p>
            <p class="mt-1 text-sm">Primero importe una carga histórica válida o registre avances manuales por proyecto.</p>
            <a class="btn-secondary mt-4 inline-flex" href="{{ route('intelligence.reporte-mensual.historicas.index', $seguimiento) }}">Importar avance consolidado</a>
        </section>
    @endif

    <section class="panel">
        <form method="get" action="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'seguimiento-analitica', 'record' => $seguimiento->getRouteKey()]) }}" class="grid gap-4 md:grid-cols-5">
            @if($dependencias->count() > 1)
                <label class="field">
                    <span>Dependencia</span>
                    <select name="dependencia">
                        <option value="">Todas</option>
                        @foreach($dependencias as $dependencia)
                            <option value="{{ $dependencia->id }}" @selected($filtros['dependencia'] === $dependencia->id)>{{ $dependencia->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="field md:col-span-2">
                <span>Buscar</span>
                <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Meta, BPIN, proyecto, pilar o dependencia">
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach($estados as $valor => $label)
                        <option value="{{ $valor }}" @selected($filtros['estado'] === $valor)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="btn-primary">Filtrar</button>
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.analitica.index', $seguimiento) }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Resumen por meta producto</h2>
                <p class="text-sm text-slate-500">Agrupa actividades y ejecución por meta producto y dependencia.</p>
            </div>
            <span class="text-sm font-semibold text-slate-500">{{ $filas->count() }} registro(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Meta producto</th>
                        <th class="px-4 py-3">Dependencia / pilar</th>
                        <th class="px-4 py-3 text-right">BPIN</th>
                        <th class="px-4 py-3 text-right">Físico</th>
                        <th class="px-4 py-3 text-right">Asignado</th>
                        <th class="px-4 py-3 text-right">Comprometido</th>
                        <th class="px-4 py-3 text-right">Obligado</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filas as $fila)
                        <tr>
                            <td class="max-w-xl px-4 py-3 align-top">
                                <p class="font-semibold text-slate-950">{{ $fila['meta_codigo'] }} — {{ $fila['meta_nombre'] }}</p>
                                @if($fila['proyectos'])
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-emerald-700">Ver {{ $fila['proyectos_count'] }} BPIN</summary>
                                        <ul class="mt-2 space-y-1 text-xs text-slate-500">
                                            @foreach($fila['proyectos'] as $proyecto)
                                                <li><span class="font-mono">{{ $proyecto['bpin'] }}</span> — {{ $proyecto['nombre'] }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if($fila['observaciones'])
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-600">Observaciones</summary>
                                        <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-slate-500">
                                            @foreach($fila['observaciones'] as $observacion)
                                                <li>{{ $observacion }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="font-semibold text-slate-900">{{ $fila['dependencia_nombre'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $fila['pilar_codigo'] ? $fila['pilar_codigo'].' — ' : '' }}{{ $fila['pilar_nombre'] ?? 'Sin pilar asociado' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $fila['programa_codigo'] ? $fila['programa_codigo'].' — ' : '' }}{{ $fila['programa_nombre'] ?? 'Sin programa asociado' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right align-top">{{ number_format($fila['proyectos_count'], 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                <p class="font-semibold">{{ number_format($fila['avance_fisico'], 2, ',', '.') }} / {{ number_format($fila['programacion_fisica'], 2, ',', '.') }}</p>
                                <p class="text-xs text-slate-500">{{ $porcentaje($fila['porcentaje_fisico']) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($fila['asignado']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($fila['comprometido']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($fila['obligado']) }}</td>
                            <td class="px-4 py-3 align-top">
                                @php($clase = match($fila['estado_analitico']) {
                                    'sin_avance', 'sin_programacion' => 'status-pending_review',
                                    'cumplida', 'sobrecumplida' => 'status-approved',
                                    default => 'status-transcribing',
                                })
                                <span class="status {{ $clase }}">{{ $estados[$fila['estado_analitico']] ?? $fila['estado_analitico'] }}</span>
                                <p class="mt-1 text-xs text-slate-500">Financiero: {{ $porcentaje($fila['porcentaje_financiero']) }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">No hay datos analíticos para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
