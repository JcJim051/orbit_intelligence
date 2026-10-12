@vite(['resources/js/inteligencia-geografica.js'])

<x-filament-panels::page>
    <div class="mapa-primero">
        <div wire:ignore class="mapa-lienzo" data-mapa-analisis>
            <script type="application/json" class="mapa-estado">@json($estadoInicial)</script>
        </div>

        <aside class="mapa-panel" x-data="{ abierto: null }">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Meta</p>
                    <h2 class="text-lg font-semibold text-slate-950">Análisis de área</h2>
                </div>
                <a href="{{ $historialUrl }}" class="text-sm font-semibold text-emerald-700">Anteriores</a>
            </div>

            <p class="mt-2 text-sm text-slate-600">
                Área del polígono:
                <strong>{{ $areaHa ? $areaHa.' ha' : 'dibuje o cargue un área' }}</strong>
            </p>

            <label class="mt-4 block text-sm font-medium text-slate-700">
                Modo
                <select wire:model="modo" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="normal">Normal</option>
                    <option value="oficial">Oficial</option>
                </select>
            </label>
            <p class="mt-1 text-xs text-slate-500">Oficial no consulta la fuente mientras el conector no exista. Si hay copia atribuible, se marca como copia por falla.</p>

            <label class="mt-4 block text-sm font-medium text-slate-700">
                BPIN
                <input wire:model.live.debounce.300ms="busquedaProyecto" type="search" placeholder="Buscar código o nombre" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </label>
            <select wire:model="proyectoId" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Sin proyecto</option>
                @foreach ($proyectos as $proyecto)
                    <option value="{{ $proyecto->id }}">{{ $proyecto->bpin }} — {{ $proyecto->name ?: 'Sin nombre' }}</option>
                @endforeach
            </select>

            <label class="mt-4 block text-sm font-medium text-slate-700">
                KMZ, KML, GeoJSON o shapefile .zip
                <input data-archivo-analisis type="file" accept=".kmz,.kml,.geojson,.json,.zip,application/zip" class="mt-1 block w-full text-sm">
            </label>
            <p class="mt-1 text-xs text-slate-500">También puede soltar el archivo sobre el mapa. El servidor valida el polígono y lo guarda en EPSG:9377.</p>

            @if ($mensaje)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $mensaje }}</p>
            @endif

            <button type="button" wire:click="procesar" wire:loading.attr="disabled" class="mt-4 w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
                <span wire:loading.remove wire:target="procesar">Procesar</span>
                <span wire:loading wire:target="procesar">Procesando…</span>
            </button>

            <div class="mt-4 space-y-3">
                @forelse ($filas as $indice => $fila)
                    <article class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="flex items-start gap-2">
                            <span class="mt-1 inline-block h-3 w-3 rounded-full" style="background: {{ $fila['color'] }}"></span>
                            <h3 class="font-semibold text-slate-950">{{ $fila['capa'] }}</h3>
                        </div>
                        <p class="mt-1 text-slate-600">Fuente: {{ $fila['fuente_catalogo'] ?: 'Sin entidad registrada' }}</p>
                        @if ($fila['pendiente'])
                            <p class="mt-2 font-medium text-amber-800">Pendiente de conector</p>
                        @else
                            <p class="mt-2">
                                @if (($fila['conteo'] ?? 0) > 0)
                                    <button type="button" class="font-semibold text-emerald-700 underline" x-on:click="abierto = {{ $indice }}">{{ $fila['conteo'] }}</button>
                                @else
                                    <span>{{ $fila['conteo'] }}</span>
                                @endif
                                entidades
                                @if ($fila['area_ha'] !== null)
                                    · {{ number_format((float) $fila['area_ha'], 2, ',', '.') }} ha
                                @endif
                                @if ($fila['porcentaje'] !== null)
                                    · {{ number_format((float) $fila['porcentaje'], 1, ',', '.') }} %
                                @endif
                            </p>
                            <p class="mt-2">{{ $fila['cita_fuente'] }}</p>
                            <p class="text-slate-500">{{ $fila['url_fuente'] }}</p>
                            <p class="text-slate-500">{{ $fila['licencia'] }}</p>
                            <p class="mt-2">Fecha de corte: {{ $fila['fecha_corte'] ?? '—' }}</p>
                            <p>Consultado en: {{ $fila['consulted_at'] ?? '—' }}</p>
                            <p>Origen: {{ $fila['origen'] }} ({{ $fila['servido_desde'] }})</p>
                            @if ($fila['obsoleto'])
                                <span class="mt-1 inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Posiblemente desactualizada</span>
                            @else
                                <span class="mt-1 inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Vigente</span>
                            @endif
                            @if ($fila['advertencia'])
                                <p class="mt-1 text-amber-800">{{ $fila['advertencia'] }}</p>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Procese el polígono para ver el resultado por capa. Las capas sin copia atribuible quedan en pendiente.</p>
                @endforelse
            </div>

            @foreach ($filas as $indice => $fila)
                @if (! $fila['pendiente'] && ($fila['conteo'] ?? 0) > 0)
                    <div class="fixed inset-0 z-[800] flex items-center justify-center bg-black/40 p-4" x-show="abierto === {{ $indice }}" x-cloak>
                        <div class="w-full max-w-lg space-y-3 rounded-lg bg-white p-4">
                            <h3 class="font-semibold">{{ $fila['capa'] }}: {{ $fila['conteo'] }} cruces</h3>
                            <p class="text-sm text-slate-600">Este número suma las entidades de la copia local que se atribuyeron al polígono.</p>
                            <ul class="max-h-80 space-y-2 overflow-auto text-sm">
                                @forelse ($fila['detalles'] as $detalle)
                                    <li class="rounded border p-2">
                                        <p class="font-medium">{{ $detalle['identificador'] }}</p>
                                        <p class="text-slate-600">{{ $detalle['atributos'] }}</p>
                                    </li>
                                @empty
                                    <li>No hay detalle de entidades para este conteo.</li>
                                @endforelse
                            </ul>
                            <button type="button" class="text-sm font-semibold" x-on:click="abierto = null">Cerrar</button>
                        </div>
                    </div>
                @endif
            @endforeach
        </aside>
    </div>
</x-filament-panels::page>
