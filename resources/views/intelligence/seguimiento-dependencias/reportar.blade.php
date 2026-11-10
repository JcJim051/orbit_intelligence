@extends('layouts.app', ['title' => 'Reportar avances · '.$dependencia->nombre.' · SIID 2.0'])

@php
    use App\Filament\Pages\Workspace;

    $money = fn (float|int $value): string => '$'.number_format((float) $value, 0, ',', '.');
    $percent = fn (float|int $value): string => number_format((float) $value, 1, ',', '.').' %';
    $quantity = fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
    $financialTotalPct = ($resumen['techo'] ?? 0) > 0 ? (($resumen['comprometido'] ?? 0) / $resumen['techo']) * 100 : 0;
@endphp

@section('content')
<style>
    .dependency-report-overview { grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1180px) {
        .dependency-report-overview {
            grid-template-columns: minmax(28rem, 5fr) minmax(30rem, 7fr);
            align-items: stretch;
        }
    }
    .indicator-report-dialog {
        position: fixed;
        inset: 0;
        margin: auto;
        max-height: min(90vh, 46rem);
        overflow-y: auto;
    }
</style>
<div class="space-y-4">
    <header class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $dependencia->id]) }}">← Hoja de vida de dependencia</a>
            <p class="eyebrow mt-2">Seguimiento a metas · Reporte operativo</p>
            <h1 class="page-title">Reportar avances · {{ $dependencia->sigla ?: $dependencia->nombre }}</h1>
            @if($seguimiento)
                <p class="mt-1 text-sm text-slate-500">
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

    @if($seguimiento)
        @php
            $puedeGestionarEnvio = auth()->user()?->perteneceADependencia($dependencia->id) || auth()->user()?->gestionaReporteSectorial();
            $porcentajeProyectos = $flujoEnvio['proyectos_total'] > 0 ? ($flujoEnvio['proyectos_completos'] / $flujoEnvio['proyectos_total']) * 100 : 100;
            $porcentajeIndicadores = $flujoEnvio['indicadores_total'] > 0 ? ($flujoEnvio['indicadores_reportados'] / $flujoEnvio['indicadores_total']) * 100 : 100;
        @endphp
        <section class="dependency-report-overview grid gap-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex min-w-0 flex-col justify-center rounded-xl bg-emerald-50 p-4"><p class="eyebrow text-emerald-700">Ejecución financiera</p><p class="mt-2 truncate text-xl font-black text-emerald-700" title="{{ $money($resumen['comprometido']) }}">{{ $money($resumen['comprometido']) }}</p><p class="mt-1 text-xs text-slate-500">{{ $percent($financialTotalPct) }} del techo {{ $money($resumen['techo']) }}</p></div>
                    <div class="flex min-w-0 flex-col justify-center rounded-xl bg-emerald-50 p-4"><p class="eyebrow">Avance físico vigencia</p><p class="mt-2 text-2xl font-black text-slate-950">{{ $percent($resumen['avance_pct']) }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ number_format((float) $resumen['avance'], 2, ',', '.') }} reportado / {{ number_format((float) $resumen['programado'], 2, ',', '.') }} programado</p></div>
                </div>
                <form method="get" action="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia-reportar', 'record' => $dependencia->id]) }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <span class="eyebrow shrink-0">Corte</span>
                    <label class="field min-w-0 flex-1">
                        <span class="sr-only">Corte de seguimiento</span>
                        <select name="seguimiento">
                            @foreach($seguimientos as $opcion)
                                <option value="{{ $opcion->id }}" @selected($seguimiento?->id === $opcion->id)>{{ $opcion->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn-secondary shrink-0">Aplicar</button>
                    <span class="shrink-0 text-xs text-slate-500">{{ $seguimiento->fecha_corte->format('d/m/Y') }} · {{ $seguimiento->modoCapturaLabel() }}</span>
                </form>
            </article>

            <article class="rounded-2xl border {{ $flujoEnvio['enviado'] ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                <p class="eyebrow {{ $flujoEnvio['enviado'] ? 'text-emerald-700' : '' }}">Cierre de la dependencia</p>
                <h2 class="mt-1 text-base font-black leading-tight text-slate-950">{{ $flujoEnvio['enviado'] ? 'Seguimiento enviado a revisión' : 'Complete metas e indicadores' }}</h2>
                <p class="mt-1 text-xs text-slate-500">El orden de diligenciamiento es libre; el envío es único.</p>
                <div class="mt-3 space-y-2 text-xs">
                    <div><div class="flex justify-between"><strong>Proyectos</strong><span>{{ $flujoEnvio['proyectos_completos'] }}/{{ $flujoEnvio['proyectos_total'] }}</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, $porcentajeProyectos) }}%"></div></div></div>
                    <div><div class="flex justify-between"><strong>Indicadores obligatorios</strong><span>{{ $flujoEnvio['indicadores_reportados'] }}/{{ $flujoEnvio['indicadores_total'] }}</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, $porcentajeIndicadores) }}%"></div></div></div>
                </div>
                <div class="mt-3">
                    @if($flujoEnvio['enviado'])
                        <span class="inline-flex rounded-full bg-emerald-100 px-4 py-2 text-sm font-bold text-emerald-800">En revisión</span>
                    @elseif($puedeGestionarEnvio)
                        <form method="post" action="{{ route('intelligence.reporte-mensual.dependencias.enviar-revision', [$seguimiento, $dependencia]) }}" onsubmit="return confirm('Se enviará a revisión todo el seguimiento de esta dependencia y los proyectos dejarán de ser editables. ¿Continuar?')">
                            @csrf
                            <button class="btn-primary w-full" @disabled(! $flujoEnvio['puede_enviar'])>Enviar dependencia a revisión</button>
                        </form>
                        <p class="mt-1 text-center text-[11px] {{ $flujoEnvio['puede_enviar'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $flujoEnvio['puede_enviar'] ? 'Todo está listo.' : 'Se habilitará al completar ambos.' }}</p>
                    @else
                        <span class="text-sm font-semibold text-slate-500">En diligenciamiento por el sector</span>
                    @endif
                </div>
            </article>
        </section>
    @endif

    @if($seguimiento && $indicadoresCorte->isNotEmpty())
        @php
            $indicadoresReportados = $indicadoresCorte->whereNotNull('dato')->count();
            $indicadoresObligatoriosPendientes = $indicadoresCorte
                ->filter(fn (array $fila): bool => $fila['obligatorio'] && ($fila['dato'] === null || $fila['dato']->estado === 'observado'))
                ->count();
            $usuarioPuedeDiligenciarIndicadores = (auth()->user()?->perteneceADependencia($dependencia->id) || auth()->user()?->gestionaReporteSectorial())
                && ! $seguimiento->estaCerrado();
            $puedeReportarIndicadores = $usuarioPuedeDiligenciarIndicadores && ! $flujoEnvio['enviado'];
        @endphp
        <details class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
            <summary class="flex cursor-pointer list-none flex-col gap-2 bg-emerald-50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="eyebrow text-emerald-700">Indicadores territoriales</p>
                    <h2 class="text-base font-semibold text-slate-950">Reporte de indicadores del corte</h2>
                    <p class="text-xs text-slate-600">Abra para diligenciar los indicadores exigibles según su periodicidad.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="rounded-full bg-white px-3 py-1.5 text-slate-700">{{ $indicadoresReportados }}/{{ $indicadoresCorte->count() }} reportados</span>
                    <span class="rounded-full px-3 py-1.5 {{ $indicadoresObligatoriosPendientes > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $indicadoresObligatoriosPendientes }} obligatorio(s) pendiente(s)
                    </span>
                    <span class="rounded-full bg-emerald-600 px-3 py-1.5 text-white">Diligenciar</span>
                </div>
            </summary>
            <div class="overflow-x-auto border-t border-emerald-100">
                <table data-siid-datatable data-page-length="5" data-search-placeholder="Buscar código, indicador, periodo o estado…" class="w-full table-fixed text-sm">
                    <colgroup><col class="w-[42%]"><col class="w-[18%]"><col class="w-[16%]"><col class="w-[15%]"><col class="w-[9%]"></colgroup>
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Indicador</th><th class="px-4 py-3">Periodo</th><th class="px-4 py-3" data-filter="select" data-filter-label="Exigibilidad">Exigibilidad</th><th class="px-4 py-3" data-filter="select" data-filter-label="Estado">Estado</th><th class="px-4 py-3">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($indicadoresCorte as $filaIndicador)
                            @php
                                $datoIndicador = $filaIndicador['dato'];
                                $estadoIndicador = $datoIndicador
                                    ? (\App\Models\IndicadorTerritorialDato::ESTADOS[$datoIndicador->estado] ?? $datoIndicador->estado)
                                    : 'Sin reportar';
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-2.5" data-label="Indicador">
                                    <strong class="text-emerald-700">{{ $filaIndicador['indicador']->codigo }}</strong>
                                    <p class="mt-1 font-semibold leading-5 text-slate-950">{{ $filaIndicador['indicador']->nombre }}</p>
                                    <small class="mt-1 block text-slate-500">{{ \App\Models\IndicadorTerritorial::PERIODICIDADES[$filaIndicador['indicador']->periodicidad] ?? $filaIndicador['indicador']->periodicidad }} · {{ $filaIndicador['indicador']->unidad_medida }}</small>
                                </td>
                                <td class="px-4 py-2.5" data-label="Periodo"><strong>{{ $filaIndicador['periodo'] }}</strong><small class="mt-1 block text-slate-500">{{ $filaIndicador['periodo_inicio']->format('d/m/Y') }} al {{ $filaIndicador['periodo_fin']->format('d/m/Y') }}</small></td>
                                <td class="px-4 py-2.5" data-label="Exigibilidad" data-filter-values="{{ $filaIndicador['obligatorio'] ? 'Obligatorio' : 'Anticipado' }}"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $filaIndicador['obligatorio'] ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' }}">{{ $filaIndicador['obligatorio'] ? 'Obligatorio' : 'Anticipado' }}</span></td>
                                <td class="px-4 py-2.5" data-label="Estado" data-filter-values="{{ $estadoIndicador }}">
                                    <span class="font-bold {{ $datoIndicador?->estado === 'aprobado' ? 'text-emerald-700' : ($datoIndicador?->estado === 'observado' ? 'text-rose-700' : 'text-amber-700') }}">{{ $estadoIndicador }}</span>
                                    @if($datoIndicador)<small class="mt-1 block text-slate-500">{{ $quantity($datoIndicador->valor) }} {{ $filaIndicador['indicador']->unidad_medida === 'Porcentaje' ? '%' : $filaIndicador['indicador']->unidad_medida }}</small>@endif
                                </td>
                                <td class="px-4 py-2.5" data-label="Acción">
                                    @if($datoIndicador?->estado === 'observado' && $usuarioPuedeDiligenciarIndicadores)
                                        <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white whitespace-nowrap" onclick="document.getElementById('reportar-indicador-{{ $filaIndicador['indicador']->id }}').showModal()">Corregir</button>
                                    @elseif($datoIndicador)
                                        <button type="button" class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 whitespace-nowrap" onclick="document.getElementById('ver-reporte-indicador-{{ $datoIndicador->id }}').showModal()">Ver reporte</button>
                                    @elseif($puedeReportarIndicadores)
                                        <button type="button" class="btn-primary whitespace-nowrap" onclick="document.getElementById('reportar-indicador-{{ $filaIndicador['indicador']->id }}').showModal()">Reportar</button>
                                    @else
                                        <span class="text-xs font-semibold text-slate-500">Pendiente del sector</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        @if($usuarioPuedeDiligenciarIndicadores)
            @foreach($indicadoresCorte as $filaIndicador)
                @php
                    $datoAjustar = $filaIndicador['dato'];
                    $esCorreccion = $datoAjustar?->estado === 'observado';
                @endphp
                @if(($datoAjustar === null && $puedeReportarIndicadores) || $esCorreccion)
                    <dialog id="reportar-indicador-{{ $filaIndicador['indicador']->id }}" class="indicator-report-dialog w-11/12 max-w-3xl rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
                        <form method="post" action="{{ route('intelligence.reporte-mensual.indicadores.store', [$seguimiento, $dependencia, $filaIndicador['indicador']]) }}">
                            @csrf
                            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                                <div><p class="eyebrow">{{ $esCorreccion ? 'Corrección del reporte' : 'Reporte de indicador' }}</p><h3 class="mt-1 text-lg font-black text-slate-950">{{ $filaIndicador['indicador']->codigo }} — {{ $filaIndicador['indicador']->nombre }}</h3></div>
                                <button type="button" class="rounded-full border border-slate-200 px-3 py-1.5 font-bold text-slate-500" onclick="this.closest('dialog').close()">×</button>
                            </div>
                            @if($esCorreccion)
                                <div class="border-b border-rose-200 bg-rose-50 px-5 py-4">
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-rose-700">Observación del enlace de información</p>
                                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-rose-900">{{ $datoAjustar->observacion_revision }}</p>
                                    <p class="mt-2 text-xs font-semibold text-rose-700">Corrija el valor, la evidencia o la observación del sector y vuelva a enviar el reporte.</p>
                                </div>
                            @endif
                            <div class="space-y-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Qué debe reportar</p>
                                    <p class="mt-1 text-sm leading-5 text-slate-700">{{ $filaIndicador['indicador']->definicion ?: ($filaIndicador['indicador']->objetivo ?: 'Registre el valor oficial consolidado para el periodo indicado.') }}</p>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Periodo</span><strong class="mt-1 block text-sm text-slate-950">{{ $filaIndicador['periodo'] }}</strong><small class="text-slate-500">{{ $filaIndicador['periodo_inicio']->format('d/m/Y') }} al {{ $filaIndicador['periodo_fin']->format('d/m/Y') }}</small></div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Unidad</span><strong class="mt-1 block text-sm text-slate-950">{{ $filaIndicador['indicador']->unidad_medida ?: 'Por definir' }}</strong></div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Fuente oficial</span><p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-700">{{ $filaIndicador['indicador']->fuente_informacion ?: ($filaIndicador['indicador']->responsable_informacion ?: 'Fuente pendiente de documentar') }}</p></div>
                                </div>
                                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5">
                                    <p class="text-[11px] font-black uppercase tracking-wide text-emerald-700">Fórmula o regla de cálculo</p>
                                    <p class="mt-1 max-h-24 overflow-y-auto whitespace-pre-line text-xs leading-5 text-slate-700">{{ $filaIndicador['indicador']->formula_calculo ?: 'No hay una regla de cálculo documentada; reporte el valor oficial consolidado por la dependencia.' }}</p>
                                    <p class="mt-1 text-[11px] font-semibold text-emerald-700">La plataforma almacena el dato reportado; no realiza el cálculo automáticamente.</p>
                                </div>
                            </div>
                            <div class="grid gap-4 p-5 sm:grid-cols-2">
                                <label class="field"><span>Valor oficial del periodo ({{ $filaIndicador['indicador']->unidad_medida ?: 'unidad' }})</span><input type="number" step="any" name="valor" value="{{ $datoAjustar?->valor }}" required></label>
                                <label class="field"><span>Enlace de evidencia</span><input type="url" name="enlace_evidencia" value="{{ $datoAjustar?->enlace_evidencia }}" placeholder="https://..."></label>
                                <label class="field sm:col-span-2"><span>Observación del sector</span><textarea name="observacion" rows="3">{{ $datoAjustar?->observacion }}</textarea></label>
                            </div>
                            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4"><button type="button" class="btn-secondary" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn-primary">{{ $esCorreccion ? 'Enviar corrección' : 'Guardar reporte' }}</button></div>
                        </form>
                    </dialog>
                @endif
            @endforeach
        @endif

        @foreach($indicadoresCorte as $filaIndicador)
            @php
                $datoConsultado = $filaIndicador['dato'];
            @endphp
            @if($datoConsultado && $datoConsultado->estado !== 'observado')
                <dialog id="ver-reporte-indicador-{{ $datoConsultado->id }}" class="indicator-report-dialog w-11/12 max-w-3xl rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div><p class="eyebrow">Vista del reporte enviado</p><h3 class="mt-1 text-lg font-black text-slate-950">{{ $filaIndicador['indicador']->codigo }} — {{ $filaIndicador['indicador']->nombre }}</h3><p class="mt-1 text-xs text-slate-500">Modo consulta · este reporte no será modificado</p></div>
                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1.5 font-bold text-slate-500" onclick="this.closest('dialog').close()">×</button>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $datoConsultado->estado === 'aprobado' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ \App\Models\IndicadorTerritorialDato::ESTADOS[$datoConsultado->estado] ?? $datoConsultado->estado }}</span>
                        <span class="text-xs text-slate-500">Reportó {{ $datoConsultado->reportadoPor?->name ?? 'Sistema' }} · {{ $datoConsultado->reportado_at?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</span>
                    </div>
                    <div class="space-y-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div><p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Qué debía reportarse</p><p class="mt-1 text-sm leading-5 text-slate-700">{{ $filaIndicador['indicador']->definicion ?: ($filaIndicador['indicador']->objetivo ?: 'Valor oficial consolidado para el periodo indicado.') }}</p></div>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Periodo</span><strong class="mt-1 block text-sm text-slate-950">{{ $datoConsultado->periodo }}</strong><small class="text-slate-500">{{ $datoConsultado->periodo_inicio?->format('d/m/Y') ?? '—' }} al {{ $datoConsultado->periodo_fin?->format('d/m/Y') ?? '—' }}</small></div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Unidad</span><strong class="mt-1 block text-sm text-slate-950">{{ $filaIndicador['indicador']->unidad_medida ?: 'Por definir' }}</strong></div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3"><span class="text-[11px] font-bold uppercase text-slate-500">Fuente oficial</span><p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-700">{{ $datoConsultado->fuente ?: ($filaIndicador['indicador']->fuente_informacion ?: 'Fuente pendiente de documentar') }}</p></div>
                        </div>
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5"><p class="text-[11px] font-black uppercase tracking-wide text-emerald-700">Fórmula o regla de cálculo</p><p class="mt-1 max-h-24 overflow-y-auto whitespace-pre-line text-xs leading-5 text-slate-700">{{ $filaIndicador['indicador']->formula_calculo ?: 'No hay una regla de cálculo documentada.' }}</p></div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <label class="field"><span>Valor oficial del periodo ({{ $filaIndicador['indicador']->unidad_medida ?: 'unidad' }})</span><input type="number" step="any" value="{{ $datoConsultado->valor }}" disabled></label>
                        <div class="field">
                            <span>Enlace de evidencia</span>
                            @if($datoConsultado->enlace_evidencia)
                                <a class="flex min-h-10 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 text-sm font-bold text-emerald-800" href="{{ $datoConsultado->enlace_evidencia }}" target="_blank" rel="noopener">Abrir evidencia ↗</a>
                            @else
                                <div class="flex min-h-10 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">Sin enlace registrado</div>
                            @endif
                        </div>
                        <label class="field sm:col-span-2"><span>Observación del sector</span><textarea rows="3" disabled>{{ $datoConsultado->observacion }}</textarea></label>
                    </div>
                    <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" class="btn-primary" onclick="this.closest('dialog').close()">Cerrar</button></div>
                </dialog>
            @endif
        @endforeach
    @endif

    @if($indicadoresPendientesPreparacion->isNotEmpty())
        <details class="rounded-2xl border border-amber-200 bg-amber-50 shadow-sm">
            <summary class="flex cursor-pointer list-none flex-col gap-2 px-4 py-2.5 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="eyebrow text-amber-700">Indicadores todavía no habilitados</p>
                    <p class="text-sm font-semibold text-slate-950">Preparación pendiente por el equipo de información</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-amber-800">{{ $indicadoresPendientesPreparacion->count() }} pendiente(s) · Ver detalle</span>
            </summary>
            <div class="overflow-x-auto border-t border-amber-200 bg-white">
                <table data-siid-datatable data-page-length="10" data-search-placeholder="Buscar indicador no habilitado…" class="w-full text-sm">
                    <thead class="bg-amber-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Indicador</th><th class="px-4 py-3">Periodicidad</th><th class="px-4 py-3" data-filter="select" data-filter-label="Estado">Estado</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@foreach($indicadoresPendientesPreparacion as $indicadorPendiente)<tr><td class="px-4 py-3 font-bold text-emerald-700">{{ $indicadorPendiente->codigo }}</td><td class="px-4 py-3 font-semibold text-slate-900">{{ $indicadorPendiente->nombre }}</td><td class="px-4 py-3 text-slate-600">{{ \App\Models\IndicadorTerritorial::PERIODICIDADES[$indicadorPendiente->periodicidad] ?? 'Por definir' }}</td><td class="px-4 py-3" data-filter-values="{{ \App\Models\IndicadorTerritorial::ESTADOS[$indicadorPendiente->estado] ?? $indicadorPendiente->estado }}"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ \App\Models\IndicadorTerritorial::ESTADOS[$indicadorPendiente->estado] ?? $indicadorPendiente->estado }}</span></td></tr>@endforeach</tbody>
                </table>
            </div>
        </details>
    @endif

    <section id="proyectos-del-sector" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Proyectos del sector</h2>
                <p class="text-sm text-slate-500">Use el buscador y los filtros de la tabla para ubicar BPIN, fuente, meta producto, estado o tipo de reporte.</p>
            </div>
            <span class="status">{{ $filas->count() }} proyecto(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable data-page-length="25" data-search-placeholder="Buscar BPIN, proyecto, meta, fuente o estado…" class="seguimiento-dependencias-table w-full table-fixed text-xs">
                <colgroup>
                    <col class="w-[38%]">
                    <col class="w-[11%]">
                    <col class="w-[16%]">
                    <col class="w-[13%]">
                    <col class="w-[14%]">
                    <col class="w-[8%]">
                </colgroup>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">BPIN / proyecto</th>
                        <th class="px-4 py-3" data-filter="select" data-filter-label="Estado">Estado</th>
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
                            <td class="px-4 py-4 align-top" data-label="BPIN / proyecto">
                                <a class="font-semibold text-emerald-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->id]) }}">{{ $proyecto->bpin }}</a>
                                <p class="mt-1 max-w-xl text-slate-700">{{ $proyecto->nombre }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Estado" data-filter-values="{{ $fila['estado'] }}">
                                <span class="status {{ $fila['estado_clase'] }}">{{ $fila['estado'] }}</span>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Metas a reportar" data-filter-values="{{ $tipoActividad }}">
                                <p class="font-semibold">{{ number_format($fila['actividades']->count(), 0, ',', '.') }} meta(s)</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $tipoActividad }}
                                    @if($fila['actividades_historicas'] > 0 && $fila['actividades_operativas'] > 0)
                                        · {{ $fila['actividades_historicas'] }} histórica(s), {{ $fila['actividades_operativas'] }} operativa(s)
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Avance físico">
                                <p class="font-semibold">{{ number_format((float) $fila['avance'], 2, ',', '.') }} / {{ number_format((float) $fila['programado'], 2, ',', '.') }}</p>
                                <p class="text-xs text-slate-500">{{ $percent($fila['avance_pct']) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Ejecución">
                                <p class="font-semibold">{{ $money($fila['comprometido']) }}</p>
                                <p class="text-xs text-slate-500">Obligado {{ $money($fila['obligado']) }}</p>
                                <p class="text-xs text-slate-500">Pagado {{ $money($fila['pagado']) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Acción">
                                @if($fila['url_reporte'])
                                    <a class="btn-primary whitespace-nowrap" href="{{ $fila['url_reporte'] }}">Abrir reporte</a>
                                @else
                                    <span class="text-xs font-semibold text-amber-700">No se puede reportar sin techo en este corte.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
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
