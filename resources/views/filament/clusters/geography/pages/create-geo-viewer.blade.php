<x-filament-panels::page>
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <strong>No fue posible crear el geovisor:</strong>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="siid-hero">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Inteligencia geográfica</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Crear un geovisor</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Defina la identidad y posición inicial. En el siguiente paso podrá escoger las capas, previsualizar y enviar a revisión.</p>
    </section>

    <form method="post" action="{{ route('admin.geo-viewers.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-slug-suggestion>
        @csrf
        <input type="hidden" name="management_panel" value="1">
        <input type="hidden" name="status" value="draft">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="siid-field"><span>Nombre</span><input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Gestión del riesgo" data-slug-source></label>
            <label class="siid-field"><span>Identificador URL</span><input name="slug" value="{{ old('slug') }}" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="gestion-del-riesgo" data-slug-target><small class="text-xs text-slate-500">Solo minúsculas, números y guiones.</small></label>
            <label class="siid-field md:col-span-2"><span>Descripción</span><textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea></label>
            <label class="siid-field"><span>Latitud inicial</span><input type="number" step="0.0000001" name="center_latitude" value="{{ old('center_latitude', '4.1500000') }}" required></label>
            <label class="siid-field"><span>Longitud inicial</span><input type="number" step="0.0000001" name="center_longitude" value="{{ old('center_longitude', '-73.6300000') }}" required></label>
            <label class="siid-field"><span>Zoom inicial</span><input type="number" name="initial_zoom" value="{{ old('initial_zoom', 8) }}" min="0" max="22" required></label>
        </div>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <a class="siid-button siid-button-secondary" href="{{ \App\Filament\Resources\GeoViewerResource::getUrl('index') }}">Cancelar</a>
            <button class="siid-button siid-button-primary">Crear y configurar capas</button>
        </div>
    </form>

    @script
    <script>
        const form = $wire.$el.querySelector('[data-slug-suggestion]');
        const source = form?.querySelector('[data-slug-source]');
        const target = form?.querySelector('[data-slug-target]');
        let slugWasEdited = Boolean(target?.value);

        target?.addEventListener('input', () => slugWasEdited = true);
        source?.addEventListener('input', () => {
            if (slugWasEdited || !target) return;
            target.value = source.value.normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    </script>
    @endscript
</x-filament-panels::page>
