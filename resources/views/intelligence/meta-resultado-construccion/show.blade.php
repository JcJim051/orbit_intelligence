@extends('layouts.app', ['title' => 'Construcción meta resultado · SIID 2.0'])

@section('content')
@php
    $meta = $task->metaResultado;
    $indicador = $meta?->indicador;
    $programa = $meta?->subprograma?->programa ?? $meta?->programa;
    $linea = $programa?->linea;
    $eje = $linea?->eje;
    $pilar = $eje?->pilar;
    $fmt = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $pct = fn ($value) => $value === null ? 'Sin dato' : number_format((float) $value, 1, ',', '.').' %';
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'construccion-metas-resultado']) }}" class="text-sm font-semibold text-indigo-700">← Volver a la bandeja</a>
            <p class="eyebrow mt-4">Construcción metas resultado</p>
            <h1 class="page-title">{{ $meta?->codigo_provisional ?? $meta?->codigo ?? 'Meta resultado' }}</h1>
            <p class="page-subtitle max-w-4xl">{{ $meta?->descripcion }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado</p>
            <p class="mt-1 font-black text-slate-950">{{ $statuses[$task->status] ?? $task->status }}</p>
            @if($canManageAssignments)
                <p class="mt-1 text-xs text-slate-500">Responsable: {{ $task->assignedUser?->name ?? 'Sin asignar' }}</p>
            @endif
        </div>
    </header>

    @if($reviewerLocked)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-800">
            Esta tarea está en validación o cerrada. El revisor no puede editarla hasta que sea devuelta.
        </div>
    @endif

    <section class="grid gap-4 lg:grid-cols-4">
        <article class="panel">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Avance gestión</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $pct($management['percentage']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Promedio simple de {{ $management['metas_count'] }} meta(s) producto.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Avance resultado</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $pct($resultProgress) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $measurementModes[$task->measurement_mode] ?? $task->measurement_mode }}</p>
        </article>
        <article class="panel">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Línea base</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $fmt($task->baseline_value ?? $meta?->linea_base ?? $indicador?->linea_base, 4) }}</p>
            <p class="mt-1 text-xs text-slate-500">Dato de trabajo o catálogo actual.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Valor actual</p>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $fmt($task->current_value, 4) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $task->current_value_date?->format('Y-m-d') ?? 'Sin fecha de corte' }}</p>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1fr_420px]">
        <div class="space-y-6">
            <section class="panel">
                <h2 class="text-lg font-black text-slate-950">Contexto de la meta resultado</h2>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div><dt class="font-semibold text-slate-500">Pilar</dt><dd>{{ $pilar ? $pilar->numeral.' · '.$pilar->nombre : 'Sin pilar' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Eje</dt><dd>{{ $eje ? $eje->numeral.' · '.$eje->nombre : 'Sin eje' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Línea estratégica</dt><dd>{{ $linea ? $linea->numeral.' · '.$linea->nombre : 'Sin línea' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Programa</dt><dd>{{ $programa?->nombre ?? 'Sin programa' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Subprograma</dt><dd>{{ $meta?->subprograma?->nombre ?? 'Sin subprograma' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Meta cuatrienio</dt><dd>{{ $fmt($meta?->meta_cuatrienio ?? $indicador?->meta_cuatrienio, 4) }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Metas producto asociadas y avance de gestión</h2>
                    <p class="text-sm text-slate-500">El avance de gestión se calcula como promedio simple del porcentaje físico de estas metas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Meta producto</th>
                                <th class="px-4 py-3">Dependencia</th>
                                <th class="px-4 py-3 text-right">Avance físico</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($management['rows'] as $row)
                                @php($metaProducto = $row['meta'])
                                @php($resumen = $row['resumen'])
                                <tr>
                                    <td class="max-w-xl px-4 py-3 align-top">
                                        <strong>{{ $metaProducto->codigo }} · {{ $metaProducto->nombre }}</strong>
                                        <p class="text-xs text-slate-500">{{ $metaProducto->sectorMga?->nombre ?? 'Sin sector MGA' }}</p>
                                    </td>
                                    <td class="px-4 py-3 align-top">{{ $metaProducto->dependencia?->nombre ?? 'Sin dependencia' }}</td>
                                    <td class="px-4 py-3 text-right align-top">
                                        <strong>{{ $pct($row['percentage']) }}</strong>
                                        <p class="text-xs text-slate-500">{{ $resumen ? $fmt($resumen['avance_fisico']).' / '.$fmt($resumen['programado_fisico']) : 'Sin reporte físico' }}</p>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Esta meta resultado no tiene metas producto asociadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel">
                <h2 class="text-lg font-black text-slate-950">Indicador resultado</h2>
                <p class="mt-2 font-semibold text-slate-900">{{ $indicador?->nombre ?? 'Sin indicador resultado asociado' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $indicador?->codigo ?? 'Sin código' }} · {{ $indicador?->unidad_medida ?? 'Sin unidad' }} · {{ $indicador?->orientacion?->label() ?? 'Sin orientación' }}</p>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div><dt class="font-semibold text-slate-500">Línea base catálogo</dt><dd>{{ $fmt($indicador?->linea_base, 4) }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Meta cuatrienio catálogo</dt><dd>{{ $fmt($indicador?->meta_cuatrienio, 4) }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Fuente verificación</dt><dd>{{ $indicador?->fuente_verificacion ?? 'Sin fuente' }}</dd></div>
                </dl>
                @if($acceptedOdsLinks->isNotEmpty())
                    <div class="mt-5 rounded-2xl bg-indigo-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Indicadores ODS/OBSA confirmados</p>
                        <div class="mt-3 space-y-2">
                            @foreach($acceptedOdsLinks as $link)
                                <article class="rounded-xl bg-white p-3 text-sm">
                                    <strong>{{ $link->odsIndicator->code }} · {{ $link->odsIndicator->name }}</strong>
                                    <p class="text-xs text-slate-500">ODS {{ $link->odsIndicator->target->goal->code }} · {{ $link->odsIndicator->target->name }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <section class="panel">
                <h2 class="text-lg font-black text-slate-950">Datos de construcción</h2>
                <form method="post" action="{{ route('intelligence.construccion-metas-resultado.update', $task) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    @if($canManageAssignments)
                        <label class="field">
                            <span>Responsable</span>
                            <select name="assigned_to" @disabled(! $canEdit)>
                                <option value="">Sin asignar</option>
                                @foreach($reviewers as $reviewer)
                                    <option value="{{ $reviewer->id }}" @selected(old('assigned_to', $task->assigned_to) == $reviewer->id)>{{ $reviewer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="field">
                        <span>Estado</span>
                        <select name="status" @disabled(! $canEdit)>
                            @foreach($availableStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $task->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Forma de medición</span>
                        <select name="measurement_mode" @disabled(! $canEdit)>
                            @foreach($measurementModes as $value => $label)
                                <option value="{{ $value }}" @selected(old('measurement_mode', $task->measurement_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-xs leading-5 text-slate-500">
                            <strong>{{ $measurementModes['dual'] ?? 'Medición separada' }}:</strong>
                            {{ $measurementModeDescriptions['dual'] ?? '' }}
                            <br>
                            <strong>{{ $measurementModes['management_as_result'] ?? 'Gestión como resultado' }}:</strong>
                            {{ $measurementModeDescriptions['management_as_result'] ?? '' }}
                        </small>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="field">
                            <span>Línea base</span>
                            <input type="number" step="0.0001" name="baseline_value" value="{{ old('baseline_value', $task->baseline_value) }}" @disabled(! $canEdit)>
                        </label>
                        <label class="field">
                            <span>Valor actual</span>
                            <input type="number" step="0.0001" name="current_value" value="{{ old('current_value', $task->current_value) }}" @disabled(! $canEdit)>
                        </label>
                    </div>
                    <label class="field">
                        <span>Fecha de corte del valor actual</span>
                        <input type="date" name="current_value_date" value="{{ old('current_value_date', $task->current_value_date?->format('Y-m-d')) }}" @disabled(! $canEdit)>
                    </label>
                    <label class="field">
                        <span>Fuente del valor actual</span>
                        <textarea name="current_value_source" rows="3" @disabled(! $canEdit)>{{ old('current_value_source', $task->current_value_source) }}</textarea>
                    </label>
                    <label class="field">
                        <span>Metodología / soporte</span>
                        <textarea name="methodology_notes" rows="5" @disabled(! $canEdit)>{{ old('methodology_notes', $task->methodology_notes) }}</textarea>
                    </label>
                    <label class="field">
                        <span>Comentario de esta actualización</span>
                        <textarea name="comment" rows="3" @disabled(! $canEdit)>{{ old('comment') }}</textarea>
                    </label>
                    @if($canEdit)
                        <button class="btn-primary w-full">Guardar construcción</button>
                    @endif
                </form>
            </section>

            <section class="panel">
                <h2 class="text-lg font-black text-slate-950">Comentarios y trazabilidad</h2>
                @if($canEdit)
                    <form method="post" action="{{ route('intelligence.construccion-metas-resultado.comments.store', $task) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="field">
                            <span>Nuevo comentario</span>
                            <textarea name="comment" rows="3" required></textarea>
                        </label>
                        <button class="btn-secondary">Agregar comentario</button>
                    </form>
                @endif
                <div class="mt-5 space-y-3">
                    @forelse($task->comments as $comment)
                        <article class="rounded-xl border border-slate-200 p-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <strong>{{ $comment->user?->name ?? 'Sistema' }}</strong>
                                <span class="text-xs text-slate-500">{{ $comment->created_at?->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="mt-2 text-slate-700">{{ $comment->comment }}</p>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $comment->event_type }}</p>
                        </article>
                    @empty
                        <p class="text-sm text-slate-500">Sin comentarios todavía.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </section>
</div>
@endsection
