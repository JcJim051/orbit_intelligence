@extends('layouts.app', ['title' => 'Construcción metas resultado · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Construcción metas resultado</h1>
            <p class="page-subtitle max-w-3xl">Bandeja para construir la metodología de medición de metas resultado: línea base, valor actual, indicador producto, avance de gestión y avance del indicador resultado.</p>
        </div>
        @if($canManageAssignments)
            <form method="post" action="{{ route('intelligence.construccion-metas-resultado.assign-team') }}" onsubmit="return confirm('Se asignarán solo metas resultado sin responsable al equipo ODS base. Las asignaciones existentes no cambiarán. ¿Continuar?')">
                @csrf
                <button class="btn-secondary">Repartir pendientes</button>
            </form>
        @endif
    </header>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($requiresMigration ?? false)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
            <p class="eyebrow text-amber-700">Requiere migración</p>
            <h2 class="mt-2 text-xl font-black">Faltan las tablas de construcción de metas resultado</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6">
                El código del módulo ya está disponible, pero la base de datos todavía no tiene
                <code class="rounded bg-white px-1 py-0.5">meta_resultado_construcciones</code>.
                Ejecute las migraciones en la conexión PostGIS administrada y vuelva a cargar.
            </p>
            <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">php artisan migrate --database=managed_postgis_admin</pre>
        </section>
    @else

    <section class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-3 lg:grid-cols-5">
        @foreach($statuses as $status => $label)
            <article class="rounded-xl bg-slate-50 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $label }}</span>
                    <p class="text-2xl font-black leading-none text-slate-950">{{ (int) ($summary[$status] ?? 0) }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="panel">
        <form method="get" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="field xl:col-span-2">
                <span>Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Meta, indicador, metodología...">
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="status">
                    <option value="">Todos</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if($canManageAssignments)
                <label class="field">
                    <span>Responsable</span>
                    <select name="assigned">
                        <option value="">Todos</option>
                        <option value="me" @selected($filters['assigned'] === 'me')>Mis asignados</option>
                        @foreach($reviewers as $reviewer)
                            <option value="{{ $reviewer->id }}" @selected($filters['assigned'] === (string) $reviewer->id)>{{ $reviewer->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="flex items-end gap-2">
                <button class="btn-primary">Filtrar</button>
                <a class="btn-secondary" href="{{ url()->current() }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Metas resultado para construcción</h2>
            <p class="text-sm text-slate-500">{{ $tasks->count() }} en esta página</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Meta resultado</th>
                        <th class="px-4 py-3">Indicador resultado</th>
                        <th class="px-4 py-3">Metas producto</th>
                        <th class="px-4 py-3">Medición</th>
                        @if($canManageAssignments)
                            <th class="px-4 py-3">Responsable</th>
                        @endif
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                        @php($meta = $task->metaResultado)
                        @php($indicador = $meta?->indicador)
                        @php($programa = $meta?->subprograma?->programa ?? $meta?->programa)
                        @php($pilar = $programa?->linea?->eje?->pilar)
                        <tr>
                            <td class="max-w-xl px-4 py-3 align-top">
                                <span class="mb-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Meta resultado PDD</span>
                                <span class="block font-semibold text-slate-950">{{ $meta?->codigo_provisional ?? $meta?->codigo ?? 'Sin código' }} · {{ $meta?->descripcion }}</span>
                                <span class="text-xs text-slate-500">{{ $pilar ? 'Pilar '.$pilar->numeral.' — '.$pilar->nombre : 'Sin pilar' }} · {{ $programa?->nombre ?? 'Sin programa' }}</span>
                            </td>
                            <td class="max-w-md px-4 py-3 align-top">
                                <span class="block font-semibold text-slate-900">{{ $indicador?->nombre ?? 'Sin indicador resultado' }}</span>
                                <span class="text-xs text-slate-500">{{ $indicador?->codigo ?? 'Sin código' }} · {{ $indicador?->unidad_medida ?? 'Sin unidad' }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="font-semibold text-slate-950">{{ $meta?->metas_producto_count ?? 0 }}</span>
                                <span class="text-xs text-slate-500">asociada(s)</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="block text-xs text-slate-500">Gestión</span>
                                <strong>{{ $task->management_progress_pct !== null ? number_format((float) $task->management_progress_pct, 1, ',', '.').' %' : 'Por calcular' }}</strong>
                                <span class="mt-1 block text-xs text-slate-500">Resultado: {{ $task->result_progress_pct !== null ? number_format((float) $task->result_progress_pct, 1, ',', '.').' %' : 'Sin dato' }}</span>
                            </td>
                            @if($canManageAssignments)
                                <td class="px-4 py-3 align-top">{{ $task->assignedUser?->name ?? 'Sin asignar' }}</td>
                            @endif
                            <td class="px-4 py-3 align-top"><span class="status status-pending_review">{{ $statuses[$task->status] ?? $task->status }}</span></td>
                            <td class="px-4 py-3 align-top">
                                <a class="font-semibold text-indigo-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'construccion-meta-resultado', 'record' => $task->getRouteKey()]) }}">Construir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageAssignments ? 7 : 6 }}" class="px-4 py-8 text-center text-slate-500">No hay metas resultado con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">
            {{ $tasks->links() }}
        </div>
    </section>
    @endif
</div>
@endsection
