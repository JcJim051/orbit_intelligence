<div>
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div><a href="{{ route('meetings.index') }}" class="text-sm text-indigo-600">← Reuniones</a><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $meeting->title }}</h1><p class="mt-2 text-slate-500">{{ $meeting->meeting_type }} · {{ $meeting->held_at->format('d/m/Y H:i') }} · {{ $meeting->user->name }}</p></div>
        <div class="flex flex-wrap gap-2"><span class="status status-{{ $meeting->status->value }}">{{ $meeting->status->label() }}</span><span class="status">Drive: {{ str_replace('_', ' ', $meeting->drive_sync_status->value) }}</span></div>
    </div>

    @if($meeting->error_message)
        <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5"><h2 class="font-semibold text-red-900">Falló la etapa {{ $meeting->error_stage }}</h2><p class="mt-2 text-sm text-red-800">{{ $meeting->error_message }}</p><button wire:click="retry" wire:confirm="¿Reintentar el procesamiento?" class="btn-danger mt-4">Reintentar</button></section>
    @endif

    <div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <div class="space-y-6">
                       @php($originalAudio = $meeting->files->firstWhere('kind', 'original'))
            <section class="panel">
                <div class="panel-head">
                    <h2>Audio original</h2>
                    <span>{{ $originalAudio?->original_name }}</span>
                </div>

                @if ($originalAudio && ! $originalAudio->deleted_at)
                    <audio controls preload="metadata" class="mt-4 w-full">
                        <source src="{{ route('meetings.audio', $meeting) }}" type="{{ in_array(strtolower(pathinfo($originalAudio->original_name, PATHINFO_EXTENSION)), ['m4a', 'mp4']) ? 'audio/mp4' : $originalAudio->mime_type }}">
                        Tu navegador no pudo mostrar el reproductor de audio.
                    </audio>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <a class="btn-secondary" href="{{ route('meetings.audio', $meeting) }}" target="_blank" rel="noopener">Abrir audio</a>
                        <a class="btn-secondary" href="{{ route('meetings.audio', $meeting) }}" download="{{ $originalAudio->original_name }}">Descargar audio</a>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-500">El audio original ya no está disponible.</p>
                @endif
            </section>

            @if($meeting->investmentProjects->isNotEmpty())
                <section class="panel">
                    <div class="panel-head"><div><h2>Proyectos de inversión en agenda</h2><span>{{ $meeting->investmentProjects->count() }} BPIN relacionados</span></div></div>
                    <div class="mt-4 space-y-3">
                        @foreach($meeting->investmentProjects as $project)
                            <article wire:key="agenda-project-{{ $project->id }}" class="rounded-xl border border-slate-200 p-4">
                                <a href="{{ route('investments.projects.show', $project) }}" class="font-semibold text-indigo-700 hover:underline">BPIN {{ $project->bpin }} · {{ $project->name }}</a>
                                <p class="mt-2 text-sm text-slate-700">{{ $project->pivot->agenda_reason }}</p>
                                @php($preparedQuestions = json_decode($project->pivot->prepared_questions ?: '[]', true) ?: [])
                                @if($preparedQuestions)
                                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Preguntas preparadas</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">@foreach($preparedQuestions as $question)<li>{{ $question }}</li>@endforeach</ul>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($meeting->transcript)
            <section class="panel">
                <div class="panel-head"><div><h2>Transcripción</h2><span>{{ $meeting->transcript->provider }} / {{ $meeting->transcript->model }}</span></div></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">@foreach(collect($segments)->pluck('speaker_key')->filter()->unique() as $speaker)<label class="field"><span>{{ $speaker }}</span><input wire:model="speakerMap.{{ $speaker }}" placeholder="Nombre del participante"></label>@endforeach</div>
                <div class="mt-5 max-h-[620px] space-y-3 overflow-y-auto pr-2">@foreach($segments as $index=>$segment)<div class="rounded-xl border border-slate-200 p-4"><div class="mb-2 flex justify-between text-xs font-medium text-slate-500"><span>{{ $speakerMap[$segment['speaker_key']] ?? $segment['speaker_key'] ?? 'Sin hablante' }}</span><span>{{ gmdate('H:i:s', intdiv($segment['start_ms'],1000)) }}</span></div><textarea wire:model="segments.{{ $index }}.edited_text" rows="3" class="w-full"></textarea></div>@endforeach</div>
            </section>
            @endif
        </div>

        <div class="space-y-6">
            @if($meeting->currentSummary())
            <section class="panel">
                <div class="panel-head"><div><h2>Acta v{{ str_pad((string)$meeting->currentSummary()->version,3,'0',STR_PAD_LEFT) }}</h2><span>{{ $meeting->currentSummary()->status === 'approved' ? 'Versión bloqueada' : 'Borrador editable' }}</span></div></div>
                <div class="mt-5 space-y-4">
                    <label class="field"><span>Resumen ejecutivo</span><textarea wire:model="executiveSummary" rows="6" @disabled($meeting->status->value==='approved')></textarea></label>
                    <label class="field"><span>Temas · uno por línea</span><textarea wire:model="topics" rows="4" @disabled($meeting->status->value==='approved')></textarea></label>
                    <label class="field"><span>Decisiones · una por línea</span><textarea wire:model="decisions" rows="4" @disabled($meeting->status->value==='approved')></textarea></label>
                    <label class="field"><span>Riesgos o bloqueos</span><textarea wire:model="risks" rows="3" @disabled($meeting->status->value==='approved')></textarea></label>
                    <label class="field"><span>Preguntas pendientes</span><textarea wire:model="questions" rows="3" @disabled($meeting->status->value==='approved')></textarea></label>
                </div>
            </section>

            <section class="panel"><div class="panel-head"><h2>Compromisos</h2><span>{{ count($actions) }} encontrados</span></div><div class="mt-4 space-y-4">@forelse($actions as $index=>$action)<article wire:key="action-{{ $action['id'] }}" class="rounded-xl border border-slate-200 p-4">@if($action['investment_project_id'])<a href="{{ route('investments.projects.show', $action['investment_project_id']) }}" class="mb-3 inline-block text-xs font-semibold text-indigo-600">BPIN {{ $action['investment_project_bpin'] }} · volver al proyecto</a>@endif<textarea wire:model="actions.{{ $index }}.description" rows="2" @disabled($meeting->status->value==='approved')></textarea><div class="mt-3 grid gap-3 sm:grid-cols-2"><input wire:model="actions.{{ $index }}.owner_name" placeholder="Responsable" @disabled($meeting->status->value==='approved')><input type="date" wire:model="actions.{{ $index }}.due_date" @disabled($meeting->status->value==='approved')></div><div class="mt-3 flex gap-2"><button wire:click="classify('{{ $action['id'] }}','approved')" class="btn-small {{ $action['status']==='approved' ? 'bg-emerald-600 text-white' : '' }}" @disabled($meeting->status->value==='approved')>Aprobar</button><button wire:click="classify('{{ $action['id'] }}','rejected')" class="btn-small {{ $action['status']==='rejected' ? 'bg-slate-700 text-white' : '' }}" @disabled($meeting->status->value==='approved')>Descartar</button></div></article>@empty<p class="text-sm text-slate-500">No se detectaron compromisos.</p>@endforelse</div></section>

            @php($projectDecisions = $meeting->currentSummary()?->projectDecisions ?? collect())
            @if($projectDecisions->isNotEmpty())
                <section class="panel"><div class="panel-head"><h2>Decisiones asociadas a BPIN</h2><span>{{ $projectDecisions->count() }}</span></div><div class="mt-4 space-y-3">@foreach($projectDecisions as $decision)<article wire:key="project-decision-{{ $decision->id }}" class="rounded-xl border border-slate-200 p-4"><a href="{{ route('investments.projects.show', $decision->project) }}" class="text-xs font-semibold text-indigo-600">BPIN {{ $decision->project->bpin }}</a><p class="mt-2 text-sm">{{ $decision->decision }}</p></article>@endforeach</div></section>
            @endif

            <section class="panel">
                <div class="flex flex-wrap gap-3">
                    @if ($meeting->status->value === 'pending_review')
                        <button wire:click="saveDraft" class="btn-secondary">Guardar cambios</button>

                        @can('approve', $meeting)
                            <button wire:click="approve" wire:confirm="El acta quedará bloqueada y se publicará en los Drives seleccionados. ¿Continuar?" class="btn-primary">Aprobar acta</button>
                        @endcan
                    @endif

                    @if ($meeting->status->value === 'approved')
                        @can('approve', $meeting)
                            <button wire:click="reopen" wire:confirm="Se creará una nueva versión. ¿Continuar?" class="btn-secondary">Reabrir</button>
                        @endcan
                    @endif

                    <a class="btn-secondary" href="{{ route('meetings.export.markdown', $meeting) }}">Markdown</a>
                    <a class="btn-secondary" href="{{ route('meetings.export.pdf', $meeting) }}">PDF</a>
                </div>
            </section>
            @else
                <section class="panel text-sm text-slate-500">El acta aparecerá cuando termine el procesamiento.</section>
            @endif

            @if ($meeting->exports->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <h2>Copias en Drive</h2>
                    </div>

                    <div class="mt-4 divide-y divide-slate-100">
                        @foreach ($meeting->exports as $export)
                            <div class="flex items-center justify-between gap-3 py-3 text-sm">
                                <div>
                                    <strong>{{ $export->connection->label }}</strong>
                                    <p class="text-slate-500">{{ $export->artifact_type }}</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="status">{{ $export->status }}</span>

                                    @if ($export->status === 'error' && auth()->user()->isAdmin())
                                        <form method="post" action="{{ route('admin.drive-exports.retry', $export) }}">
                                            @csrf
                                            <button class="btn-small">Reintentar</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
