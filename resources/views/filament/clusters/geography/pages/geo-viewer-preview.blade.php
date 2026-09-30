<x-filament-panels::page>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-950">{{ $geoViewer->name }}</p>
                <p class="text-xs text-slate-500">La previsualización usa la configuración real del borrador sin publicarlo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ \App\Filament\Clusters\Geography\Pages\ManageGeoViewer::getUrl(['geoViewer' => $geoViewer]) }}">Configurar</a>
                <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ route('admin.geo-viewers.preview', $geoViewer) }}" target="_blank" rel="noopener">Abrir aparte ↗</a>
            </div>
        </div>
        <iframe class="block min-h-[36rem] w-full border-0" style="height: 72vh" src="{{ route('admin.geo-viewers.preview', $geoViewer) }}" title="Previsualización de {{ $geoViewer->name }}"></iframe>
    </section>
</x-filament-panels::page>
