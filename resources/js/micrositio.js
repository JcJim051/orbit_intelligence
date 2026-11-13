import { prepararPdf } from './pdf-viewer.js';
import { montarTablerosSuperset } from './superset-embed.js';

let solicitud;
let destinoFallido;
let desmontarTablero;
let desmontarPdf;
let version = 0;

function prepararMenu() {
    const boton = document.querySelector('.menu-toggle');
    const menu = document.querySelector('[data-micrositio-nav]');
    if (!boton || !menu) return;
    boton.hidden = false;
    document.documentElement.classList.add('menu-enhanced');
    boton.setAttribute('aria-expanded', 'false');
    menu.classList.remove('is-open');
}

function montarSeriesTerritoriales() {
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const crearSvg = (tag, attributes = {}) => {
        const element = document.createElementNS(svgNamespace, tag);
        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
        return element;
    };
    const valoresUnicos = values => [...new Set(values)];
    const completarSelect = (select, values, selected) => {
        select.replaceChildren(...values.map(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.selected = value === selected;
            return option;
        }));
    };
    const fecha = value => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`)) : '—';

    document.querySelectorAll('[data-territorial-series]').forEach(container => {
        const payload = JSON.parse(container.querySelector('[data-territorial-series-payload]')?.textContent || '{}');
        const series = payload.series || [];
        if (!series.length) return;

        const number = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 6 });
        const territorySelect = container.querySelector('[data-territorial-filter="territorio"]');
        const typeSelect = container.querySelector('[data-territorial-filter="tipo"]');
        const valueSelect = container.querySelector('[data-territorial-filter="valor"]');
        const chart = container.querySelector('[data-territorial-chart]');
        const tableBody = container.querySelector('[data-territorial-table-body]');
        const summary = container.querySelector('[data-territorial-series-summary]');
        const initial = series.find(item => item.predeterminada) || series[0];

        const seriesForTerritory = () => series.filter(item => item.territorio === territorySelect.value);
        const seriesForType = () => seriesForTerritory().filter(item => item.desagregacion_tipo === typeSelect.value);

        const updateValues = preferred => {
            const values = valoresUnicos(seriesForType().map(item => item.desagregacion_valor));
            completarSelect(valueSelect, values, values.includes(preferred) ? preferred : values[0]);
        };
        const updateTypes = (preferredType, preferredValue) => {
            const types = valoresUnicos(seriesForTerritory().map(item => item.desagregacion_tipo));
            completarSelect(typeSelect, types, types.includes(preferredType) ? preferredType : types[0]);
            updateValues(preferredValue);
        };

        const renderChart = item => {
            chart.replaceChildren();
            const points = item.puntos;
            const width = 900;
            const height = 320;
            const left = 90;
            const right = 28;
            const top = 24;
            const bottom = 58;
            const plotWidth = width - left - right;
            const plotHeight = height - top - bottom;
            const values = points.map(point => Number(point.valor));
            const rawMin = Math.min(...values);
            const rawMax = Math.max(...values);
            const padding = rawMin === rawMax ? Math.max(Math.abs(rawMin) * .1, 1) : 0;
            const min = rawMin - padding;
            const max = rawMax + padding;
            const range = Math.max(max - min, 1);

            for (let index = 0; index <= 4; index++) {
                const y = top + (plotHeight * index / 4);
                chart.appendChild(crearSvg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'territorial-chart-grid' }));
                const label = crearSvg('text', { x: left - 12, y: y + 4, 'text-anchor': 'end', class: 'territorial-chart-label' });
                label.textContent = number.format(max - (range * index / 4));
                chart.appendChild(label);
            }

            const coordinates = points.map((point, index) => ({
                point,
                x: left + (points.length === 1 ? plotWidth / 2 : plotWidth * index / (points.length - 1)),
                y: top + plotHeight - ((Number(point.valor) - min) / range * plotHeight),
            }));

            if (coordinates.length > 1) {
                const line = crearSvg('polyline', {
                    points: coordinates.map(point => `${point.x},${point.y}`).join(' '),
                    class: 'territorial-chart-line',
                });
                chart.appendChild(line);
            }

            coordinates.forEach(({ point, x, y }, index) => {
                const circle = crearSvg('circle', { cx: x, cy: y, r: 6, class: 'territorial-chart-point', tabindex: 0 });
                const title = crearSvg('title');
                title.textContent = `${point.periodo}: ${number.format(point.valor)} ${payload.unidad || ''}`.trim();
                circle.appendChild(title);
                chart.appendChild(circle);

                if (coordinates.length <= 10 || index % Math.ceil(coordinates.length / 10) === 0 || index === coordinates.length - 1) {
                    const label = crearSvg('text', { x, y: height - 24, 'text-anchor': 'middle', class: 'territorial-chart-label' });
                    label.textContent = point.periodo.length > 14 ? `${point.periodo.slice(0, 12)}…` : point.periodo;
                    chart.appendChild(label);
                }
            });
        };

        const render = () => {
            const item = series.find(candidate => candidate.territorio === territorySelect.value
                && candidate.desagregacion_tipo === typeSelect.value
                && candidate.desagregacion_valor === valueSelect.value) || series[0];
            summary.textContent = `${item.territorio} · ${item.desagregacion_tipo}: ${item.desagregacion_valor} · ${item.puntos.length} periodo(s)`;
            renderChart(item);
            tableBody.replaceChildren(...item.puntos.map(point => {
                const row = document.createElement('tr');
                [point.periodo, fecha(point.fecha_corte), item.territorio, `${item.desagregacion_tipo}: ${item.desagregacion_valor}`, `${number.format(point.valor)} ${payload.unidad || ''}`.trim()].forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                return row;
            }));
        };

        completarSelect(territorySelect, valoresUnicos(series.map(item => item.territorio)), initial.territorio);
        updateTypes(initial.desagregacion_tipo, initial.desagregacion_valor);
        territorySelect.addEventListener('change', () => { updateTypes(); render(); });
        typeSelect.addEventListener('change', () => { updateValues(); render(); });
        valueSelect.addEventListener('change', render);
        render();
    });
}

document.addEventListener('click', event => {
    const boton = event.target.closest('.menu-toggle');
    if (boton) {
        const abierto = boton.getAttribute('aria-expanded') !== 'true';
        boton.setAttribute('aria-expanded', String(abierto));
        document.getElementById(boton.getAttribute('aria-controls')).classList.toggle('is-open', abierto);
    }
});
document.addEventListener('keydown', event => {
    const boton = document.querySelector('.menu-toggle');
    if (event.key === 'Escape' && boton?.getAttribute('aria-expanded') === 'true') {
        prepararMenu();
        boton.focus();
    }
});

function iniciarContenido() {
    prepararMenu();

    const repo = document.querySelector('[data-publication-repository]');
    if (repo) {
        const query = repo.querySelector('[data-publication-query]');
        const sort = repo.querySelector('[data-publication-sort]');
        const docs = [...repo.querySelectorAll('[data-publication-document]')];
        const normalizar = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
        let paginaPublicaciones = 1;
        const paginacion = repo.querySelector('[data-publication-pagination]');
        const filtrar = (reiniciar = true) => {
            if (reiniciar) paginaPublicaciones = 1;
            const tipos = [...repo.querySelectorAll('[data-publication-type]:checked')].map(c => c.value);
            const years = [...repo.querySelectorAll('[data-publication-year]:checked')].map(c => c.value);
            const etiquetas = repo.querySelector('[data-publication-tags]');
            etiquetas.replaceChildren();
            const agregarEtiqueta = (nombre, quitar) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.textContent = nombre + ' ×';
                boton.setAttribute('aria-label', 'Quitar filtro ' + nombre);
                boton.addEventListener('click', () => {
                    quitar();
                    filtrar();
                    etiquetas.querySelector('button')?.focus({ preventScroll:true });
                    if (etiquetas.hidden) query.focus({ preventScroll:true });
                });
                etiquetas.append(boton);
            };
            if (query.value.trim()) agregarEtiqueta('Búsqueda: ' + query.value.trim(), () => query.value = '');
            repo.querySelectorAll('[data-publication-type]:checked, [data-publication-year]:checked').forEach(campo => {
                agregarEtiqueta((campo.hasAttribute('data-publication-type') ? 'Tipo: ' : 'Año: ') + campo.value, () => campo.checked = false);
            });
            etiquetas.hidden = !etiquetas.childElementCount;
            docs.sort((a,b) => sort.value === 'title' ? a.dataset.title.localeCompare(b.dataset.title, 'es') : (sort.value === 'old' ? 1 : -1) * a.dataset.date.localeCompare(b.dataset.date));
            const resultados = docs.filter(doc => normalizar(doc.dataset.title+' '+doc.dataset.type).includes(normalizar(query.value)) && (!tipos.length || tipos.includes(doc.dataset.type)) && (!years.length || years.includes(doc.dataset.year)));
            const totalPaginas = Math.ceil(resultados.length / 4);
            paginaPublicaciones = Math.min(paginaPublicaciones, Math.max(1, totalPaginas));
            const visibles = new Set(resultados.slice((paginaPublicaciones - 1) * 4, paginaPublicaciones * 4));
            docs.forEach(doc => {
                doc.hidden = !visibles.has(doc);
                repo.querySelector('[data-publication-results]').append(doc);
            });
            repo.querySelector('[data-publication-count]').textContent = resultados.length + (resultados.length === 1 ? ' documento' : ' documentos');
            repo.querySelector('[data-publication-empty]').hidden = resultados.length !== 0;
            paginacion.replaceChildren();
            const agregarPagina = (etiqueta, destino, nombre) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.textContent = etiqueta;
                boton.setAttribute('aria-label', nombre);
                if (destino === paginaPublicaciones) boton.setAttribute('aria-current', 'page');
                boton.addEventListener('click', () => {
                    paginaPublicaciones = destino;
                    filtrar(false);
                    paginacion.querySelector('[aria-current="page"]')?.focus({ preventScroll:true });
                });
                paginacion.append(boton);
            };
            if (paginaPublicaciones > 1) agregarPagina('‹', paginaPublicaciones - 1, 'Página anterior');
            for (let pagina = 1; pagina <= totalPaginas; pagina++) {
                if (pagina !== 1 && pagina !== totalPaginas && Math.abs(pagina - paginaPublicaciones) > 1) {
                    if (pagina === paginaPublicaciones - 2 || pagina === paginaPublicaciones + 2) {
                        const puntos = document.createElement('span'); puntos.textContent = '…'; paginacion.append(puntos);
                    }
                    continue;
                }
                agregarPagina(String(pagina), pagina, 'Página ' + pagina);
            }
            if (paginaPublicaciones < totalPaginas) agregarPagina('›', paginaPublicaciones + 1, 'Página siguiente');
            paginacion.hidden = !totalPaginas;
        };
        query.addEventListener('input', () => filtrar());
        repo.addEventListener('change', () => filtrar());
        repo.querySelector('[data-publication-clear]').addEventListener('click', () => {
            query.value = ''; sort.value = 'recent';
            repo.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            filtrar(); query.focus();
        });
        filtrar();
    }
    const buscador = document.querySelector('[data-indicator-search]');
    if (buscador) {
        const campo = buscador.querySelector('input');
        const limpiar = buscador.querySelector('[data-search-clear]');
        const estado = buscador.querySelector('[data-search-status]');
        const enlaces = [...document.querySelectorAll('[data-busqueda]')];
        const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
        const filtrar = () => {
            const consulta = normalizar(campo.value);
            let cantidad = 0;
            enlaces.forEach(enlace => {
                const coincide = normalizar(enlace.querySelector('b').textContent).includes(consulta);
                enlace.hidden = !coincide;
                if (coincide) cantidad++;
            });
            limpiar.hidden = campo.value.length === 0;
            estado.textContent = cantidad === 0 ? 'No se encontraron indicadores. Prueba con otro nombre o código, o usa Limpiar búsqueda para ver la lista completa.' :
                cantidad + (cantidad === 1 ? ' indicador disponible.' : ' indicadores disponibles.');
        };
        campo.addEventListener('input', filtrar);
        buscador.addEventListener('submit', event => { event.preventDefault(); filtrar(); });
        limpiar.addEventListener('click', () => { campo.value = ''; filtrar(); campo.focus(); });
        filtrar();
    }

    montarSeriesTerritoriales();

    const actual = ++version;
    const visor = document.querySelector('.pdf-dialog');
    if (visor) desmontarPdf = prepararPdf(visor);
    const botones = [...document.querySelectorAll('.tabs [role="tab"][data-tema]')];
    const mostrar = slug => {
        botones.forEach(boton => {
            const activo = boton.dataset.tema === slug;
            boton.classList.toggle('on', activo);
            boton.setAttribute('aria-selected', String(activo));
            boton.tabIndex = activo ? 0 : -1;
        });
        document.querySelectorAll('[data-theme-panel]').forEach(panel => {
            panel.hidden = panel.dataset.themePanel !== slug;
        });
    };
    botones.forEach((boton, indice) => {
        boton.addEventListener('click', () => mostrar(boton.dataset.tema));
        boton.addEventListener('keydown', event => {
            let destino;
            if (event.key === 'ArrowRight') destino = (indice + 1) % botones.length;
            if (event.key === 'ArrowLeft') destino = (indice - 1 + botones.length) % botones.length;
            if (event.key === 'Home') destino = 0;
            if (event.key === 'End') destino = botones.length - 1;
            if (destino === undefined) return;
            event.preventDefault();
            mostrar(botones[destino].dataset.tema);
            botones[destino].focus();
        });
    });
    if (botones[0]) mostrar(botones[0].dataset.tema);
    const ejes = [...document.querySelectorAll('[data-indicator-axis-button]')];
    const mostrarEje = id => {
        ejes.forEach(boton => {
            const activo = boton.dataset.indicatorAxisButton === id;
            boton.classList.toggle('is-active', activo);
            boton.setAttribute('aria-selected', String(activo));
        });
        document.querySelectorAll('[data-indicator-axis-panel]').forEach(panel => {
            panel.hidden = panel.dataset.indicatorAxisPanel !== id;
        });
    };
    ejes.forEach(boton => boton.addEventListener('click', () => mostrarEje(boton.dataset.indicatorAxisButton)));
    if (ejes[0]) mostrarEje(ejes[0].dataset.indicatorAxisButton);
    document.querySelectorAll('[data-looker-frame]').forEach(frame => {
        const container = frame.closest('[data-dashboard-container]');
        frame.addEventListener('load', () => {
            container?.classList.add('is-loaded');
            container?.querySelector('[data-dashboard-loader]')?.setAttribute('hidden', '');
        }, { once: true });
    });
    montarTablerosSuperset().then(desmontar => {
        if (actual !== version) desmontar?.();
        else desmontarTablero = desmontar;
    });
}

async function navegar(url, guardar = true) {
    solicitud?.abort();
    const actual = new AbortController();
    solicitud = actual;
    const principal = document.getElementById('contenido');
    principal.setAttribute('aria-busy', 'true');
    const aviso = document.querySelector('[data-navigation-feedback]');
    const mensaje = aviso.querySelector('[data-navigation-message]');
    const reintentar = aviso.querySelector('[data-navigation-retry]');
    aviso.hidden = false;
    aviso.classList.remove('has-error');
    mensaje.textContent = 'Cargando contenido…';
    reintentar.hidden = true;
    const espera = setTimeout(() => {
        if (solicitud === actual && !actual.signal.aborted) {
            mensaje.textContent = 'La carga está tardando más de lo habitual. Seguimos esperando la respuesta…';
        }
    }, 10000);
    let fallo = false;
    try {
        const respuesta = await fetch(url, { signal: actual.signal, headers: { Accept: 'text/html' } });
        if (!respuesta.ok || respuesta.redirected) throw new Error('Página no disponible');
        const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
        const contenido = pagina.getElementById('contenido');
        const menu = pagina.querySelector('[data-micrositio-nav]');
        if (!contenido || !menu) throw new Error('Página no compatible');
        if (actual.signal.aborted) return;
        desmontarPdf?.();
        desmontarPdf = undefined;
        desmontarTablero?.();
        desmontarTablero = undefined;
        principal.replaceChildren(...contenido.childNodes);
        document.querySelector('[data-micrositio-nav]').replaceWith(menu);
        document.title = pagina.title;
        if (guardar) history.pushState({}, '', url);
        iniciarContenido();
        const destino = url.hash ? document.getElementById(decodeURIComponent(url.hash.slice(1))) : null;
        if (destino) {
            destino.scrollIntoView({ behavior: 'instant', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'instant' });
        }
        principal.focus({ preventScroll: true });
    } catch (error) {
        if (!actual.signal.aborted && solicitud === actual) {
            fallo = true;
            destinoFallido = url;
            aviso.classList.add('has-error');
            mensaje.textContent = 'No se pudo cargar el contenido. Revisa tu conexión e inténtalo de nuevo.';
            reintentar.hidden = false;
        }
    } finally {
        clearTimeout(espera);
        if (solicitud === actual) {
            principal.removeAttribute('aria-busy');
            if (!fallo) aviso.hidden = true;
        }
    }
}

document.addEventListener('click', event => {
    const enlace = event.target.closest('a[href]');
    if (!enlace || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || enlace.hasAttribute('download') || (enlace.target && enlace.target !== '_self')) return;
    const url = new URL(enlace.href);
    const rutas = JSON.parse(document.querySelector('[data-micrositio-nav]')?.dataset.rutas || '[]');
    if (url.origin !== location.origin || !rutas.includes(url.pathname) || url.searchParams.get('preview') !== new URL(location.href).searchParams.get('preview')) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
    event.preventDefault();
    navegar(url);
});
document.querySelector('[data-navigation-retry]')?.addEventListener('click', () => {
    if (destinoFallido) navegar(destinoFallido, location.href !== destinoFallido.href);
});
window.addEventListener('popstate' , () => navegar(new URL(location.href), false));
document.addEventListener('DOMContentLoaded', iniciarContenido);
const volverArriba = document.querySelector('.back-to-top');
if (volverArriba) {
    const actualizarRetorno = () => {
        volverArriba.hidden = window.scrollY < 200;
    };
    window.addEventListener('scroll', actualizarRetorno, { passive: true });
    window.addEventListener('resize', actualizarRetorno);
    volverArriba.addEventListener('click', () => {
        document.querySelector('.top')?.focus({ preventScroll: true });
        window.scrollTo({ top: 0, behavior: 'instant' });
        actualizarRetorno();
    });
    actualizarRetorno();
}

let escalaTexto = 100;
const controlesAccesibilidad = document.querySelector('.a11y');
controlesAccesibilidad?.addEventListener('click', event => {
    const boton = event.target.closest('[data-accessibility]');
    if (!boton) return;
    const accion = boton.dataset.accessibility;
    if (accion === 'contrast') {
        const activo = document.body.classList.toggle('hc');
        boton.setAttribute('aria-pressed', String(activo));
    } else {
        escalaTexto = Math.max(80, Math.min(200, escalaTexto + (accion === 'increase' ? 10 : -10)));
        document.documentElement.style.fontSize = `${escalaTexto}%`;
        controlesAccesibilidad.querySelector('[data-accessibility="decrease"]').classList.toggle('is-selected', escalaTexto < 100);
        controlesAccesibilidad.querySelector('[data-accessibility="increase"]').classList.toggle('is-selected', escalaTexto > 100);

        controlesAccesibilidad.querySelector('[data-accessibility="decrease"]').disabled = escalaTexto === 80;
        controlesAccesibilidad.querySelector('[data-accessibility="increase"]').disabled = escalaTexto === 200;
    }
});
