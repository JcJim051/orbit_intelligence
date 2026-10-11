@assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
@endassets

<div
    wire:ignore
    class="space-y-2"
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
            const dibujo = new L.FeatureGroup();
            mapa.addLayer(dibujo);
            mapa.addControl(new L.Control.Draw({
                draw: {
                    polygon: { allowIntersection: false, showArea: true },
                    polyline: false,
                    rectangle: false,
                    circle: false,
                    marker: false,
                    circlemarker: false,
                },
                edit: { featureGroup: dibujo },
            }));
            mapa.on(L.Draw.Event.CREATED, (evento) => {
                dibujo.clearLayers();
                dibujo.addLayer(evento.layer);
                $wire.set(@js($rutaGeojson ?? 'data.geojson'), JSON.stringify(evento.layer.toGeoJSON().geometry));
            });
        },
    }"
    x-init="iniciar()"
>
    <div x-ref="mapa" class="h-96 w-full rounded-lg border border-gray-200"></div>
    <p class="text-sm text-gray-500">Dibuje el polígono o cargue un KMZ, KML o GeoJSON. El área se guarda en EPSG:9377.</p>
</div>
