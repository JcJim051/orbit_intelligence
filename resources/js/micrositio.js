import { montarTablerosSuperset } from './superset-embed.js';

let solicitud;
let desmontarTablero;
let version = 0;

function iniciarContenido() {
    const actual = ++version;
    const visor = document.querySelector('.pdf-dialog');
    if (visor) {
        const marco = visor.querySelector('iframe');
        document.querySelectorAll('[data-pdf-preview]').forEach(boton => {
            boton.addEventListener('click', () => {
                visor.querySelector('h2').textContent = boton.dataset.documentName;
                visor.querySelector('[data-pdf-download]').href = boton.dataset.downloadUrl;
                marco.src = boton.dataset.pdfPreview;
                visor.showModal();
            });
        });
        visor.querySelector('[data-pdf-close]').addEventListener('click', () => visor.close());
        visor.addEventListener('close', () => marco.removeAttribute('src'));
    }
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
        desmontarTablero?.();
        desmontarTablero = undefined;
        principal.replaceChildren(...contenido.childNodes);
        document.querySelector('[data-micrositio-nav]').replaceWith(menu);
        document.title = pagina.title;
        if (guardar) history.pushState({}, '', url);
        iniciarContenido();
        const destino = url.hash ? document.getElementById(decodeURIComponent(url.hash.slice(1))) : principal;
        destino?.scrollIntoView({ behavior: 'instant', block: 'start' });
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