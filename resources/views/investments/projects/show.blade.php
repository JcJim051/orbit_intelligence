@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => $value === null ? 'No reportado' : '$'.number_format((float) $value, 0, ',', '.');
    $percent = fn ($value) => $value === null ? 'No reportado' : number_format((float) $value, 1, ',', '.').'%';
@endphp
<div class="space-y-7">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div class="max-w-4xl">
            <a href="{{ route('investments.projects.index') }}" class="text-sm text-indigo-600">← Portafolio</a>
            <p class="mt-3 text-xs font-semibold uppercase tracking-[.18em] text-indigo-600">BPIN {{ $project->bpin }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $project->name ?? 'Nombre no reportado' }}</h1>
            <p class="mt-3 text-slate-600">{{ $project->objective ?? 'Objetivo general no reportado por la fuente consultada.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><span class="status">{{ $project->status ?? 'Estado no reportado' }}</span>@if($project->project_type)<span class="status">{{ $project->project_type }}</span>@endif</div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Valor total', $money($project->total_value)], ['Valor vigente', $money($project->current_value)],
            ['Obligado', $money($project->obligated_value)], ['Pagado', $money($project->paid_value)],
            ['Avance físico', $percent($project->physical_progress)], ['Avance financiero reportado', $percent($project->financial_progress)],
            ['Beneficiarios', $project->beneficiaries_total === null ? 'No reportado' : number_format($project->beneficiaries_total, 0, ',', '.')],
            ['Horizonte', $project->horizon ?? 'No reportado'],
        ] as [$label, $value])
            <article class="panel"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-semibold">{{ $value }}</p></article>
        @endforeach
    </div>

    @if($alerts->isNotEmpty())
        <section class="panel border-amber-200 bg-amber-50/50">
            <div class="panel-head"><div><h2>Alertas internas de seguimiento</h2><span>No son calificaciones oficiales del DNP. Umbrales configurables por la aplicación.</span></div></div>
            <div class="mt-4 grid gap-2 md:grid-cols-2">@foreach($alerts as $alert)<div class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-950">{{ $alert['label'] }}</div>@endforeach</div>
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(340px,.7fr)]">
        <div class="space-y-6">
            <section class="panel">
                <h2 class="text-lg font-semibold">Identificación y responsables</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach([
                        'Sector' => $project->sector, 'Entidad responsable' => $project->responsible_entity,
                        'Código de entidad' => $project->responsible_entity_code, 'Entidad ejecutora' => $project->executing_entity,
                        'Programa presupuestal' => $project->budget_program, 'Plan nacional' => $project->national_development_plan,
                    ] as $label => $value)
                        <div><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 text-sm">{{ $value ?: 'No reportado' }}</dd></div>
                    @endforeach
                </dl>
            </section>

            <section class="panel">
                <div class="panel-head"><div><h2>Entidades descentralizadas</h2><span>Clasificación interna revisable; la fuente DNP suele identificar la entidad como Meta.</span></div>@if(auth()->user()->isAdmin())<a class="text-xs font-semibold text-indigo-600" href="{{ route('admin.investment-entities.index') }}">Revisar</a>@endif</div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($project->entityAssignments->where('status', '!=', 'rejected') as $assignment)
                        <a href="{{ route('investments.entities.show', $assignment->entity) }}" class="rounded-xl border border-slate-200 p-3 hover:bg-slate-50"><div class="flex justify-between gap-2"><p class="text-sm font-semibold">{{ $assignment->entity->name }}</p><span class="status {{ $assignment->status === 'confirmed' ? 'status-approved' : 'status-pending_review' }}">{{ $assignment->status === 'confirmed' ? 'Confirmada' : 'Sugerida' }}</span></div><p class="mt-2 text-xs text-slate-500">{{ $assignment->role === 'primary' ? 'Entidad principal' : 'Colaboradora' }} · {{ $assignment->method }}</p></a>
                    @empty <p class="text-sm text-slate-500">Proyecto pendiente de clasificación institucional.</p> @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Localización</h2><span>{{ $project->locations->count() }} registros relacionados</span></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($project->locations as $location)
                        <div class="rounded-xl border border-slate-200 p-3"><p class="font-medium">{{ $location->municipality ?: ($location->is_department_wide ? 'Alcance departamental' : 'Municipio no reportado') }}</p><p class="mt-1 text-xs text-slate-500">{{ $location->department ?? 'Departamento no reportado' }} · DIVIPOLA {{ $location->municipality_code ?: 'N/D' }}</p></div>
                    @empty <p class="text-sm text-slate-500">Sin localización enlazable.</p> @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Ejecución por vigencia y fuente</h2><span>Valores de cada fila financiera; no se replica el valor total del proyecto.</span></div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="text-left text-xs text-slate-500"><tr><th class="py-2 pr-3">Vigencia</th><th class="py-2 pr-3">Fuente</th><th class="py-2 pr-3 text-right">Vigente</th><th class="py-2 pr-3 text-right">Comprometido</th><th class="py-2 pr-3 text-right">Obligado</th><th class="py-2 text-right">Pagado</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($project->financials as $row)<tr><td class="py-3 pr-3">{{ $row->fiscal_year ?? 'N/D' }}</td><td class="max-w-xs py-3 pr-3">{{ $row->funding_source ?? 'No reportada' }}</td><td class="py-3 pr-3 text-right">{{ $money($row->current_value) }}</td><td class="py-3 pr-3 text-right">{{ $money($row->committed_value) }}</td><td class="py-3 pr-3 text-right">{{ $money($row->obligated_value) }}</td><td class="py-3 text-right">{{ $money($row->paid_value) }}</td></tr>@empty<tr><td colspan="6" class="py-5 text-slate-500">Sin ejecución financiera enlazable.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Seguimiento físico y financiero</h2><span>{{ $project->progressReports->count() }} reportes conservados</span></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">@forelse($project->progressReports as $report)<div class="rounded-xl border border-slate-200 p-3"><p class="text-sm font-medium">Físico {{ $percent($report->physical_progress) }} · Financiero {{ $percent($report->financial_progress) }}</p><p class="mt-1 text-xs text-slate-500">Valor vigente: {{ $money($report->current_value) }} · fuente {{ $report->source_dataset_id }}</p></div>@empty<p class="text-sm text-slate-500">Sin seguimiento reportado.</p>@endforelse</div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Productos, metas e indicadores</h2><span>{{ $project->products->count() }} registros</span></div>
                <div class="mt-4 space-y-3">@forelse($project->products as $product)<article class="rounded-xl border border-slate-200 p-4"><p class="font-medium">{{ $product->product ?? 'Producto no reportado' }}</p><p class="mt-2 text-sm text-slate-600">{{ $product->indicator ?? 'Indicador no reportado' }}</p><p class="mt-2 text-xs text-slate-500">Meta: {{ $product->indicator_target ?? 'N/D' }} · Avance: {{ $product->indicator_progress ?? 'N/D' }} · Valor: {{ $money($product->product_value) }}</p></article>@empty<p class="text-sm text-slate-500">Sin productos reportados.</p>@endforelse</div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Contratos asociados</h2><span>{{ $project->contracts->count() }} registros</span></div>
                <div class="mt-4 space-y-3">@forelse($project->contracts as $contract)<article class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-2"><p class="font-medium">{{ $contract->reference ?? 'Referencia no reportada' }}</p><span class="status">{{ $contract->status ?? 'Estado N/D' }}</span></div><p class="mt-2 text-sm text-slate-600">{{ $contract->object ?? 'Objeto no reportado' }}</p><p class="mt-2 text-xs text-slate-500">{{ $contract->supplier ?? 'Proveedor no reportado' }} · {{ $money($contract->value) }}</p>@if($contract->process_url)<a class="mt-3 inline-block text-sm text-indigo-600 hover:underline" href="{{ $contract->process_url }}" target="_blank" rel="noopener noreferrer">Abrir proceso oficial ↗</a>@endif</article>@empty<p class="text-sm text-slate-500">Sin contratos enlazados.</p>@endforelse</div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Focalización transversal</h2><span>{{ $project->policyFocuses->count() }} registros</span></div>
                <div class="mt-4 space-y-2">@forelse($project->policyFocuses as $focus)<div class="rounded-xl border border-slate-200 p-3"><p class="text-sm font-medium">{{ $focus->policy ?? 'Política no reportada' }}</p><p class="mt-1 text-xs text-slate-500">{{ $focus->dimension ?? 'Dimensión no reportada' }} · {{ $focus->fiscal_year ?? 'Vigencia N/D' }}</p></div>@empty<p class="text-sm text-slate-500">Sin focalización reportada.</p>@endforelse</div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel">
                <h2 class="text-lg font-semibold">Llevar a una reunión</h2>
                <p class="mt-1 text-sm text-slate-500">El proyecto se vincula por BPIN a una reunión existente.</p>
                <form method="post" action="{{ route('investments.meetings.store', $project) }}" class="mt-4 space-y-3">@csrf
                    <label class="field"><span>Reunión</span><select name="meeting_id" required><option value="">Seleccione…</option>@foreach($meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->held_at->format('d/m/Y') }} · {{ $meeting->title }}</option>@endforeach</select></label>
                    <label class="field"><span>Motivo de agenda</span><textarea name="agenda_reason" rows="3" required>{{ old('agenda_reason') }}</textarea></label>
                    <label class="field"><span>Preguntas preparadas · una por línea</span><textarea name="prepared_questions" rows="4">{{ old('prepared_questions') }}</textarea></label>
                    <button class="btn-primary w-full">Agregar a la agenda</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Historial de reuniones</h2><span>{{ $project->meetings->count() }}</span></div>
                <div class="mt-4 space-y-3">@forelse($project->meetings as $meeting)<article class="rounded-xl border border-slate-200 p-4"><a class="font-semibold text-indigo-700 hover:underline" href="{{ route('meetings.show', $meeting) }}">{{ $meeting->title }}</a><p class="mt-1 text-xs text-slate-500">{{ $meeting->held_at->format('d/m/Y H:i') }} · {{ $meeting->user->name }}</p><p class="mt-2 text-sm">{{ $meeting->pivot->agenda_reason }}</p><form method="post" action="{{ route('investments.meetings.destroy', [$project, $meeting]) }}" class="mt-3">@csrf @method('DELETE')<button class="btn-small">Retirar</button></form></article>@empty<p class="text-sm text-slate-500">Aún no se ha llevado a reuniones.</p>@endforelse</div>
            </section>

            <section class="panel">
                <h2 class="text-lg font-semibold">Crear compromiso borrador</h2>
                <form method="post" action="{{ route('investments.actions.store', $project) }}" class="mt-4 space-y-3">@csrf
                    <select name="meeting_id" required><option value="">Reunión vinculada…</option>@foreach($project->meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->title }}</option>@endforeach</select>
                    <textarea name="description" rows="3" placeholder="Compromiso" required></textarea><input name="owner_name" placeholder="Responsable"><input type="date" name="due_date"><button class="btn-secondary w-full">Crear borrador</button>
                </form>
            </section>

            <section class="panel">
                <h2 class="text-lg font-semibold">Registrar decisión</h2>
                <form method="post" action="{{ route('investments.decisions.store', $project) }}" class="mt-4 space-y-3">@csrf
                    <select name="meeting_id" required><option value="">Reunión vinculada…</option>@foreach($project->meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->title }}</option>@endforeach</select><textarea name="decision" rows="3" placeholder="Decisión" required></textarea><button class="btn-secondary w-full">Asociar decisión</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Compromisos</h2><span>{{ $project->actionItems->count() }}</span></div>
                <div class="mt-4 space-y-3">@forelse($project->actionItems as $item)<article class="rounded-xl border border-slate-200 p-3"><p class="text-sm">{{ $item->description }}</p><p class="mt-2 text-xs text-slate-500">{{ $item->owner_name ?: 'Sin responsable' }} · {{ $item->due_date?->format('d/m/Y') ?: 'Sin plazo' }} · {{ $item->status->value }}</p>@if($item->summary?->meeting)<a class="mt-2 inline-block text-xs text-indigo-600" href="{{ route('meetings.show', $item->summary->meeting) }}">Abrir reunión</a>@endif</article>@empty<p class="text-sm text-slate-500">Sin compromisos asociados.</p>@endforelse</div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Decisiones</h2><span>{{ $project->decisions->count() }}</span></div>
                <div class="mt-4 space-y-3">@forelse($project->decisions as $decision)<article class="rounded-xl border border-slate-200 p-3"><p class="text-sm">{{ $decision->decision }}</p>@if($decision->summary?->meeting)<a class="mt-2 inline-block text-xs text-indigo-600" href="{{ route('meetings.show', $decision->summary->meeting) }}">{{ $decision->summary->meeting->title }}</a>@endif</article>@empty<p class="text-sm text-slate-500">Sin decisiones asociadas.</p>@endforelse</div>
            </section>

            <section class="panel text-sm">
                <h2 class="font-semibold">Fuente y actualización</h2>
                <dl class="mt-3 space-y-2"><div><dt class="text-xs text-slate-500">Conjunto principal</dt><dd>{{ $project->source_dataset_id ?? 'No reportado' }}</dd></div><div><dt class="text-xs text-slate-500">Última sincronización</dt><dd>{{ $project->last_synced_at?->format('d/m/Y H:i') ?? 'No reportada' }}</dd></div></dl>
                @if($project->source_dataset_id)<a class="mt-3 inline-block text-indigo-600 hover:underline" href="https://www.datos.gov.co/d/{{ $project->source_dataset_id }}" target="_blank" rel="noopener noreferrer">Abrir fuente oficial ↗</a>@endif
            </section>
        </aside>
    </div>
</div>
@endsection
