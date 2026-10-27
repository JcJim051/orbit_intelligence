@php
    $groupLabels = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'blanco' => 'Blanco', 'sin_asignar' => 'Sin asignar'];
@endphp

<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">Plan Estadístico Territorial</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Alistamiento de indicadores</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Revise la ficha técnica antes de solicitar la serie histórica. Los enlaces solo ven los indicadores que tienen asignados.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><strong>{{ $indicadores->count() }}</strong> indicador(es)</div>
                @if($canManage)<a class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white" href="{{ route('intelligence.indicadores-territoriales.create') }}">+ Nuevo indicador</a>@endif
            </div>
        </div>
    </section>

    <section class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:flex xl:flex-nowrap">
        @foreach($estados as $key => $label)
            @php($total = (int) ($summary[$key] ?? 0))
            @if($key !== 'historico_validado' && ($total > 0 || in_array($key, ['precargado', 'revision_tecnica', 'pendiente_historico'], true)))
                <div class="min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm xl:flex-1">
                    <span class="block text-[10px] font-bold uppercase leading-tight tracking-wide text-slate-500">{{ $label }}</span>
                    <strong class="mt-1 block text-xl leading-none text-slate-950">{{ $total }}</strong>
                </div>
            @endif
            @if($key === 'historico_validado')
                <div class="min-w-0 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 shadow-sm xl:flex-1">
                    <span class="block text-[10px] font-bold uppercase leading-tight tracking-wide text-emerald-700">Dependencias con indicadores</span>
                    <strong class="mt-1 block text-xl leading-none text-emerald-950">{{ $dependencyCount }}</strong>
                </div>
            @endif
        @endforeach
    </section>

    <form method="post" action="{{ route('intelligence.indicadores-territoriales.assign') }}" class="space-y-4">
        @csrf
        @if($canAssign)
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <h2 class="font-black text-emerald-950">Asignación individual o masiva</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <label><span class="mb-1 block text-xs font-bold uppercase text-emerald-700">Enlace de información</span><select required class="w-full rounded-xl border-emerald-200 bg-white" name="asignado_a"><option value="">Seleccione</option>@foreach($revisores as $revisor)<option value="{{ $revisor->id }}">{{ $revisor->name }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-xs font-bold uppercase text-emerald-700">O asignar todo el grupo</span><select class="w-full rounded-xl border-emerald-200 bg-white" name="grupo"><option value="">Solo seleccionados</option>@foreach(['verde','amarillo','blanco','sin_asignar'] as $grupo)<option value="{{ $grupo }}">{{ $groupLabels[$grupo] }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-xs font-bold uppercase text-emerald-700">Motivo</span><input class="w-full rounded-xl border-emerald-200 bg-white" name="motivo" placeholder="Carga inicial o reasignación"></label>
                    <div class="flex items-end"><button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Asignar</button></div>
                </div>
                <p class="mt-2 text-xs text-emerald-700">Si elige un grupo, se asignarán todos sus indicadores. Sin grupo, se asignan únicamente las filas marcadas.</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-indicator-table>
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-end sm:justify-between">
                <label class="block w-full sm:max-w-xl">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Buscar en la tabla</span>
                    <input type="search" class="w-full rounded-xl border-slate-300" placeholder="Código, indicador, dependencia, área, responsable o estado" data-table-search>
                </label>
                <label class="block sm:w-44">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Filas por página</span>
                    <select class="w-full rounded-xl border-slate-300" data-table-size><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr>@if($canAssign)<th class="px-4 py-3"><span class="sr-only">Seleccionar</span></th>@endif<th class="px-4 py-3">Indicador</th><th class="px-4 py-3">Dependencia / área</th><th class="px-4 py-3">Periodicidad</th><th class="px-4 py-3">Responsable interno</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($indicadores as $indicador)
                            <tr class="align-top hover:bg-slate-50" data-table-row>
                                @if($canAssign)<td class="px-4 py-4"><input class="rounded border-slate-300" type="checkbox" name="indicator_ids[]" value="{{ $indicador->id }}"></td>@endif
                                <td class="px-4 py-4"><a class="font-bold text-emerald-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">{{ $indicador->codigo }}</a><p class="mt-1 max-w-xl text-slate-700">{{ $indicador->nombre }}</p><span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Grupo {{ $groupLabels[$indicador->grupo_asignacion] ?? $indicador->grupo_asignacion }}</span></td>
                                <td class="px-4 py-4 text-slate-600"><strong class="text-slate-800">{{ $indicador->dependencia?->nombre ?? $indicador->dependencia_fuente ?? 'Sin relacionar' }}</strong><br>{{ $indicador->area_tematica ?: 'Sin área' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ \App\Models\IndicadorTerritorial::PERIODICIDADES[$indicador->periodicidad] ?? $indicador->periodicidad_original ?? 'Por definir' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $indicador->asignado?->name ?? 'Sin asignar' }}</td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $estados[$indicador->estado] ?? $indicador->estado }}</span></td>
                                <td class="px-4 py-4"><div class="flex flex-wrap gap-2"><a class="inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">Ver</a>@if(auth()->user()->canEditTerritorialIndicator($indicador))<a class="inline-flex rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white" href="{{ route('intelligence.indicadores-territoriales.edit', $indicador) }}">Editar</a>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canAssign ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">No hay indicadores para mostrar.</td></tr>
                        @endforelse
                        @if($indicadores->isNotEmpty())<tr class="hidden" data-table-empty><td colspan="{{ $canAssign ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">No se encontraron indicadores con esa búsqueda.</td></tr>@endif
                    </tbody>
                </table>
            </div>
            @if($indicadores->isNotEmpty())
                <div class="flex flex-col gap-3 border-t border-slate-200 p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-slate-600" data-table-summary></span>
                    <div class="flex items-center gap-2"><button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" data-table-prev>Anterior</button><span class="min-w-24 text-center font-bold text-slate-700" data-table-page></span><button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" data-table-next>Siguiente</button></div>
                </div>
            @endif
        </section>
    </form>
</div>

<script>
(() => {
    const initIndicatorTable = () => {
        const root = document.querySelector('[data-indicator-table]');
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';

        const rows = [...root.querySelectorAll('[data-table-row]')];
        const search = root.querySelector('[data-table-search]');
        const size = root.querySelector('[data-table-size]');
        const summary = root.querySelector('[data-table-summary]');
        const pageLabel = root.querySelector('[data-table-page]');
        const previous = root.querySelector('[data-table-prev]');
        const next = root.querySelector('[data-table-next]');
        const empty = root.querySelector('[data-table-empty]');
        let page = 1;

        const normalize = value => value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const render = () => {
            const term = normalize(search?.value.trim() || '');
            const pageSize = Number(size?.value || 25);
            const filtered = rows.filter(row => normalize(row.textContent).includes(term));
            const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
            page = Math.min(page, pages);
            const start = (page - 1) * pageSize;
            const visible = new Set(filtered.slice(start, start + pageSize));

            rows.forEach(row => row.classList.toggle('hidden', !visible.has(row)));
            empty?.classList.toggle('hidden', filtered.length !== 0);
            if (summary) summary.textContent = filtered.length === 0 ? '0 resultados' : `Mostrando ${start + 1}–${Math.min(start + pageSize, filtered.length)} de ${filtered.length}`;
            if (pageLabel) pageLabel.textContent = `Página ${page} de ${pages}`;
            if (previous) previous.disabled = page <= 1;
            if (next) next.disabled = page >= pages;
        };

        search?.addEventListener('input', () => { page = 1; render(); });
        size?.addEventListener('change', () => { page = 1; render(); });
        previous?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
        next?.addEventListener('click', () => { page += 1; render(); });
        render();
    };

    initIndicatorTable();
    document.addEventListener('livewire:navigated', initIndicatorTable);
})();
</script>
