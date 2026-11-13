@extends('layouts.app', ['title' => 'Analítica y tableros · SIID 2.0'])

@section('content')
<div class="space-y-7">
    <header><p class="eyebrow">Constructor básico · Motor Superset</p><h1 class="page-title">Analítica y tableros</h1><p class="page-subtitle">Cree y publique tableros desde SIID. Superset se mantiene como el motor de analítica y el modo avanzado.</p></header>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="panel"><p class="eyebrow">Datasets preparados</p><strong class="mt-2 block text-3xl text-slate-950">{{ $datasets->count() }}</strong><span class="text-sm text-slate-500">Disponibles para el constructor</span></article>
        <article class="panel"><p class="eyebrow">Tableros Superset</p><strong class="mt-2 block text-3xl text-slate-950">{{ $dashboards->filter->usesSuperset()->count() }}</strong><span class="text-sm text-slate-500">Creados desde el nuevo flujo</span></article>
        <article class="panel"><p class="eyebrow">Motor analítico</p><strong class="mt-2 block text-lg {{ $supersetConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $supersetConfigured ? 'Superset configurado' : 'Pendiente de configurar' }}</strong><span class="text-sm text-slate-500">{{ $supersetConfigured ? 'Listo para sincronización' : 'Puede preparar borradores localmente' }}</span></article>
    </section>

    <details class="panel action-disclosure" @if($errors->any()) open @endif>
        <summary><div><h2>Crear tablero en Superset</h2><span>Configure dataset, métricas, dimensiones, gráficos y filtros mediante el asistente.</span></div><strong>Abrir formulario</strong></summary>
        <form method="post" action="{{ route('admin.dashboards.store') }}" class="mt-5 grid gap-4 md:grid-cols-2" data-slug-suggestion>@csrf
            <label class="field"><span>Nombre</span><input name="name" required maxlength="120" data-slug-source></label>
            <label class="field"><span>Identificador URL</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" data-slug-target></label>
            <label class="field md:col-span-2"><span>Descripción</span><textarea name="description" maxlength="1000"></textarea></label>
            <input type="hidden" name="template" value="superset_basic">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"><strong>Flujo unificado.</strong><br>El tablero se prepara en SIID y se sincroniza con Superset sin crear copias desconectadas.</div>
            <button class="btn-primary self-end">Crear y abrir asistente</button>
        </form>
    </details>

    <details class="panel action-disclosure">
        <summary><div><h2>Datasets disponibles</h2><span>Catálogo único de datos preparados para Superset.</span></div><strong>{{ $datasets->count() }} dataset(s)</strong></summary>
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            @forelse($datasets as $dataset)
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">{{ $dataset['domain'] }}</p><h3 class="mt-1 font-semibold text-slate-950">{{ $dataset['name'] }}</h3></div><span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $dataset['columns'] }} columnas</span></div>
                    <p class="mt-2 text-sm text-slate-600">{{ $dataset['description'] }}</p>
                    <p class="mt-3 text-xs text-slate-500">Fuente: {{ $dataset['source'] }}</p>
                    <p class="text-xs text-slate-500">Corte: {{ $dataset['cutoff'] }}</p>
                </article>
            @empty
                <p class="text-sm text-slate-500 lg:col-span-2">Todavía no hay datasets preparados. La carga general de CSV, XLSX y URL se integrará en esta misma pantalla.</p>
            @endforelse
        </div>
    </details>

    <section class="space-y-4"><div><h2 class="text-xl font-semibold">Mis tableros</h2><p class="text-sm text-slate-500">{{ $dashboards->count() }} configurados. Los anteriores permanecen disponibles como legado.</p></div>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($dashboards as $dashboard)
                <article class="panel"><div class="flex items-start justify-between gap-4"><div><div class="mb-2 flex flex-wrap gap-2"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $dashboard->engine->label() }}</span></div><h3 class="text-lg font-semibold">{{ $dashboard->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $dashboard->description }}</p></div><span class="status @if($dashboard->isPublished()) status-approved @endif">{{ $dashboard->status->label() }}</span></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500"><div><dt>Responsable</dt><dd class="font-semibold text-slate-700">{{ $dashboard->owner?->name ?? 'Sin responsable' }}</dd></div><div><dt>Versión pública</dt><dd class="font-semibold text-slate-700">{{ $dashboard->published_version ?? '—' }}</dd></div></dl>
                    <div class="mt-5 flex flex-wrap gap-2"><a class="btn-primary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'tablero', 'record' => $dashboard->slug]) }}">{{ $dashboard->usesSuperset() ? 'Abrir asistente' : 'Abrir legado' }}</a>@if(! $dashboard->usesSuperset())<a class="btn-secondary" href="{{ route('admin.dashboards.preview', $dashboard) }}" target="_blank">Vista previa</a>@elseif($dashboard->superset_dashboard_id && filled(config('superset.url')))<a class="btn-secondary" href="{{ rtrim(config('superset.url'), '/').'/superset/dashboard/'.$dashboard->superset_dashboard_id.'/' }}" target="_blank" rel="noopener">Abrir en Superset</a>@endif @if($dashboard->isPublished())<a class="btn-secondary" href="{{ route('dashboards.embed', $dashboard) }}" target="_blank">Abrir público</a>@endif</div>
                    @if($dashboard->isPublished())<details class="mt-4"><summary class="cursor-pointer text-xs font-semibold text-emerald-700">Código para embeber</summary><textarea class="mt-2 font-mono text-xs" rows="3" readonly>&lt;iframe src="{{ route('dashboards.embed', $dashboard) }}" title="{{ $dashboard->name }}" width="100%" height="720" loading="lazy"&gt;&lt;/iframe&gt;</textarea></details>@endif
                </article>
            @empty <div class="panel lg:col-span-2 text-sm text-slate-500">Todavía no hay dashboards. Cree el primero arriba.</div>@endforelse
        </div>
    </section>
</div>
@endsection
