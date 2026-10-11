@assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endassets

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
            let marca = null;
            mapa.on('click', (evento) => {
                if (marca) {
                    mapa.removeLayer(marca);
                }
                marca = L.marker(evento.latlng).addTo(mapa);
                $wire.set(@js($rutaX ?? 'data.x_original'), evento.latlng.lng.toFixed(6));
                $wire.set(@js($rutaY ?? 'data.y_original'), evento.latlng.lat.toFixed(6));
            });
        },
    }"
    x-init="iniciar()"
>
    <div x-ref="mapa" class="h-72 w-full rounded-lg border border-gray-200"></div>
    <p class="mt-2 text-sm text-gray-500">Si el sistema es EPSG:4326, un clic fija longitud y latitud. En otro sistema escriba las coordenadas del formulario.</p>
</div>
