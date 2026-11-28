@extends('layouts.app', ['title' => $dashboard->name.' · Constructor Superset'])

@php
    $wizard = $dashboard->wizard_config ?? ['dataset_name' => null, 'charts' => []];
    $charts = old('charts', $wizard['charts'] ?? []);
    if ($charts === []) {
        $charts = [[
            'title' => 'Indicador principal',
            'type' => 'big_number',
            'dimension' => '',
            'metric' => '',
            'aggregation' => 'SUM',
        ]];
    }
@endphp

@section('content')
<div class="space-y-6" data-superset-wizard>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'tableros']) }}">← Analítica y tableros</a>
            <p class="eyebrow mt-4">Constructor básico · Motor Superset</p>
            <h1 class="page-title">{{ $dashboard->name }}</h1>
            <p class="page-subtitle">Configure el dataset y los gráficos desde SIID. El mismo tablero podrá continuar editándose en Superset.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="status">{{ $dashboard->status->label() }}</span>
            @if($dashboard->superset_dashboard_id && $supersetConfigured)
                <a class="btn-secondary" href="{{ rtrim(config('superset.url'), '/').'/superset/dashboard/'.$dashboard->superset_dashboard_id.'/' }}" target="_blank" rel="noopener">Modo avanzado en Superset ↗</a>
            @endif
        </div>
    </header>

    @unless($supersetConfigured)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Superset todavía no está configurado en este entorno. Puede preparar el tablero, pero no sincronizarlo ni previsualizarlo.
        </div>
    @endunless

    <section class="grid gap-4 lg:grid-cols-4" aria-label="Flujo del constructor">
        @foreach([
            ['1', 'Dataset', 'Seleccione la fuente validada'],
            ['2', 'Gráficos', 'Defina métricas y dimensiones'],
            ['3', 'Previsualización', 'Revise el tablero embebido'],
            ['4', 'Publicación', 'Controle acceso y catálogo'],
        ] as $step)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Paso {{ $step[0] }}</span>
                <h2 class="mt-2 font-semibold text-slate-950">{{ $step[1] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $step[2] }}</p>
            </article>
        @endforeach
    </section>

    <form method="post" action="{{ route('admin.dashboards.superset.update', $dashboard) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="eyebrow">Identidad del tablero</p>
                    <h2 class="mt-1 text-xl font-semibold">Información general</h2>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">Superset</span>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <label class="field"><span>Título</span><input name="name" value="{{ old('name', $dashboard->name) }}" required maxlength="120"></label>
                <label class="field"><span>Dataset analítico</span>
                    <select name="dataset_name" data-dataset required>
                        <option value="">Seleccione un dataset</option>
                        @foreach($datasets as $dataset)
                            <option
                                value="{{ $dataset['name'] }}"
                                data-columns='@json($dataset['columns'])'
                                @selected(old('dataset_name', $wizard['dataset_name'] ?? null) === $dataset['name'])
                            >{{ $dataset['name'] }} · {{ $dataset['domain'] }}</option>
                        @endforeach
                    </select>
                    <small data-dataset-help>Solo aparecen datasets preparados en el esquema analítico del SIID.</small>
                </label>
                <label class="field lg:col-span-2"><span>Descripción</span><textarea name="description" maxlength="1000" rows="3">{{ old('description', $dashboard->description) }}</textarea></label>
            </div>
        </section>

        <section class="panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="eyebrow">Composición</p>
                    <h2 class="mt-1 text-xl font-semibold">Gráficos del tablero</h2>
                    <p class="mt-1 text-sm text-slate-500">La primera versión admite indicadores, barras, líneas, torta y tabla.</p>
                </div>
                <button type="button" class="btn-secondary" data-add-chart>+ Agregar gráfico</button>
            </div>

            <div class="mt-5 space-y-4" data-chart-list>
                @foreach($charts as $index => $chart)
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4" data-chart>
                        <input type="hidden" data-chart-input="key" name="charts[{{ $index }}][key]" value="{{ $chart['key'] ?? '' }}">
                        <div class="flex items-center justify-between gap-3">
                            <strong data-chart-number>Gráfico {{ $loop->iteration }}</strong>
                            <button type="button" class="text-sm font-semibold text-red-700" data-remove-chart>Quitar</button>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <label class="field xl:col-span-2"><span>Título</span><input data-chart-input="title" name="charts[{{ $index }}][title]" value="{{ $chart['title'] ?? '' }}" required maxlength="120"></label>
                            <label class="field"><span>Visualización</span><select data-chart-input="type" name="charts[{{ $index }}][type]">
                                @foreach(['big_number' => 'Indicador', 'bar' => 'Barras', 'line' => 'Líneas', 'pie' => 'Torta', 'table' => 'Tabla'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($chart['type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select></label>
                            <label class="field"><span>Métrica</span><select data-chart-input="metric" data-column-select name="charts[{{ $index }}][metric]" data-value="{{ $chart['metric'] ?? '' }}" required></select></label>
                            <label class="field"><span>Agregación</span><select data-chart-input="aggregation" name="charts[{{ $index }}][aggregation]">
                                @foreach(['SUM' => 'Suma', 'AVG' => 'Promedio', 'MIN' => 'Mínimo', 'MAX' => 'Máximo', 'COUNT' => 'Conteo', 'COUNT_DISTINCT' => 'Conteo único'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($chart['aggregation'] ?? 'SUM') === $value)>{{ $label }}</option>
                                @endforeach
                            </select></label>
                            <label class="field md:col-span-2 xl:col-span-5"><span>Dimensión o agrupación</span><select data-chart-input="dimension" data-column-select data-optional name="charts[{{ $index }}][dimension]" data-value="{{ $chart['dimension'] ?? '' }}"></select><small>Puede quedar vacía para un indicador único.</small></label>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold">Guardar estructura</h2>
                <p class="text-sm text-slate-500">Los cambios quedarán en SIID antes de crear o actualizar los objetos en Superset.</p>
            </div>
            <button class="btn-primary">Guardar y continuar</button>
        </section>
    </form>

    <section class="panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="eyebrow">Sincronización</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-950">Crear o actualizar en Superset</h2>
                @if($dashboard->superset_synced_at)
                    <p class="mt-1 text-sm text-emerald-700">Sincronizado {{ $dashboard->superset_synced_at->diffForHumans() }}.</p>
                @else
                    <p class="mt-1 text-sm text-amber-700">Hay cambios locales pendientes de enviar al motor analítico.</p>
                @endif
                @if($dashboard->superset_sync_error)
                    <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $dashboard->superset_sync_error }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.dashboards.superset.sync', $dashboard) }}">
                    @csrf
                    <button class="btn-primary" @disabled(! $supersetConfigured)>{{ $dashboard->superset_dashboard_id ? 'Actualizar en Superset' : 'Crear en Superset' }}</button>
                </form>
                @if($dashboard->superset_synced_at)
                    <form method="post" action="{{ route('admin.dashboards.submit', $dashboard) }}">
                        @csrf
                        <button class="btn-secondary">Enviar a revisión</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if($dashboard->superset_embedded_uuid && $supersetConfigured)
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><p class="eyebrow">Previsualización</p><h2 class="mt-1 text-xl font-semibold">Resultado en Superset</h2></div>
                <a class="btn-secondary" href="{{ rtrim(config('superset.url'), '/').'/superset/dashboard/'.$dashboard->superset_dashboard_id.'/' }}" target="_blank" rel="noopener">Modo avanzado ↗</a>
            </div>
            <div
                class="mt-5 min-h-[620px] overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                data-superset
                data-token-url="{{ route('admin.dashboards.superset.token', $dashboard) }}"
                data-dominio="{{ config('superset.url') }}"
            ><div class="p-6 text-sm text-slate-500">Cargando previsualización…</div></div>
        </section>
    @endif

    <details class="panel">
        <summary class="cursor-pointer font-semibold">Colaboradores</summary>
        <form method="post" action="{{ route('admin.dashboards.collaborators.update', $dashboard) }}" class="mt-4 grid gap-2 md:grid-cols-2">
            @csrf
            @method('PUT')
            @foreach($users as $user)
                <label class="check"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked($dashboard->collaborators->contains($user)) @disabled($dashboard->owner_id === $user->id)>{{ $user->name }} <small>{{ $user->role->label() }}</small></label>
            @endforeach
            <button class="btn-secondary md:col-span-2">Guardar colaboradores</button>
        </form>
    </details>
</div>

<template data-chart-template>
    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4" data-chart>
        <input type="hidden" data-chart-input="key">
        <div class="flex items-center justify-between gap-3"><strong data-chart-number></strong><button type="button" class="text-sm font-semibold text-red-700" data-remove-chart>Quitar</button></div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="field xl:col-span-2"><span>Título</span><input data-chart-input="title" required maxlength="120"></label>
            <label class="field"><span>Visualización</span><select data-chart-input="type"><option value="big_number">Indicador</option><option value="bar">Barras</option><option value="line">Líneas</option><option value="pie">Torta</option><option value="table">Tabla</option></select></label>
            <label class="field"><span>Métrica</span><select data-chart-input="metric" data-column-select required></select></label>
            <label class="field"><span>Agregación</span><select data-chart-input="aggregation"><option value="SUM">Suma</option><option value="AVG">Promedio</option><option value="MIN">Mínimo</option><option value="MAX">Máximo</option><option value="COUNT">Conteo</option><option value="COUNT_DISTINCT">Conteo único</option></select></label>
            <label class="field md:col-span-2 xl:col-span-5"><span>Dimensión o agrupación</span><select data-chart-input="dimension" data-column-select data-optional></select><small>Puede quedar vacía para un indicador único.</small></label>
        </div>
    </article>
</template>

<script>
    (() => {
        const root = document.querySelector('[data-superset-wizard]');
        if (!root) return;
        const dataset = root.querySelector('[data-dataset]');
        const list = root.querySelector('[data-chart-list]');
        const template = root.querySelector('[data-chart-template]');

        const columns = () => {
            const option = dataset.options[dataset.selectedIndex];
            try { return JSON.parse(option?.dataset.columns || '[]'); } catch { return []; }
        };
        const refreshColumns = () => {
            const values = columns();
            root.querySelectorAll('[data-column-select]').forEach((select) => {
                const selected = select.value || select.dataset.value || '';
                select.innerHTML = select.hasAttribute('data-optional') ? '<option value="">Sin dimensión</option>' : '<option value="">Seleccione una columna</option>';
                values.forEach((value) => select.add(new Option(value, value, false, value === selected)));
                delete select.dataset.value;
            });
        };
        const reindex = () => {
            list.querySelectorAll('[data-chart]').forEach((chart, index) => {
                chart.querySelector('[data-chart-number]').textContent = `Gráfico ${index + 1}`;
                chart.querySelectorAll('[data-chart-input]').forEach((input) => {
                    input.name = `charts[${index}][${input.dataset.chartInput}]`;
                });
                chart.querySelector('[data-remove-chart]').disabled = list.querySelectorAll('[data-chart]').length === 1;
            });
        };
        root.addEventListener('click', (event) => {
            if (event.target.closest('[data-add-chart]')) {
                if (list.querySelectorAll('[data-chart]').length >= 12) return;
                list.append(template.content.cloneNode(true));
                refreshColumns();
                reindex();
            }
            const remove = event.target.closest('[data-remove-chart]');
            if (remove && list.querySelectorAll('[data-chart]').length > 1) {
                remove.closest('[data-chart]').remove();
                reindex();
            }
        });
        dataset.addEventListener('change', refreshColumns);
        refreshColumns();
        reindex();
    })();
</script>
@endsection
