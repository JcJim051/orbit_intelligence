@extends('layouts.app', ['title' => 'Revisión ODS · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Revisión humana ODS</h1>
            <p class="page-subtitle max-w-3xl">Bandeja para que el equipo relacione indicadores de resultado con indicadores ODS, dejando responsable, comentarios y trazabilidad de cada decisión.</p>
        </div>
        @if($canGenerateOdsSuggestions)
            <div class="flex flex-col gap-2 sm:flex-row">
                <form method="post" action="{{ route('intelligence.revision-ods.assign-team') }}" onsubmit="return confirm('Se asignarán solo indicadores ODS sin responsable a Luisa, Diana, Braian, Fabian y Clara. Las asignaciones existentes no cambiarán. ¿Continuar?')">
                    @csrf
                    <button class="btn-secondary">Repartir pendientes</button>
                </form>
                <form method="post" action="{{ route('intelligence.revision-ods.suggest') }}" onsubmit="return confirm('Se crearán relaciones ODS en estado propuesta. No se aceptará nada automáticamente. ¿Continuar?')">
                    @csrf
                    <button class="btn-primary">Generar sugerencias ODS</button>
                </form>
            </div>
        @endif
    </header>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

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
                <span>Buscar en toda la tabla</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Indicador, responsable, ODS, estado, comentario...">
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
            @if($canManageOdsAssignments)
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
            <h2 class="text-lg font-semibold">Indicadores para revisión</h2>
            <p class="text-sm text-slate-500">{{ $reviews->count() }} en total</p>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Indicador de resultado</th>
                        <th class="px-4 py-3">Metas resultado</th>
                        <th class="px-4 py-3">Relaciones ODS</th>
                        <th class="px-4 py-3">Responsable</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reviews as $review)
                        <tr>
                            <td class="max-w-md px-4 py-3 align-top">
                                <span class="mb-2 inline-flex rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-indigo-700">Indicador de resultado PDD</span>
                                <span class="block font-semibold text-slate-950">{{ $review->indicador->nombre }}</span>
                                <span class="text-xs text-slate-500">{{ $review->indicador->codigo ?? 'Sin código' }} · {{ $review->indicador->unidad_medida ?? 'Sin unidad' }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if($review->indicador->metasResultado->count() > 0)
                                    <button
                                        type="button"
                                        class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-4"
                                        data-open-modal="metas-resultado-{{ $review->id }}"
                                    >
                                        {{ $review->indicador->metasResultado->count() }} · ver
                                    </button>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                @php($linkSummary = $review->links->groupBy('status')->map->count())
                                <div class="flex flex-wrap gap-1">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{ $review->links_count }} total</span>
                                    @if(($linkSummary['accepted'] ?? 0) > 0)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ $linkSummary['accepted'] }} confirmada(s)</span>
                                    @endif
                                    @if(($linkSummary['proposed'] ?? 0) > 0)
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ $linkSummary['proposed'] }} propuesta(s)</span>
                                    @endif
                                    @if(($linkSummary['rejected'] ?? 0) > 0)
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-red-700">{{ $linkSummary['rejected'] }} rechazada(s)</span>
                                    @endif
                                </div>
                                @foreach($review->links->sortByDesc('updated_at')->take(3) as $link)
                                    @php($linkStatusLabel = ['proposed' => 'Propuesta', 'accepted' => 'Confirmada', 'rejected' => 'Rechazada'][$link->status] ?? $link->status)
                                    @php($linkStatusClass = ['proposed' => 'text-amber-700', 'accepted' => 'text-emerald-700', 'rejected' => 'text-red-700'][$link->status] ?? 'text-slate-500')
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Indicador ODS {{ $link->odsIndicator->code }} · ODS {{ $link->odsIndicator->target->goal->code }}
                                        · <strong class="{{ $linkStatusClass }}">{{ $linkStatusLabel }}</strong>
                                    </span>
                                @endforeach
                                @if($review->links_count === 0)
                                    <span class="mt-1 block text-xs text-slate-400">Sin indicador ODS relacionado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">{{ $review->assignedUser?->name ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-3 align-top"><span class="status status-pending_review">{{ $statuses[$review->status] ?? $review->status }}</span></td>
                            <td class="px-4 py-3 align-top">
                                <a class="font-semibold text-indigo-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $review->id]) }}">Revisar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay indicadores con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach($reviews as $review)
        @if($review->indicador->metasResultado->count() > 0)
            <div id="metas-resultado-{{ $review->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" data-modal>
                <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="eyebrow">Metas resultado contadas</p>
                            <h3 class="text-lg font-black text-slate-950">{{ $review->indicador->nombre }}</h3>
                            <p class="mt-1 text-sm text-slate-500">Estas son las metas resultado PDD asociadas a este indicador. Esto no es el indicador ODS.</p>
                        </div>
                        <button type="button" class="rounded-full px-3 py-1 text-xl text-slate-500 hover:bg-slate-100" data-close-modal="metas-resultado-{{ $review->id }}">×</button>
                    </div>
                    <div class="max-h-[65vh] overflow-y-auto p-5">
                        <div class="space-y-3">
                            @foreach($review->indicador->metasResultado as $meta)
                                @php($programa = $meta->subprograma?->programa ?? $meta->programa)
                                @php($pilar = $programa?->linea?->eje?->pilar)
                                <article class="rounded-xl border border-slate-200 p-3">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Meta resultado PDD</span>
                                    <p class="mt-2 font-semibold text-slate-950">{{ $meta->codigo_provisional ?? $meta->codigo ?? 'Sin código' }} · {{ $meta->descripcion }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $pilar ? 'Pilar '.$pilar->numeral.' — '.$pilar->nombre : 'Sin pilar' }}
                                        · {{ $programa?->nombre ?? 'Sin programa' }}
                                    </p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<script>
    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-modal]');
        if (openButton) {
            document.getElementById(openButton.dataset.openModal)?.classList.remove('hidden');
            document.getElementById(openButton.dataset.openModal)?.classList.add('flex');
        }

        const closeButton = event.target.closest('[data-close-modal]');
        if (closeButton) {
            document.getElementById(closeButton.dataset.closeModal)?.classList.add('hidden');
            document.getElementById(closeButton.dataset.closeModal)?.classList.remove('flex');
        }

        if (event.target.matches('[data-modal]')) {
            event.target.classList.add('hidden');
            event.target.classList.remove('flex');
        }
    });
</script>
@endsection
