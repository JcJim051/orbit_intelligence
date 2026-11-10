import * as pdfjs from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

export function prepararPdf(visor) {
    const estado = visor.querySelector('[data-pdf-status]');
    const superficie = visor.querySelector('[data-pdf-page]');
    const texto = visor.querySelector('[data-pdf-text]');
    const controles = [...visor.querySelectorAll('[data-pdf-action]')];
    let carga, documento, pagina = 1, zoom = 1, turno = 0, render, origen;
    const actualizar = () => {
        controles.forEach(b => b.disabled = !documento ||
            (b.dataset.pdfAction === 'prev' && pagina === 1) ||
            (b.dataset.pdfAction === 'next' && pagina === documento.numPages) ||
            (b.dataset.pdfAction === 'minus' && zoom <= .5) ||
            (b.dataset.pdfAction === 'plus' && zoom >= 2));
    };
    const limpiar = () => {
        turno++;
        render?.cancel();
        carga?.destroy().catch(() => {});
        carga = documento = render = undefined;
        superficie.replaceChildren();
        texto.textContent = '';
        actualizar();
    };
    async function dibujar() {
        if (!documento || !visor.open) return;
        const actual = ++turno;
        render?.cancel();
        estado.textContent = 'Cargando página…';
        try {
            const hoja = await documento.getPage(pagina);
            if (actual !== turno) return;
            const base = hoja.getViewport({ scale: 1 });
            const escala = Math.min(1.5, Math.max(1, superficie.clientWidth - 32) / base.width) * zoom;
            const viewport = hoja.getViewport({ scale: escala });
            const canvas = document.createElement('canvas');
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.ceil(viewport.width * ratio);
            canvas.height = Math.ceil(viewport.height * ratio);
            canvas.style.width = viewport.width + 'px';
            canvas.style.height = viewport.height + 'px';
            canvas.setAttribute('aria-label', 'Página ' + pagina);
            canvas.setAttribute('role', 'img');
            const tarea = hoja.render({ canvasContext: canvas.getContext('2d'), viewport,
                transform: [ratio, 0, 0, ratio, 0, 0] });
            render = tarea;
            await tarea.promise;
            if (actual !== turno) return;
            superficie.replaceChildren(canvas);
            const contenido = await hoja.getTextContent();
            if (actual !== turno) return;
            texto.textContent = contenido.items.map(item => item.str + (item.hasEOL ? '\n' : ' ')).join('');
            estado.textContent = 'Página ' + pagina + ' de ' + documento.numPages + ' · Zoom ' + Math.round(zoom * 100) + '%';
            actualizar();
        } catch (error) {
            if (actual !== turno || error.name === 'RenderingCancelledException') return;
            estado.textContent = 'No se pudo mostrar esta página. Puedes descargar el documento.';
        }
    }
    const abrir = async event => {
        const boton = event.target.closest('[data-pdf-preview]');
        if (!boton) return;
        limpiar();
        origen = boton;
        pagina = 1; zoom = 1;
        visor.querySelector('h2').textContent = boton.dataset.documentName;
        visor.querySelector('[data-pdf-download]').href = boton.dataset.downloadUrl;
        estado.textContent = 'Cargando documento…';
        visor.showModal();
        const actual = turno;
        try {
            carga = pdfjs.getDocument({ url: boton.dataset.pdfPreview });
            const pdf = await carga.promise;
            if (actual !== turno) return;
            documento = pdf;
            actualizar();
            await dibujar();
        } catch (error) {
            console.error("No se pudo cargar el documento PDF:", error);
            if (actual === turno) estado.textContent = 'No se pudo abrir el PDF. Puedes descargarlo e intentarlo de nuevo.';
        }
    };
    const accion = event => {
        const tipo = event.target.closest('[data-pdf-action]')?.dataset.pdfAction;
        if (!tipo || !documento) return;
        if (tipo === 'prev') pagina = Math.max(1, pagina - 1);
        if (tipo === 'next') pagina = Math.min(documento.numPages, pagina + 1);
        if (tipo === 'minus') zoom = Math.max(.5, zoom - .25);
        if (tipo === 'plus') zoom = Math.min(2, zoom + .25);
        actualizar();
        dibujar();
    };
    const cerrar = () => { limpiar(); origen?.focus({ preventScroll: true }); };
    document.addEventListener('click', abrir);
    visor.addEventListener('click', accion);
    visor.querySelector('[data-pdf-close]').addEventListener('click', () => visor.close());
    visor.addEventListener('close', cerrar);
    let timer;
    const observar = new ResizeObserver(() => { clearTimeout(timer); timer = setTimeout(dibujar, 150); });
    observar.observe(superficie);
    actualizar();
    return () => { observar.disconnect(); clearTimeout(timer); document.removeEventListener('click', abrir); limpiar(); };
}



