@extends('layouts.app', ['title' => 'Revisión ODS · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a class="font-semibold text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods']) }}">← Revisión ODS</a>
            <p class="eyebrow mt-3">Indicador de resultado PDD · no es indicador ODS</p>
            <h1 class="page-title">{{ $task->indicador->nombre }}</h1>
            <p class="page-subtitle max-w-3xl">{{ $task->indicador->codigo ?? 'Sin código' }} · {{ $task->indicador->unidad_medida ?? 'Sin unidad de medida' }}</p>
        </div>
        <span class="status status-pending_review">{{ $statuses[$task->status] ?? $task->status }}</span>
    </header>

    @if($reviewerLocked)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Este indicador ya está en <strong>{{ $statuses[$task->status] ?? $task->status }}</strong>. La revisión quedó enviada y el revisor no puede seguir editando relaciones ni comentarios. Si requiere ajustes, el Validador ODS debe devolverlo a <strong>Requiere ajuste</strong>.
        </div>
    @endif

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="panel lg:col-span-2">
            <h2 class="text-lg font-semibold">Contexto PDD</h2>
            <div class="mt-4 space-y-3">
                @forelse($task->indicador->metasResultado as $meta)
                        @php($programa = $meta->subprograma?->programa ?? $meta->programa)
                        @php($pilar = $programa?->linea?->eje?->pilar)
                        <div class="rounded-xl border border-slate-200 p-3">
                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Meta resultado PDD contada</span>
                        <p class="font-semibold">{{ $meta->codigo_provisional }} · {{ $meta->descripcion }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $pilar ? 'Pilar '.$pilar->numeral.' — '.$pilar->nombre : 'Sin pilar' }} · {{ $programa?->nombre ?? 'Sin programa' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Este indicador no tiene metas de resultado asociadas.</p>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <h2 class="text-lg font-semibold">Asignación y estado</h2>
            <form method="post" action="{{ route('intelligence.revision-ods.update', $task) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                @if($canManageOdsAssignments)
                    <label class="field">
                        <span>Responsable</span>
                        <select name="assigned_to">
                            <option value="">Sin asignar</option>
                            @foreach($reviewers as $reviewer)
                                <option value="{{ $reviewer->id }}" @selected($task->assigned_to === $reviewer->id)>{{ $reviewer->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                        Responsable: <strong>{{ $task->assignedUser?->name ?? 'Sin asignar' }}</strong>
                    </div>
                    <input type="hidden" name="assigned_to" value="{{ $task->assigned_to }}">
                @endif
                <label class="field">
                    <span>Estado</span>
                    <select name="status" @disabled(! $canEditOdsReview)>
                        @foreach($availableStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if(! $canConfirmOdsRelations && ! $reviewerLocked)
                        <small>Cuando termine, cambie el estado a <strong>En validación</strong> para que lo revise el Validador ODS.</small>
                    @endif
                </label>
                <label class="field"><span>Comentario de cambio</span><textarea name="comment" rows="3" @disabled(! $canEditOdsReview)></textarea></label>
                <button class="btn-primary" @disabled(! $canEditOdsReview)>Guardar estado</button>
            </form>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="panel">
            <h2 class="text-lg font-semibold">Registrar relación con indicador ODS</h2>
            <p class="mt-1 text-sm text-slate-500">Seleccione un indicador del catálogo importado desde Sinergia DNP. Si aún no aparece, use el registro manual.</p>
            @if($canEditOdsRelations)
                <form method="post" action="{{ route('intelligence.revision-ods.links.store', $task) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <label class="field md:col-span-2">
                        <span>Indicador ODS oficial</span>
                        <select name="ods_indicator_id" data-searchable-select data-search-placeholder="Buscar por código, nombre, ODS o meta…">
                            <option value="">Registrar manualmente / catálogo no importado</option>
                            @foreach($odsIndicators as $odsIndicator)
                                <option value="{{ $odsIndicator->id }}">
                                    {{ $odsIndicator->code }} · {{ $odsIndicator->name }} — ODS {{ $odsIndicator->target->goal->code }} / Meta {{ $odsIndicator->target->code }}
                                </option>
                            @endforeach
                        </select>
                        <small>Si el catálogo está vacío, ejecute <code>php artisan ods:import-sinergia</code>.</small>
                    </label>
                    <div class="md:col-span-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Registro manual excepcional</p>
                        <p class="mt-1 text-xs text-slate-500">Solo diligencie estos campos si no seleccionó un indicador oficial arriba.</p>
                    </div>
                    <label class="field"><span>ODS código</span><input name="ods_goal_code" placeholder="3"></label>
                    <label class="field"><span>ODS nombre</span><input name="ods_goal_name" placeholder="Salud y bienestar"></label>
                    <label class="field"><span>Meta ODS código</span><input name="ods_target_code" placeholder="3.8"></label>
                    <label class="field"><span>Meta ODS nombre</span><input name="ods_target_name"></label>
                    <label class="field"><span>Indicador ODS código</span><input name="ods_indicator_code" placeholder="3.8.1"></label>
                    <label class="field"><span>Unidad ODS</span><input name="ods_indicator_unit"></label>
                    <label class="field md:col-span-2"><span>Indicador ODS nombre</span><input name="ods_indicator_name"></label>
                    <label class="field"><span>Tipo de relación</span><select name="relation_type">@foreach($relationTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="field"><span>Confianza</span><select name="confidence">@foreach($confidenceLevels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="field">
                        <span>Estado de la relación</span>
                        <select name="status">
                            <option value="proposed">Propuesta</option>
                            <option value="rejected">Rechazada</option>
                            @if($canConfirmOdsRelations)
                                <option value="accepted">Confirmada</option>
                            @endif
                        </select>
                    </label>
                    <label class="field md:col-span-2"><span>Justificación técnica</span><textarea name="justification" rows="3" required></textarea></label>
                    <label class="field md:col-span-2"><span>Comentario adicional</span><textarea name="comment" rows="2"></textarea></label>
                    <div class="md:col-span-2"><button class="btn-primary">Guardar relación ODS</button></div>
                </form>
            @else
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    La edición quedó bloqueada para el revisor porque el indicador ya fue enviado a validación.
                </div>
            @endif
        </article>

        <article class="panel">
            <h2 class="text-lg font-semibold">Relaciones registradas</h2>
            <div class="mt-4 space-y-3">
                @forelse($task->links as $link)
                    @php($statusLabel = ['proposed' => 'Propuesta', 'accepted' => 'Confirmada', 'rejected' => 'Rechazada'][$link->status] ?? $link->status)
                    @php($statusClass = [
                        'proposed' => 'border-amber-200 bg-amber-50/40 text-amber-800',
                        'accepted' => 'border-emerald-200 bg-emerald-50/40 text-emerald-800',
                        'rejected' => 'border-red-200 bg-red-50/50 text-red-800',
                    ][$link->status] ?? 'border-slate-200 text-slate-600')
                    @php($cardClass = [
                        'proposed' => 'border-amber-200 bg-white',
                        'accepted' => 'border-emerald-200 bg-white',
                        'rejected' => 'border-red-200 bg-red-50/20',
                    ][$link->status] ?? 'border-slate-200 bg-white')
                    <div class="rounded-xl border p-3 {{ $cardClass }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="inline-flex rounded-full bg-sky-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-700">Indicador ODS relacionado</span>
                                <p class="font-semibold">{{ $link->odsIndicator->code }} · {{ $link->odsIndicator->name }}</p>
                                <p class="text-xs text-slate-500">
                                    ODS {{ $link->odsIndicator->target->goal->code }} — {{ $link->odsIndicator->target->goal->name }}
                                    · Meta ODS {{ $link->odsIndicator->target->code }}
                                </p>
                            </div>
                            <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        @if($link->status === 'rejected')
                            <p class="mt-2 rounded-lg bg-red-100 px-3 py-2 text-xs font-semibold text-red-800">
                                Esta relación fue rechazada y permanece visible como trazabilidad. No fue eliminada.
                            </p>
                        @endif
                        <p class="mt-2 text-sm text-slate-600">{{ $link->justification }}</p>
                        <p class="mt-2 text-xs text-slate-500">
                            Relación: {{ $relationTypes[$link->relation_type] ?? $link->relation_type }}
                            · Confianza: {{ $confidenceLevels[$link->confidence] ?? $link->confidence }}
                            · Propuesta por {{ $link->creator?->name ?? 'Sistema' }}
                            @if($link->reviewer)
                                · Revisada por {{ $link->reviewer->name }} el {{ $link->reviewed_at?->format('d/m/Y H:i') }}
                            @endif
                        </p>

                        @if($link->comments->isNotEmpty())
                            <div class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-white/70 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Comentarios de esta relación</p>
                                @foreach($link->comments->sortByDesc('created_at') as $comment)
                                    <article class="border-l-2 border-slate-200 pl-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-semibold text-slate-700">
                                                {{ $comment->user?->name ?? 'Sistema' }}
                                                <span class="font-normal text-slate-500">· {{ [
                                                    'link_saved' => 'Registro/actualización',
                                                    'link_accepted' => 'Aceptación',
                                                    'link_rejected' => 'Rechazo',
                                                    'auto_suggestion' => 'Sugerencia automática',
                                                ][$comment->event_type] ?? $comment->event_type }}</span>
                                            </p>
                                            <time class="text-[11px] text-slate-400">{{ $comment->created_at->format('d/m/Y H:i') }}</time>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-700">{{ $comment->comment }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if(($canEditOdsRelations && $link->status === 'proposed') || ($canConfirmOdsRelations && in_array($link->status, ['proposed', 'rejected'], true)))
                            <form method="post" action="{{ route('intelligence.revision-ods.links.update', [$task, $link]) }}" class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                @csrf
                                @method('PATCH')
                                <label class="field">
                                    <span>Comentario de decisión</span>
                                    <textarea name="comment" rows="2" required placeholder="{{ $link->status === 'rejected' ? 'Explique por qué se confirma pese al rechazo previo.' : 'Explique por qué se confirma o rechaza esta relación.' }}"></textarea>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    @if($canConfirmOdsRelations)
                                        <button class="btn-primary" name="decision" value="accept">{{ $link->status === 'rejected' ? 'Confirmar de todas formas' : 'Confirmar relación' }}</button>
                                    @endif
                                    @if($link->status === 'proposed')
                                        <button class="btn-secondary" name="decision" value="reject">Rechazar sugerencia</button>
                                    @endif
                                </div>
                                @unless($canConfirmOdsRelations)
                                    <p class="text-xs text-slate-500">Como revisor puede rechazar o comentar. La confirmación final la realiza el Validador ODS.</p>
                                @endunless
                                @if($link->status === 'rejected' && $canConfirmOdsRelations)
                                    <p class="text-xs text-slate-500">Esta relación tiene un rechazo previo. Si su criterio técnico es aprobarla, quedarán visibles ambos comentarios en la trazabilidad.</p>
                                @endif
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aún no hay relaciones ODS registradas para este indicador.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel">
        <h2 class="text-lg font-semibold">Comentarios y trazabilidad</h2>
        @if($canEditOdsReview)
            <form method="post" action="{{ route('intelligence.revision-ods.comments.store', $task) }}" class="mt-4 flex flex-col gap-3 md:flex-row">
                @csrf
                <label class="field flex-1"><span>Nuevo comentario</span><textarea name="comment" rows="2" required></textarea></label>
                <div class="flex items-end"><button class="btn-secondary">Agregar comentario</button></div>
            </form>
        @else
            <p class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">Los comentarios del revisor quedan cerrados mientras el indicador está en validación.</p>
        @endif
        <div class="mt-5 space-y-3">
            @forelse($task->comments as $comment)
                <article class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ $comment->user?->name ?? 'Sistema' }} <span class="text-xs font-normal text-slate-500">· {{ $comment->event_type }}</span></p>
                        <time class="text-xs text-slate-500">{{ $comment->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    <p class="mt-2 text-sm text-slate-700">{{ $comment->comment }}</p>
                    @if($comment->metadata)
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-50 p-2 text-xs text-slate-500">{{ json_encode($comment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Sin comentarios todavía.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
