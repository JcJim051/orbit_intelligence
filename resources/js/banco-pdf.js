import { prepararPdf } from './pdf-viewer.js';

const visor = document.querySelector('.pdf-dialog');

if (visor) {
    prepararPdf(visor);
}
