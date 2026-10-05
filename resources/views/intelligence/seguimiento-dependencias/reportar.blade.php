@extends('layouts.app', ['title' => 'Reportar avances · '.$dependencia->nombre.' · SIID 2.0'])

@php
    use App\Filament\Pages\Workspace;

    $money = fn (float|int $value): string => '$'.number_format((float) $value, 0, ',', '.');
    $percent = fn (float|int $value): string => number_format((float) $value, 1, ',', '.').' %';
    $physicalTotalPct = ($resumen['programado'] ?? 0) > 0 ? (($resumen['avance'] ?? 0) / $resumen['programado']) * 100 : 0;
    $financialTotalPct = ($resumen['techo'] ?? 0) > 0 ? (($resumen['comprometido'] ?? 0) / $resumen['techo']) * 100 : 0;
@endphp

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $dependencia->id]) }}">← Hoja de vida de dependencia</a>
            <p class="eyebrow mt-4">Seguimiento a metas · Reporte operativo</p>
            <h1 class="page-title">Reportar avances · {{ $dependencia->sigla ?: $dependencia->nombre }}</h1>
            <p class="page-subtitle max-w-5xl">
                Bandeja para que los enlaces de planeación reporten o ajusten la información enviada por los sectores.
                Cada proyecto abre la vista operativa con techos, fuentes de financiación, metas producto, ejecución financiera y avance físico.
            </p>
            @if($seguimiento)
                <p class="mt-2 text-sm text-slate-500">
                    Corte seleccionado: <strong>{{ $seguimiento->etiqueta() }}</strong> · {{ $seguimiento->fecha_corte->format('d/m/Y') }}
                    · Modo: <strong>{{ $seguimiento->modoCapturaLabel() }}</strong>
                </p>
            @else
                <p class="mt-2 text-sm text-amber-700">No hay seguimientos mensuales registrados para reportar avances.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $dependencia->id]) }}">Ver hoja de vida</a>
            @if($seguimiento)
                <a class="btn-primary" href="{{ Workspace::getUrl(['workspace' => 'seguimiento', 'record' => $seguimiento->id, 'dependencia' => $dependencia->id]) }}">Abrir reporte mensual</a>
            @endif
        </div>
    </header>

    <section class="panel">
        <form method="get" action="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia-reportar', 'record' => $dependencia->id]) }}" class="grid gap-4 md:grid-cols-[minmax(0,24rem)_auto] md:items-end">
            <label class="field">
                <span>Corte de seguimiento</span>
                <select name="seguimiento">
                    @foreach($seguimientos as $opcion)
                        <option value="{{ $opcion->id }}" @selected($seguimiento?->id === $opcion->id)>
                            {{ $opcion->etiqueta() }} · {{ $opcion->fecha_corte->format('d/m/Y') }} · {{ $opcion->modoCapturaLabel() }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button class="btn-secondary">Cambiar corte</button>
        </form>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Proyectos con techo</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight text-slate-950">{{ number_format($resumen['con_techo'], 0, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ number_format($resumen['proyectos'], 0, ',', '.') }} proyecto(s) vinculados a la dependencia</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Reportes creados</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight text-slate-950">{{ number_format($resumen['con_reporte'], 0, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ number_format($resumen['pendientes'], 0, ',', '.') }} pendiente(s) por iniciar</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Ejecución financiera</p>
            <p class="mt-2 break-words text-[clamp(1.35rem,2.1vw,2.25rem)] font-black leading-tight text-emerald-700">{{ $money($resumen['comprometido']) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $percent($financialTotalPct) }} del techo {{ $money($resumen['techo']) }}</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Avance físico</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight text-slate-950">{{ $percent($physicalTotalPct) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ number_format((float) $resumen['avance'], 2, ',', '.') }} / {{ number_format((float) $resumen['programado'], 2, ',', '.') }}</p>
        </article>
    </section>

    <section class="panel border-l-4 border-l-indigo-500 bg-indigo-50/50">
        <h2 class="text-base font-bold text-slate-950">Cómo se debe usar esta bandeja</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            En 2026 los enlaces de planeación pueden abrir cada BPIN y ajustar el reporte sectorial validado. Cuando el proceso pase a captura directa,
            los sectores entrarán a esta misma lógica desde sus dependencias asignadas. Los registros marcados como histórico consolidado no exigen evidencia;
            los registros operativos por meta sí conservan la regla normal de evidencia cuando se reporta avance físico.
        </p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Proyectos del sector</h2>
                <p class="text-sm text-slate-500">Use el buscador y los filtros de la tabla para ubicar BPIN, fuente, meta producto, estado o tipo de reporte.</p>
            </div>
            <span class="status">{{ $filas->count() }} proyecto(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable data-page-length="25" data-search-placeholder="Buscar BPIN, proyecto, meta, fuente o estado…" class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">BPIN / proyecto</th>
                        <th class="px-4 py-3" data-filter="select" data-filter-label="Estado">Estado</th>
                        <th class="px-4 py-3">Metas producto</th>
                        <th class="px-4 py-3">Techo y fuentes</th>
                        <th class="px-4 py-3" data-filter="select" data-filter-label="Tipo de reporte">Metas a reportar</th>
                        <th class="px-4 py-3">Avance físico</th>
                        <th class="px-4 py-3">Ejecución</th>
                        <th class="px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filas as $fila)
                        @php
                            $proyecto = $fila['proyecto'];
                            $tipoActividad = $fila['actividades_historicas'] > 0 && $fila['actividades_operativas'] > 0
                                ? 'Mixto'
                                : ($fila['actividades_historicas'] > 0 ? 'Histórico consolidado' : ($fila['actividades_operativas'] > 0 ? 'Captura operativa' : 'Sin registros'));
                        @endphp
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->id]) }}">{{ $proyecto->bpin }}</a>
                                <p class="mt-1 max-w-xl text-slate-700">{{ $proyecto->nombre }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-filter-values="{{ $fila['estado'] }}">
                                <span class="status {{ $fila['estado_clase'] }}">{{ $fila['estado'] }}</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex max-w-md flex-col gap-1">
                                    @forelse($fila['metas_producto'] as $metaProducto)
                                        <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $metaProducto->id]) }}">
                                            {{ $metaProducto->codigo }} — {{ \Illuminate\Support\Str::limit($metaProducto->nombre, 80) }}
                                        </a>
                                    @empty
                                        <span class="text-slate-500">Sin metas producto vinculadas</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-slate-950">{{ $money($fila['techo']) }}</p>
                                <div class="mt-1 flex max-w-md flex-wrap gap-1 text-xs text-slate-500">
                                    @forelse($fila['fuentes'] as $fuente)
                                        <span class="rounded-full bg-slate-100 px-2 py-1">{{ $fuente }}</span>
                                    @empty
                                        <span>Sin fuente en el corte</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top" data-filter-values="{{ $tipoActividad }}">
                                <p class="font-semibold">{{ number_format($fila['actividades']->count(), 0, ',', '.') }} meta(s)</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $tipoActividad }}
                                    @if($fila['actividades_historicas'] > 0 && $fila['actividades_operativas'] > 0)
                                        · {{ $fila['actividades_historicas'] }} histórica(s), {{ $fila['actividades_operativas'] }} operativa(s)
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">
                                <p class="font-semibold">{{ number_format((float) $fila['avance'], 2, ',', '.') }} / {{ number_format((float) $fila['programado'], 2, ',', '.') }}</p>
                                <p class="text-xs text-slate-500">{{ $percent($fila['avance_pct']) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">
                                <p class="font-semibold">{{ $money($fila['comprometido']) }}</p>
                                <p class="text-xs text-slate-500">Obligado {{ $money($fila['obligado']) }}</p>
                                <p class="text-xs text-slate-500">Pagado {{ $money($fila['pagado']) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                @if($fila['url_reporte'])
                                    <a class="btn-primary whitespace-nowrap" href="{{ $fila['url_reporte'] }}">Abrir reporte</a>
                                @else
                                    <span class="text-xs font-semibold text-amber-700">No se puede reportar sin techo en este corte.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                Esta dependencia no tiene proyectos vinculados ni techos en el corte seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
