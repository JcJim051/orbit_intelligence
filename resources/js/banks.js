const contrastButton = document.querySelector('[data-accessibility="contrast"]');
const decreaseButton = document.querySelector('[data-accessibility="decrease"]');
const increaseButton = document.querySelector('[data-accessibility="increase"]');
let contrast = false;
let fontSize = 16;

try {
    contrast = localStorage.getItem('bank-contrast') === 'true';
    fontSize = Math.max(14, Math.min(32, Number(localStorage.getItem('bank-font-size')) || 16));
} catch {
    // Preferences remain usable when the browser blocks local storage.
}

function applyPreferences() {
    document.body.classList.toggle('hc', contrast);
    document.documentElement.style.fontSize = `${fontSize}px`;
    contrastButton?.setAttribute('aria-pressed', String(contrast));
    if (decreaseButton) decreaseButton.disabled = fontSize <= 14;
    if (increaseButton) increaseButton.disabled = fontSize >= 32;
    try {
        localStorage.setItem('bank-contrast', String(contrast));
        localStorage.setItem('bank-font-size', String(fontSize));
    } catch {
        // Use the selected settings for this page even if they cannot be saved.
    }
}

contrastButton?.addEventListener('click', () => {
    contrast = !contrast;
    applyPreferences();
});
decreaseButton?.addEventListener('click', () => {
    fontSize = Math.max(14, fontSize - 2);
    applyPreferences();
});
increaseButton?.addEventListener('click', () => {
    fontSize = Math.min(32, fontSize + 2);
    applyPreferences();
});
applyPreferences();

const backToTop = document.querySelector('.back-to-top');
function updateBackToTop() {
    if (backToTop) backToTop.hidden = document.documentElement.scrollHeight < innerHeight * 2 || scrollY < innerHeight;
}
backToTop?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    document.querySelector('.top')?.focus({ preventScroll: true });
});
addEventListener('scroll', updateBackToTop, { passive: true });
addEventListener('resize', updateBackToTop);
updateBackToTop();


// Preview the local selection without uploading it or retaining its blob URL.
const bankFile = document.querySelector('#bank_file');
const pdfPreview = document.querySelector('#bank-pdf-preview');
const pdfFrame = document.querySelector('#bank-pdf-frame');
const pdfLink = document.querySelector('#bank-pdf-link');
const previewMessage = document.querySelector('#bank-preview-message');
let previewUrl;

function clearPdfPreview() {
    if (pdfPreview) pdfPreview.hidden = true;
    pdfFrame?.removeAttribute('src');
    pdfLink?.removeAttribute('href');
    if (previewMessage) previewMessage.hidden = true;
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = undefined;
}

bankFile?.addEventListener('change', () => {
    clearPdfPreview();
    const file = bankFile.files?.[0];
    if (!file || !pdfPreview || !pdfFrame || !pdfLink || !previewMessage) return;
    if (file.size > 10 * 1024 * 1024) {
        previewMessage.textContent = 'El archivo supera los 10 MB. Seleccione un archivo más pequeño.';
        previewMessage.hidden = false;
        return;
    }
    if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) {
        previewMessage.textContent = 'La vista previa está disponible para PDF. El documento de Word se adjuntará al enviar la solicitud.';
        previewMessage.hidden = false;
        return;
    }
    previewUrl = URL.createObjectURL(file);
    pdfFrame.src = previewUrl;
    pdfLink.href = previewUrl;
    pdfPreview.hidden = false;
    pdfPreview.open = true;
    previewMessage.textContent = `PDF seleccionado: ${file.name}. Aún no se ha enviado.`;
    previewMessage.hidden = false;
});
bankFile?.form?.addEventListener('reset', clearPdfPreview);
addEventListener('pagehide', clearPdfPreview);


document.querySelectorAll('[data-dismiss-error]').forEach((button) => {
    button.addEventListener('click', () => {
        const alert = button.closest('.bank-errors');
        if (!alert) return;
        alert.hidden = true;
        document.querySelector('[aria-invalid="true"]')?.focus({ preventScroll: true });
    });
});
