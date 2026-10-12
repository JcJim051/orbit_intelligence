import { iniciarMapas } from './inteligencia-mapa.js';

function arrancar() {
    iniciarMapas();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', arrancar);
} else {
    arrancar();
}

document.addEventListener('livewire:navigated', arrancar);
