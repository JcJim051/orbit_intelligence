@extends('layouts.app', ['title' => 'Indicadores ODS por dependencia · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Estructura plan PDD · ODS</p>
            <h1 class="page-title">Indicadores ODS por dependencia</h1>
            <p class="page-subtitle max-w-4xl">Identifica cómo se conectan los ODS con los indicadores y las metas a cargo de cada dependencia.</p>
        </div>
        <nav class="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Agrupación de indicadores ODS">
            <a class="rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:bg-emerald-50 hover:text-emerald-800" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'ods-relaciones']) }}">Por pilar</a>
            <span class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white">Por dependencia</span>
        </nav>
    </header>

    <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <form method="get" action="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'ods-relaciones-dependencias']) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="field min-w-72">
                <span>Corte financiero del informe</span>
                <select name="seguimiento">
                    @foreach($followUps as $option)
                        <option value="{{ $option->id }}" @selected($followUp?->id === $option->id)>{{ $option->etiqueta() }} · {{ $option->fecha_corte->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn-secondary">Aplicar corte</button>
        </form>
        @if($followUp)
            <a class="btn-primary text-center" href="{{ route('intelligence.indicadores-ods.dependencias.download', ['seguimiento' => $followUp->id]) }}">Descargar informe Excel</a>
        @else
            <span class="text-sm font-semibold text-amber-700">Cree un corte mensual para habilitar la descarga financiera.</span>
        @endif
    </section>

    <section class="grid gap-3 sm:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="eyebrow">Dependencias relacionadas</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $dependenciesWithRelations }}</p>
            <p class="mt-1 text-sm text-slate-500">de {{ $dependencyRows->count() }} dependencias activas</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="eyebrow">Objetivos ODS</p>
            <p class="mt-2 text-3xl font-black text-emerald-700">{{ $goalCount }}</p>
            <p class="mt-1 text-sm text-slate-500">presentes en las relaciones</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="eyebrow">Indicadores ODS</p>
            <p class="mt-2 text-3xl font-black text-emerald-700">{{ $odsCount }}</p>
            <p class="mt-1 text-sm text-slate-500">vinculados al Plan de Desarrollo</p>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @php
            $counterModalTypes = [
                'goals' => ['title' => 'Objetivos ODS', 'count' => 'goal_count'],
                'ods_indicators' => ['title' => 'Indicadores ODS', 'count' => 'ods_count'],
                'result_goals' => ['title' => 'Metas resultado', 'count' => 'meta_count'],
                'product_goals' => ['title' => 'Metas producto', 'count' => 'product_count'],
                'result_indicators' => ['title' => 'Indicadores resultado', 'count' => 'result_indicator_count'],
            ];
        @endphp
        <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Mapa ODS de las dependencias</h2>
                <p class="text-sm text-slate-500">Los conteos se derivan de las metas producto asignadas a cada dependencia.</p>
            </div>
            <span class="status">{{ $dependencyRows->count() }} dependencias</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable data-page-length="25" data-search-placeholder="Buscar dependencia, sigla o código…" class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Dependencia</th>
                        <th class="px-4 py-3 text-right">Objetivos ODS</th>
                        <th class="px-4 py-3 text-right">Indicadores ODS</th>
                        <th class="px-4 py-3 text-right">Metas resultado</th>
                        <th class="px-4 py-3 text-right">Metas producto</th>
                        <th class="px-4 py-3 text-right">Indicadores resultado</th>
                        <th class="px-4 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($dependencyRows as $row)
                        @php($dependency = $row['dependency'])
                        <tr>
                            <td class="px-4 py-4" data-label="Dependencia">
                                <p class="font-bold text-slate-900">{{ $dependency->sigla ?: $dependency->codigo }}</p>
                                <p class="mt-1 max-w-xl text-xs leading-5 text-slate-600">{{ $dependency->nombre }}</p>
                            </td>
                            @foreach($counterModalTypes as $type => $modalConfig)
                                <td class="px-4 py-4 text-right" data-label="{{ $modalConfig['title'] }}">
                                    @if($row[$modalConfig['count']] > 0)
                                        <button
                                            type="button"
                                            class="ods-count-button"
                                            data-modal-open="dependency-{{ $type }}-{{ $dependency->id }}"
                                            aria-label="Ver {{ strtolower($modalConfig['title']) }} de {{ $dependency->nombre }}"
                                        >{{ $row[$modalConfig['count']] }}</button>
                                    @else
                                        <span class="font-bold text-slate-400">0</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-4 text-right" data-label="Acción">
                                @if($row['details']->isNotEmpty())
                                    <button type="button" class="btn-small whitespace-nowrap" data-modal-open="dependency-ods-{{ $dependency->id }}">Ver relaciones</button>
                                @else
                                    <span class="text-xs font-semibold text-slate-400">Sin relaciones</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach($dependencyRows as $row)
        @continue($row['details']->isEmpty())
        @php($dependency = $row['dependency'])
        @foreach($counterModalTypes as $type => $modalConfig)
            <div id="dependency-{{ $type }}-{{ $dependency->id }}" class="siid-modal" hidden>
                <div class="siid-modal-backdrop" data-modal-close></div>
                <section class="siid-modal-card siid-modal-card-compact" role="dialog" aria-modal="true" aria-labelledby="dependency-{{ $type }}-title-{{ $dependency->id }}">
                    <button type="button" class="siid-modal-close" data-modal-close aria-label="Cerrar">×</button>
                    <p class="eyebrow">{{ $dependency->sigla ?: $dependency->codigo }} · Detalle del contador</p>
                    <h3 id="dependency-{{ $type }}-title-{{ $dependency->id }}" class="mt-1 pr-10 text-xl font-semibold text-slate-950">{{ $modalConfig['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $dependency->nombre }}</p>
                    <div class="mt-5 space-y-2">
                        @foreach($row['counter_details'][$type] as $item)
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="shrink-0 rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">{{ $item['code'] }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold leading-5 text-slate-900">{{ $item['name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['context'] }}</p>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-4 text-right text-xs font-semibold text-slate-500">{{ $row[$modalConfig['count']] }} registro(s) único(s)</p>
                </section>
            </div>
        @endforeach
        <div id="dependency-ods-{{ $dependency->id }}" class="siid-modal" hidden>
            <div class="siid-modal-backdrop" data-modal-close></div>
            <section class="siid-modal-card" role="dialog" aria-modal="true" aria-labelledby="dependency-ods-title-{{ $dependency->id }}">
                <button type="button" class="siid-modal-close" data-modal-close aria-label="Cerrar">×</button>
                <p class="eyebrow">{{ $dependency->sigla ?: $dependency->codigo }} · Relaciones ODS</p>
                <h3 id="dependency-ods-title-{{ $dependency->id }}" class="mt-1 pr-10 text-xl font-semibold text-slate-950">{{ $dependency->nombre }}</h3>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                    <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-800">{{ $row['ods_count'] }} indicador(es) ODS</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">{{ $row['meta_count'] }} meta(s) resultado</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">{{ $row['product_count'] }} meta(s) producto</span>
                </div>
                <div class="mt-5 space-y-4">
                    @foreach($row['details'] as $detail)
                        @php($relation = $detail['relation'])
                        @php($ods = $relation->odsIndicator)
                        <article class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex flex-col gap-3 bg-emerald-50 p-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-black text-emerald-900">{{ $ods?->code }} · {{ $ods?->name }}</p>
                                    <p class="mt-1 text-xs text-emerald-800">Objetivo {{ $ods?->target?->goal?->code }} · {{ $ods?->target?->goal?->name }} · Meta {{ $ods?->target?->code }}</p>
                                </div>
                                <a class="shrink-0 text-sm font-bold text-emerald-800 underline underline-offset-4" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'ods-relacion', 'record' => $relation->id]) }}">Ver detalle</a>
                            </div>
                            <div class="p-4">
                                <p class="text-sm font-bold text-slate-900">Indicador resultado: {{ $relation->indicador?->codigo ?? 'Sin código' }} · {{ $relation->indicador?->nombre ?? 'Sin nombre' }}</p>
                                <div class="mt-3 space-y-3">
                                    @foreach($detail['metas'] as $metaDetail)
                                        @php($meta = $metaDetail['meta'])
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-sm font-semibold text-slate-800">Meta resultado: {{ $meta->codigo_provisional ?? $meta->codigo }} · {{ $meta->descripcion }}</p>
                                            <div class="mt-2 space-y-1 border-l-2 border-emerald-300 pl-3 text-xs text-slate-600">
                                                @foreach($metaDetail['productos'] as $producto)
                                                    <p><strong>{{ $producto->codigo }}</strong> · {{ $producto->nombre }}</p>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    @endforeach
</div>

<style>
    .siid-modal[hidden] { display: none; }
    .siid-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 1rem; }
    .siid-modal-backdrop { position: absolute; inset: 0; background: rgb(15 23 42 / .45); }
    .siid-modal-card { position: relative; z-index: 1; width: min(100%, 920px); max-height: min(90vh, 820px); overflow-y: auto; border: 1px solid rgb(226 232 240); border-radius: 1.25rem; background: white; padding: 1.5rem; box-shadow: 0 24px 80px rgb(15 23 42 / .24); }
    .siid-modal-card-compact { width: min(100%, 720px); }
    .siid-modal-close { position: absolute; top: .75rem; right: .9rem; width: 2rem; height: 2rem; border: 1px solid rgb(203 213 225); border-radius: 999px; color: rgb(71 85 105); font-size: 1.35rem; line-height: 1; }
    .ods-count-button { min-width: 2.25rem; border: 1px solid rgb(167 243 208); border-radius: .65rem; background: rgb(236 253 245); padding: .4rem .65rem; color: rgb(4 120 87); font-weight: 800; line-height: 1; transition: background-color .15s ease, border-color .15s ease, transform .15s ease; }
    .ods-count-button:hover { border-color: rgb(52 211 153); background: rgb(209 250 229); transform: translateY(-1px); }
    .ods-count-button:focus-visible { outline: 3px solid rgb(110 231 183 / .55); outline-offset: 2px; }
</style>
<script>
    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.modalOpen);
            if (modal) modal.hidden = false;
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => { button.closest('.siid-modal').hidden = true; });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.siid-modal:not([hidden])').forEach((modal) => { modal.hidden = true; });
    });
</script>
@endsection
