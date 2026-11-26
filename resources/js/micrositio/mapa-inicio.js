import { geoArea, geoCentroid, geoMercator, geoPath } from 'd3-geo';
import { ascending, max, quantileSorted } from 'd3-array';
import { abrirModal, cerrarModal, escapar, iniciarModal } from './modal.js';

const PALETAS = {
    poblacion: ['#2b8e57', '#94c47e', '#efcd6a', '#ea935b', '#c8463e'],
    petroleo: ['#2c7bb6', '#00a6ca', '#ffffbf', '#fdae61', '#d7191c'],
    agricultura: ['#762a83', '#af8dc3', '#f7f7b9', '#7fbf7b', '#1b7837'],
    azul: ['#e6edfa', '#b4c8ef', '#769ce0', '#3a6bcb', '#0943b5'],
    verde: ['#e5f4ec', '#b2ddc3', '#72bf93', '#2c975f', '#006b38'],
    semaforo: ['#c8463e', '#ea935b', '#efcd6a', '#94c47e', '#2b8e57'],
};
const SIN_DATO = '#e3e6ec';

function orientar(coleccion) {
    coleccion.features?.forEach(feature => {
        if (!feature.geometry || geoArea(feature) <= Math.PI) return;
        const invertir = anillo => anillo.slice().reverse();
        if (feature.geometry.type === 'Polygon') {
            feature.geometry.coordinates = feature.geometry.coordinates.map(invertir);
        } else if (feature.geometry.type === 'MultiPolygon') {
            feature.geometry.coordinates = feature.geometry.coordinates.map(poligono => poligono.map(invertir));
        }
    });
}

const numero = (capa, valor) => new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: capa.decimales || 0,
    maximumFractionDigits: capa.decimales || 0,
}).format(valor);

const compacto = new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 });
const entero = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });

function formato(capa, valor, conUnidad = true) {
    if (valor == null) return 's. d.';
    const texto = numero(capa, valor);
    if (capa.unidad === '%') return `${texto} %`;
    if (!conUnidad) return texto;
    const sufijo = {
        habitantes: 'hab.',
        barriles_mes: 'barriles/mes',
        toneladas: 't',
    }[capa.unidad] || capa.unidad_etiqueta || capa.unidad;
    return `${texto} ${sufijo}`;
}

function rotulo(capa) {
    return capa.rotulo_corto || capa.nombre;
}

function polaridad(capa, configuracion) {
    if (configuracion.polaridad && configuracion.polaridad !== 'auto' && capa.codigo === configuracion.capa) {
        return configuracion.polaridad;
    }
    return capa.polaridad || 'alto_bueno';
}

function paleta(capa, configuracion) {
    let nombre = configuracion.paleta && configuracion.paleta !== 'auto' && capa.codigo === configuracion.capa
        ? configuracion.paleta
        : ({
            'POB-2026': 'poblacion',
            PETROLEO: 'petroleo',
            'AGRO-PROD': 'agricultura',
        }[capa.codigo] || 'azul');
    const colores = (PALETAS[nombre] || PALETAS.semaforo).slice();
    if (polaridad(capa, configuracion) === 'alto_malo' && nombre === 'semaforo') colores.reverse();
    return colores;
}

function cortes(capa, clases = 5) {
    const valores = capa.valores.map(item => item.valor).filter(valor => valor != null).sort(ascending);
    if (!valores.length) return [];
    const puntos = Array.from({ length: Math.max(1, clases - 1) }, (_, indice) => (indice + 1) / clases);
    const cuantiles = puntos.map(punto => quantileSorted(valores, punto)).filter(valor => valor != null);
    const redondear = capa.unidad === '%'
        ? valor => Math.round(valor)
        : valor => {
            if (!valor) return 0;
            const magnitud = Math.pow(10, Math.max(0, Math.floor(Math.log10(Math.abs(valor))) - 1));
            return Math.round(valor / magnitud) * magnitud;
        };
    return [...new Set(cuantiles.map(redondear))];
}

function clase(valor, limites) {
    if (valor == null) return -1;
    let indice = 0;
    while (indice < limites.length && valor > limites[indice]) indice += 1;
    return indice;
}

function colorDe(indice, colores, limites) {
    if (indice < 0) return SIN_DATO;
    if (!limites.length) return colores[0];
    const posicion = Math.round(indice * (colores.length - 1) / limites.length);
    return colores[posicion] || colores[colores.length - 1];
}

function etiquetaRango(capa, limites, indice) {
    const inferior = indice ? limites[indice - 1] : null;
    const superior = indice < limites.length ? limites[indice] : null;
    const texto = valor => (capa.unidad === '%' ? entero.format(valor) : compacto.format(valor).replace(/\s?(mil|k)\b/i, ' mil'));
    const unidad = capa.unidad === '%' ? ' %' : '';
    if (inferior == null) return `≤ ${texto(superior)}${unidad}`;
    if (superior == null) return `> ${texto(inferior)}${unidad}`;
    return `${texto(inferior)}–${texto(superior)}${unidad}`;
}

function puesto(codigo, capa, configuracion) {
    const filas = capa.valores.filter(item => item.valor != null).slice();
    const mayorPrimero = polaridad(capa, configuracion) !== 'alto_malo';
    filas.sort((a, b) => (mayorPrimero ? b.valor - a.valor : a.valor - b.valor));
    return filas.findIndex(item => item.divipola === codigo) + 1;
}

function textoPuesto(capa, configuracion) {
    if (String(capa.codigo).startsWith('POB')) return '1 = mayor población';
    if (polaridad(capa, configuracion) === 'neutral') return `1 = mayor ${String(capa.unidad_etiqueta || capa.unidad).toLocaleLowerCase('es-CO')}`;
    if (polaridad(capa, configuracion) === 'alto_malo') return `1 = menor ${capa.codigo.split('-')[0]}`;
    return '1 = mejor situación';
}

function plantilla(url, valores) {
    if (!url) return '#';
    return Object.entries(valores).reduce((salida, [clave, valor]) => salida.replaceAll(`{${clave}}`, valor), url);
}

export async function iniciar(raiz) {
    if (!raiz || raiz.dataset.mapaListo === '1') return;
    raiz.dataset.mapaListo = '1';
    iniciarModal();
    try {
    await iniciarMapa(raiz);
    } catch (error) {
        raiz.dataset.mapaListo = '0';
        throw error;
    }
}

async function iniciarMapa(raiz) {
    const configuracion = JSON.parse(raiz.dataset.config || '{}');
    const [datos, municipios, colombia] = await Promise.all([
        fetch(raiz.dataset.datos).then(respuesta => respuesta.json()),
        fetch(raiz.dataset.municipios).then(respuesta => respuesta.json()),
        fetch(raiz.dataset.colombia).then(respuesta => respuesta.json()),
    ]);
    orientar(municipios);
    orientar(colombia);
    const remoto = datos.configuracion || {};
    Object.assign(configuracion, {
        capa: configuracion.capa || remoto.capa_principal,
        paleta: configuracion.paleta || remoto.paleta,
        polaridad: configuracion.polaridad || remoto.polaridad,
        texto: configuracion.texto || remoto.texto_tarjeta,
        cta: configuracion.cta || remoto.texto_cta,
        urlCta: configuracion.urlCta || remoto.url_cta,
        urlFicha: configuracion.urlFicha || remoto.url_ficha_tecnica,
        urlMunicipio: configuracion.urlMunicipio || remoto.url_indicadores_municipio,
    });
    const capas = datos.capas || [];
    const porCodigo = Object.fromEntries(capas.map(capa => [capa.codigo, capa]));
    const hash = new URLSearchParams(location.hash.slice(1));
    let actual = porCodigo[hash.get('capa')] || porCodigo[configuracion.capa] || capas[0];
    let seleccionado = null;
    let focoPrevio = null;
    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const caja = raiz.querySelector('.mapbox');
    const svg = raiz.querySelector('.mapa-svg');
    const grupo = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    svg.append(grupo);
    const proyeccion = geoMercator();
    const camino = geoPath(proyeccion);
    let ancho = 640;
    let alto = 520;

    const ajustar = () => {
        ancho = caja.clientWidth || 640;
        alto = caja.clientHeight || 520;
        svg.setAttribute('viewBox', `0 0 ${ancho} ${alto}`);
        proyeccion.fitExtent([[96, 8], [ancho - 8, alto - 96]], municipios);
    };

    ajustar();
    const villavicencio = municipios.features.find(feature => feature.properties.divipola === '50001');
    const centroCapital = villavicencio ? geoCentroid(villavicencio) : [-73.63, 4.142];
    let puntoCapital = proyeccion(centroCapital);
    const capital = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    capital.setAttribute('fill', '#fff');
    capital.setAttribute('stroke', '#17233a');
    capital.setAttribute('pointer-events', 'none');
    const rotuloCapital = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    rotuloCapital.textContent = 'Villavicencio';
    rotuloCapital.setAttribute('font-weight', '700');
    rotuloCapital.setAttribute('fill', '#17233a');
    rotuloCapital.setAttribute('stroke', '#fff');
    rotuloCapital.setAttribute('paint-order', 'stroke');
    rotuloCapital.setAttribute('pointer-events', 'none');
    const caminos = municipios.features.map(feature => {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.classList.add('m');
        path.setAttribute('tabindex', '0');
        path.setAttribute('role', 'button');
        path.dataset.divipola = feature.properties.divipola;
        path.addEventListener('click', () => abrirMunicipio(feature.properties.divipola, false));
        path.addEventListener('keydown', evento => {
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                abrirMunicipio(feature.properties.divipola, false);
            }
        });
        path.addEventListener('mousemove', evento => mostrarTooltip(evento, feature));
        path.addEventListener('focus', () => mostrarTooltip(null, feature, path));
        path.addEventListener('mouseleave', ocultarTooltip);
        path.addEventListener('blur', ocultarTooltip);
        grupo.append(path);
        return { path, feature };
    });
    grupo.append(capital, rotuloCapital);

    const inset = raiz.querySelector('.inset');
    const proyeccionInset = geoMercator().fitExtent([[4, 4], [82, 100]], colombia);
    const caminoInset = geoPath(proyeccionInset);
    inset.setAttribute('viewBox', '0 0 86 104');
    colombia.features.forEach(feature => {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', caminoInset(feature));
        if (feature.properties.meta) path.classList.add('me');
        inset.append(path);
    });

    const pildoras = raiz.querySelector('.layerpills');
    caja.id = 'mapa-panel-inicio';
    caja.setAttribute('role', 'tabpanel');
    capas.slice(0, 4).forEach((capa, indice) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'lp';
        boton.dataset.codigo = capa.codigo;
        boton.textContent = rotulo(capa);
        boton.setAttribute('role', 'tab');
        boton.setAttribute('aria-controls', caja.id);
        boton.id = `capa-tab-${indice}`;
        boton.addEventListener('click', () => {
            actual = capa;
            pintar(false);
        });
        boton.addEventListener('keydown', evento => {
            if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(evento.key)) return;
            evento.preventDefault();
            const botones = [...pildoras.querySelectorAll('.lp')];
            const indiceDestino = evento.key === 'Home' ? 0 : evento.key === 'End' ? botones.length - 1
                : (botones.indexOf(boton) + (evento.key === 'ArrowRight' ? 1 : -1) + botones.length) % botones.length;
            const destino = botones[indiceDestino];
            destino.click();
            destino.focus();
        });
        pildoras.append(boton);
    });

    function valor(codigo, capa = actual) {
        return capa?.valores?.find(item => item.divipola === codigo)?.valor ?? null;
    }

    function municipio(codigo, capa = actual) {
        return capa?.valores?.find(item => item.divipola === codigo);
    }

    function mostrarTooltip(evento, feature, ancla) {
        const tooltip = raiz.querySelector('.tip');
        const registro = municipio(feature.properties.divipola);
        tooltip.textContent = `${feature.properties.divipola} — ${feature.properties.municipio}: ${formato(actual, registro?.valor)} (${actual.periodo})`;
        const recta = caja.getBoundingClientRect();
        const origen = evento ? { x: evento.clientX, y: evento.clientY } : ancla.getBoundingClientRect();
        tooltip.style.left = `${(evento ? origen.x : origen.left + origen.width / 2) - recta.left}px`;
        tooltip.style.top = `${(evento ? origen.y : origen.top) - recta.top}px`;
        tooltip.style.opacity = '1';
    }

    function ocultarTooltip() {
        raiz.querySelector('.tip').style.opacity = '0';
    }

    function pintar(instantaneo) {
        if (!actual) return;
        pildoras.querySelectorAll('.lp').forEach(boton => {
            const activo = boton.dataset.codigo === actual.codigo;
            boton.classList.toggle('on', activo);
            boton.setAttribute('aria-selected', String(activo));
            boton.tabIndex = activo ? 0 : -1;
            if (activo) caja.setAttribute('aria-labelledby', boton.id);
        });
        raiz.querySelector('.maphead h3').textContent = `${actual.codigo} — ${actual.nombre}`;
        const fuente = String(actual.fuente || '').split(' — ')[0];
        const nota = actual.nota ? `<div>${escapar(actual.nota)}</div>` : '';
        const fuenteActualizada = actual.fuente_actualizada_en
            ? ` · Fuente actualizada: ${escapar(new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium' }).format(new Date(actual.fuente_actualizada_en)))}`
            : '';
        raiz.querySelector('.maphead .meta').innerHTML = `Periodo ${escapar(actual.periodo)} · Fuente: ${escapar(fuente)} · <a href="${escapar(actual.fuente_url || '#')}" target="_blank" rel="noopener">ver fuente</a>${fuenteActualizada}${nota}`;
        const departamental = raiz.querySelector('.dep');
        if (actual.valor_departamental == null) {
            departamental.className = 'dep na';
            departamental.innerHTML = '<small>Dato departamental</small><b>No publicado en la fuente</b>';
        } else {
            departamental.className = 'dep';
            departamental.innerHTML = `<small>Dato departamental</small><b>${escapar(formato(actual, actual.valor_departamental))}</b>`;
        }
        const colores = paleta(actual, configuracion);
        const limites = cortes(actual, colores.length);
        caminos.forEach(({ path, feature }) => {
            const indice = clase(valor(feature.properties.divipola), limites);
            path.setAttribute('fill', colorDe(indice, colores, limites));
            path.setAttribute('d', camino(feature));
            const registro = municipio(feature.properties.divipola);
            path.setAttribute('aria-label', `${feature.properties.divipola} — ${feature.properties.municipio}: ${formato(actual, registro?.valor)} (${actual.periodo})`);
        });
        puntoCapital = proyeccion(centroCapital);
        const leyenda = raiz.querySelector('.legend');
        const grupos = [];
        const etiquetas = [];
        for (let indice = 0; indice <= limites.length; indice += 1) {
            etiquetas.push(etiquetaRango(actual, limites, indice));
            grupos.push(actual.valores.filter(item => clase(item.valor, limites) === indice));
        }
        const total = grupos.reduce((suma, grupo) => suma + grupo.length, 0);
        const titulo = actual.unidad_etiqueta || (actual.unidad === '%' ? 'Porcentaje' : actual.unidad);
        const textoPolaridad = polaridad(actual, configuracion) === 'neutral' || String(actual.codigo).startsWith('POB')
            ? 'Escala descriptiva (no indica mejor/peor)'
            : (polaridad(actual, configuracion) === 'alto_malo' ? '▲ Valor alto = situación desfavorable' : '▲ Valor alto = situación favorable');
        leyenda.innerHTML = `<h4>${titulo} · ${total} municipios por rango</h4><div class="rows">${etiquetas.map((etiqueta, indice) => `<button type="button" class="row" data-indice="${indice}"><span class="sw" style="background:${colorDe(indice, colores, limites)}"></span>${escapar(etiqueta)}<span class="n">${grupos[indice].length} mun.</span></button>`).join('')}</div><div class="pol">${textoPolaridad}</div>`;
        leyenda.querySelectorAll('.row').forEach(boton => {
            const indice = Number(boton.dataset.indice);
            const cantidad = grupos[indice].length;
            boton.disabled = cantidad === 0;
            boton.setAttribute('aria-haspopup', 'dialog');
            boton.setAttribute('aria-label', `Ver ${cantidad} ${cantidad === 1 ? 'municipio' : 'municipios'} del rango ${etiquetas[indice]} (${titulo})`);
            boton.addEventListener('click', () => {
                const filas = grupos[indice].slice().sort((a, b) => b.valor - a.valor);
                abrirModal(`<h3 id="modal-titulo">${escapar(actual.codigo)} — ${escapar(actual.nombre)}</h3><p class="note">Rango ${escapar(etiquetas[indice])} · ${filas.length} municipios · ${escapar(actual.periodo)}</p><table><tr><th>Municipio</th><th>Valor</th></tr>${filas.map(fila => `<tr><td><button type="button" class="linkcount" data-divipola="${fila.divipola}">${escapar(fila.divipola)} — ${escapar(fila.municipio)}</button></td><td class="num">${escapar(formato(actual, fila.valor))}</td></tr>`).join('')}</table>`, cuerpo => {
                    cuerpo.querySelectorAll('[data-divipola]').forEach(enlace => {
                        enlace.addEventListener('click', () => {
                            cerrarModal();
                            abrirMunicipio(enlace.dataset.divipola, false);
                        });
                    });
                });
            });
        });
        const tarjeta = raiz.querySelector('.float p');
        tarjeta.innerHTML = escapar(configuracion.texto || '29 municipios · capital Villavicencio').replace(/(\d+)\s+municipios/i, '<button type="button" class="linkcount" data-todos-municipios>$1 municipios</button>');
        tarjeta.querySelector('[data-todos-municipios]')?.addEventListener('click', () => listarMunicipios());
        raiz.querySelector('.float .ix-btn').textContent = `${configuracion.cta || 'Explorar en el visor'} →`;
        if (configuracion.urlCta) raiz.querySelector('.float .ix-btn').href = configuracion.urlCta;
        if (seleccionado) abrirMunicipio(seleccionado, true);
        else encuadrar(false, null, instantaneo);
        escribirHash();
        raiz.querySelector('.mapa-fallback')?.setAttribute('hidden', '');
    }

    function encuadrar(abierto, codigo, instantaneo) {
        let escala = 1;
        let x = 0;
        let y = 0;
        if (abierto) {
            const feature = municipios.features.find(item => item.properties.divipola === codigo);
            const [[x0, y0], [x1, y1]] = camino.bounds(feature);
            const anchoUtil = ancho - 300 - 16;
            const altoUtil = alto - 96 - 16;
            escala = Math.max(0.7, Math.min(2.6, 0.55 * Math.min(anchoUtil / (x1 - x0), altoUtil / (y1 - y0))));
            x = 8 + anchoUtil / 2 - escala * ((x0 + x1) / 2);
            y = 8 + altoUtil / 2 - escala * ((y0 + y1) / 2);
        }
        grupo.setAttribute('transform', `translate(${x},${y}) scale(${escala})`);
        if (!reducido && !instantaneo) grupo.style.transition = 'transform .45s ease';
        capital.setAttribute('cx', puntoCapital[0]);
        capital.setAttribute('cy', puntoCapital[1]);
        capital.setAttribute('r', 4.5 / escala);
        capital.setAttribute('stroke-width', 2 / escala);
        rotuloCapital.setAttribute('x', puntoCapital[0] + 8 / escala);
        rotuloCapital.setAttribute('y', puntoCapital[1] - 6 / escala);
        rotuloCapital.setAttribute('font-size', 11 / escala);
        rotuloCapital.setAttribute('stroke-width', 3 / escala);
        raiz.querySelector('.legend').classList.toggle('narrow', abierto);
        inset.classList.toggle('hidden', abierto);
    }

    function abrirMunicipio(codigo, instantaneo) {
        const registro = municipio(codigo);
        if (!registro) return;
        focoPrevio = focoPrevio || document.activeElement;
        seleccionado = codigo;
        encuadrar(true, codigo, instantaneo);
        caminos.forEach(({ path, feature }) => path.classList.toggle('sel', feature.properties.divipola === codigo));
        svg.classList.add('has-sel');
        raiz.querySelector('.float').classList.add('hidden');
        const lugar = puesto(codigo, actual, configuracion);
        const explicacion = textoPuesto(actual, configuracion);
        const barras = capas.map(capa => {
            const cantidad = valor(codigo, capa);
            const maximo = max(capa.valores, item => item.valor) || 1;
            const anchoBarra = cantidad == null ? 0 : Math.min(100, (cantidad / maximo) * 100);
            const linea = capa.valor_departamental != null && !String(capa.codigo).startsWith('POB')
                ? `<span class="dm" style="left:${Math.min(100, (capa.valor_departamental / maximo) * 100)}%" title="Dato departamental"></span>`
                : '';
            return `<div class="bar"><div class="lbl"><span>${escapar(capa.codigo)} — ${escapar(rotulo(capa))}</span><b>${escapar(formato(capa, cantidad, false))}</b></div><div class="track"><span class="fill" style="width:${anchoBarra}%;opacity:${capa.codigo === actual.codigo ? 1 : .45}"></span>${linea}</div></div>`;
        }).join('');
        const lateral = raiz.querySelector('.side');
        const grande = actual.unidad === '%'
            ? `${numero(actual, registro.valor)}<small>%</small>`
            : `${entero.format(registro.valor)}<small>hab.</small>`;
        const acciones = [
            [plantilla(configuracion.urlFicha, { codigo: actual.codigo }), 'Ver ficha técnica del indicador', 'ix-btn block'],
            [plantilla(configuracion.urlMunicipio, { divipola: codigo }), 'Ver indicadores del municipio', 'ix-btn ghost block'],
        ].filter(([url]) => url && url !== '#')
            .map(([url, texto, clase]) => `<a class="${clase}" href="${escapar(url)}">${texto}</a>`).join('');
        lateral.innerHTML = `<button type="button" class="x" aria-label="Cerrar">✕</button><div class="code">DIVIPOLA ${escapar(codigo)}</div><h4 id="mapa-municipio-titulo">${escapar(codigo)} — ${escapar(registro.municipio)}</h4><div style="font-size:12px;color:var(--muted)">${escapar(actual.codigo)} — ${escapar(actual.nombre)} (${escapar(actual.periodo)})</div><div class="big">${grande}</div><span class="rank" title="${escapar(explicacion)}">Puesto ${lugar} de 29 · ${escapar(explicacion)}</span><div class="bars"><h6>El municipio en ${capas.length} ${capas.length === 1 ? 'capa' : 'capas'} <span style="text-transform:none;letter-spacing:0">(barra = valor frente al máximo municipal; línea = Meta)</span></h6>${barras}</div>${acciones ? '<div class="actions">' + acciones + '</div>' : ''}`;
        lateral.hidden = false;
        lateral.classList.add('open');
        lateral.querySelector('.x').addEventListener('click', cerrarMunicipio);
        if (!instantaneo) lateral.querySelector('.x').focus();
        escribirHash();
    }

    function cerrarMunicipio() {
        if (!seleccionado) return;
        seleccionado = null;
        encuadrar(false, null, reducido);
        raiz.querySelector('.side').classList.remove('open');
        raiz.querySelector('.side').hidden = true;
        caminos.forEach(({ path }) => path.classList.remove('sel'));
        svg.classList.remove('has-sel');
        raiz.querySelector('.float').classList.remove('hidden');
        if (focoPrevio?.isConnected && typeof focoPrevio.focus === 'function') focoPrevio.focus();
        focoPrevio = null;
        escribirHash();
    }

    function listarMunicipios() {
        const filas = actual.valores.map(fila => `<tr><td><button type="button" class="linkcount" data-divipola="${fila.divipola}">${escapar(fila.divipola)} — ${escapar(fila.municipio)}</button></td><td class="num">${escapar(formato(actual, fila.valor))}</td></tr>`).join('');
        abrirModal(`<h3 id="modal-titulo">29 municipios del Meta</h3><p class="note">${escapar(actual.codigo)} — ${escapar(actual.nombre)} (${escapar(actual.periodo)})</p><table><tr><th>Municipio</th><th>Valor</th></tr>${filas}</table>`, cuerpo => {
            cuerpo.querySelectorAll('[data-divipola]').forEach(enlace => {
                enlace.addEventListener('click', () => {
                    cerrarModal();
                    abrirMunicipio(enlace.dataset.divipola, false);
                });
            });
        });
    }

    function escribirHash() {
        const parametros = new URLSearchParams(location.hash.slice(1));
        if (actual) parametros.set('capa', actual.codigo);
        if (seleccionado) parametros.set('mun', seleccionado);
        else parametros.delete('mun');
        history.replaceState(null, '', `#${parametros.toString()}`);
    }

    raiz.addEventListener('keydown', evento => {
        if (evento.key === 'Escape' && seleccionado && !document.getElementById('modal-micrositio')?.classList.contains('open')) {
            cerrarMunicipio();
        }
    });
    new ResizeObserver(() => {
        ajustar();
        pintar(true);
    }).observe(caja);
    pintar(true);
    if (hash.get('mun') && municipios.features.some(feature => feature.properties.divipola === hash.get('mun'))) {
        abrirMunicipio(hash.get('mun'), true);
    }
}

