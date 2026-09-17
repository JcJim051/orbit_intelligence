@extends('layouts.app', ['title' => 'Datos SIG · SIID 2.0'])

@section('content')
<div class="space-y-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Gobierno del dato</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Conjuntos de datos y formularios SIG</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">Laravel define la estructura que consumirá QGIS. Las versiones publicadas son inmutables y cada campo declara cómo tratar los registros históricos.</p>
        </div>
        <a class="btn-secondary" href="{{ route('admin.geo-viewers.index') }}">Administrar geovisores</a>
    </header>

    @if(auth()->user()->isAdmin())
    <details class="panel action-disclosure" @if($errors->any()) open @endif>
        <summary><div><h2>Crear conjunto de datos</h2><span>Se generará la primera versión editable del formulario.</span></div><strong>Abrir formulario</strong></summary>
        <form method="post" action="{{ route('admin.spatial-datasets.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-slug-suggestion>
            @csrf
            <label class="field"><span>Nombre</span><input name="name" required maxlength="120" placeholder="Puntos críticos" data-slug-source></label>
            <label class="field"><span>Identificador</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="puntos-criticos" data-slug-target><small>Se completa automáticamente y puede editarse.</small></label>
            <label class="field"><span>Sector responsable</span><input name="sector" required maxlength="120" placeholder="Gestión del Riesgo"></label>
            <label class="field"><span>Geometría</span><select name="geometry_type" required><option value="point">Punto</option><option value="line">Línea</option><option value="polygon">Polígono</option><option value="none">Sin geometría</option></select></label>
            <label class="field"><span>Coordenadas para guardar en PostGIS</span><select name="storage_srid" required>@foreach($storageCrss as $srid => $label)<option value="{{ $srid }}" @selected((int) old('storage_srid', 9377) === $srid)>{{ $label }}</option>@endforeach</select><small>Se pueden recibir capas en otro EPSG reconocido; PostGIS las transforma al elegido. El visor público utiliza WGS 84.</small></label>
            <label class="field sm:col-span-2"><span>Descripción</span><textarea name="description" rows="2" maxlength="2000"></textarea></label>
            <button class="btn-primary sm:col-span-2 lg:col-span-3">Crear conjunto de datos</button>
        </form>
    </details>
    @endif

    <section class="space-y-4">
        <div><h2 class="text-xl font-semibold">Catálogo institucional</h2><p class="mt-1 text-sm text-slate-500">{{ $datasets->count() }} conjuntos configurados.</p></div>

        @forelse($datasets as $dataset)
            @php
                $draft = $dataset->versions->first(fn ($version) => $version->status === \App\Enums\DatasetFormVersionStatus::Draft);
                $published = $dataset->versions->first(fn ($version) => $version->status === \App\Enums\DatasetFormVersionStatus::Published);
                $structureLocked = $dataset->physical_table || $dataset->versions->contains(fn ($version) => $version->status !== \App\Enums\DatasetFormVersionStatus::Draft);
                $materialized = $published && $dataset->physical_table && $dataset->materialized_form_version >= $published->version;
            @endphp
            <details class="panel" id="dataset-{{ $dataset->slug }}" @if(session('prepared_dataset') === $dataset->slug) open @endif>
                <summary class="cursor-pointer list-none">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-indigo-600">{{ $dataset->sector }}</p>
                            <h3 class="mt-1 text-lg font-semibold">{{ $dataset->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ match($dataset->geometry_type) { 'point' => 'Puntos', 'line' => 'Líneas', 'polygon' => 'Polígonos', default => 'Sin geometría' } }} · {{ $dataset->versions->count() }} versiones · PostGIS EPSG:{{ $dataset->storage_srid }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a class="status status-action {{ $dataset->status === \App\Enums\DatasetStatus::Active ? 'status-approved' : '' }}" href="#dataset-{{ $dataset->slug }}" data-status-link title="Abrir la administración de este conjunto">{{ $dataset->status->label() }}</a>
                            @if($draft)<a class="status status-action" href="#dataset-{{ $dataset->slug }}" data-status-link title="Abrir el formulario en edición">Versión {{ $draft->version }} en edición</a>@endif
                        </div>
                    </div>
                </summary>

                <div class="mt-6 space-y-7">
                    @if(auth()->user()->isAdmin())
                    <form method="post" action="{{ route('admin.spatial-datasets.update', $dataset) }}" class="grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        @csrf @method('PATCH')
                        <label class="field"><span>Nombre</span><input name="name" value="{{ $dataset->name }}" required></label>
                        <label class="field"><span>Identificador</span><input name="slug" value="{{ $dataset->slug }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" @readonly($structureLocked)></label>
                        <label class="field"><span>Sector</span><input name="sector" value="{{ $dataset->sector }}" required></label>
                        <label class="field"><span>Geometría</span>@if($structureLocked)<input type="hidden" name="geometry_type" value="{{ $dataset->geometry_type }}">@endif<select name="geometry_type" @disabled($structureLocked)>@foreach(['point' => 'Punto', 'line' => 'Línea', 'polygon' => 'Polígono', 'none' => 'Sin geometría'] as $value => $label)<option value="{{ $value }}" @selected($dataset->geometry_type === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="field"><span>Coordenadas en PostGIS</span>@if($structureLocked)<input type="hidden" name="storage_srid" value="{{ $dataset->storage_srid }}">@endif<select name="storage_srid" @disabled($structureLocked)>@foreach($storageCrss as $srid => $label)<option value="{{ $srid }}" @selected($dataset->storage_srid === $srid)>{{ $label }}</option>@endforeach</select>@if($structureLocked)<small>Fijo tras la primera publicación para no alterar las coordenadas históricas.</small>@endif</label>
                        <label class="field sm:col-span-2"><span>Descripción</span><textarea name="description" rows="2">{{ $dataset->description }}</textarea></label>
                        <button class="btn-secondary sm:col-span-2 lg:col-span-3">Guardar información general</button>
                    </form>
                    @else
                        <div class="rounded-xl border border-slate-200 p-4 text-sm"><p><strong>{{ $dataset->name }}</strong> · {{ $dataset->sector }}</p><p class="mt-2 text-slate-600">{{ $dataset->description ?: 'Sin descripción.' }}</p></div>
                    @endif

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase text-slate-500">Integración QGIS / PostGIS</p>
                                <p class="mt-1 text-sm font-semibold">
                                    {{ $materialized
                                        ? 'Tabla disponible: capture.'.$dataset->physical_table
                                        : ($dataset->physical_table ? 'La tabla de QGIS necesita actualizarse' : 'Pendiente de crear la tabla para QGIS') }}
                                </p>
                            </div>
                            @if($materialized)
                                <a class="status status-action status-approved" href="#dataset-{{ $dataset->slug }}" data-status-link title="Ver la configuración para QGIS">Disponible para QGIS · Formulario v{{ $dataset->materialized_form_version }}</a>
                            @elseif($published)
                                <span class="status">Pendiente de preparar para QGIS</span>
                            @else
                                <a class="status status-action" href="#dataset-{{ $dataset->slug }}" data-status-link title="Revisar y publicar el formulario">Formulario pendiente de publicación</a>
                            @endif
                        </div>
                        @if($published && ! $materialized)
                            @can('approve-spatial-publication')
                                <form method="post" action="{{ route('admin.spatial-datasets.materialization.store', $dataset) }}" class="mt-3">
                                    @csrf
                                    <button class="btn-secondary">Reintentar preparación</button>
                                </form>
                            @endcan
                        @endif
                        <p class="mt-2 text-xs text-slate-600">Contrato QGIS: <code>/api/v1/qgis/datasets/{{ $dataset->slug }}/form</code>@if($dataset->materialized_at) · Actualizado {{ $dataset->materialized_at->format('d/m/Y H:i') }}@endif</p>
                        @if($dataset->materialization_error)<p class="mt-2 text-xs font-semibold text-red-700">Último error: {{ $dataset->materialization_error }}</p>@endif
                    </div>

                    @if($draft)
                        <div class="rounded-xl border-2 border-indigo-100 bg-indigo-50/40 p-4 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div><p class="text-xs font-semibold uppercase text-indigo-600">Constructor editable</p><h4 class="mt-1 text-lg font-semibold">Formulario versión {{ $draft->version }}</h4><p class="mt-1 text-sm text-slate-600">{{ $draft->fields->count() }} campos configurados.</p></div>
                                <a class="status status-action" href="#dataset-{{ $dataset->slug }}" data-status-link title="Revisar este borrador">Borrador</a>
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                @foreach($draft->fields as $field)
                                    <details class="rounded-xl border border-slate-200 bg-white p-4">
                                        <summary class="cursor-pointer list-none">
                                            <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $field->label }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $field->key }} · {{ $field->field_type->label() }}@if($field->unit) · {{ $field->unit }}@endif · desde v{{ $field->introduced_in_version }}</p></div><span class="status {{ $field->required ? 'status-approved' : '' }}">{{ $field->required ? 'Obligatorio' : 'Opcional' }}</span></div>
                                        </summary>
                                        @if(auth()->user()->isAdmin())
                                        <form method="post" action="{{ route('admin.spatial-datasets.versions.fields.update', [$dataset, $draft, $field]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                            @csrf @method('PATCH')
                                            @include('admin.spatial-datasets.partials.field-form', ['submitLabel' => 'Guardar campo'])
                                        </form>
                                        <form method="post" action="{{ route('admin.spatial-datasets.versions.fields.destroy', [$dataset, $draft, $field]) }}" class="mt-3" onsubmit="return confirm('¿Retirar este campo del borrador?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700">Retirar campo</button></form>
                                        @else
                                            <p class="mt-3 text-sm text-slate-600">{{ $field->help_text ?: 'Sin indicaciones adicionales.' }}</p>
                                            <p class="mt-2 text-xs text-slate-500">Visible al público: {{ $field->public_visible ? 'Sí' : 'No' }} · Disponible para análisis: {{ $field->available_for_analytics ? 'Sí' : 'No' }}</p>
                                        @endif
                                    </details>
                                @endforeach
                            </div>

                            @if(auth()->user()->isAdmin())
                            <details class="mt-5 rounded-xl border border-dashed border-indigo-300 bg-white p-4">
                                <summary class="cursor-pointer font-semibold text-indigo-700">+ Agregar campo al formulario</summary>
                                <form method="post" action="{{ route('admin.spatial-datasets.versions.fields.store', [$dataset, $draft]) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @csrf
                                    @include('admin.spatial-datasets.partials.field-form', ['field' => null, 'submitLabel' => 'Agregar campo'])
                                </form>
                            </details>
                            @endif

                            @can('approve-spatial-publication')
                            <form method="post" action="{{ route('admin.spatial-datasets.versions.publication.store', [$dataset, $draft]) }}" class="mt-5 flex flex-wrap items-end gap-3 rounded-xl bg-slate-900 p-4 text-white">
                                @csrf
                                <label class="field grow"><span class="text-slate-200">Vigente desde</span><input class="text-slate-900" type="date" name="effective_from" value="{{ now()->toDateString() }}" required></label>
                                <div class="max-w-xl text-xs text-slate-300">Al publicar, esta versión quedará bloqueada. Los cambios posteriores se harán en una nueva versión.</div>
                                <button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900">Publicar versión {{ $draft->version }}</button>
                            </form>
                            @else
                                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Pendiente de aprobación.</strong> Sólo un usuario con perfil Gerente o Apoyo administrativo de Gerencia puede publicar esta versión.</div>
                            @endcan
                        </div>
                    @elseif($published)
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <div><p class="font-semibold text-emerald-900">Versión {{ $published->version }} publicada</p><p class="mt-1 text-sm text-emerald-800">Aprobada por {{ $published->approver?->name ?? 'usuario autorizado' }}. Está protegida contra modificaciones.</p></div>
                            @if(auth()->user()->isAdmin())<form method="post" action="{{ route('admin.spatial-datasets.drafts.store', $dataset) }}">@csrf<button class="btn-primary">Crear siguiente borrador</button></form>@endif
                        </div>
                    @endif

                    <div>
                        <h4 class="font-semibold">Historial de versiones</h4>
                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Versión</th><th class="p-3">Estado</th><th class="p-3">Campos</th><th class="p-3">Vigencia</th><th class="p-3">Publicación</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">@foreach($dataset->versions as $version)<tr><td class="p-3 font-semibold">{{ $version->version }}</td><td class="p-3">{{ $version->status->label() }}</td><td class="p-3">{{ $version->fields->count() }}</td><td class="p-3">{{ $version->effective_from?->format('d/m/Y') ?: 'Pendiente' }}</td><td class="p-3">{{ $version->published_at?->format('d/m/Y H:i') ?: 'Sin publicar' }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>
        @empty
            <div class="panel text-sm text-slate-500">Todavía no hay conjuntos de datos. Cree el primero para diseñar su formulario.</div>
        @endforelse
    </section>
</div>
@endsection
