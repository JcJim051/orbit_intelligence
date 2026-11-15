let retorno = null;

function focoInicial(dialogo) {
    const primero = dialogo.querySelector('button, a, [tabindex]:not([tabindex="-1"])');
    (primero || dialogo).focus();
}

export function abrirModal(html, despues) {
    const fondo = document.getElementById('modal-micrositio');
    const cuerpo = document.getElementById('modal-cuerpo');
    const dialogo = fondo?.querySelector('.modal');
    if (!fondo || !cuerpo || !dialogo) return;
    if (!fondo.classList.contains('open')) {
        retorno = document.activeElement;
    }
    cuerpo.innerHTML = html;
    fondo.hidden = false;
    fondo.classList.add('open');
    despues?.(cuerpo);
    focoInicial(dialogo);
}

export function cerrarModal() {
    const fondo = document.getElementById('modal-micrositio');
    if (!fondo?.classList.contains('open')) return;
    fondo.classList.remove('open');
    fondo.hidden = true;
    document.getElementById('modal-cuerpo').innerHTML = '';
    if (retorno instanceof HTMLElement) {
        retorno.focus();
    }
    retorno = null;
}

export function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[caracter]);
}

export function iniciarModal() {
    const fondo = document.getElementById('modal-micrositio');
    if (!fondo || fondo.dataset.listo) return;
    fondo.dataset.listo = '1';
    fondo.addEventListener('click', evento => {
        if (evento.target === fondo) cerrarModal();
    });
    fondo.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') {
            evento.stopPropagation();
            cerrarModal();
            return;
        }
        if (evento.key !== 'Tab') return;
        const dialogo = fondo.querySelector('.modal');
        const focos = [...dialogo.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])')].filter(nodo => !nodo.disabled);
        if (!focos.length) return;
        const primero = focos[0];
        const ultimo = focos[focos.length - 1];
        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });
}
