@extends('layouts.app')

@section('content')
<div class="space-y-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Datos.gov.co</p>
            <h1 class="page-title">Crear visor desde Datos Abiertos</h1>
            <p class="page-subtitle">Pegue la portada oficial. SIID comprobará la geografía, propondrá la configuración y conservará una copia válida para el portal.</p>
        </div>
        <a class="btn-secondary" href="{{ route('admin.geo-viewers.index') }}">Volver a visores</a>
    </div>

    @if($canCreate)
    <section class="panel" data-open-data-wizard
        data-analyze-url="{{ route('admin.open-data-sources.analyze') }}"
        data-store-url="{{ route('admin.open-data-sources.store') }}">
        <ol class="open-data-steps" aria-label="Pasos del asistente">
            <li class="is-active" data-wizard-step-label="1"><strong>1</strong><span>Pegar enlace</span></li>
            <li data-wizard-step-label="2"><strong>2</strong><span>Analizar</span></li>
            <li data-wizard-step-label="3"><strong>3</strong><span>Configurar</span></li>
            <li data-wizard-step-label="4"><strong>4</strong><span>Guardar borrador</span></li>
        </ol>

        <div class="mt-6" data-wizard-step="1">
            <label class="field"><span>Portada del conjunto en Datos.gov.co</span><input type="url" data-open-data-url placeholder="https://www.datos.gov.co/.../abcd-1234" required><small>También se aceptan enlaces /d/{id} y direcciones API oficiales. No se consultan otros dominios.</small></label>
            <div class="mt-4 flex items-center gap-3"><button type="button" class="btn-primary" data-open-data-analyze>Analizar conjunto</button><span class="text-sm text-slate-500" data-open-data-status></span></div>
        </div>

        <form class="mt-6 hidden space-y-6" data-open-data-form>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5" data-open-data-metadata></div>
            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="field"><span>Nombre de la capa</span><input name="name" maxlength="120" required data-open-data-name></label>
                        <label class="field"><span>Identificador estable</span><input name="slug" maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required data-open-data-slug></label>
                        <label class="field sm:col-span-2"><span>Descripción</span><textarea name="description" rows="3" maxlength="2000" data-open-data-description></textarea></label>
                        <label class="field sm:col-span-2"><span>Atribución</span><input name="attribution" maxlength="500" required data-open-data-attribution></label>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <h3 class="font-semibold">Representación y análisis</h3>
                        <p class="mt-1 text-sm text-slate-500" data-open-data-geography></p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="field"><span>Indicador numérico</span><select name="metric_field" data-open-data-metric><option value="">Ninguno</option></select></label>
                            <label class="field"><span>Operación</span><select name="aggregation"><option value="count">Conteo</option><option value="sum">Suma</option><option value="avg">Promedio</option><option value="min">Mínimo</option><option value="max">Máximo</option></select></label>
                            <label class="field"><span>Campo de título</span><select name="label_field" data-open-data-label><option value="">Automático</option></select></label>
                            <label class="field"><span>Paleta</span><select name="palette"><option value="green">Verde institucional</option><option value="blue">Azul</option><option value="orange">Naranja</option><option value="purple">Morado</option><option value="red">Rojo</option></select></label>
                            <label class="field"><span>Color sin datos</span><input type="color" name="no_data_color" value="#d1d5db"></label>
                            <label class="field"><span>Transparencia</span><input type="range" name="opacity" min="0.1" max="1" value="0.75" step="0.05"></label>
                        </div>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <fieldset><legend class="font-semibold">Ficha emergente <small class="font-normal text-slate-500">máx. 12</small></legend><div class="mt-3 max-h-64 space-y-2 overflow-auto rounded-xl border border-slate-200 p-3" data-open-data-popup-fields></div></fieldset>
                        <fieldset><legend class="font-semibold">Filtros <small class="font-normal text-slate-500">máx. 4</small></legend><div class="mt-3 max-h-64 space-y-2 overflow-auto rounded-xl border border-slate-200 p-3" data-open-data-filters></div></fieldset>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <h3 class="font-semibold">Destino</h3>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="field"><span>Resultado</span><select name="target" data-open-data-target><option value="new">Crear visor nuevo</option><option value="existing">Agregar a visor borrador</option></select></label>
                            <label class="field hidden" data-existing-viewer><span>Visor borrador</span><select name="viewer_id"><option value="">Seleccione…</option>@foreach($editableViewers as $viewer)<option value="{{ $viewer->id }}">{{ $viewer->name }}</option>@endforeach</select></label>
                            <label class="field" data-new-viewer><span>Nombre del visor</span><input name="viewer_name" maxlength="120" data-viewer-name></label>
                            <label class="field" data-new-viewer><span>Identificador del visor</span><input name="viewer_slug" maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" data-viewer-slug></label>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="sticky top-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between"><h3 class="font-semibold">Vista previa real</h3><span class="text-xs text-slate-500" data-open-data-preview-count></span></div>
                        <div class="mt-3 h-[520px] overflow-hidden rounded-xl border border-slate-200 bg-white" data-open-data-preview-map></div>
                        <div class="mt-4 text-sm" data-open-data-diagnostics></div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="url" data-open-data-hidden-url>
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button class="btn-primary" type="submit" data-open-data-save>Crear borrador</button><span class="text-sm text-slate-500" data-open-data-save-status></span></div>
        </form>
    </section>
    @endif

    <section class="space-y-4">
        <div><h2 class="text-xl font-semibold">Fuentes conectadas</h2><p class="text-sm text-slate-500">La última versión válida continúa disponible aunque la fuente externa falle.</p></div>
        @forelse($sources as $source)
            <article class="panel">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><div class="flex items-center gap-2"><h3 class="text-lg font-semibold">{{ $source->name }}</h3><span class="status-pill">{{ str_replace('_', ' ', $source->status) }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $source->dataset_id }} · {{ $source->owner?->name ?? 'Sistema' }} · Última versión válida: {{ $source->last_success_at?->format('d/m/Y H:i') ?? 'pendiente' }}</p></div>
                    <div class="flex flex-wrap gap-2"><a class="btn-secondary" href="{{ $source->landing_page_url }}" target="_blank" rel="noopener">Portada oficial ↗</a><a class="btn-secondary" href="{{ route('admin.open-data-sources.preview', $source) }}" target="_blank" rel="noopener">Ver GeoJSON</a></div>
                </div>
                @if($source->last_error)<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $source->last_error }}</p>@endif
                @if($source->owner_id === auth()->id() || auth()->user()->isAdmin())
                    <form method="post" action="{{ route('admin.open-data-sources.submit', $source) }}" class="mt-4">@csrf<button class="btn-primary" type="submit">Enviar a revisión</button></form>
                @endif
            </article>
        @empty
            <div class="panel text-sm text-slate-500">Todavía no hay fuentes abiertas conectadas.</div>
        @endforelse
    </section>
</div>
@endsection
