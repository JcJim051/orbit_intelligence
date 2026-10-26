@php
    $groupLabels = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'blanco' => 'Blanco', 'sin_asignar' => 'Sin asignar'];
@endphp

<div class="space-y-5">
    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">Plan Estadístico Territorial</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Alistamiento de indicadores</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Revise la ficha técnica antes de solicitar la serie histórica. Los enlaces solo ven los indicadores que tienen asignados.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-xl bg-indigo-50 px-4 py-3 text-sm text-indigo-900"><strong>{{ $indicadores->total() }}</strong> resultado(s) con los filtros actuales</div>
                @if($canManage)<a class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white" href="{{ route('intelligence.indicadores-territoriales.create') }}">+ Nuevo indicador</a>@endif
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($estados as $key => $label)
            @php($total = (int) ($summary[$key] ?? 0))
            @if($total > 0 || in_array($key, ['precargado', 'revision_tecnica', 'pendiente_historico'], true))
                <a href="{{ route('intelligence.indicadores-territoriales.index', ['estado' => $key]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-300">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</span>
                    <strong class="mt-2 block text-2xl text-slate-950">{{ $total }}</strong>
                </a>
            @endif
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="get" action="{{ route('intelligence.indicadores-territoriales.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="xl:col-span-2"><span class="mb-1 block text-xs font-bold uppercase text-slate-500">Buscar</span><input class="w-full rounded-xl border-slate-300" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Código, nombre, dependencia o área"></label>
            <label><span class="mb-1 block text-xs font-bold uppercase text-slate-500">Estado</span><select class="w-full rounded-xl border-slate-300" name="estado"><option value="">Todos</option>@foreach($estados as $key => $label)<option value="{{ $key }}" @selected(($filters['estado'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="mb-1 block text-xs font-bold uppercase text-slate-500">Grupo inicial</span><select class="w-full rounded-xl border-slate-300" name="grupo"><option value="">Todos</option>@foreach($grupos as $grupo)<option value="{{ $grupo }}" @selected(($filters['grupo'] ?? '') === $grupo)>{{ $groupLabels[$grupo] ?? ucfirst($grupo) }}</option>@endforeach</select></label>
            @if($canAssign)<label><span class="mb-1 block text-xs font-bold uppercase text-slate-500">Enlace</span><select class="w-full rounded-xl border-slate-300" name="asignado_a"><option value="">Todos</option><option value="sin_asignar" @selected(($filters['asignado_a'] ?? '') === 'sin_asignar')>Sin asignar</option>@foreach($revisores as $revisor)<option value="{{ $revisor->id }}" @selected((string)($filters['asignado_a'] ?? '') === (string)$revisor->id)>{{ $revisor->name }}</option>@endforeach</select></label>@endif
            <div class="flex items-end gap-2"><button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filtrar</button><a class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.index') }}">Limpiar</a></div>
        </form>
    </section>

    <form method="post" action="{{ route('intelligence.indicadores-territoriales.assign') }}" class="space-y-4">
        @csrf
        @if($canAssign)
            <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <h2 class="font-black text-indigo-950">Asignación individual o masiva</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <label><span class="mb-1 block text-xs font-bold uppercase text-indigo-700">Enlace de información</span><select required class="w-full rounded-xl border-indigo-200 bg-white" name="asignado_a"><option value="">Seleccione</option>@foreach($revisores as $revisor)<option value="{{ $revisor->id }}">{{ $revisor->name }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-xs font-bold uppercase text-indigo-700">O asignar todo el grupo</span><select class="w-full rounded-xl border-indigo-200 bg-white" name="grupo"><option value="">Solo seleccionados</option>@foreach(['verde','amarillo','blanco','sin_asignar'] as $grupo)<option value="{{ $grupo }}">{{ $groupLabels[$grupo] }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-xs font-bold uppercase text-indigo-700">Motivo</span><input class="w-full rounded-xl border-indigo-200 bg-white" name="motivo" placeholder="Carga inicial o reasignación"></label>
                    <div class="flex items-end"><button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Asignar</button></div>
                </div>
                <p class="mt-2 text-xs text-indigo-700">Si elige un grupo, se asignarán todos sus indicadores. Sin grupo, se asignan únicamente las filas marcadas.</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr>@if($canAssign)<th class="px-4 py-3"><span class="sr-only">Seleccionar</span></th>@endif<th class="px-4 py-3">Indicador</th><th class="px-4 py-3">Dependencia / área</th><th class="px-4 py-3">Periodicidad</th><th class="px-4 py-3">Responsable interno</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($indicadores as $indicador)
                            <tr class="align-top hover:bg-slate-50">
                                @if($canAssign)<td class="px-4 py-4"><input class="rounded border-slate-300" type="checkbox" name="indicator_ids[]" value="{{ $indicador->id }}"></td>@endif
                                <td class="px-4 py-4"><a class="font-bold text-indigo-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">{{ $indicador->codigo }}</a><p class="mt-1 max-w-xl text-slate-700">{{ $indicador->nombre }}</p><span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Grupo {{ $groupLabels[$indicador->grupo_asignacion] ?? $indicador->grupo_asignacion }}</span></td>
                                <td class="px-4 py-4 text-slate-600"><strong class="text-slate-800">{{ $indicador->dependencia?->nombre ?? $indicador->dependencia_fuente ?? 'Sin relacionar' }}</strong><br>{{ $indicador->area_tematica ?: 'Sin área' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ \App\Models\IndicadorTerritorial::PERIODICIDADES[$indicador->periodicidad] ?? $indicador->periodicidad_original ?? 'Por definir' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $indicador->asignado?->name ?? 'Sin asignar' }}</td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $estados[$indicador->estado] ?? $indicador->estado }}</span></td>
                                <td class="px-4 py-4"><div class="flex flex-wrap gap-2"><a class="inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">Ver</a>@if(auth()->user()->canEditTerritorialIndicator($indicador))<a class="inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white" href="{{ route('intelligence.indicadores-territoriales.edit', $indicador) }}">Editar</a>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No hay indicadores para mostrar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $indicadores->links() }}</div>
        </section>
    </form>
</div>
