@extends('layouts.app', ['title' => 'Construcción metas resultado · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Construcción metas resultado</h1>
            <p class="page-subtitle max-w-3xl">Bandeja para construir la metodología de medición de metas resultado: línea base, valor actual, indicador producto, avance de gestión y avance del indicador resultado.</p>
        </div>
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

    @if($canManageAssignments)
        <section class="panel">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Repartir pendientes</h2>
                    <p class="text-sm text-slate-500">Asigne las metas resultado sin responsable en partes iguales entre los usuarios que seleccione.</p>
                </div>
                <button type="button" class="btn-secondary" onclick="document.getElementById('assign-construction-modal')?.showModal()" @disabled($reviewers->isEmpty())>
                    Repartir seleccionados
                </button>
            </div>

            <dialog id="assign-construction-modal" class="rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/40" style="position: fixed; inset: 0; margin: auto; width: min(720px, calc(100vw - 2rem)); max-height: calc(100vh - 3rem); overflow: hidden; background: #ffffff; color: #0f172a;">
                <form method="post" action="{{ route('intelligence.construccion-metas-resultado.assign-team') }}" class="space-y-5 p-6" style="background: #ffffff; color: #0f172a;" onsubmit="return confirm('Se asignarán solo metas resultado sin responsable en partes iguales entre los usuarios seleccionados. Las asignaciones existentes no cambiarán. ¿Continuar?')">
                    @csrf
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="eyebrow" style="color: #047857;">Reparto automático</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950" style="color: #020617;">Seleccionar usuarios</h2>
                            <p class="mt-1 text-sm text-slate-500" style="color: #475569;">El sistema dividirá los pendientes sin responsable en partes iguales entre los usuarios marcados.</p>
                        </div>
                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-lg font-black text-slate-500 hover:bg-slate-50" style="color: #475569; background: #ffffff;" onclick="document.getElementById('assign-construction-modal')?.close()" aria-label="Cerrar">×</button>
                    </div>

                    <div class="grid max-h-[52vh] gap-2 overflow-y-auto pr-1">
                    @forelse($reviewers as $reviewer)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-indigo-200 hover:bg-indigo-50" style="display: flex; align-items: flex-start; gap: 12px; min-height: 56px; width: 100%; background: #f8fafc; color: #0f172a; overflow: visible;">
                            <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}" @checked(in_array((string) $reviewer->id, old('reviewer_ids', []), true))>
                            <span style="display: block; flex: 1 1 auto; min-width: 0; max-width: 100%; color: #0f172a; font-size: 14px; font-weight: 700; line-height: 18px; white-space: normal; overflow-wrap: anywhere;">
                                {{ $reviewer->name ?: $reviewer->email }}
                                @if($reviewer->email)
                                    <small style="display: block; margin-top: 2px; color: #64748b; font-size: 12px; font-weight: 500; line-height: 16px; white-space: normal; overflow-wrap: anywhere;">{{ $reviewer->email }}</small>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">No hay usuarios activos con permiso para revisar ODS.</p>
                    @endforelse
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('assign-construction-modal')?.close()">Cancelar</button>
                        <button class="btn-primary" @disabled($reviewers->isEmpty())>Repartir en partes iguales</button>
                    </div>
                </form>
            </dialog>

            @if($errors->has('reviewer_ids') || $errors->has('reviewer_ids.*'))
                <script>
                    queueMicrotask(() => document.getElementById('assign-construction-modal')?.showModal());
                </script>
            @endif
        </section>
    @endif

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
