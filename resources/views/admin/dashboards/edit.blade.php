@extends('layouts.app', ['title' => $dashboard->name.' · Constructor'])

@section('content')
@php
    $config = $dashboard->draft_config ?? ['widgets' => [], 'map' => [], 'global_filters' => []];
    $populationYear = $config['population_year']
        ?? data_get(collect($config['widgets'] ?? [])->first(fn ($widget) => str_starts_with(data_get($widget, 'query.operation', ''), 'population_')), 'query.year', 2026);
@endphp
<div class="dashboard-builder" data-dashboard-builder data-save-url="{{ route('admin.dashboards.update', $dashboard, false) }}" data-preview-url="{{ route('admin.dashboards.preview', $dashboard, false) }}" data-diagnostic-url="{{ route('admin.dashboards.relationship-diagnostic', $dashboard, false) }}" data-config='@json($config)'>
    <header class="dashboard-builder-head"><div><a class="text-sm font-semibold text-emerald-700" href="{{ route('admin.dashboards.index') }}">← Dashboards</a><h1 class="page-title">{{ $dashboard->name }}</h1><p class="page-subtitle">Arrastre los componentes, cambie su tamaño y configure qué información muestran.</p></div><div class="flex flex-wrap gap-2"><span class="dashboard-save-state" data-dashboard-save-state>Sin cambios</span><a class="btn-secondary" data-dashboard-preview target="_blank">Vista previa</a></div></header>

    <section class="panel dashboard-source-settings"><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <label class="field"><span>Fuente tabular</span><select data-dashboard-source><option value="">Sin fuente</option>@foreach($sources as $source)<option value="{{ $source->id }}" data-fields='@json($source->currentVersion?->fields ?? [])' @selected(($config['data_source_id'] ?? null) === $source->id)>{{ $source->name }} (v{{ $source->current_version }})</option>@endforeach</select></label>
        <label class="field"><span>Año poblacional</span><input type="number" min="1900" max="2200" step="1" data-dashboard-year value="{{ $populationYear }}"><small>Se aplica a indicadores, dona y pirámide.</small></label>
        <label class="field"><span>Geovisor del mapa</span><select data-dashboard-viewer><option value="">Sin geovisor</option>@foreach($geoViewers as $viewer)<option value="{{ $viewer->id }}" @selected(data_get($config, 'map.geo_viewer_id') === $viewer->id)>{{ $viewer->name }}</option>@endforeach</select></label>
        <label class="field"><span>Código territorial en la capa</span><input data-join-layer value="{{ data_get($config, 'map.join_layer_field', 'codigo_dane') }}"></label>
        <label class="field"><span>Código territorial en los datos</span><input data-join-data value="{{ data_get($config, 'map.join_data_field', 'codigo_dane') }}"></label>
    </div><div class="mt-4 flex flex-wrap items-center gap-3"><button type="button" class="btn-secondary" data-run-diagnostic>Comprobar relación territorial</button><p class="text-sm text-slate-500" data-diagnostic-result>Use esta comprobación antes de publicar.</p></div></section>

    <div class="dashboard-builder-layout"><aside class="dashboard-palette panel"><h2>Agregar componente</h2><div class="grid grid-cols-2 gap-2">@foreach(['indicator' => 'Indicador', 'bar' => 'Barras', 'line' => 'Líneas', 'donut' => 'Dona', 'pyramid' => 'Pirámide', 'table' => 'Tabla', 'text' => 'Texto', 'filter' => 'Filtro', 'map' => 'Mapa'] as $type => $label)<button type="button" class="btn-small" data-add-widget="{{ $type }}">{{ $label }}</button>@endforeach</div><p class="mt-4 text-xs text-slate-500">La cuadrícula tiene 12 columnas. Use los controles de cada bloque para ajustar su tamaño.</p></aside>
        <main class="dashboard-grid" data-dashboard-grid aria-label="Lienzo del dashboard"></main>
        <aside class="dashboard-inspector panel" data-dashboard-inspector><h2>Configuración</h2><p class="mt-2 text-sm text-slate-500">Seleccione un componente para configurar título, alcance y consulta.</p></aside>
    </div>

    <section class="panel flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-semibold">Publicación</h2><p class="text-sm text-slate-500">Estado: {{ $dashboard->status->label() }}. La versión pública actual permanece intacta mientras edita.</p></div><div class="flex gap-2"><form method="post" action="{{ route('admin.dashboards.submit', $dashboard) }}">@csrf<button class="btn-primary">Enviar a revisión</button></form>@if(auth()->user()->canApproveDashboards() && $dashboard->status === App\Enums\DashboardStatus::PendingReview)<form method="post" action="{{ route('admin.dashboards.publication.store', $dashboard) }}">@csrf<button class="btn-primary">Aprobar y publicar</button></form>@endif</div></section>
    <details class="panel"><summary class="cursor-pointer font-semibold">Colaboradores</summary><form method="post" action="{{ route('admin.dashboards.collaborators.update', $dashboard) }}" class="mt-4 grid gap-2 md:grid-cols-2">@csrf @method('PUT')@foreach($users as $user)<label class="check"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked($dashboard->collaborators->contains($user)) @disabled($dashboard->owner_id === $user->id)>{{ $user->name }} <small>{{ $user->role->label() }}</small></label>@endforeach<button class="btn-secondary md:col-span-2">Guardar colaboradores</button></form></details>
</div>
@endsection
