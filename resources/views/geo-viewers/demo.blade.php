<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demostración del geovisor · Gobernación del Meta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <header class="border-b border-emerald-800 bg-emerald-950 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <div class="flex items-center gap-4">
                <div class="grid h-12 w-12 place-items-center rounded-full border border-white/30 bg-white/10 text-lg font-bold">M</div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.18em] text-emerald-200">Gobernación del Meta</p>
                    <p class="mt-1 text-lg font-semibold">Sistema Integral de Información Departamental</p>
                </div>
            </div>
            <nav class="flex flex-wrap gap-1 text-sm text-emerald-50" aria-label="Navegación de demostración">
                <span class="rounded-lg px-3 py-2">Inicio</span>
                <span class="rounded-lg px-3 py-2">Indicadores</span>
                <span class="rounded-lg bg-white/15 px-3 py-2 font-semibold">Geovisor</span>
                <span class="rounded-lg px-3 py-2">Datos abiertos</span>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6" data-iframe-demo>
        <div class="mb-6 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.18em] text-emerald-700">Página de prueba</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Información geográfica del departamento</h1>
                <p class="mt-2 max-w-3xl text-slate-600">Esta vista reproduce la integración que podrá incorporarse al portal oficial. El contenido del mapa funciona de manera independiente dentro del recuadro.</p>
            </div>
            @if($selectedViewer)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <strong>{{ $selectedViewer->layers_count }}</strong> {{ $selectedViewer->layers_count === 1 ? 'capa disponible' : 'capas disponibles' }}
                </div>
            @endif
        </div>

        @if($selectedViewer)
            <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-[minmax(240px,1fr)_auto_auto] lg:items-end">
                    <form method="get" action="{{ route('geo-viewers.demo') }}">
                        <label class="field">
                            <span>Contenido que desea consultar</span>
                            <select name="visor" data-iframe-viewer-select>
                                @foreach($viewers as $viewer)
                                    <option value="{{ $viewer->slug }}" @selected($viewer->is($selectedViewer))>{{ $viewer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>

                    <div>
                        <p class="mb-1.5 text-sm font-medium text-slate-700">Vista del dispositivo</p>
                        <div class="flex rounded-xl border border-slate-300 bg-slate-50 p-1" role="group" aria-label="Ancho de demostración">
                            <button type="button" class="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm" data-iframe-width="100%">Escritorio</button>
                            <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600" data-iframe-width="820px">Tableta</button>
                            <button type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600" data-iframe-width="390px">Móvil</button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary" data-iframe-reload>Recargar</button>
                        <button type="button" class="btn-secondary" data-iframe-fullscreen>Pantalla completa</button>
                        <a class="btn-secondary" href="{{ route('geo-viewers.embed', $selectedViewer) }}" target="_blank" rel="noopener">Abrir aparte</a>
                    </div>
                </div>
            </section>

            <section class="overflow-auto rounded-2xl border border-slate-300 bg-slate-200/70 p-3 shadow-xl" data-iframe-stage>
                <div class="mx-auto overflow-hidden rounded-xl bg-white shadow-lg transition-[max-width] duration-300" data-iframe-frame-container style="max-width: 100%;">
                    <iframe
                        src="{{ route('geo-viewers.embed', $selectedViewer) }}"
                        title="{{ $selectedViewer->name }}"
                        width="100%"
                        height="720"
                        loading="eager"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                        class="block w-full border-0"
                        data-iframe-preview
                    ></iframe>
                </div>
            </section>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['Mover y acercar', 'Arrastre el mapa y utilice la rueda o los botones de zoom.'],
                    ['Activar capas', 'Encienda o apague información desde el panel lateral.'],
                    ['Consultar elementos', 'Pulse puntos, líneas o polígonos para abrir su información.'],
                    ['Adaptación automática', 'Pruebe los tamaños para observar la respuesta en distintos equipos.'],
                ] as [$title, $description])
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="font-semibold">{{ $title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950">
                <h2 class="font-semibold">Todavía no hay un geovisor publicado</h2>
                <p class="mt-2 text-sm">Apruebe al menos un geovisor desde el panel administrativo y vuelva a cargar esta página.</p>
                <a class="btn-secondary mt-4" href="{{ route('admin.geo-viewers.index') }}">Ir a geovisores</a>
            </section>
        @endif
    </main>

    <footer class="mt-8 border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 text-sm text-slate-500 sm:px-6">Demostración local de integración · Sistema Integral de Información Departamental</div>
    </footer>
</body>
</html>
