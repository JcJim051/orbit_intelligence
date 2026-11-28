<dialog class="pdf-dialog" aria-labelledby="pdf-dialog-title">
    <header>
        <h2 id="pdf-dialog-title">Vista previa</h2>
        <a data-pdf-download download>Descargar</a>
        <button type="button" data-pdf-close aria-label="Cerrar vista previa">Cerrar ×</button>
    </header>
    <div class="pdf-toolbar" role="group" aria-label="Controles del documento">
        <button type="button" data-pdf-action="prev" disabled>Anterior</button>
        <button type="button" data-pdf-action="next" disabled>Siguiente</button>
        <button type="button" data-pdf-action="minus" aria-label="Reducir zoom" disabled>−</button>
        <button type="button" data-pdf-action="plus" aria-label="Aumentar zoom" disabled>+</button>
    </div>
    <p data-pdf-status role="status" aria-live="polite"></p>
    <div class="pdf-body">
        <div data-pdf-page class="pdf-page"></div>
        <details class="pdf-text"><summary>Texto de esta página</summary><div data-pdf-text></div></details>
    </div>
</dialog>
