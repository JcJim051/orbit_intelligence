<x-filament-panels::page>
    <section class="siid-hero">
        <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">Piloto modular</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Inteligencia geográfica</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">Una bandeja única para recibir información, preparar conjuntos, configurar visores y controlar la publicación territorial.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            @if(auth()->user()->isAdmin())<a class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'cargas-qgis']) }}#nueva-importacion">Autorizar carga QGIS</a>@endif
            @if(auth()->user()->canManageOpenDataSources())<a class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'fuentes-abiertas']) }}">Conectar Datos Abiertos</a>@endif
            <a class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ route('geo-viewers.demo') }}" target="_blank" rel="noopener">Ver portal ciudadano ↗</a>
        </div>
    </section>

    <section aria-labelledby="sig-summary-heading">
        <h2 id="sig-summary-heading" class="sr-only">Resumen SIG</h2>
        <div class="siid-stat-grid">
            @foreach($stats as $stat)
                <article class="siid-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        <article class="rounded-2xl border border-red-200 bg-white p-5">
            <h2 class="font-semibold text-slate-950">Requieren acción</h2>
            <p class="mt-1 text-sm text-slate-500">Importaciones cuyo proceso falló.</p>
            <div class="mt-4 space-y-3">
                @forelse($failedImports as $import)
                    <a class="block rounded-xl bg-red-50 p-3 hover:bg-red-100" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'cargas-qgis']) }}#import-{{ $import->id }}"><strong class="block text-sm text-red-900">{{ $import->name }}</strong><span class="mt-1 block text-xs text-red-700">{{ str($import->failure_message)->limit(90) }}</span></a>
                @empty
                    <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">No hay fallos de importación pendientes.</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-amber-200 bg-white p-5">
            <h2 class="font-semibold text-slate-950">En preparación</h2>
            <p class="mt-1 text-sm text-slate-500">Conjuntos en borrador antes de publicación.</p>
            <div class="mt-4 space-y-3">
                @forelse($draftDatasets as $dataset)
                    <a class="block rounded-xl bg-amber-50 p-3 hover:bg-amber-100" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'catalogo-datos']) }}#dataset-{{ $dataset->slug }}"><strong class="block text-sm text-amber-950">{{ $dataset->name }}</strong><span class="mt-1 block text-xs text-amber-700">{{ $dataset->sector }} · {{ $dataset->geometry_type }}</span></a>
                @empty
                    <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">No hay conjuntos en borrador.</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-blue-200 bg-white p-5">
            <h2 class="font-semibold text-slate-950">Pendientes de aprobación</h2>
            <p class="mt-1 text-sm text-slate-500">Geovisores enviados a revisión.</p>
            <div class="mt-4 space-y-3">
                @forelse($reviewViewers as $viewer)
                    <a class="block rounded-xl bg-blue-50 p-3 hover:bg-blue-100" href="{{ \App\Filament\Clusters\Geography\Pages\ManageGeoViewer::getUrl(['geoViewer' => $viewer]) }}"><strong class="block text-sm text-blue-950">{{ $viewer->name }}</strong><span class="mt-1 block text-xs text-blue-700">Revisar configuración y capas</span></a>
                @empty
                    <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">No hay geovisores pendientes.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-filament-panels::page>
