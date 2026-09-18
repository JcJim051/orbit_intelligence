import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { geoViewerPopupFields } from './geo-viewer-popup-fields.js';

const formatNumber = new Intl.NumberFormat('es-CO');

document.addEventListener('DOMContentLoaded', async () => {
    initializeApplicationShell();
    initializeContextLinks();
    initializeSlugSuggestions();
    initializeIframeDemo();

    await Promise.all([
        initializeInvestmentMap(),
        ...Array.from(document.querySelectorAll('[data-geo-viewer]')).map(initializeGeoViewer),
    ]);
});

function initializeContextLinks() {
    const openTarget = hash => {
        if (! hash) {
            return;
        }

        const target = document.getElementById(decodeURIComponent(hash.slice(1)));
        if (! target) {
            return;
        }

        let current = target;
        while (current) {
            if (current instanceof HTMLDetailsElement) {
                current.open = true;
            }
            current = current.parentElement?.closest('details') ?? null;
        }

        window.requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    };

    document.querySelectorAll('[data-status-link]').forEach(link => {
        link.addEventListener('click', event => {
            event.stopPropagation();

            const destination = new URL(link.href, window.location.href);
            if (destination.pathname === window.location.pathname && destination.hash) {
                openTarget(destination.hash);
            }
        });
    });
    window.addEventListener('hashchange', () => openTarget(window.location.hash));
    openTarget(window.location.hash);
}

function initializeApplicationShell() {
    const shell = document.querySelector('[data-app-shell]');
    if (! shell) {
        return;
    }

    const openButton = shell.querySelector('[data-sidebar-open]');
    const closeMenu = () => {
        shell.classList.remove('sidebar-open');
        openButton?.setAttribute('aria-expanded', 'false');
    };
    const openMenu = () => {
        shell.classList.add('sidebar-open');
        openButton?.setAttribute('aria-expanded', 'true');
        shell.querySelector('[data-sidebar-close]')?.focus();
    };

    openButton?.addEventListener('click', openMenu);
    shell.querySelector('[data-sidebar-close]')?.addEventListener('click', closeMenu);
    shell.querySelector('[data-sidebar-overlay]')?.addEventListener('click', closeMenu);
    shell.querySelectorAll('[data-sidebar] a').forEach(link => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    document.querySelectorAll('[data-workflow-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const workflow = button.closest('.sig-workflow');
            const open = workflow?.classList.toggle('is-open') ?? false;
            button.setAttribute('aria-expanded', String(open));
            button.textContent = open ? 'Ocultar pasos' : 'Ver pasos';
        });
    });
}

function initializeIframeDemo() {
    const demo = document.querySelector('[data-iframe-demo]');

    if (! demo) {
        return;
    }

    const viewerSelect = demo.querySelector('[data-iframe-viewer-select]');
    const frame = demo.querySelector('[data-iframe-preview]');
    const frameContainer = demo.querySelector('[data-iframe-frame-container]');
    const stage = demo.querySelector('[data-iframe-stage]');

    viewerSelect?.addEventListener('change', event => event.target.form?.submit());
    demo.querySelector('[data-iframe-reload]')?.addEventListener('click', () => {
        if (frame) {
            frame.src = frame.src;
        }
    });
    demo.querySelector('[data-iframe-fullscreen]')?.addEventListener('click', () => stage?.requestFullscreen());

    demo.querySelectorAll('[data-iframe-width]').forEach(button => {
        button.addEventListener('click', () => {
            if (! frameContainer) {
                return;
            }

            frameContainer.style.maxWidth = button.dataset.iframeWidth;
            demo.querySelectorAll('[data-iframe-width]').forEach(candidate => {
                const selected = candidate === button;
                candidate.classList.toggle('bg-white', selected);
                candidate.classList.toggle('text-emerald-800', selected);
                candidate.classList.toggle('shadow-sm', selected);
                candidate.classList.toggle('text-slate-600', ! selected);
            });
        });
    });
}

function initializeSlugSuggestions() {
    document.querySelectorAll('[data-slug-suggestion]').forEach(form => {
        const source = form.querySelector('[data-slug-source]');
        const target = form.querySelector('[data-slug-target]');

        if (! source || ! target) {
            return;
        }

        let suggestedValue = slugify(source.value);
        let manuallyEdited = target.value !== '' && target.value !== suggestedValue;

        if (! target.value) {
            target.value = suggestedValue;
        }

        source.addEventListener('input', () => {
            const nextSuggestion = slugify(source.value);

            if (! manuallyEdited || target.value === suggestedValue || target.value === '') {
                target.value = nextSuggestion;
                manuallyEdited = false;
            }

            suggestedValue = nextSuggestion;
        });

        target.addEventListener('input', () => {
            manuallyEdited = target.value !== '' && target.value !== suggestedValue;
        });

        target.addEventListener('blur', () => {
            if (! target.value) {
                target.value = slugify(source.value);
                suggestedValue = target.value;
                manuallyEdited = false;
            }
        });
    });
}

function slugify(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120)
        .replace(/-+$/g, '');
}

async function initializeInvestmentMap() {
    const element = document.querySelector('[data-investment-map]');

    if (! element) {
        return;
    }

    const status = document.querySelector('[data-investment-map-status]');
    const map = L.map(element, {
        attributionControl: true,
        scrollWheelZoom: false,
        zoomControl: true,
    });

    try {
        const response = await fetch(element.dataset.investmentMap, {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            throw new Error('Map boundaries unavailable');
        }

        const geojson = await response.json();
        const layer = L.geoJSON(geojson, {
            style(feature) {
                const projects = Number(feature.properties.project_count ?? 0);
                const fillColor = projects >= 100 ? '#3730a3'
                    : projects >= 50 ? '#4f46e5'
                        : projects >= 20 ? '#6366f1'
                            : projects >= 5 ? '#a5b4fc'
                                : projects > 0 ? '#c7d2fe' : '#e2e8f0';

                return { color: '#ffffff', fillColor, fillOpacity: 0.9, weight: 1.5 };
            },
            onEachFeature(feature, municipalityLayer) {
                const properties = feature.properties;
                const projects = Number(properties.project_count ?? 0);
                const content = document.createElement('div');
                const name = document.createElement('strong');
                const link = document.createElement('a');

                name.textContent = properties.mpio_cnmbr;
                link.href = properties.projects_url;
                link.textContent = 'Ver portafolio';
                content.append(name, document.createElement('br'), `${formatNumber.format(projects)} proyectos`, document.createElement('br'), link);

                municipalityLayer.bindTooltip(`${properties.mpio_cnmbr}: ${formatNumber.format(projects)}`, { sticky: true });
                municipalityLayer.bindPopup(content);
                municipalityLayer.on({
                    mouseover: event => event.target.setStyle({ fillOpacity: 1, weight: 3 }),
                    mouseout: event => layer.resetStyle(event.target),
                });
            },
        }).addTo(map);

        map.fitBounds(layer.getBounds(), { padding: [12, 12] });
        status.textContent = 'Límites municipales oficiales MGN 2024 · DANE';
    } catch {
        map.remove();
        element.hidden = true;
        status.textContent = 'El mapa geográfico no está disponible en este momento. Use la lista municipal inferior.';
    }
}

async function initializeGeoViewer(element) {
    const mapElement = element.querySelector('[data-geo-viewer-map]');
    const layerPanel = element.querySelector('[data-geo-viewer-layers]');
    const status = element.querySelector('[data-geo-viewer-status]');
    const attribution = element.querySelector('[data-geo-viewer-attribution]');
    const map = L.map(mapElement, {
        attributionControl: true,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    try {
        const response = await fetch(element.dataset.configUrl, {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            throw new Error('Viewer configuration unavailable');
        }

        const config = await response.json();
        map.setView(config.viewer.center, config.viewer.zoom);
        element.querySelector('[data-geo-viewer-title]').textContent = config.viewer.name;
        element.querySelector('[data-geo-viewer-description]').textContent = config.viewer.description ?? '';

        const groups = config.layers.reduce((groupedLayers, layer) => {
            if (! groupedLayers.has(layer.group)) {
                groupedLayers.set(layer.group, []);
            }
            groupedLayers.get(layer.group).push(layer);

            return groupedLayers;
        }, new Map());
        const layerEntries = new Map();

        for (const [groupName, layers] of groups) {
            const group = document.createElement('details');
            const summary = document.createElement('summary');
            const list = document.createElement('div');
            group.className = 'geo-layer-group';
            group.open = true;
            summary.textContent = groupName;
            list.className = 'geo-layer-list';
            group.append(summary, list);

            for (const layerConfig of layers) {
                if (! layerConfig.show_in_legend && ! layerConfig.visible_by_default) {
                    continue;
                }

                const layerItem = document.createElement('div');
                const row = document.createElement('label');
                const checkbox = document.createElement('input');
                const label = document.createElement('span');
                const swatch = document.createElement('span');
                const text = document.createElement('span');
                checkbox.type = 'checkbox';
                checkbox.checked = layerConfig.visible_by_default;
                checkbox.className = 'geo-layer-checkbox';
                layerItem.className = 'geo-layer-entry';
                row.className = 'geo-layer-row';
                label.className = 'geo-layer-label';
                swatch.className = 'geo-layer-swatch';
                swatch.style.backgroundColor = layerConfig.style.fillColor ?? layerConfig.style.color ?? '#4338ca';
                text.textContent = layerConfig.name;
                label.append(swatch, text);
                row.append(checkbox, label);
                layerItem.append(row);

                if (layerConfig.download?.allowed) {
                    const download = document.createElement('a');
                    download.className = 'geo-layer-download';
                    download.href = layerConfig.download.url;
                    download.download = `${layerConfig.slug}.${layerConfig.download.format ?? 'geojson'}`;
                    download.textContent = `Descargar ${String(layerConfig.download.format ?? 'datos').toUpperCase()}`;
                    download.setAttribute('aria-label', `Descargar capa ${layerConfig.name}`);
                    layerItem.append(download);
                } else if (layerConfig.access_policy === 'view_only') {
                    const notice = document.createElement('span');
                    notice.className = 'geo-layer-view-only';
                    notice.textContent = 'Solo consulta en el mapa';
                    layerItem.append(notice);
                }

                list.append(layerItem);

                const entry = { checkbox, config: layerConfig, leafletLayer: null, loading: null };
                layerEntries.set(layerConfig.slug, entry);
                checkbox.addEventListener('change', async () => {
                    if (checkbox.checked) {
                        await loadGeoViewerLayer(map, entry, status);
                    } else if (entry.leafletLayer) {
                        map.removeLayer(entry.leafletLayer);
                    }
                    updateGeoViewerAttribution(attribution, layerEntries);
                });
            }

            if (list.childElementCount > 0) {
                layerPanel.append(group);
            }
        }

        map.on('zoomend', () => updateGeoViewerLayerVisibility(map, layerEntries));
        await Promise.all(Array.from(layerEntries.values())
            .filter(entry => entry.checkbox.checked)
            .map(entry => loadGeoViewerLayer(map, entry, status)));
        updateGeoViewerAttribution(attribution, layerEntries);
        status.classList.add('is-hidden');
    } catch {
        status.textContent = 'No fue posible cargar la configuración del geovisor.';
        status.classList.remove('is-hidden');
    }
}

async function loadGeoViewerLayer(map, entry, status) {
    if (entry.leafletLayer) {
        updateGeoViewerLayerVisibility(map, new Map([[entry.config.slug, entry]]));
        return;
    }

    if (! entry.loading) {
        if (entry.config.source.type === 'wms') {
            entry.leafletLayer = L.tileLayer.wms(entry.config.source.url, {
                layers: entry.config.source.layer_name,
                format: 'image/png',
                transparent: true,
                opacity: entry.config.opacity ?? 1,
                minZoom: entry.config.min_zoom,
                maxZoom: entry.config.max_zoom,
                attribution: entry.config.attribution ?? '',
            });
            updateGeoViewerLayerVisibility(map, new Map([[entry.config.slug, entry]]));

            return;
        }

        entry.loading = fetch(entry.config.source.url, { headers: { Accept: 'application/geo+json, application/json' } })
            .then(response => {
                if (! response.ok) {
                    throw new Error('Layer unavailable');
                }
                return response.json();
            })
            .then(geojson => {
                const style = entry.config.style ?? {};
                const opacity = entry.config.opacity ?? 1;
                entry.leafletLayer = L.geoJSON(geojson, {
                    style: {
                        color: style.color ?? '#4338ca',
                        fillColor: style.fillColor ?? '#818cf8',
                        weight: style.weight ?? 2,
                        opacity,
                        fillOpacity: 0.45 * opacity,
                    },
                    pointToLayer(feature, latlng) {
                        return L.circleMarker(latlng, {
                            radius: style.radius ?? 7,
                            color: style.color ?? '#4338ca',
                            fillColor: style.fillColor ?? '#818cf8',
                            weight: style.weight ?? 2,
                            opacity,
                            fillOpacity: 0.75 * opacity,
                        });
                    },
                    onEachFeature(feature, layer) {
                        const popup = buildGeoViewerPopup(entry.config, feature.properties ?? {});
                        if (popup) {
                            layer.bindPopup(popup);
                        }
                    },
                });
            });
    }

    try {
        status.textContent = `Cargando ${entry.config.name}…`;
        status.classList.remove('is-hidden');
        await entry.loading;
        if (entry.checkbox.checked) {
            updateGeoViewerLayerVisibility(map, new Map([[entry.config.slug, entry]]));
        }
        status.classList.add('is-hidden');
    } catch {
        entry.checkbox.checked = false;
        entry.loading = null;
        status.textContent = `No fue posible cargar la capa “${entry.config.name}”.`;
        status.classList.remove('is-hidden');
    }
}

function updateGeoViewerLayerVisibility(map, layerEntries) {
    const zoom = map.getZoom();

    for (const entry of layerEntries.values()) {
        if (! entry.leafletLayer) {
            continue;
        }

        const shouldDisplay = entry.checkbox.checked && zoom >= entry.config.min_zoom && zoom <= entry.config.max_zoom;
        if (shouldDisplay && ! map.hasLayer(entry.leafletLayer)) {
            entry.leafletLayer.addTo(map);
        } else if (! shouldDisplay && map.hasLayer(entry.leafletLayer)) {
            map.removeLayer(entry.leafletLayer);
        }
    }
}

function updateGeoViewerAttribution(element, layerEntries) {
    const sources = Array.from(layerEntries.values())
        .filter(entry => entry.checkbox.checked && entry.config.attribution)
        .map(entry => entry.config.attribution);
    element.textContent = [...new Set(sources)].join(' · ');
}

function buildGeoViewerPopup(layerConfig, properties) {
    const fields = geoViewerPopupFields(properties, layerConfig.popup_fields ?? [], layerConfig.popup_all_attributes === true);
    if (! fields.length) {
        return null;
    }

    const content = document.createElement('div');
    const heading = document.createElement('strong');
    const list = document.createElement('dl');
    heading.textContent = layerConfig.name;
    list.className = 'geo-popup-list';
    content.append(heading, list);

    for (const field of fields) {
        const term = document.createElement('dt');
        const value = document.createElement('dd');
        term.textContent = field.replaceAll('_', ' ');
        value.textContent = formatGeoViewerValue(properties[field]);
        list.append(term, value);
    }

    return list.childElementCount ? content : null;
}

function formatGeoViewerValue(value) {
    if (value === null || value === undefined || value === '') {
        return 'Sin información';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}
