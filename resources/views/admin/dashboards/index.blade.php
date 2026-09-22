@extends('layouts.app', ['title' => 'Dashboards · SIID 2.0'])

@section('content')
<div class="space-y-7">
    <header><p class="eyebrow">Análisis territorial</p><h1 class="page-title">Dashboards interactivos</h1><p class="page-subtitle">Combine mapas, indicadores, tablas y gráficas sin modificar las capas publicadas.</p></header>

    <details class="panel action-disclosure" @if($errors->any()) open @endif>
        <summary><div><h2>Crear dashboard</h2><span>Comience vacío o use la plantilla poblacional territorial.</span></div><strong>Abrir formulario</strong></summary>
        <form method="post" action="{{ route('admin.dashboards.store') }}" class="mt-5 grid gap-4 md:grid-cols-2" data-slug-suggestion>@csrf
            <label class="field"><span>Nombre</span><input name="name" required maxlength="120" data-slug-source></label>
            <label class="field"><span>Identificador URL</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" data-slug-target></label>
            <label class="field md:col-span-2"><span>Descripción</span><textarea name="description" maxlength="1000"></textarea></label>
            <label class="field"><span>Punto de partida</span><select name="template"><option value="population">Perfil poblacional territorial</option><option value="blank">Dashboard vacío</option></select></label>
            <button class="btn-primary self-end">Crear y configurar</button>
        </form>
    </details>

    <section class="space-y-4"><div><h2 class="text-xl font-semibold">Mis dashboards</h2><p class="text-sm text-slate-500">{{ $dashboards->count() }} configurados.</p></div>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($dashboards as $dashboard)
                <article class="panel"><div class="flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold">{{ $dashboard->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $dashboard->description }}</p></div><span class="status @if($dashboard->isPublished()) status-approved @endif">{{ $dashboard->status->label() }}</span></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500"><div><dt>Responsable</dt><dd class="font-semibold text-slate-700">{{ $dashboard->owner?->name ?? 'Sin responsable' }}</dd></div><div><dt>Versión pública</dt><dd class="font-semibold text-slate-700">{{ $dashboard->published_version ?? '—' }}</dd></div></dl>
                    <div class="mt-5 flex flex-wrap gap-2"><a class="btn-primary" href="{{ route('admin.dashboards.edit', $dashboard) }}">Abrir constructor</a><a class="btn-secondary" href="{{ route('admin.dashboards.preview', $dashboard) }}" target="_blank">Vista previa</a>@if($dashboard->isPublished())<a class="btn-secondary" href="{{ route('dashboards.embed', $dashboard) }}" target="_blank">Abrir público</a>@endif</div>
                    @if($dashboard->isPublished())<details class="mt-4"><summary class="cursor-pointer text-xs font-semibold text-emerald-700">Código para embeber</summary><textarea class="mt-2 font-mono text-xs" rows="3" readonly>&lt;iframe src="{{ route('dashboards.embed', $dashboard) }}" title="{{ $dashboard->name }}" width="100%" height="720" loading="lazy"&gt;&lt;/iframe&gt;</textarea></details>@endif
                </article>
            @empty <div class="panel lg:col-span-2 text-sm text-slate-500">Todavía no hay dashboards. Cree el primero arriba.</div>@endforelse
        </div>
    </section>
</div>
@endsection
