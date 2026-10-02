@extends('layouts.app')

@section('content')
<div class="space-y-7">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <a href="{{ \App\Filament\Pages\Dashboards::getUrl() }}" class="text-sm font-semibold text-emerald-700">← Dashboards</a>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Indicadores</h1>
            <p class="mt-2 max-w-3xl text-slate-600">Cree fichas públicas de indicadores con su documento técnico oficial, ruta pública y QR para campañas de difusión.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Nuevo indicador</p>
                <h2>Ficha oficial y micrositio público</h2>
            </div>
        </div>

        <form method="post" action="{{ route('admin.indicators.store') }}" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2">
            @csrf
            <label class="field">
                <span>Nombre del indicador</span>
                <input name="name" value="{{ old('name') }}" required placeholder="Ej. Evaluaciones Agropecuarias Municipales">
            </label>
            <label class="field">
                <span>Identificador para URL</span>
                <input name="slug" value="{{ old('slug') }}" required placeholder="evaluaciones-agropecuarias-municipales">
            </label>
            <label class="field">
                <span>Sector o temática</span>
                <input name="sector" value="{{ old('sector') }}" placeholder="Agricultura, salud, educación...">
            </label>
            <label class="field">
                <span>Unidad</span>
                <input name="unit" value="{{ old('unit') }}" placeholder="Personas, hectáreas, toneladas...">
            </label>
            <label class="field">
                <span>Periodicidad</span>
                <input name="periodicity" value="{{ old('periodicity') }}" placeholder="Anual, mensual, trimestral...">
            </label>
            <label class="field">
                <span>Ficha técnica oficial (PDF)</span>
                <input type="file" name="technical_sheet" accept="application/pdf" required>
                <small>Máximo 20 MB. Este documento se mostrará en la página pública del indicador.</small>
            </label>
            <label class="field lg:col-span-2">
                <span>Resumen público</span>
                <textarea name="summary" rows="3" placeholder="Explique brevemente qué mide el indicador y por qué es importante.">{{ old('summary') }}</textarea>
            </label>
            <label class="field lg:col-span-2">
                <span>Descripción</span>
                <textarea name="description" rows="4" placeholder="Contexto metodológico, fuente, alcance territorial o notas de lectura.">{{ old('description') }}</textarea>
            </label>
            <div class="lg:col-span-2">
                <button class="btn-primary" type="submit">Crear indicador</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Bandeja</p>
                <h2>Indicadores en preparación y publicados</h2>
            </div>
            <span class="badge">{{ $indicators->count() }} indicador(es)</span>
        </div>

        @forelse($indicators as $indicator)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xl font-bold text-slate-950">{{ $indicator->name }}</h3>
                            <span class="badge">{{ $indicator->status->label() }}</span>
                            @if($indicator->sector)<span class="badge">{{ $indicator->sector }}</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $indicator->summary ?: 'Sin resumen público todavía.' }}</p>
                        <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
                            <span>URL: <code>/indicadores/{{ $indicator->slug }}</code></span>
                            @if($indicator->published_at)<span>Publicado: {{ $indicator->published_at->format('d/m/Y H:i') }}</span>@endif
                            @if($indicator->technical_sheet_original_name)<span>Ficha: {{ $indicator->technical_sheet_original_name }}</span>@endif
                        </div>
                    </div>

                    @if($indicator->isPublished())
                        <div class="w-full max-w-xs rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">QR público</p>
                            <img class="mt-3 h-32 w-32 rounded-xl bg-white p-2" alt="QR de {{ $indicator->name }}" src="{{ (new \chillerlan\QRCode\QRCode)->render(route('indicators.show', $indicator)) }}">
                            <a class="mt-3 inline-flex text-sm font-semibold text-emerald-800 underline" href="{{ route('indicators.show', $indicator) }}" target="_blank" rel="noopener">Abrir micrositio ↗</a>
                        </div>
                    @endif
                </div>

                @if(! $indicator->isPublished())
                    <details class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer font-semibold text-slate-800">Editar datos básicos y ficha técnica</summary>
                        <form method="post" action="{{ route('admin.indicators.update', $indicator) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 lg:grid-cols-2">
                            @csrf
                            @method('patch')
                            <label class="field">
                                <span>Nombre</span>
                                <input name="name" value="{{ old("indicators.{$indicator->id}.name", $indicator->name) }}" required>
                            </label>
                            <label class="field">
                                <span>Identificador</span>
                                <input name="slug" value="{{ old("indicators.{$indicator->id}.slug", $indicator->slug) }}" required>
                            </label>
                            <label class="field">
                                <span>Sector o temática</span>
                                <input name="sector" value="{{ old("indicators.{$indicator->id}.sector", $indicator->sector) }}">
                            </label>
                            <label class="field">
                                <span>Unidad</span>
                                <input name="unit" value="{{ old("indicators.{$indicator->id}.unit", $indicator->unit) }}">
                            </label>
                            <label class="field">
                                <span>Periodicidad</span>
                                <input name="periodicity" value="{{ old("indicators.{$indicator->id}.periodicity", $indicator->periodicity) }}">
                            </label>
                            <label class="field">
                                <span>Reemplazar ficha técnica PDF</span>
                                <input type="file" name="technical_sheet" accept="application/pdf">
                            </label>
                            <label class="field lg:col-span-2">
                                <span>Resumen público</span>
                                <textarea name="summary" rows="3">{{ old("indicators.{$indicator->id}.summary", $indicator->summary) }}</textarea>
                            </label>
                            <label class="field lg:col-span-2">
                                <span>Descripción</span>
                                <textarea name="description" rows="4">{{ old("indicators.{$indicator->id}.description", $indicator->description) }}</textarea>
                            </label>
                            <div class="lg:col-span-2">
                                <button class="btn-secondary" type="submit">Guardar cambios</button>
                            </div>
                        </form>
                    </details>
                @endif

                <div class="mt-4 flex flex-wrap gap-3">
                    @if(! $indicator->isPublished() && $indicator->status !== \App\Enums\IndicatorStatus::PendingReview && $indicator->canEdit(auth()->user()))
                        <form method="post" action="{{ route('admin.indicators.submit', $indicator) }}">
                            @csrf
                            <button class="btn-secondary" type="submit">Enviar a revisión</button>
                        </form>
                    @endif
                    @if($indicator->status === \App\Enums\IndicatorStatus::PendingReview && auth()->user()->canApproveDashboards())
                        <form method="post" action="{{ route('admin.indicators.publication.store', $indicator) }}">
                            @csrf
                            <button class="btn-primary" type="submit">Publicar</button>
                        </form>
                    @endif
                    @if($indicator->isPublished())
                        <a class="btn-secondary" href="{{ route('indicators.technical-sheet', $indicator) }}" target="_blank" rel="noopener">Ver ficha técnica</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">Todavía no hay indicadores creados.</div>
        @endforelse
    </section>
</div>
@endsection
