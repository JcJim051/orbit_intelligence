@extends('layouts.app', ['title' => 'Indicadores ODS por pilar · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header>
        <p class="eyebrow">Estructura plan PDD · ODS</p>
        <h1 class="page-title">Indicadores ODS por pilar</h1>
        <p class="page-subtitle max-w-4xl">Consulta los seis pilares del Plan de Desarrollo y los indicadores ODS relacionados con cada uno.</p>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Pilares del Plan de Desarrollo</h2>
            <span class="text-sm text-slate-500">{{ $pillarRows->count() }} pilares</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Pilar</th>
                        <th class="px-5 py-3">Nombre del pilar</th>
                        <th class="px-5 py-3 text-right">Indicadores ODS</th>
                        <th class="px-5 py-3 text-right">Metas resultado</th>
                        <th class="px-5 py-3 text-right">Indicadores resultado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($pillarRows as $row)
                        @php($pillar = $row['pillar'])
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $row['pillar_label'] }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $pillar->nombre }}</td>
                            <td class="px-5 py-4 text-right">
                                @if($row['ods_count'] > 0)
                                    <button type="button" class="font-bold text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900" data-modal-open="pillar-ods-{{ $pillar->id }}">{{ $row['ods_count'] }}</button>
                                @else
                                    <span class="text-slate-400">Sin indicadores</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if($row['meta_count'] > 0)
                                    <button type="button" class="font-bold text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900" data-modal-open="pillar-metas-{{ $pillar->id }}">{{ $row['meta_count'] }}</button>
                                @else
                                    <span class="text-slate-400">Sin metas</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if($row['result_indicator_count'] > 0)
                                    <button type="button" class="font-bold text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900" data-modal-open="pillar-resultados-{{ $pillar->id }}">{{ $row['result_indicator_count'] }}</button>
                                @else
                                    <span class="text-slate-400">Sin indicadores</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="p-0">
                                <div id="pillar-ods-{{ $pillar->id }}" class="siid-modal" hidden>
                                    <div class="siid-modal-backdrop" data-modal-close></div>
                                    <section class="siid-modal-card" role="dialog" aria-modal="true" aria-labelledby="pillar-ods-title-{{ $pillar->id }}">
                                        <button type="button" class="siid-modal-close" data-modal-close aria-label="Cerrar">×</button>
                                        <p class="eyebrow">{{ $row['pillar_label'] }} · Pilar PDD</p>
                                        <h3 id="pillar-ods-title-{{ $pillar->id }}" class="mt-1 text-xl font-semibold">{{ $pillar->nombre }}</h3>
                                        @if($row['details']->isEmpty())
                                            <p class="mt-5 text-sm text-slate-500">Este pilar aún no tiene indicadores ODS relacionados.</p>
                                        @else
                                            <div class="mt-5 space-y-4">
                                                @foreach($row['details'] as $detail)
                                                    @php($relation = $detail['relation'])
                                                    @php($ods = $relation->odsIndicator)
                                                    <article class="rounded-xl border border-slate-200 p-4">
                                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                                            <div>
                                                                <p class="font-semibold text-indigo-800">{{ $ods?->code }} · {{ $ods?->name }}</p>
                                                                <p class="mt-1 text-xs text-slate-500">Objetivo {{ $ods?->target?->goal?->code }} · Meta {{ $ods?->target?->code }}</p>
                                                            </div>
                                                            <a class="text-sm font-semibold text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'ods-relacion', 'record' => $relation->id]) }}">Ver detalle</a>
                                                        </div>
                                                        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                                            @foreach($detail['metas'] as $meta)
                                                                <div class="rounded-lg bg-slate-50 p-3">
                                                                    <p class="text-sm font-semibold text-slate-800">Meta resultado: {{ $meta->codigo_provisional ?? $meta->codigo }} · {{ $meta->descripcion }}</p>
                                                                    <p class="mt-1 text-sm text-slate-600">Indicador resultado: {{ $relation->indicador?->codigo ?? 'Sin código' }} · {{ $relation->indicador?->nombre ?? 'Sin nombre' }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </article>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                </div>
                                <div id="pillar-metas-{{ $pillar->id }}" class="siid-modal" hidden>
                                    <div class="siid-modal-backdrop" data-modal-close></div>
                                    <section class="siid-modal-card" role="dialog" aria-modal="true" aria-labelledby="pillar-metas-title-{{ $pillar->id }}">
                                        <button type="button" class="siid-modal-close" data-modal-close aria-label="Cerrar">×</button>
                                        <p class="eyebrow">{{ $row['pillar_label'] }} · Metas resultado</p>
                                        <h3 id="pillar-metas-title-{{ $pillar->id }}" class="mt-1 text-xl font-semibold">Metas resultado relacionadas</h3>
                                        <div class="mt-5 space-y-3">
                                            @foreach($row['details'] as $detail)
                                                @foreach($detail['metas'] as $meta)
                                                    <article class="rounded-xl border border-slate-200 p-4">
                                                        <p class="font-semibold text-slate-800">{{ $meta->codigo_provisional ?? $meta->codigo }} · {{ $meta->descripcion }}</p>
                                                        <p class="mt-1 text-sm text-slate-600">Indicador resultado: {{ $detail['relation']->indicador?->codigo ?? 'Sin código' }} · {{ $detail['relation']->indicador?->nombre ?? 'Sin nombre' }}</p>
                                                        <p class="mt-1 text-xs text-slate-500">ODS: {{ $detail['relation']->odsIndicator?->code }} · {{ $detail['relation']->odsIndicator?->name }}</p>
                                                    </article>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </section>
                                </div>
                                <div id="pillar-resultados-{{ $pillar->id }}" class="siid-modal" hidden>
                                    <div class="siid-modal-backdrop" data-modal-close></div>
                                    <section class="siid-modal-card" role="dialog" aria-modal="true" aria-labelledby="pillar-resultados-title-{{ $pillar->id }}">
                                        <button type="button" class="siid-modal-close" data-modal-close aria-label="Cerrar">×</button>
                                        <p class="eyebrow">{{ $row['pillar_label'] }} · Indicadores resultado</p>
                                        <h3 id="pillar-resultados-title-{{ $pillar->id }}" class="mt-1 text-xl font-semibold">Indicadores resultado relacionados</h3>
                                        <div class="mt-5 space-y-3">
                                            @foreach($row['details'] as $detail)
                                                <article class="rounded-xl border border-slate-200 p-4">
                                                    <p class="font-semibold text-slate-800">{{ $detail['relation']->indicador?->codigo ?? 'Sin código' }} · {{ $detail['relation']->indicador?->nombre ?? 'Sin nombre' }}</p>
                                                    <p class="mt-1 text-sm text-slate-600">ODS: {{ $detail['relation']->odsIndicator?->code }} · {{ $detail['relation']->odsIndicator?->name }}</p>
                                                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                                                        @foreach($detail['metas'] as $meta)
                                                            <p>Meta resultado: {{ $meta->codigo_provisional ?? $meta->codigo }} · {{ $meta->descripcion }}</p>
                                                        @endforeach
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

<style>
    .siid-modal[hidden] { display: none; }
    .siid-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 1rem; }
    .siid-modal-backdrop { position: absolute; inset: 0; background: rgb(15 23 42 / .45); }
    .siid-modal-card { position: relative; z-index: 1; width: min(100%, 820px); max-height: min(90vh, 800px); overflow-y: auto; border: 1px solid rgb(226 232 240); border-radius: 1.25rem; background: white; padding: 1.5rem; box-shadow: 0 24px 80px rgb(15 23 42 / .24); }
    .siid-modal-close { position: absolute; top: .75rem; right: .9rem; width: 2rem; height: 2rem; border: 1px solid rgb(203 213 225); border-radius: 999px; color: rgb(71 85 105); font-size: 1.35rem; line-height: 1; }
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
</script>
@endsection
