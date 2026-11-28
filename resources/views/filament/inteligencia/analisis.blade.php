@assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endassets

<div class="space-y-4" x-data="{ abierto: null }">
    <div
        wire:ignore
        x-data="{
            iniciar() {
                if (typeof L === 'undefined' || this.$refs.mapa.dataset.listo) {
                    return;
                }
                this.$refs.mapa.dataset.listo = '1';
                const mapa = L.map(this.$refs.mapa).setView([4.15, -73.63], 8);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                }).addTo(mapa);
                const geojson = @js($geojson);
                if (geojson) {
                    const capa = L.geoJSON(JSON.parse(geojson), { style: { color: '#047857', weight: 2, fillOpacity: 0.15 } }).addTo(mapa);
                    const fuera = @js($geojsonFuera);
                    if (fuera) {
                        L.geoJSON(JSON.parse(fuera), { style: { color: '#c2410c', weight: 2, fillOpacity: 0.45 } }).addTo(mapa);
                    }
                    mapa.fitBounds(capa.getBounds());
                }
            },
        }"
        x-init="iniciar()"
    >
        <div x-ref="mapa" class="h-96 w-full rounded-lg border border-gray-200"></div>
    </div>

    @if ($notaFuera)
        <p data-nota-fuera class="rounded-lg bg-orange-50 px-3 py-2 text-sm text-orange-950">{{ $notaFuera }}</p>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="px-2 py-2">Capa</th>
                    <th class="px-2 py-2">Resultado</th>
                    <th class="px-2 py-2">Cita de la fuente</th>
                    <th class="px-2 py-2">Fecha de corte</th>
                    <th class="px-2 py-2">Consultado en</th>
                    <th class="px-2 py-2">Origen</th>
                    <th class="px-2 py-2">Cruces</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $indice => $fila)
                    <tr class="border-b align-top">
                        <td class="px-2 py-2">{{ $fila['capa'] }}</td>
                        <td class="px-2 py-2">
                            @if ($fila['pendiente'])
                                <span>Pendiente de conector</span>
                            @else
                                @if (($fila['conteo'] ?? 0) > 0)
                                    <button type="button" class="font-semibold text-emerald-700 underline" x-on:click="abierto = {{ $indice }}">
                                        {{ $fila['conteo'] }}
                                    </button>
                                @else
                                    <span>{{ $fila['conteo'] }}</span>
                                @endif
                                @if ($fila['area_m2'] !== null)
                                    <span class="block text-gray-500">{{ $fila['area_m2'] }} m²</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            @if ($fila['pendiente'])
                                —
                            @else
                                <p>{{ $fila['cita_fuente'] }}</p>
                                <p class="text-gray-500">{{ $fila['url_fuente'] }}</p>
                                <p class="text-gray-500">{{ $fila['licencia'] }}</p>
                            @endif
                        </td>
                        <td class="px-2 py-2">{{ $fila['fecha_corte'] ?? '—' }}</td>
                        <td class="px-2 py-2">{{ $fila['consulted_at'] ?? '—' }}</td>
                        <td class="px-2 py-2">
                            {{ $fila['origen'] ?? '—' }}
                            @if ($fila['obsoleto'])
                                <span class="mt-1 inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Posiblemente desactualizada</span>
                            @endif
                            @if ($fila['advertencia'])
                                <span class="mt-1 block text-amber-700">{{ $fila['advertencia'] }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            @if (isset($analisis))
                                @include('filament.inteligencia.enlaces-cruces', ['analisisId' => $analisis->id, 'fila' => $fila])
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach ($filas as $indice => $fila)
        @if (! $fila['pendiente'] && ($fila['conteo'] ?? 0) > 0)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-show="abierto === {{ $indice }}" x-cloak>
                <div class="w-full max-w-2xl space-y-3 rounded-lg bg-white p-4">
                    <h3 class="text-base font-semibold">{{ $fila['capa'] }}: {{ $fila['conteo'] }} cruces</h3>
                    <p class="text-sm text-gray-600">El conteo suma las entidades de la copia local que intersectan el polígono.</p>
                    <ul class="max-h-80 space-y-2 overflow-auto text-sm">
                        @forelse ($fila['detalles'] as $detalle)
                            <li class="rounded border p-2">
                                <p class="font-medium">{{ $detalle['identificador'] }}</p>
                                <p class="text-gray-600">{{ $detalle['atributos'] }}</p>
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
</div>
