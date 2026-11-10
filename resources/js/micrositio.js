import { prepararPdf } from './pdf-viewer.js';
import { montarTablerosSuperset } from './superset-embed.js';

let solicitud;
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
    const actual = ++version;
    const visor = document.querySelector('.pdf-dialog');
    if (visor) desmontarPdf = prepararPdf(visor);
    const botones = [...document.querySelectorAll('.tabs button[data-tema]')];
    const mostrar = slug => {
        botones.forEach(boton => boton.classList.toggle('on', boton.dataset.tema === slug));
        document.querySelectorAll('.ind').forEach(tarjeta => {
            tarjeta.style.display = tarjeta.dataset.tema === slug ? '' : 'none';
        });
    };
    botones.forEach(boton => boton.addEventListener('click', () => mostrar(boton.dataset.tema)));
    if (botones[0]) mostrar(botones[0].dataset.tema);
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
        if (!actual.signal.aborted) window.location.assign(url.href);
    } finally {
        if (solicitud === actual) principal.removeAttribute('aria-busy');
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
window.addEventListener('popstate', () => navegar(new URL(location.href), false));
document.addEventListener('DOMContentLoaded', iniciarContenido);
// Kit UI 9.2, página 13: retorno visible al desplazarse y foco al inicio.
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
