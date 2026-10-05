@extends('layouts.app', ['title' => $meta->codigo.' · Hoja de vida meta producto · SIID 2.0'])

@php
    $pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor);
    $porcentaje = fn ($valor) => number_format((float) $valor, 1, ',', '.').' %';
    $metaResultado = $meta->metaResultado;
    $indicadorResultado = $metaResultado?->indicador;
    $programa = $meta->subprograma?->programa ?? $metaResultado?->subprograma?->programa ?? $metaResultado?->programa;
    $linea = $programa?->linea;
    $eje = $linea?->eje;
    $pilar = $eje?->pilar;
    $odsReview = $metaResultado?->indicador?->odsReview;
    $odsLinks = $odsReview?->links ?? collect();
    $odsApprovedStatuses = ['accepted', 'validated', 'confirmed', 'approved'];
    $odsApprovedLinks = $odsLinks
        ->filter(fn ($link) => in_array($link->status, $odsApprovedStatuses, true) || ($odsReview?->status === 'completed' && $link->status !== 'rejected'))
        ->sortBy(fn ($link) => $link->odsIndicator?->target?->goal?->code.'-'.$link->odsIndicator?->code)
        ->values();
    $odsPendingLinks = $odsLinks->reject(fn ($link) => $odsApprovedLinks->contains('id', $link->id))->values();
    $codigoPdd = function ($record): ?string {
        if (! $record) {
            return null;
        }

        $codigo = trim((string) ($record->numeral ?: $record->codigo));

        if ($codigo === '') {
            return null;
        }

        $compacto = preg_replace('/0+$/', '', $codigo);

        return $compacto !== '' ? $compacto : $codigo;
    };
    $pddLabel = fn ($record, string $fallback): string => $record
        ? trim(($codigoPdd($record) ? $codigoPdd($record).' — ' : '').$record->nombre)
        : $fallback;
@endphp

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-resultado']) }}">← Metas resultado</a>
            <p class="eyebrow mt-4">Hoja de vida del indicador · Meta producto</p>
            <h1 class="page-title">{{ $meta->codigo }}</h1>
            <p class="page-subtitle max-w-4xl">{{ $meta->nombre }}</p>
        </div>
        @if(auth()->user()?->isAdmin())
            <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'meta-producto-editar', 'record' => $meta->getRouteKey()]) }}">Editar catálogo</a>
        @endif
    </header>

    <section class="grid gap-4 lg:grid-cols-4">
        <article class="panel lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Meta resultado asociada</p>
            @if($metaResultado)
                <p class="mt-2 text-lg font-bold text-slate-950">{{ $metaResultado->codigo_provisional }} — {{ $metaResultado->descripcion }}</p>
                <p class="mt-2 text-sm text-slate-500">Esta es la meta resultado del PDD que agrupa y da contexto a la meta producto.</p>
            @else
                <p class="mt-2 text-sm text-slate-500">Esta meta producto todavía no tiene meta resultado asociada.</p>
            @endif
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sectores asociados</p>
            <div class="mt-2 space-y-1 text-xs font-semibold leading-snug text-slate-700">
                @forelse($sectoresAsociados as $dependencia)
                    <p>{{ $dependencia->codigo }} — {{ $dependencia->nombre }}</p>
                @empty
                    <p class="font-normal text-slate-500">Sin sectores asociados.</p>
                @endforelse
            </div>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sector MGA</p>
            <p class="mt-2 text-lg font-bold text-slate-950">{{ $meta->sectorMga ? $meta->sectorMga->codigo.' — '.$meta->sectorMga->nombre : 'Sin sector' }}</p>
        </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <article class="panel border-indigo-200 bg-indigo-50/40">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-700">Indicador resultado</p>
            @if($indicadorResultado)
                <div class="mt-3">
                    <p class="text-2xl font-black leading-tight text-slate-950">
                        {{ $indicadorResultado->codigo ? $indicadorResultado->codigo.' — ' : '' }}{{ $indicadorResultado->nombre }}
                    </p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <dt class="font-semibold text-slate-500">Unidad</dt>
                            <dd class="mt-1 font-bold text-slate-950">{{ $indicadorResultado->unidad_medida ?: 'No reportada' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Línea base</dt>
                            <dd class="mt-1 font-bold text-slate-950">{{ $indicadorResultado->linea_base_texto ?: ($indicadorResultado->linea_base !== null ? number_format((float) $indicadorResultado->linea_base, 2, ',', '.') : 'No reportada') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Meta cuatrienio</dt>
                            <dd class="mt-1 font-bold text-slate-950">{{ $indicadorResultado->meta_cuatrienio !== null ? number_format((float) $indicadorResultado->meta_cuatrienio, 2, ',', '.') : 'No reportada' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Orientación</dt>
                            <dd class="mt-1 font-bold text-slate-950">{{ $indicadorResultado->orientacion?->label() ?? 'No definida' }}</dd>
                        </div>
                    </dl>
                    @if($indicadorResultado->fuente_verificacion)
                        <p class="mt-4 rounded-2xl bg-white/70 px-4 py-3 text-sm text-slate-700">
                            <strong>Fuente de verificación:</strong> {{ $indicadorResultado->fuente_verificacion }}
                        </p>
                    @endif
                </div>
            @else
                <p class="mt-3 text-sm text-slate-600">La meta resultado asociada todavía no tiene indicador resultado relacionado.</p>
            @endif
        </article>

        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lectura rápida</p>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                La meta producto es el compromiso operativo. El indicador resultado es la medida de cambio que permite leer el efecto agregado de las metas producto relacionadas.
            </p>
            @if($metaResultado)
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Meta resultado</p>
                <p class="mt-1 text-sm font-bold text-slate-950">{{ $metaResultado->codigo_provisional ?: 'Sin código' }}</p>
            @endif
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Relación ODS</p>
                <h2 class="text-lg font-semibold">Objetivos e indicadores ODS aprobados</h2>
                <p class="text-sm text-slate-500">
                    Relación construida desde el indicador de resultado:
                    <strong>{{ $metaResultado?->indicador?->nombre ?? 'Sin indicador resultado' }}</strong>.
                </p>
            </div>
            @if($odsReview && auth()->user()?->isAdmin())
                <div class="flex flex-wrap gap-2">
                    <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $odsReview->getRouteKey()]) }}">Abrir revisión ODS</a>
                </div>
            @endif
        </div>

        @if(! $metaResultado?->indicador)
            <p class="px-5 py-8 text-center text-slate-500">La meta resultado asociada todavía no tiene indicador de resultado.</p>
        @elseif(! $odsReview)
            <p class="px-5 py-8 text-center text-slate-500">Este indicador de resultado todavía no tiene proceso de revisión ODS.</p>
        @elseif($odsApprovedLinks->isEmpty())
            <div class="px-5 py-8 text-center text-slate-500">
                <p>La revisión ODS existe, pero todavía no tiene indicadores ODS aprobados.</p>
                @if($odsPendingLinks->isNotEmpty() && auth()->user()?->isAdmin())
                    <a class="mt-3 inline-flex text-sm font-semibold text-indigo-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $odsReview->getRouteKey()]) }}">
                        Ver {{ $odsPendingLinks->count() }} indicador(es) en revisión
                    </a>
                @elseif($odsPendingLinks->isNotEmpty())
                    <p class="mt-2 text-xs text-slate-400">Hay {{ $odsPendingLinks->count() }} relación(es) ODS en revisión.</p>
                @endif
            </div>
        @else
            <div class="grid gap-4 p-5 lg:grid-cols-2">
                @foreach($odsApprovedLinks as $link)
                    @php($ods = $link->odsIndicator)
                    @php($target = $ods?->target)
                    @php($goal = $target?->goal)
                    <article class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">ODS {{ $goal?->code ?? '—' }}</p>
                        <h3 class="mt-2 text-2xl font-black leading-tight text-slate-950">{{ $goal?->name ?? 'Objetivo ODS sin nombre' }}</h3>
                        <button
                            type="button"
                            class="mt-4 text-left text-sm font-semibold leading-relaxed text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900"
                            data-ods-detail-target="ods-detail-{{ $link->id }}"
                        >
                            {{ $ods?->code ?? 'Sin código' }} — {{ $ods?->name ?? 'Indicador ODS sin nombre' }}
                        </button>
                    </article>

                    <dialog id="ods-detail-{{ $link->id }}" class="w-full max-w-3xl rounded-3xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">ODS {{ $goal?->code ?? '—' }}</p>
                            <h3 class="mt-2 text-2xl font-black text-slate-950">{{ $goal?->name ?? 'Objetivo ODS sin nombre' }}</h3>
                            <p class="mt-2 text-sm text-slate-500">{{ $target ? $target->code.' — '.$target->name : 'Meta ODS no disponible' }}</p>
                        </div>
                        <div class="space-y-4 px-6 py-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Indicador ODS oficial</p>
                                <p class="mt-1 text-base font-bold text-slate-950">{{ $ods?->code ?? 'Sin código' }} — {{ $ods?->name ?? 'Indicador ODS sin nombre' }}</p>
                            </div>
                            @if($ods?->unit)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidad</p>
                                    <p class="mt-1 text-sm text-slate-700">{{ $ods->unit }}</p>
                                </div>
                            @endif
                            @if($ods?->description)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descripción</p>
                                    <p class="mt-1 text-sm text-slate-700">{{ $ods->description }}</p>
                                </div>
                            @endif
                            @if($link->justification)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Justificación de relación</p>
                                    <p class="mt-1 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $link->justification }}</p>
                                </div>
                            @endif
                            @if($ods?->source || $ods?->csv_url || $ods?->excel_url)
                                <div class="flex flex-wrap gap-2 text-sm">
                                    @if($ods->source)<span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600">Fuente: {{ $ods->source }}</span>@endif
                                    @if($ods->csv_url)<a class="font-semibold text-indigo-700" href="{{ $ods->csv_url }}" target="_blank" rel="noopener">CSV oficial</a>@endif
                                    @if($ods->excel_url)<a class="font-semibold text-indigo-700" href="{{ $ods->excel_url }}" target="_blank" rel="noopener">Excel oficial</a>@endif
                                </div>
                            @endif
                        </div>
                        <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                            <button type="button" class="btn-secondary" data-ods-detail-close>Cerrar</button>
                        </div>
                    </dialog>
                @endforeach
            </div>

            @if($odsPendingLinks->isNotEmpty())
                <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    Hay {{ $odsPendingLinks->count() }} relación(es) ODS en revisión que aún no se muestran aquí.
                    @if(auth()->user()?->isAdmin())
                        <a class="font-semibold text-indigo-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $odsReview->getRouteKey()]) }}">Abrir revisión ODS</a>
                    @endif
                </div>
            @endif
        @endif
    </section>

    <section class="grid gap-4 lg:grid-cols-4">
        <article class="panel lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avance según reporte más reciente</p>
            @if($avanceActual)
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-slate-500">Corte</p>
                        <p class="text-2xl font-black text-slate-950">{{ $avanceActual['seguimiento'] }}</p>
                        <p class="text-xs text-slate-500">Fecha: {{ $avanceActual['fecha_corte'] ?? 'Sin fecha' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Cobertura</p>
                        <p class="text-2xl font-black text-slate-950">{{ number_format($avanceActual['proyectos'], 0, ',', '.') }} BPIN</p>
                        <p class="text-xs text-slate-500">{{ number_format($avanceActual['dependencias'], 0, ',', '.') }} dependencia(s)</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Avance físico</p>
                        <p class="text-2xl font-black text-slate-950">{{ $porcentaje($avanceActual['porcentaje_fisico']) }}</p>
                        <p class="text-xs text-slate-500">{{ number_format($avanceActual['avance_fisico'], 2, ',', '.') }} / {{ number_format($avanceActual['programado_fisico'], 2, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Ejecución financiera</p>
                        <p class="text-2xl font-black text-slate-950">{{ $porcentaje($avanceActual['porcentaje_financiero']) }}</p>
                        <p class="text-xs text-slate-500">Comprometido: {{ $pesos($avanceActual['comprometido']) }}</p>
                    </div>
                </div>
            @else
                <p class="mt-2 text-sm text-slate-500">Aún no hay avance físico ni ejecución financiera reportada para esta meta producto.</p>
            @endif
        </article>

        <article class="panel lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cadena PDD</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div><dt class="font-semibold text-slate-600">Pilar</dt><dd class="text-slate-950">{{ $pddLabel($pilar, 'Sin pilar') }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Eje</dt><dd class="text-slate-950">{{ $pddLabel($eje, 'Sin eje') }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Línea</dt><dd class="text-slate-950">{{ $pddLabel($linea, 'Sin línea') }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Programa</dt><dd class="text-slate-950">{{ $pddLabel($programa, 'Sin programa') }}</dd></div>
                <div><dt class="font-semibold text-slate-600">Subprograma</dt><dd class="text-slate-950">{{ $pddLabel($meta->subprograma, 'Sin subprograma') }}</dd></div>
            </dl>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Histórico de avances</h2>
            <p class="text-sm text-slate-500">Cortes mensuales encontrados en el reporte sectorial.</p>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Corte</th>
                        <th class="px-4 py-3 text-right">Físico</th>
                        <th class="px-4 py-3 text-right">Asignado</th>
                        <th class="px-4 py-3 text-right">Comprometido</th>
                        <th class="px-4 py-3 text-right">Obligado</th>
                        <th class="px-4 py-3 text-right">Financiero</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($historico as $corte)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $corte['seguimiento'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($corte['avance_fisico'], 2, ',', '.') }} / {{ number_format($corte['programado_fisico'], 2, ',', '.') }}<br><span class="text-xs text-slate-500">{{ $porcentaje($corte['porcentaje_fisico']) }}</span></td>
                            <td class="px-4 py-3 text-right">{{ $pesos($corte['asignado']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $pesos($corte['comprometido']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $pesos($corte['obligado']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $porcentaje($corte['porcentaje_financiero']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay histórico de avances para esta meta producto.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">BPIN relacionados</h2>
            <p class="text-sm text-slate-500">Proyectos asociados directamente a esta meta producto.</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($meta->proyectos as $proyecto)
                <article class="px-5 py-4">
                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->getRouteKey()]) }}">
                        {{ $proyecto->bpin }} — {{ $proyecto->nombre }}
                    </a>
                    <p class="mt-1 text-sm text-slate-500">
                        Dependencias:
                        {{ $proyecto->dependencias->map(fn ($dependencia) => $dependencia->etiqueta())->implode(' · ') ?: 'Sin dependencias asociadas' }}
                    </p>
                </article>
            @empty
                <p class="px-5 py-8 text-center text-slate-500">No hay BPIN asociados a esta meta producto.</p>
            @endforelse
        </div>
    </section>
</div>

<script>
    document.querySelectorAll('[data-ods-detail-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.odsDetailTarget);
            if (!dialog) return;
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
        });
    });

    document.querySelectorAll('[data-ods-detail-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
</script>
@endsection
