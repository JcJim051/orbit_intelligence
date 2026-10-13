import { iniciarMapas } from './inteligencia-mapa.js';

export function arrancarMapasDeInteligencia() {
    return iniciarMapas();
}

function arrancar() {
    arrancarMapasDeInteligencia();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', arrancar);
} else {
    arrancar();
}

document.addEventListener('livewire:initialized', arrancar);
document.addEventListener('livewire:navigated', arrancar);
