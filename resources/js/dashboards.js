import L from 'leaflet';

const numberFormat = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 });

export function initializeDashboards() {
    document.querySelectorAll('[data-dashboard-builder]').forEach(initializeBuilder);
    document.querySelectorAll('[data-dashboard-view]').forEach(initializeViewer);
}

function initializeBuilder(root) {
    const grid = root.querySelector('[data-dashboard-grid]');
    const inspector = root.querySelector('[data-dashboard-inspector]');
    const sourceSelect = root.querySelector('[data-dashboard-source]');
    const state = { config: JSON.parse(root.dataset.config || '{}'), selected: null, timer: null, saving: null };
    state.config.widgets ||= [];
    state.config.map ||= {};
    state.config.global_filters ||= {};

    const fields = () => JSON.parse(sourceSelect.selectedOptions[0]?.dataset.fields || '[]');
    const saveConfig = async () => {
        if (state.saving) return state.saving;
        state.saving = fetch(root.dataset.saveUrl, {
                method: 'PATCH', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ config: state.config }),
            }).then(response => {
            root.querySelector('[data-dashboard-save-state]').textContent = response.ok ? 'Guardado' : 'No fue posible guardar';
            return response;
        }).finally(() => { state.saving = null; });
        return state.saving;
    };
    const flushSave = async () => {
        window.clearTimeout(state.timer); state.timer = null;
        return saveConfig();
    };
    const scheduleSave = () => {
        root.querySelector('[data-dashboard-save-state]').textContent = 'Cambios pendientes…';
        window.clearTimeout(state.timer);
        state.timer = window.setTimeout(() => { state.timer = null; saveConfig(); }, 650);
    };

    const adaptPopulationWidgets = () => {
        const keys = new Set(fields().map(field => field.key));
        if (! ['total', 'hombres', 'mujeres', 'ano', 'area_geografica'].every(key => keys.has(key)) || ! [...keys].some(key => /^hombres_\d+_ano(s)?/.test(key))) return false;
        const queries = {
            'dept-total': { operation: 'population_indicator', field: 'total', year: '2026', area: 'Total' },
            'dept-female': { operation: 'population_indicator', field: 'mujeres', year: '2026', area: 'Total' },
            'dept-male': { operation: 'population_indicator', field: 'hombres', year: '2026', area: 'Total' },
            'municipal-total': { operation: 'population_indicator', field: 'total', year: '2026', area: 'Total' },
            'municipal-female': { operation: 'population_indicator', field: 'mujeres', year: '2026', area: 'Total' },
            'municipal-male': { operation: 'population_indicator', field: 'hombres', year: '2026', area: 'Total' },
            'rural-urban': { operation: 'population_area_distribution', field: 'total', year: '2026' },
            'population-pyramid': { operation: 'population_pyramid', year: '2026', area: 'Total' },
        };
        let changed = false;
        state.config.widgets.forEach(widget => {
            if (queries[widget.id] && JSON.stringify(widget.query) !== JSON.stringify(queries[widget.id])) { widget.query = queries[widget.id]; changed = true; }
        });
        if (keys.has('mpio') && ['codigo_dane', 'dpmp', '', undefined].includes(state.config.map.join_data_field)) { state.config.map.join_data_field = 'mpio'; root.querySelector('[data-join-data]').value = 'mpio'; changed = true; }
        return changed;
    };

    const renderInspector = () => {
        const widget = state.config.widgets.find(item => item.id === state.selected);
        if (! widget) {
            inspector.innerHTML = '<h2>Configuración</h2><p class="mt-2 text-sm text-slate-500">Seleccione un componente para editarlo.</p>';
            return;
        }
        const hasWidePopulation = fields().some(field => /^(hombres|mujeres)_\d+_ano(s)?(_y_mas)?$/.test(field.key));
        if (widget.type === 'pyramid' && hasWidePopulation && widget.query?.operation !== 'population_pyramid') {
            widget.query = { operation: 'population_pyramid', year: '2026', area: 'Total' };
            scheduleSave();
        }
        const fieldOptions = ['<option value="">Seleccione…</option>', ...fields().map(field => `<option value="${escapeHtml(field.key)}">${escapeHtml(field.label)} · ${escapeHtml(field.type)}</option>`)].join('');
        inspector.innerHTML = `<h2>Configuración</h2>
            <label class="field mt-4"><span>Título</span><input data-inspector="title" value="${escapeHtml(widget.title)}"></label>
            <label class="field mt-3"><span>Alcance</span><select data-inspector="scope"><option value="global">Global</option><option value="departamental_fijo">Departamental fijo</option><option value="seleccion_territorial">Selección territorial</option></select></label>
            ${widget.type === 'pyramid' && hasWidePopulation ? `<div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"><strong>Edades por sexo detectadas</strong><p>La pirámide agrupa automáticamente las columnas de hombres y mujeres en los mismos rangos del tablero poblacional.</p></div>
            <label class="field mt-3"><span>Año</span><input data-query="year" inputmode="numeric" value="${escapeHtml(widget.query?.year || '2026')}"></label>
            <label class="field mt-3"><span>Área geográfica</span><select data-query="area"><option value="Total">Total municipal</option><option value="Cabecera Municipal">Cabecera municipal</option><option value="Centros Poblados y Rural Disperso">Centros poblados y rural disperso</option></select></label>` : widget.query?.operation === 'population_indicator' ? `<label class="field mt-3"><span>Campo de valor</span><select data-query="field">${fieldOptions}</select></label><label class="field mt-3"><span>Año</span><input data-query="year" inputmode="numeric" value="${escapeHtml(widget.query?.year || '2026')}"></label><label class="field mt-3"><span>Área geográfica</span><select data-query="area"><option value="Total">Total municipal</option><option value="Cabecera Municipal">Cabecera municipal</option><option value="Centros Poblados y Rural Disperso">Centros poblados y rural disperso</option></select></label>` : widget.query?.operation === 'population_area_distribution' ? `<div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">Compara cabecera municipal con centros poblados y rural disperso sin duplicar la fila Total.</div><label class="field mt-3"><span>Año</span><input data-query="year" inputmode="numeric" value="${escapeHtml(widget.query?.year || '2026')}"></label>` : widget.type === 'text' || widget.type === 'map' ? '' : `<label class="field mt-3"><span>Operación</span><select data-query="operation"><option value="count">Contar</option><option value="sum">Sumar</option><option value="average">Promedio</option><option value="min">Mínimo</option><option value="max">Máximo</option></select></label>
            <label class="field mt-3"><span>Campo de valor</span><select data-query="field">${fieldOptions}</select></label>
            ${['bar','line','donut','pyramid','filter'].includes(widget.type) ? `<label class="field mt-3"><span>Categoría</span><select data-query="category">${fieldOptions}</select></label>` : ''}
            ${widget.type === 'pyramid' ? `<label class="field mt-3"><span>Serie (sexo)</span><select data-query="series">${fieldOptions}</select></label>` : ''}`}`;
        inspector.querySelector('[data-inspector="scope"]').value = widget.scope;
        const areaSelect = inspector.querySelector('[data-query="area"]');
        if (areaSelect) areaSelect.value = widget.query?.area || 'Total';
        inspector.querySelectorAll('[data-query]').forEach(input => { input.value = widget.query?.[input.dataset.query] || ''; });
        inspector.querySelectorAll('[data-inspector], [data-query]').forEach(input => input.addEventListener('input', () => {
            if (input.dataset.inspector) widget[input.dataset.inspector] = input.value;
            if (input.dataset.query) { widget.query ||= {}; widget.query[input.dataset.query] = input.value; }
            renderGrid(); scheduleSave();
        }));
    };

    const renderGrid = () => {
        grid.innerHTML = '';
        state.config.widgets.forEach(widget => {
            const card = document.createElement('article');
            card.className = `dashboard-builder-widget${widget.id === state.selected ? ' is-selected' : ''}`;
            card.style.gridColumn = `${widget.x + 1} / span ${Math.min(widget.w, 12 - widget.x)}`;
            card.style.gridRow = `${widget.y + 1} / span ${widget.h}`;
            card.draggable = true;
            card.dataset.widgetId = widget.id;
            card.innerHTML = `<div class="dashboard-widget-toolbar"><strong>${escapeHtml(widget.title)}</strong><span>${escapeHtml(widget.type)}</span></div><div class="dashboard-widget-placeholder">${widgetIcon(widget.type)}</div><div class="dashboard-widget-actions"><button type="button" data-size="w-">− ancho</button><button type="button" data-size="w+">+ ancho</button><button type="button" data-size="h-">− alto</button><button type="button" data-size="h+">+ alto</button><button type="button" data-remove>Eliminar</button></div>`;
            card.addEventListener('click', () => { state.selected = widget.id; renderGrid(); renderInspector(); });
            card.addEventListener('dragstart', event => event.dataTransfer.setData('text/plain', widget.id));
            card.querySelectorAll('[data-size]').forEach(button => button.addEventListener('click', event => {
                event.stopPropagation();
                const action = button.dataset.size;
                if (action === 'w-') widget.w = Math.max(1, widget.w - 1);
                if (action === 'w+') widget.w = Math.min(12 - widget.x, widget.w + 1);
                if (action === 'h-') widget.h = Math.max(1, widget.h - 1);
                if (action === 'h+') widget.h = Math.min(12, widget.h + 1);
                renderGrid(); scheduleSave();
            }));
            card.querySelector('[data-remove]').addEventListener('click', event => {
                event.stopPropagation(); state.config.widgets = state.config.widgets.filter(item => item.id !== widget.id); state.selected = null; renderGrid(); renderInspector(); scheduleSave();
            });
            grid.append(card);
        });
    };

    grid.addEventListener('dragover', event => event.preventDefault());
    grid.addEventListener('drop', event => {
        event.preventDefault(); const widget = state.config.widgets.find(item => item.id === event.dataTransfer.getData('text/plain')); if (! widget) return;
        const box = grid.getBoundingClientRect(); widget.x = Math.max(0, Math.min(12 - widget.w, Math.floor(((event.clientX - box.left) / box.width) * 12))); widget.y = Math.max(0, Math.round((event.clientY - box.top) / 74)); renderGrid(); scheduleSave();
    });
    root.querySelectorAll('[data-add-widget]').forEach(button => button.addEventListener('click', () => {
        const type = button.dataset.addWidget; const id = `${type}-${Date.now()}`;
        state.config.widgets.push({ id, type, title: button.textContent.trim(), scope: type === 'map' ? 'global' : 'seleccion_territorial', x: 0, y: Math.max(0, ...state.config.widgets.map(item => item.y + item.h)), w: type === 'map' ? 7 : 3, h: type === 'map' ? 6 : 2, query: { operation: 'count' } });
        state.selected = id; renderGrid(); renderInspector(); scheduleSave();
    }));
    sourceSelect.addEventListener('change', () => { state.config.data_source_id = sourceSelect.value || null; adaptPopulationWidgets(); renderInspector(); scheduleSave(); });
    root.querySelector('[data-dashboard-viewer]').addEventListener('change', event => { state.config.map.geo_viewer_id = event.target.value || null; scheduleSave(); });
    root.querySelector('[data-join-layer]').addEventListener('input', event => { state.config.map.join_layer_field = event.target.value; scheduleSave(); });
    root.querySelector('[data-join-data]').addEventListener('input', event => { state.config.map.join_data_field = event.target.value; scheduleSave(); });
    root.querySelector('[data-dashboard-preview]').href = root.dataset.previewUrl;
    root.querySelector('[data-run-diagnostic]').addEventListener('click', async () => {
        const button = root.querySelector('[data-run-diagnostic]'); const result = root.querySelector('[data-diagnostic-result]'); result.textContent = 'Comprobando códigos…'; button.disabled = true;
        const controller = new AbortController(); const timeout = window.setTimeout(() => controller.abort(), 60000);
        try {
            if (state.timer || state.saving) {
                const saveResponse = await flushSave();
                if (! saveResponse.ok) throw new Error('No fue posible guardar los campos de relación. Revise la configuración.');
            }
            const response = await fetch(root.dataset.diagnosticUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            if (! response.ok) throw new Error(data.message || `La comprobación respondió con error ${response.status}.`);
            result.textContent = `${data.matched} códigos relacionados · ${data.data_without_geometry.length} sin geometría · ${data.geometry_without_data.length} geometrías sin datos · ${data.duplicate_codes.length} duplicados${data.type_warning ? ` · ${data.type_warning}` : ''}`;
        } catch (error) {
            result.textContent = controller.signal.aborted ? 'La comprobación tardó más de 60 segundos. Revise los campos elegidos o consulte el registro del servidor.' : error.message;
        } finally {
            window.clearTimeout(timeout); button.disabled = false;
        }
    });
    root.querySelectorAll('form').forEach(form => form.addEventListener('submit', async event => {
        if (form.dataset.configSaved === 'true') return;
        event.preventDefault();
        const response = await flushSave();
        if (! response.ok) return;
        form.dataset.configSaved = 'true';
        form.submit();
    }));
    if (adaptPopulationWidgets()) scheduleSave();
    renderGrid(); renderInspector();
}

async function initializeViewer(root) {
    try {
        const response = await fetch(root.dataset.configUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (! response.ok) throw new Error('No fue posible cargar la configuración.');
        const payload = await response.json(); const config = payload.config || {}; const widgets = config.widgets || [];
        root.innerHTML = `<header class="dashboard-public-head"><div><p>GOBERNACIÓN DEL META · SIID 2.0</p><h1>${escapeHtml(payload.dashboard.name)}</h1></div><div><strong data-selection-label>Vista departamental</strong><button type="button" class="btn-small" data-clear-selection hidden>Limpiar selección</button></div></header><section class="dashboard-runtime-grid" data-runtime-grid></section>`;
        const grid = root.querySelector('[data-runtime-grid]'); const runtime = { selection: null, map: null, selectedLayer: null };
        widgets.forEach(widget => {
            const card = document.createElement('article'); card.className = `dashboard-runtime-widget type-${widget.type}`; card.style.gridColumn = `${widget.x + 1} / span ${Math.min(widget.w, 12 - widget.x)}`; card.style.gridRow = `${widget.y + 1} / span ${widget.h}`; card.dataset.widget = widget.id;
            card.innerHTML = `<h2>${escapeHtml(widget.title)}</h2><div class="dashboard-widget-content" data-widget-content><span class="dashboard-loading-inline">Cargando…</span></div>`; grid.append(card);
        });
        const refresh = async () => Promise.all(widgets.filter(widget => ! ['map', 'text'].includes(widget.type)).map(async widget => {
            const filters = { ...(config.global_filters || {}) }; if (runtime.selection && widget.scope === 'seleccion_territorial') filters[config.map?.join_data_field || 'codigo_dane'] = runtime.selection.value;
            const url = new URL(payload.query_url, window.location.href); url.searchParams.set('widget', widget.id); Object.entries(filters).forEach(([key, value]) => { if (value !== null && value !== '') url.searchParams.set(`filters[${key}]`, value); });
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const container = grid.querySelector(`[data-widget="${CSS.escape(widget.id)}"] [data-widget-content]`);
            if (! response.ok) { container.textContent = 'No fue posible consultar este componente.'; return; }
            renderWidget(container, widget, await response.json(), config.theme || {}, value => { config.global_filters ||= {}; config.global_filters[widget.query?.category] = value; refresh(); });
        }));
        const selectTerritory = (value, label, layer) => {
            if (runtime.selectedLayer) runtime.selectedLayer.setStyle({ weight: runtime.selectedLayer.options._baseWeight || 1 });
            runtime.selection = { value: String(value), label }; runtime.selectedLayer = layer; layer?.setStyle({ weight: 4, color: '#f59e0b' });
            root.querySelector('[data-selection-label]').textContent = label; root.querySelector('[data-clear-selection]').hidden = false; refresh();
        };
        root.querySelector('[data-clear-selection]').addEventListener('click', () => { if (runtime.selectedLayer) runtime.selectedLayer.setStyle({ weight: runtime.selectedLayer.options._baseWeight || 1 }); runtime.selection = null; runtime.selectedLayer = null; root.querySelector('[data-selection-label]').textContent = 'Vista departamental'; root.querySelector('[data-clear-selection]').hidden = true; refresh(); });
        const mapWidget = widgets.find(widget => widget.type === 'map');
        if (mapWidget) await renderMap(grid.querySelector(`[data-widget="${CSS.escape(mapWidget.id)}"] [data-widget-content]`), payload.map, config.map || {}, selectTerritory);
        widgets.filter(widget => widget.type === 'text').forEach(widget => { grid.querySelector(`[data-widget="${CSS.escape(widget.id)}"] [data-widget-content]`).textContent = widget.text || ''; });
        await refresh();
    } catch (error) { root.innerHTML = `<div class="dashboard-error">${escapeHtml(error.message)}</div>`; }
}

async function renderMap(container, mapConfig, relation, selectTerritory) {
    container.innerHTML = ''; if (! mapConfig) { container.textContent = 'Seleccione un geovisor para mostrar el mapa.'; return; }
    const response = await fetch(mapConfig.config_url, { credentials: 'same-origin' }); if (! response.ok) { container.textContent = 'No fue posible cargar el geovisor.'; return; }
    const config = await response.json(); const map = L.map(container, { zoomControl: true }).setView(config.viewer.center, config.viewer.zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    await Promise.all(config.layers.filter(layer => layer.source.type === 'geojson').map(async layer => {
        const layerResponse = await fetch(layer.source.url, { credentials: 'same-origin' }); if (! layerResponse.ok) return;
        const data = await layerResponse.json(); const style = { color: layer.style.color || '#2563eb', fillColor: layer.style.fillColor || '#60a5fa', fillOpacity: .45, weight: layer.style.weight || 1 };
        L.geoJSON(data, { style, pointToLayer: (_feature, latlng) => L.circleMarker(latlng, { ...style, radius: layer.style.radius || 6 }), onEachFeature: (feature, featureLayer) => {
            featureLayer.options._baseWeight = style.weight; const properties = feature.properties || {}; const value = properties[relation.join_layer_field || 'codigo_dane']; const label = properties.nombre || properties.municipio || properties.name || value;
            if (value !== undefined && value !== null) featureLayer.on('click', () => selectTerritory(value, String(label || value), featureLayer));
        } }).addTo(map);
    }));
    window.setTimeout(() => map.invalidateSize(), 50);
}

function renderWidget(container, widget, result, theme, onFilter) {
    container.innerHTML = '';
    if (widget.type === 'indicator') { const strong = document.createElement('strong'); strong.className = 'dashboard-indicator-value'; strong.textContent = numberFormat.format(result.value || 0); container.append(strong); return; }
    if (widget.type === 'table') { renderTable(container, result); return; }
    const rows = result.rows || [];
    if (! rows.length) { container.textContent = 'Sin datos para la selección.'; return; }
    if (widget.type === 'donut') { renderDonut(container, rows, theme); return; }
    if (widget.type === 'pyramid') { renderPyramid(container, rows, theme); return; }
    if (widget.type === 'filter') { const select = document.createElement('select'); select.innerHTML = `<option value="">Todos</option>${rows.map(row => `<option value="${escapeHtml(row.label)}">${escapeHtml(row.label)}</option>`).join('')}`; select.addEventListener('change', () => onFilter(select.value)); container.append(select); return; }
    if (widget.type === 'line') { renderLine(container, rows, theme); return; }
    renderBars(container, rows, false, theme);
}

function renderDonut(container, rows, theme) {
    const total = rows.reduce((sum, row) => sum + Number(row.value || 0), 0) || 1; let offset = 0; const colors = [theme.primary || '#047857', '#3b82f6', '#f59e0b', '#e879f9'];
    const circles = rows.map((row, index) => { const length = Number(row.value || 0) / total * 100; const circle = `<circle cx="58" cy="58" r="42" fill="none" stroke="${colors[index % colors.length]}" stroke-width="20" stroke-dasharray="${length} ${100 - length}" stroke-dashoffset="-${offset}" pathLength="100"/>`; offset += length; return circle; }).join('');
    container.innerHTML = `<div class="dashboard-donut"><svg viewBox="0 0 116 116" role="img" aria-label="Gráfico de distribución">${circles}<circle cx="58" cy="58" r="27" fill="white"/></svg><ul>${rows.map((row, index) => `<li><i style="background:${colors[index % colors.length]}"></i><span>${escapeHtml(row.label)}</span><strong>${numberFormat.format(Number(row.value || 0) / total * 100)} %</strong></li>`).join('')}</ul></div>`;
}

function renderBars(container, rows, line, theme) {
    const max = Math.max(...rows.map(row => Number(row.value || 0)), 1); container.innerHTML = `<div class="dashboard-bars ${line ? 'is-line' : ''}">${rows.map(row => `<div><span>${escapeHtml(row.label)}</span><i style="--value:${Number(row.value || 0) / max * 100}%;--color:${theme.primary || '#047857'}"></i><strong>${numberFormat.format(row.value || 0)}</strong></div>`).join('')}</div>`;
}

function renderLine(container, rows, theme) {
    const values = rows.map(row => Number(row.value || 0)); const max = Math.max(...values, 1); const width = 600; const height = 180; const points = values.map((value, index) => `${rows.length === 1 ? width / 2 : index / (rows.length - 1) * width},${height - value / max * (height - 30) - 10}`).join(' ');
    container.innerHTML = `<div class="dashboard-line"><svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Gráfico de líneas"><polyline points="${points}" fill="none" stroke="${theme.primary || '#047857'}" stroke-width="5" stroke-linejoin="round"/>${points.split(' ').map(point => { const [x,y] = point.split(','); return `<circle cx="${x}" cy="${y}" r="5" fill="${theme.primary || '#047857'}"/>`; }).join('')}</svg><div>${rows.map(row => `<span>${escapeHtml(row.label)}<strong>${numberFormat.format(row.value || 0)}</strong></span>`).join('')}</div></div>`;
}

function renderPyramid(container, rows, theme) {
    const normalized = rows.map(row => { const series = row.series || []; return { label: row.label, female: Number(series.find(item => /fem|mujer/i.test(item.label))?.value || 0), male: Number(series.find(item => /masc|hombre/i.test(item.label))?.value || 0) }; }); const max = Math.max(...normalized.flatMap(row => [row.female, row.male]), 1);
    container.innerHTML = `<div class="dashboard-pyramid" role="img" aria-label="Pirámide poblacional">${normalized.map(row => `<div><strong>${numberFormat.format(row.female)}</strong><i class="female" style="--value:${row.female / max * 100}%;--color:${theme.female || '#e89ca3'}"></i><span>${escapeHtml(row.label)}</span><i class="male" style="--value:${row.male / max * 100}%;--color:${theme.male || '#1683c4'}"></i><strong>${numberFormat.format(row.male)}</strong></div>`).join('')}</div>`;
}

function renderTable(container, result) {
    const table = document.createElement('table'); table.className = 'dashboard-table'; const head = table.createTHead().insertRow(); (result.columns || []).forEach(column => { const th = document.createElement('th'); th.textContent = column; head.append(th); }); const body = table.createTBody(); (result.rows || []).forEach(row => { const tr = body.insertRow(); (result.columns || []).forEach(column => { const td = tr.insertCell(); td.textContent = row[column] ?? '—'; }); }); container.append(table);
}

function widgetIcon(type) { return ({ map: '🗺', indicator: '123', bar: '▥', line: '⌁', donut: '◉', pyramid: '◀▶', table: '▦', text: 'T', filter: '⌄' })[type] || '□'; }
function escapeHtml(value) { const node = document.createElement('span'); node.textContent = value ?? ''; return node.innerHTML; }
