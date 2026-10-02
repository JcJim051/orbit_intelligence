<x-filament-panels::page>
    @if(session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-900" role="alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <strong>No fue posible guardar:</strong>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $activeLayers = $geoViewer->layers->where('active', true);
        $unclassifiedLayers = $activeLayers->filter(fn ($layer) => $layer->access_policy === \App\Enums\GeoLayerAccessPolicy::Pending);
        $workflow = [
            ['key' => 'configuration', 'label' => 'Configurar', 'done' => true],
            ['key' => 'preview', 'label' => 'Previsualizar', 'done' => $geoViewer->layers->isNotEmpty()],
            ['key' => 'review', 'label' => 'Revisión', 'done' => in_array($geoViewer->status->value, ['review', 'published'], true)],
            ['key' => 'publication', 'label' => 'Publicar', 'done' => $geoViewer->isPublished()],
        ];
    @endphp

    <section class="siid-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Geovisor · {{ match($geoViewer->status->value) { 'published' => 'Publicado', 'review' => 'En revisión', 'archived' => 'Archivado', default => 'Borrador' } }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $geoViewer->name }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $geoViewer->description ?: 'Configure las capas, revise el resultado y controle su publicación desde este espacio.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="siid-button siid-button-secondary" href="{{ \App\Filament\Clusters\Geography\Pages\GeoViewerPreview::getUrl(['geoViewer' => $geoViewer]) }}">Previsualizar</a>
                @if($geoViewer->isPublished())
                    <a class="siid-button siid-button-primary" href="{{ route('geo-viewers.embed', $geoViewer) }}" target="_blank" rel="noopener">Abrir público ↗</a>
                @endif
            </div>
        </div>
        <div class="mt-6 grid gap-2 sm:grid-cols-4" aria-label="Progreso del geovisor">
            @foreach($workflow as $step)
                <div @class(['rounded-xl border px-3 py-2 text-sm font-semibold', 'border-emerald-200 bg-emerald-50 text-emerald-800' => $step['done'], 'border-slate-200 bg-white text-slate-500' => ! $step['done']])>
                    <span class="mr-1" aria-hidden="true">{{ $step['done'] ? '✓' : $loop->iteration }}</span> {{ $step['label'] }}
                </div>
            @endforeach
        </div>
    </section>

    @if($geoViewer->canBeEditedBy(auth()->user()))
        <form method="post" action="{{ route('admin.geo-viewers.update', $geoViewer) }}" class="space-y-6" @if($geoViewer->isPublished()) onsubmit="return confirm('¿Aplicar estos cambios ahora al visor público?')" @endif>
            @csrf
            @method('PATCH')

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-slate-950">Configuración general</h2>
                    <p class="mt-1 text-sm text-slate-500">Nombre, dirección estable y posición inicial del mapa.</p>
                </div>
                @if($geoViewer->isPublished())
                    <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Los cambios se aplicarán al visor público sin modificar su enlace ni el código embebible.</p>
                @endif
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label class="siid-field"><span>Nombre</span><input name="name" value="{{ old('name', $geoViewer->name) }}" required maxlength="120"></label>
                    <label class="siid-field"><span>Identificador URL</span><input name="slug" value="{{ old('slug', $geoViewer->slug) }}" required maxlength="120" @readonly($geoViewer->isPublished())></label>
                    <label class="siid-field"><span>Estado de trabajo</span>
                        <select name="status" @disabled($geoViewer->isPublished())>
                            <option value="draft" @selected(old('status', $geoViewer->status->value) === 'draft')>Borrador</option>
                            <option value="review" @selected(old('status', $geoViewer->status->value) === 'review')>Enviar a revisión</option>
                            <option value="archived" @selected(old('status', $geoViewer->status->value) === 'archived')>Archivado</option>
                            @if($geoViewer->isPublished())<option value="published" selected>Publicado</option>@endif
                        </select>
                        @if($geoViewer->isPublished())<input type="hidden" name="status" value="published">@endif
                    </label>
                    <label class="siid-field md:col-span-2 xl:col-span-3"><span>Descripción</span><textarea name="description" rows="3" maxlength="1000">{{ old('description', $geoViewer->description) }}</textarea></label>
                    <label class="siid-field"><span>Latitud inicial</span><input type="number" step="0.0000001" name="center_latitude" value="{{ old('center_latitude', $geoViewer->center_latitude) }}" required></label>
                    <label class="siid-field"><span>Longitud inicial</span><input type="number" step="0.0000001" name="center_longitude" value="{{ old('center_longitude', $geoViewer->center_longitude) }}" required></label>
                    <label class="siid-field"><span>Zoom inicial</span><input type="number" name="initial_zoom" value="{{ old('initial_zoom', $geoViewer->initial_zoom) }}" min="0" max="22" required></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div><h2 class="text-lg font-semibold text-slate-950">Capas del geovisor</h2><p class="mt-1 text-sm text-slate-500">Seleccione, ordene y configure la forma en que cada capa aparece en el mapa.</p></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $geoViewer->layers->count() }} asignadas</span>
                </div>
                <div class="grid gap-3 xl:grid-cols-2">
                    @forelse($layers as $layer)
                        @php($assigned = $geoViewer->layers->firstWhere('id', $layer->id))
                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start gap-3">
                                <input type="hidden" name="layers[{{ $loop->index }}][geo_layer_id]" value="{{ $layer->id }}">
                                <input type="hidden" name="layers[{{ $loop->index }}][included]" value="0">
                                <input class="mt-1 h-4 w-4 shrink-0" type="checkbox" name="layers[{{ $loop->index }}][included]" value="1" @checked($assigned)>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-slate-950">{{ $layer->name }}</h3><span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $layer->active, 'bg-slate-100 text-slate-500' => ! $layer->active])>{{ $layer->active ? 'Disponible' : 'Inactiva' }}</span></div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $layer->geometry_type }} · {{ $layer->access_policy->label() }}</p>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <label class="siid-field"><span>Etiqueta pública</span><input name="layers[{{ $loop->index }}][label]" value="{{ old('layers.'.$loop->index.'.label', $assigned?->pivot->label) }}" placeholder="{{ $layer->name }}"></label>
                                <label class="siid-field"><span>Grupo temático</span><input name="layers[{{ $loop->index }}][group_name]" value="{{ old('layers.'.$loop->index.'.group_name', $assigned?->pivot->group_name ?: $layer->group_name) }}"></label>
                                <label class="siid-field"><span>Orden</span><input type="number" name="layers[{{ $loop->index }}][sort_order]" value="{{ old('layers.'.$loop->index.'.sort_order', $assigned?->pivot->sort_order ?? $loop->iteration * 10) }}" min="0" max="1000" required></label>
                                <label class="siid-field"><span>Opacidad</span><input type="number" step="0.05" name="layers[{{ $loop->index }}][opacity]" value="{{ old('layers.'.$loop->index.'.opacity', $assigned?->pivot->opacity ?? 1) }}" min="0" max="1" required></label>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700">
                                <input type="hidden" name="layers[{{ $loop->index }}][visible_by_default]" value="0"><label class="inline-flex items-center gap-2"><input type="checkbox" name="layers[{{ $loop->index }}][visible_by_default]" value="1" @checked(old('layers.'.$loop->index.'.visible_by_default', $assigned?->pivot->visible_by_default))> Visible al abrir</label>
                                <input type="hidden" name="layers[{{ $loop->index }}][show_in_legend]" value="0"><label class="inline-flex items-center gap-2"><input type="checkbox" name="layers[{{ $loop->index }}][show_in_legend]" value="1" @checked(old('layers.'.$loop->index.'.show_in_legend', $assigned?->pivot->show_in_legend ?? true))> Mostrar en selector</label>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 xl:col-span-2">No existen capas registradas. Registre o publique una capa antes de completar este geovisor.</p>
                    @endforelse
                </div>
            </section>

            <div class="sticky bottom-4 z-10 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur">
                <button class="siid-button siid-button-primary">{{ $geoViewer->isPublished() ? 'Guardar y aprobar cambios públicos' : 'Guardar configuración' }}</button>
            </div>
        </form>
    @elseif($geoViewer->status->value === 'review' && ! auth()->user()->canApproveSpatialPublication())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Pendiente de aprobación.</strong> El geovisor está bloqueado para edición mientras Gerencia o Administración revisan su publicación.</div>
    @endif

    @if(! $geoViewer->isPublished() && (auth()->user()->isAdmin() || $geoViewer->owner_id === auth()->id()))
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="post" action="{{ route('admin.geo-viewers.collaborators.update', $geoViewer) }}">
                @csrf
                @method('PUT')
                <h2 class="text-lg font-semibold text-slate-950">Colaboradores</h2>
                <p class="mt-1 text-sm text-slate-500">Pueden editar el borrador, pero no aprobar su publicación.</p>
                <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($collaboratorCandidates as $candidate)
                        @if($candidate->id !== $geoViewer->owner_id)
                            <label class="rounded-xl border border-slate-200 p-3 text-sm text-slate-700"><input class="mr-2" type="checkbox" name="collaborators[]" value="{{ $candidate->id }}" @checked($geoViewer->collaborators->contains($candidate))> {{ $candidate->name }}</label>
                        @endif
                    @endforeach
                </div>
                <button class="siid-button siid-button-secondary mt-4">Guardar colaboradores</button>
            </form>
        </section>
    @endif

    @if(! $geoViewer->isPublished())
        @can('approve-spatial-publication')
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <form method="post" action="{{ route('admin.geo-viewers.publication.store', $geoViewer) }}" onsubmit="return confirm('¿Aprobar la publicación de este geovisor en el portal oficial?')">
                    @csrf
                    <h2 class="text-lg font-semibold text-emerald-950">Aprobar publicación</h2>
                    <p class="mt-1 text-sm text-emerald-800">Confirme primero en la previsualización que las capas, colores, atributos y accesos son correctos.</p>
                    @if($activeLayers->isEmpty())
                        <p class="mt-3 rounded-xl border border-amber-200 bg-white p-3 text-sm text-amber-900"><strong>Falta una capa disponible.</strong> Active y asigne al menos una capa antes de publicar.</p>
                    @endif
                    @if($unclassifiedLayers->isNotEmpty())
                        <p class="mt-3 rounded-xl border border-amber-200 bg-white p-3 text-sm text-amber-900"><strong>Acceso público pendiente:</strong> {{ $unclassifiedLayers->pluck('name')->join(', ') }}. Defina si permiten descarga antes de publicar.</p>
                    @endif
                    <button class="siid-button siid-button-primary mt-4" @disabled($activeLayers->isEmpty() || $unclassifiedLayers->isNotEmpty())>Aprobar y publicar geovisor</button>
                </form>
            </section>
        @endcan
    @else
        <section class="rounded-2xl border border-emerald-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-950">Publicación activa</h2>
            <p class="mt-1 text-sm text-slate-600">Aprobada por {{ $geoViewer->approver?->name ?? 'usuario autorizado' }} el {{ $geoViewer->published_at?->format('d/m/Y H:i') }}.</p>
            <div class="mt-4 rounded-xl bg-slate-950 p-4 text-xs text-slate-200"><code class="break-all">&lt;iframe src=&quot;{{ route('geo-viewers.embed', $geoViewer) }}&quot; title=&quot;{{ $geoViewer->name }}&quot; width=&quot;100%&quot; height=&quot;800&quot; loading=&quot;lazy&quot; allowfullscreen&gt;&lt;/iframe&gt;</code></div>
        </section>
    @endif
</x-filament-panels::page>
