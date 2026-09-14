<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $geoViewer->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="geo-viewer-embed">
    <div class="geo-viewer-shell" data-geo-viewer data-config-url="{{ $configUrl }}">
        <aside class="geo-viewer-sidebar">
            <div class="border-b border-slate-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-[.16em] text-emerald-700">Gobernación del Meta · SIID</p>
                <h1 class="mt-2 text-xl font-semibold" data-geo-viewer-title>{{ $geoViewer->name }}</h1>
                <p class="mt-2 text-sm text-slate-500" data-geo-viewer-description>{{ $geoViewer->description }}</p>
            </div>
            <div class="grow overflow-y-auto p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capas temáticas disponibles</p>
                <div class="space-y-3" data-geo-viewer-layers></div>
            </div>
            <div class="border-t border-slate-200 p-4 text-xs text-slate-500" data-geo-viewer-attribution></div>
        </aside>
        <main class="relative min-h-96" aria-label="Mapa interactivo">
            <div class="h-full w-full" data-geo-viewer-map></div>
            <div class="geo-viewer-message" data-geo-viewer-status>Cargando visor…</div>
        </main>
    </div>
</body>
</html>
