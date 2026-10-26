export async function montarTablerosSuperset() {
    const nodo = document.querySelector('[data-superset]');

    if (! nodo) {
        return;
    }

    const dominio = nodo.dataset.dominio;
    const tokenUrl = nodo.dataset.tokenUrl;

    if (! dominio || ! tokenUrl) {
        nodo.textContent = 'Superset no está configurado.';

        return;
    }

    const pedir = async () => {
        const respuesta = await fetch(tokenUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (! respuesta.ok) {
            throw new Error('token');
        }

        return respuesta.json();
    };

    try {
        const inicial = await pedir();
        const { embedDashboard } = await import('@superset-ui/embedded-sdk');

        await embedDashboard({
            id: inicial.uuid,
            supersetDomain: dominio,
            mountPoint: nodo,
            fetchGuestToken: async () => (await pedir()).token,
            dashboardUiConfig: {
                hideTitle: true,
                filters: { expanded: true },
            },
        });
    } catch {
        nodo.textContent = 'No se pudo abrir el tablero.';
    }
}
