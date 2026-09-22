@extends('layouts.app')

@section('content')
<div class="space-y-7">
    <div>
        <p class="eyebrow">Publicaciones SIID</p>
        <h1 class="page-title">Geovisores y capas</h1>
        <p class="page-subtitle">Controle qué información geográfica se publica, cómo se agrupa y cómo se presenta en el visor embebible.</p>
    </div>

    @if(auth()->user()->isAdmin())
    <div class="grid gap-6 xl:grid-cols-2">
        <details class="panel action-disclosure" @if($errors->any()) open @endif>
            <summary><div><h2>Crear un visor</h2><span>Configure un nuevo mapa para después asignarle capas.</span></div><strong>Abrir formulario</strong></summary>
            <form method="post" action="{{ route('admin.geo-viewers.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2" data-slug-suggestion>
                @csrf
                <label class="field"><span>Nombre</span><input name="name" required maxlength="120" placeholder="Gestión del riesgo" data-slug-source></label>
                <label class="field"><span>Identificador URL</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="gestion-del-riesgo" data-slug-target><small>Se completa automáticamente y puede editarse.</small></label>
                <label class="field sm:col-span-2"><span>Descripción</span><textarea name="description" rows="2" maxlength="1000"></textarea></label>
                <label class="field"><span>Latitud inicial</span><input type="number" step="0.0000001" name="center_latitude" value="4.1500000" required></label>
                <label class="field"><span>Longitud inicial</span><input type="number" step="0.0000001" name="center_longitude" value="-73.6300000" required></label>
                <label class="field"><span>Zoom inicial</span><input type="number" name="initial_zoom" value="8" min="0" max="22" required></label>
                <label class="field"><span>Estado</span><select name="status" required><option value="draft">Borrador</option><option value="archived">Archivado</option></select></label>
                <button class="btn-primary sm:col-span-2">Crear visor</button>
            </form>
        </details>

        <details class="panel action-disclosure" @if($errors->any()) open @endif>
            <summary><div><h2>Registrar una capa</h2><span>Incorpore una fuente geográfica y defina su acceso público.</span></div><strong>Abrir formulario</strong></summary>
            <form method="post" action="{{ route('admin.geo-layers.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2" data-slug-suggestion>
                @csrf
                <label class="field"><span>Nombre</span><input name="name" required maxlength="120" data-slug-source></label>
                <label class="field"><span>Identificador</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" data-slug-target><small>Se completa automáticamente y puede editarse.</small></label>
                <label class="field"><span>Grupo temático</span><input name="group_name" maxlength="120" placeholder="Hídrico ambiental"></label>
                <label class="field"><span>Servicio</span><select name="source_type"><option value="geojson">GeoJSON / WFS</option><option value="wms">WMS (GeoServer)</option></select></label>
                <label class="field"><span>Geometría</span><select name="geometry_type"><option value="mixed">Mixta</option><option value="point">Punto</option><option value="line">Línea</option><option value="polygon">Polígono</option></select></label>
                <label class="field sm:col-span-2"><span>URL del servicio</span><input type="text" inputmode="url" name="source_url" required maxlength="2000" placeholder="https://.../ows, /api/public/... o /data/geovisores/..."></label>
                <label class="field sm:col-span-2"><span>Nombre de capa WMS</span><input name="source_layer_name" maxlength="255" placeholder="meta:fuentes_hidricas"><small>Obligatorio únicamente para WMS.</small></label>
                <label class="field sm:col-span-2"><span>Campos del popup</span><textarea name="popup_fields" rows="2" maxlength="4000" placeholder="codigo, nombre, municipio"></textarea><small>Sepárelos con comas o saltos de línea.</small></label>
                <label class="field"><span>Acceso para la comunidad</span><select name="access_policy" required>@foreach(\App\Enums\GeoLayerAccessPolicy::cases() as $policy)<option value="{{ $policy->value }}" @selected($policy === \App\Enums\GeoLayerAccessPolicy::Downloadable)>{{ $policy->label() }}</option>@endforeach</select><small>Por defecto permite consulta y descarga pública. Para WMS, indique un archivo descargable o seleccione otra política.</small></label>
                <label class="field"><span>Formato de descarga</span><select name="download_format"><option value="geojson">GeoJSON</option><option value="csv">CSV</option><option value="kml">KML</option><option value="gpkg">GeoPackage</option><option value="zip">Archivo ZIP</option></select></label>
                <label class="field sm:col-span-2"><span>Archivo público para descargar</span><input name="download_url" maxlength="2000" placeholder="Opcional para GeoJSON; obligatorio si el mapa usa WMS y permite descarga"><small>Si queda vacío en una capa GeoJSON, se descargará la misma fuente que dibuja el mapa.</small></label>
                <label class="field sm:col-span-2"><span>Justificación si es solo visualización</span><textarea name="restriction_reason" rows="2" maxlength="2000" placeholder="Explique por qué no se entrega el archivo completo."></textarea></label>
                <label class="field"><span>Color de línea</span><span class="layer-color-control"><input class="layer-color-input" type="color" name="color" value="#4338ca" data-layer-color-input required><output class="layer-color-value">#4338ca</output></span></label>
                <label class="field"><span>Color de relleno</span><span class="layer-color-control"><input class="layer-color-input" type="color" name="fill_color" value="#818cf8" data-layer-color-input required><output class="layer-color-value">#818cf8</output></span></label>
                <label class="field"><span>Grosor</span><input type="number" name="weight" value="2" min="0" max="10" required></label>
                <label class="field"><span>Radio de punto</span><input type="number" name="radius" value="7" min="1" max="30" required></label>
                <label class="field"><span>Zoom mínimo</span><input type="number" name="min_zoom" value="0" min="0" max="22" required></label>
                <label class="field"><span>Zoom máximo</span><input type="number" name="max_zoom" value="18" min="0" max="22" required></label>
                <label class="field sm:col-span-2"><span>Fuente y atribución</span><input name="attribution" maxlength="500" placeholder="Fuente: Gobernación del Meta"></label>
                <input type="hidden" name="active" value="0"><label class="check sm:col-span-2"><input type="checkbox" name="active" value="1" checked> Capa disponible para asignación</label>
                <button class="btn-primary sm:col-span-2">Registrar capa</button>
            </form>
        </details>
    </div>
    @endif

    <section class="space-y-4">
        <div><h2 class="text-xl font-semibold">Visores configurados</h2><p class="mt-1 text-sm text-slate-500">La previsualización funciona también para borradores. La URL pública solo responde cuando el estado es publicado.</p></div>
        @forelse($viewers as $viewer)
            <details class="panel" id="viewer-{{ $viewer->slug }}">
                <summary class="cursor-pointer list-none">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h3 class="font-semibold">{{ $viewer->name }}</h3><p class="mt-1 text-xs text-slate-500">/visores/{{ $viewer->slug }}/embed · {{ $viewer->layers->count() }} capas</p></div>
                        <a class="status status-action {{ $viewer->isPublished() ? 'status-approved' : '' }}" href="#viewer-{{ $viewer->slug }}" data-status-link title="Abrir la administración de este visor">{{ match($viewer->status->value) { 'published' => 'Publicado', 'archived' => 'Archivado', default => 'Borrador' } }}</a>
                    </div>
                </summary>

                <div class="mt-5 flex flex-wrap gap-2">
                    <a class="btn-secondary" href="{{ route('admin.geo-viewers.preview', $viewer) }}" target="_blank" rel="noopener">Previsualizar</a>
                    @if($viewer->isPublished())<a class="btn-secondary" href="{{ route('geo-viewers.embed', $viewer) }}" target="_blank" rel="noopener">Abrir público</a>@endif
                </div>

                @if(auth()->user()->isAdmin())
                <form method="post" action="{{ route('admin.geo-viewers.update', $viewer) }}" class="mt-5 space-y-5" @if($viewer->isPublished()) onsubmit="return confirm('¿Aplicar estos cambios ahora al visor público?')" @endif>
                    @csrf @method('PATCH')
                    @if($viewer->isPublished())<p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Este visor ya es público. Al guardar, los cambios quedan registrados con su aprobación y pueden tardar hasta un minuto en verse. Su dirección y código iframe no cambian.</p>@endif
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="field"><span>Nombre</span><input name="name" value="{{ $viewer->name }}" required maxlength="120"></label>
                        <label class="field"><span>Identificador URL</span><input name="slug" value="{{ $viewer->slug }}" required maxlength="120" @readonly($viewer->isPublished())></label>
                        <label class="field"><span>Estado</span><select name="status" @disabled($viewer->isPublished())><option value="draft" @selected($viewer->status->value === 'draft')>Borrador</option><option value="archived" @selected($viewer->status->value === 'archived')>Archivado</option>@if($viewer->isPublished())<option value="published" selected>Publicado</option>@endif</select>@if($viewer->isPublished())<input type="hidden" name="status" value="published">@endif</label>
                        <label class="field sm:col-span-2 lg:col-span-3"><span>Descripción</span><textarea name="description" rows="2" maxlength="1000">{{ $viewer->description }}</textarea></label>
                        <label class="field"><span>Latitud</span><input type="number" step="0.0000001" name="center_latitude" value="{{ $viewer->center_latitude }}" required></label>
                        <label class="field"><span>Longitud</span><input type="number" step="0.0000001" name="center_longitude" value="{{ $viewer->center_longitude }}" required></label>
                        <label class="field"><span>Zoom</span><input type="number" name="initial_zoom" value="{{ $viewer->initial_zoom }}" min="0" max="22" required></label>
                    </div>

                    <div>
                        <h4 class="font-semibold">Capas del visor</h4>
                        <p class="mt-1 text-xs text-slate-500">Active las capas, cambie su grupo y determine cuáles aparecen encendidas inicialmente.</p>
                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Incluir</th><th class="p-3">Capa y etiqueta</th><th class="p-3">Grupo</th><th class="p-3">Orden</th><th class="p-3">Opacidad</th><th class="p-3">Opciones</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($layers as $layer)
                                        @php($assigned = $viewer->layers->firstWhere('id', $layer->id))
                                        <tr>
                                            <td class="p-3"><input type="hidden" name="layers[{{ $loop->index }}][geo_layer_id]" value="{{ $layer->id }}"><input type="hidden" name="layers[{{ $loop->index }}][included]" value="0"><input class="h-4 w-4" type="checkbox" name="layers[{{ $loop->index }}][included]" value="1" @checked($assigned)></td>
                                            <td class="p-3"><p class="font-medium">{{ $layer->name }}</p><input class="mt-2 min-w-48" name="layers[{{ $loop->index }}][label]" value="{{ $assigned?->pivot->label }}" placeholder="Etiqueta pública opcional"></td>
                                            <td class="p-3"><input class="min-w-44" name="layers[{{ $loop->index }}][group_name]" value="{{ $assigned?->pivot->group_name ?: $layer->group_name }}"></td>
                                            <td class="p-3"><input class="w-24" type="number" name="layers[{{ $loop->index }}][sort_order]" value="{{ $assigned?->pivot->sort_order ?? $loop->iteration * 10 }}" min="0" max="1000" required></td>
                                            <td class="p-3"><input class="w-24" type="number" step="0.05" name="layers[{{ $loop->index }}][opacity]" value="{{ $assigned?->pivot->opacity ?? 1 }}" min="0" max="1" required></td>
                                            <td class="p-3"><input type="hidden" name="layers[{{ $loop->index }}][visible_by_default]" value="0"><label class="flex items-center gap-2"><input class="h-4 w-4" type="checkbox" name="layers[{{ $loop->index }}][visible_by_default]" value="1" @checked($assigned?->pivot->visible_by_default)> Visible al abrir</label><input type="hidden" name="layers[{{ $loop->index }}][show_in_legend]" value="0"><label class="mt-2 flex items-center gap-2"><input class="h-4 w-4" type="checkbox" name="layers[{{ $loop->index }}][show_in_legend]" value="1" @checked($assigned?->pivot->show_in_legend ?? true)> En selector</label></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button class="btn-primary">{{ $viewer->isPublished() ? 'Guardar y aprobar cambios públicos' : 'Guardar y aplicar configuración' }}</button>
                </form>
                @endif

                @if(! $viewer->isPublished())
                    @can('approve-spatial-publication')
                        <form method="post" action="{{ route('admin.geo-viewers.publication.store', $viewer) }}" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4" onsubmit="return confirm('¿Aprobar la publicación de este geovisor en el portal oficial?')">
                            @csrf
                            <p class="text-sm text-emerald-900">Revise la previsualización y confirme que las capas y la información son correctas.</p>
                            <button class="btn-primary mt-3">Aprobar y publicar geovisor</button>
                        </form>
                    @else
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Pendiente de aprobación.</strong> Sólo Gerencia o su apoyo administrativo pueden publicarlo.</div>
                    @endcan
                @else
                    <p class="mt-4 text-sm text-emerald-800">Aprobado por <strong>{{ $viewer->approver?->name ?? 'usuario autorizado' }}</strong> el {{ $viewer->published_at?->format('d/m/Y H:i') }}.</p>
                @endif

                @if($viewer->isPublished())
                    <div class="mt-5 rounded-xl bg-slate-950 p-4 text-xs text-slate-200"><p class="mb-2 font-semibold text-white">Código para el portal oficial</p><code class="break-all">&lt;iframe src=&quot;{{ route('geo-viewers.embed', $viewer) }}&quot; title=&quot;{{ $viewer->name }}&quot; width=&quot;100%&quot; height=&quot;800&quot; loading=&quot;lazy&quot; allowfullscreen&gt;&lt;/iframe&gt;</code></div>
                @endif
            </details>
        @empty
            <div class="panel text-sm text-slate-500">Todavía no hay visores. Cree el primero arriba.</div>
        @endforelse
    </section>

    <section class="space-y-4">
        <div><h2 class="text-xl font-semibold">Catálogo de capas</h2><p class="mt-1 text-sm text-slate-500">Desactivar una capa la retira de todos los visores públicos sin eliminar su configuración.</p></div>
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($layers as $layer)
                @php($managedDataset = $managedDatasets->get($layer->slug))
                @php($managedVersion = $managedDataset?->versions->first())
                @php($isManagedLayer = $managedVersion && $managedDataset->materialized_form_version >= $managedVersion->version && $layer->source_type === 'geojson' && $layer->source_url === '/api/public/geodata/'.$managedDataset->slug)
                @php($selectedAttributes = $layer->public_attribute_fields ?? $managedVersion?->fields->where('public_visible', true)->pluck('key')->all() ?? [])
                <details class="panel" id="layer-{{ $layer->slug }}">
                    <summary class="cursor-pointer list-none"><div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $layer->name }}</h3><p class="mt-1 text-xs text-slate-500">{{ $layer->group_name ?: 'Sin grupo' }} · {{ $layer->geometry_type }} · {{ $layer->access_policy->label() }}</p></div><a class="status status-action {{ $layer->active ? 'status-approved' : '' }}" href="#layer-{{ $layer->slug }}" data-status-link title="Abrir la administración de esta capa">{{ $layer->active ? 'Disponible' : 'Inactiva' }}</a></div></summary>
                    @if(auth()->user()->isAdmin())
                    <form method="post" action="{{ route('admin.geo-layers.update', $layer) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <label class="field"><span>Nombre</span><input name="name" value="{{ $layer->name }}" required></label>
                        <label class="field"><span>Identificador</span><input name="slug" value="{{ $layer->slug }}" required></label>
                        <label class="field"><span>Grupo</span><input name="group_name" value="{{ $layer->group_name }}"></label>
                        <label class="field"><span>Servicio</span><select name="source_type"><option value="geojson" @selected($layer->source_type === 'geojson')>GeoJSON / WFS</option><option value="wms" @selected($layer->source_type === 'wms')>WMS (GeoServer)</option></select></label>
                        <label class="field"><span>Geometría</span><select name="geometry_type">@foreach(['mixed' => 'Mixta', 'point' => 'Punto', 'line' => 'Línea', 'polygon' => 'Polígono'] as $value => $label)<option value="{{ $value }}" @selected($layer->geometry_type === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="field sm:col-span-2"><span>URL del servicio</span><input type="text" inputmode="url" name="source_url" value="{{ $layer->source_url }}" required></label>
                        <label class="field sm:col-span-2"><span>Nombre de capa WMS</span><input name="source_layer_name" value="{{ $layer->source_layer_name }}" placeholder="meta:fuentes_hidricas"></label>
                        @if($isManagedLayer)
                            <input type="hidden" name="popup_fields" value="{{ implode(', ', $layer->popup_fields ?? []) }}">
                        @else
                            <label class="field sm:col-span-2"><span>Campos del popup</span><textarea name="popup_fields" rows="2">{{ implode(', ', $layer->popup_fields ?? []) }}</textarea></label>
                        @endif
                        <label class="field"><span>Acceso para la comunidad</span><select name="access_policy" required>@foreach(\App\Enums\GeoLayerAccessPolicy::cases() as $policy)<option value="{{ $policy->value }}" @selected($layer->access_policy === $policy)>{{ $policy->label() }}</option>@endforeach</select><small>Solo visualización exige una fuente WMS.</small></label>
                        <label class="field"><span>Formato de descarga</span><select name="download_format">@foreach(['geojson' => 'GeoJSON', 'csv' => 'CSV', 'kml' => 'KML', 'gpkg' => 'GeoPackage', 'zip' => 'Archivo ZIP'] as $value => $label)<option value="{{ $value }}" @selected($layer->download_format === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="field sm:col-span-2"><span>Archivo público para descargar</span><input name="download_url" value="{{ $layer->download_url }}" maxlength="2000" placeholder="Opcional para GeoJSON; obligatorio para descargas asociadas a WMS"></label>
                        <label class="field sm:col-span-2"><span>Justificación si es solo visualización</span><textarea name="restriction_reason" rows="2" maxlength="2000">{{ $layer->restriction_reason }}</textarea>@if($layer->access_policy_approved_at)<small>Política definida el {{ $layer->access_policy_approved_at->format('d/m/Y H:i') }}.</small>@endif</label>
                        <label class="field"><span>Color de línea</span><span class="layer-color-control"><input class="layer-color-input" type="color" name="color" value="{{ data_get($layer->style, 'color', '#4338ca') }}" data-layer-color-input><output class="layer-color-value">{{ data_get($layer->style, 'color', '#4338ca') }}</output></span></label>
                        <label class="field"><span>Relleno</span><span class="layer-color-control"><input class="layer-color-input" type="color" name="fill_color" value="{{ data_get($layer->style, 'fillColor', '#818cf8') }}" data-layer-color-input><output class="layer-color-value">{{ data_get($layer->style, 'fillColor', '#818cf8') }}</output></span></label>
                        <label class="field"><span>Grosor</span><input type="number" name="weight" value="{{ data_get($layer->style, 'weight', 2) }}" min="0" max="10"></label>
                        <label class="field"><span>Radio</span><input type="number" name="radius" value="{{ data_get($layer->style, 'radius', 7) }}" min="1" max="30"></label>
                        <label class="field"><span>Zoom mínimo</span><input type="number" name="min_zoom" value="{{ $layer->min_zoom }}" min="0" max="22"></label>
                        <label class="field"><span>Zoom máximo</span><input type="number" name="max_zoom" value="{{ $layer->max_zoom }}" min="0" max="22"></label>
                        <label class="field sm:col-span-2"><span>Atribución</span><input name="attribution" value="{{ $layer->attribution }}"></label>
                        <input type="hidden" name="active" value="0"><label class="check sm:col-span-2"><input type="checkbox" name="active" value="1" @checked($layer->active)> Disponible para publicación</label>
                        <button class="btn-primary sm:col-span-2">Guardar capa</button>
                    </form>
                    @else
                        <div class="mt-4 text-sm text-slate-600"><p>Grupo: {{ $layer->group_name ?: 'Sin grupo' }}</p><p class="mt-1">Fuente: {{ $layer->attribution ?: 'Sin atribución registrada' }}</p></div>
                    @endif
                    @if($isManagedLayer)
                        <section class="mt-5 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4">
                            <h4 class="font-semibold">Atributos visibles al consultar un punto</h4>
                            <p class="mt-1 text-sm text-slate-600">Active solo los datos que puede consultar la comunidad. Esta selección también controla las propiedades del GeoJSON descargable y no crea una nueva versión de QGIS.</p>
                            @can('approve-spatial-publication')
                                <form method="post" action="{{ route('admin.geo-layers.public-attributes.update', $layer) }}" class="mt-4">
                                    @csrf
                                    <div class="grid gap-2">
                                        @foreach($managedVersion->fields as $field)
                                            <label class="flex min-w-0 items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                <input class="mt-0.5 h-4 w-4 shrink-0 p-0" type="checkbox" name="fields[]" value="{{ $field->key }}" @checked(in_array($field->key, $selectedAttributes, true))>
                                                <span class="min-w-0 flex-1 break-words">{{ $field->label }} <small class="block break-all font-mono text-slate-500">{{ $field->key }}</small></span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <button class="btn-primary mt-4">Guardar atributos visibles</button>
                                </form>
                            @else
                                <p class="mt-3 text-sm text-slate-600">{{ count($selectedAttributes) }} de {{ $managedVersion->fields->count() }} atributos visibles. Un usuario autorizado para publicar puede cambiar esta selección.</p>
                            @endcan
                        </section>
                    @endif
                </details>
            @endforeach
        </div>
    </section>
</div>
@endsection
