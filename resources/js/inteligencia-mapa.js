import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import JSZip from 'jszip';
import { kml } from '@tmcw/togeojson';
import { aplicarEstado, estadoDeGeometria, geometriaDePoligonos, origenDeNombre, sridDeArchivo } from './inteligencia-geometria.js';

const centroMeta = [3.4, -73.0];

L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

const geometriaPendiente = new WeakMap();

export async function iniciarMapas() {
    const analisis = marcarPendientes('[data-mapa-analisis]');
    const visor = marcarPendientes('[data-visor-capas]');

    if (analisis.length === 0 && visor.length === 0) {
        return;
    }

    window.L = L;
    await import('leaflet-draw');
    await import('leaflet-draw/dist/leaflet.draw.css');
    traducirDibujo();

    analisis.forEach((nodo) => prepararAnalisis(nodo));
    visor.forEach((nodo) => prepararVisor(nodo));
    sincronizarPendientes();
}

function marcarPendientes(selector) {
    return Array.from(document.querySelectorAll(selector)).filter((nodo) => {
        if (nodo.dataset.listo === '1') {
            return false;
        }

        nodo.dataset.listo = '1';

        return true;
    });
}

function prepararAnalisis(nodo) {
    const mapa = crearMapa(nodo);
    const dibujados = new L.FeatureGroup();
    mapa.addLayer(dibujados);
    mapa.addControl(new L.Control.Draw({
        position: 'topleft',
        draw: {
            polygon: { allowIntersection: false, shapeOptions: { color: '#047857' } },
            rectangle: { shapeOptions: { color: '#047857' } },
            polyline: false,
            circle: false,
            marker: false,
            circlemarker: false,
        },
        edit: { featureGroup: dibujados, remove: true },
    }));

    const resultado = new L.FeatureGroup();
    mapa.addLayer(resultado);

    const publicar = (opciones = {}) => {
        const estado = estadoDeGeometria(geometriaDeGrupo(dibujados), {
            origen: 'dibujo',
            srid: 4326,
            nombreArchivo: null,
            mensaje: null,
            ...opciones,
        });
        aplicarEnPantalla(nodo, estado);
    };

    mapa.on(L.Draw.Event.CREATED, (evento) => {
        dibujados.clearLayers();
        dibujados.addLayer(evento.layer);
        publicar();
    });
    mapa.on(L.Draw.Event.EDITED, () => publicar());
    mapa.on(L.Draw.Event.DELETED, () => publicar());

    nodo.addEventListener('dragover', (evento) => {
        evento.preventDefault();
        nodo.classList.add('mapa-soltar');
    });
    nodo.addEventListener('dragleave', () => nodo.classList.remove('mapa-soltar'));
    nodo.addEventListener('drop', async (evento) => {
        evento.preventDefault();
        nodo.classList.remove('mapa-soltar');
        const archivo = evento.dataTransfer?.files?.[0];

        if (archivo) {
            await cargarArchivo(archivo, nodo, dibujados, mapa);
        }
    });

    document.addEventListener('change', async (evento) => {
        const entrada = evento.target;

        if (! (entrada instanceof HTMLInputElement) || ! entrada.matches('[data-archivo-analisis]') || ! entrada.files?.[0]) {
            return;
        }

        if (entrada.closest('.mapa-primero') !== nodo.closest('.mapa-primero')) {
            return;
        }

        await cargarArchivo(entrada.files[0], nodo, dibujados, mapa);
    });

    const estado = leerEstado(nodo);
    pintarAnalisis(dibujados, resultado, mapa, estado);
    escucharPintado(nodo, dibujados, resultado, mapa);
    const reintentar = () => {
        if (! nodo.isConnected) {
            document.removeEventListener('livewire:initialized', reintentar);
            document.removeEventListener('livewire:navigated', reintentar);

            return;
        }

        escucharPintado(nodo, dibujados, resultado, mapa);
        sincronizarPendientes();
    };
    document.addEventListener('livewire:initialized', reintentar);
    document.addEventListener('livewire:navigated', reintentar);
    requestAnimationFrame(() => mapa.invalidateSize());
}

function prepararVisor(nodo) {
    const mapa = crearMapa(nodo);
    const estado = leerEstado(nodo);
    const grupos = new Map();

    const cargar = async (capa) => {
        const grupo = grupos.get(capa.id) ?? L.geoJSON(null, {
            style: { color: capa.color, weight: 2, fillOpacity: 0.25 },
            onEachFeature: (feature, layer) => layer.bindPopup(popup(feature.properties ?? {})),
        });
        grupos.set(capa.id, grupo);

        if (! mapa.hasLayer(grupo)) {
            grupo.addTo(mapa);
        }

        const bbox = mapa.getBounds().toBBoxString();
        const respuesta = await fetch(`${capa.url}?bbox=${encodeURIComponent(bbox)}`, {
            headers: { Accept: 'application/json' },
        });

        if (! respuesta.ok) {
            return;
        }

        grupo.clearLayers();
        grupo.addData(await respuesta.json());
    };

    const sincronizar = () => {
        const activos = new Set(
            Array.from(document.querySelectorAll('[data-capa-toggle]:checked')).map((entrada) => entrada.value),
        );

        for (const capa of estado.capas ?? []) {
            const grupo = grupos.get(capa.id);

            if (! activos.has(String(capa.id))) {
                if (grupo) {
                    mapa.removeLayer(grupo);
                }

                continue;
            }

            cargar(capa);
        }
    };

    document.addEventListener('change', (evento) => {
        if (evento.target instanceof HTMLInputElement && evento.target.matches('[data-capa-toggle]')) {
            sincronizar();
        }
    });
    mapa.on('moveend', sincronizar);
    sincronizar();
    requestAnimationFrame(() => mapa.invalidateSize());
}

function crearMapa(nodo) {
    const mapa = L.map(nodo, { zoomControl: true }).setView(centroMeta, 7);
    const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    });
    const satelite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Imágenes &copy; Esri',
        maxZoom: 19,
    });
    osm.addTo(mapa);
    L.control.layers({ 'OpenStreetMap': osm, 'Satélite': satelite }, null, { position: 'topleft' }).addTo(mapa);

    return mapa;
}

function pintarAnalisis(dibujados, resultado, mapa, estado) {
    if (! estado) {
        return;
    }

    if (estado.poligono) {
        const geometria = typeof estado.poligono === 'string' ? JSON.parse(estado.poligono) : estado.poligono;
        dibujados.clearLayers();
        const capa = L.geoJSON(geometria, { style: { color: '#047857', weight: 2, fillOpacity: 0.15 } });
        capa.eachLayer((layer) => dibujados.addLayer(layer));
        const limites = dibujados.getBounds();

        if (limites.isValid()) {
            mapa.fitBounds(limites, { padding: [24, 24] });
        }
    }

    resultado.clearLayers();

    for (const capa of estado.capas ?? []) {
        if (! capa.geojson || (capa.geojson.features ?? []).length === 0) {
            continue;
        }

        L.geoJSON(capa.geojson, {
            style: { color: capa.color, weight: 2, fillOpacity: 0.35 },
            onEachFeature: (feature, layer) => layer.bindPopup(popup(feature.properties ?? {}, capa.etiqueta)),
        }).addTo(resultado);
    }
}

async function cargarArchivo(archivo, nodo, dibujados, mapa) {
    const leido = await leerArchivo(archivo);
    const geometria = geometriaDePoligonos(leido.geojson);
    const mensaje = ! geometria
        ? 'El archivo no contiene un polígono.'
        : (leido.srid === null ? 'No se reconoció el sistema del archivo. Use EPSG:4326 o EPSG:9377.' : null);

    if (! geometria || leido.srid === null) {
        aplicarEnPantalla(nodo, estadoDeGeometria(null, { mensaje }));

        return;
    }

    dibujados.clearLayers();
    const capa = L.geoJSON(geometria);
    capa.eachLayer((layer) => dibujados.addLayer(layer));
    const limites = dibujados.getBounds();

    if (limites.isValid()) {
        mapa.fitBounds(limites, { padding: [24, 24] });
    }

    aplicarEnPantalla(nodo, estadoDeGeometria(geometria, {
        origen: origenDeNombre(archivo.name),
        srid: leido.srid,
        nombreArchivo: archivo.name,
        mensaje: null,
    }));
}

function aplicarEnPantalla(nodo, estado) {
    const panel = nodo.closest('.mapa-primero') ?? document;
    const area = panel.querySelector('[data-area-poligono]');
    const boton = panel.querySelector('[data-procesar-analisis]');
    const aviso = panel.querySelector('[data-mensaje-analisis]');

    if (area) {
        area.textContent = estado.textoArea;
    }

    if (boton) {
        boton.disabled = ! estado.puedeProcesar;
    }

    if (aviso) {
        aviso.hidden = ! estado.mensaje;
        aviso.textContent = estado.mensaje ?? '';
    }

    const wire = componenteDe(nodo);

    if (! wire) {
        geometriaPendiente.set(nodo, estado);

        return;
    }

    aplicarEstado({ textoArea: '', puedeProcesar: false, mensaje: null }, wire, estado);
    geometriaPendiente.delete(nodo);
}

function sincronizarPendientes() {
    document.querySelectorAll('[data-mapa-analisis]').forEach((nodo) => {
        const estado = geometriaPendiente.get(nodo);
        const wire = componenteDe(nodo);

        if (! estado || ! wire) {
            return;
        }

        aplicarEstado({ textoArea: '', puedeProcesar: false, mensaje: null }, wire, estado);
        geometriaPendiente.delete(nodo);
    });
}

function escucharPintado(nodo, dibujados, resultado, mapa) {
    if (nodo.dataset.escucha === '1') {
        return;
    }

    const wire = componenteDe(nodo);

    if (! wire) {
        return;
    }

    nodo.dataset.escucha = '1';
    wire.on('analisis-pintado', (evento) => {
        const carga = evento?.estado ?? evento?.[0]?.estado ?? evento;

        pintarAnalisis(dibujados, resultado, mapa, carga);
    });
}

async function leerArchivo(archivo) {
    const nombre = archivo.name.toLowerCase();
    const buffer = await archivo.arrayBuffer();

    if (nombre.endsWith('.geojson') || nombre.endsWith('.json')) {
        const geojson = JSON.parse(new TextDecoder().decode(buffer));

        return { geojson, srid: sridDeArchivo(archivo.name, '', geojson) };
    }

    if (nombre.endsWith('.kml')) {
        const geojson = kml(new DOMParser().parseFromString(new TextDecoder().decode(buffer), 'text/xml'));

        return { geojson, srid: 4326 };
    }

    if (nombre.endsWith('.kmz')) {
        const zip = await JSZip.loadAsync(buffer);
        const kmlNombre = Object.keys(zip.files).find((entrada) => entrada.toLowerCase().endsWith('.kml'));

        if (! kmlNombre) {
            return { geojson: null, srid: 4326 };
        }

        const texto = await zip.files[kmlNombre].async('string');

        return { geojson: kml(new DOMParser().parseFromString(texto, 'text/xml')), srid: 4326 };
    }

    if (nombre.endsWith('.zip')) {
        const zip = await JSZip.loadAsync(buffer);
        const prjNombre = Object.keys(zip.files).find((entrada) => entrada.toLowerCase().endsWith('.prj'));
        const prj = prjNombre ? await zip.files[prjNombre].async('string') : '';
        const shp = (await import('shpjs')).default;
        const geojson = await shp(buffer);

        return { geojson, srid: sridDeArchivo(archivo.name, prj, geojson) };
    }

    return { geojson: null, srid: null };
}

function geometriaDeGrupo(grupo) {
    const coleccion = grupo.toGeoJSON();

    return geometriaDePoligonos(coleccion);
}

function leerEstado(nodo) {
    const script = nodo.querySelector('.mapa-estado');

    if (! script?.textContent) {
        return null;
    }

    return JSON.parse(script.textContent);
}

function componenteDe(nodo) {
    if (! window.Livewire?.find) {
        return null;
    }

    const identificado = nodo.dataset.componente
        ? window.Livewire.find(nodo.dataset.componente)
        : null;

    if (identificado) {
        return identificado;
    }

    const raiz = nodo.closest('[wire\\:id]');

    if (! raiz) {
        return null;
    }

    return window.Livewire.find(raiz.getAttribute('wire:id'));
}

function popup(propiedades, titulo) {
    const identificador = escapeHtml(propiedades.identificador ?? titulo ?? 'Entidad');
    const atributos = propiedades.atributos ?? {};
    const filas = Object.entries(atributos).map(([clave, valor]) => {
        const texto = typeof valor === 'object' ? JSON.stringify(valor) : String(valor ?? '');

        return `<div><strong>${escapeHtml(clave)}</strong>: ${escapeHtml(texto)}</div>`;
    }).join('');

    return `<div class="text-sm"><p class="font-semibold">${identificador}</p>${filas}</div>`;
}

function escapeHtml(valor) {
    return String(valor)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function traducirDibujo() {
    if (! L.drawLocal) {
        return;
    }

    L.drawLocal.draw.toolbar.buttons.polygon = 'Dibujar polígono';
    L.drawLocal.draw.toolbar.buttons.rectangle = 'Dibujar rectángulo';
    L.drawLocal.edit.toolbar.buttons.edit = 'Editar';
    L.drawLocal.edit.toolbar.buttons.editDisabled = 'No hay geometría para editar';
    L.drawLocal.edit.toolbar.buttons.remove = 'Borrar';
    L.drawLocal.edit.toolbar.buttons.removeDisabled = 'No hay geometría para borrar';
    L.drawLocal.draw.handlers.polygon.tooltip.start = 'Haga clic para iniciar el polígono.';
    L.drawLocal.draw.handlers.polygon.tooltip.cont = 'Continúe el contorno.';
    L.drawLocal.draw.handlers.polygon.tooltip.end = 'Cierre el polígono en el primer punto.';
    L.drawLocal.draw.handlers.rectangle.tooltip.start = 'Arrastre para trazar el rectángulo.';
    L.drawLocal.edit.handlers.edit.tooltip.text = 'Arrastre los vértices para editar.';
    L.drawLocal.edit.handlers.edit.tooltip.subtext = 'Pulse cancelar para deshacer.';
    L.drawLocal.edit.handlers.remove.tooltip.text = 'Haga clic en una geometría para borrarla.';
}
