let filters = { query: '', sector: '', state: 'todas' };

export function iniciarPoliticas() {
    const workspace = document.querySelector('[data-policy-workspace]');
    if (!workspace) return;
    const query = workspace.querySelector('[data-policy-query]');
    const sector = workspace.querySelector('[data-policy-sector]');
    const items = [...workspace.querySelectorAll('[data-policy-item]')];
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    query.value = filters.query;
    sector.value = [...sector.options].some(option => option.value === filters.sector) ? filters.sector : '';
    workspace.querySelector('[name="policy-state"][value="' + filters.state + '"]').checked = true;
    const filter = () => {
        const words = normalize(query.value).split(/\s+/).filter(Boolean);
        const state = workspace.querySelector('[name="policy-state"]:checked').value;
        filters = { query: query.value, sector: sector.value, state };
        let count = 0;
        items.forEach(item => {
            const text = normalize(item.dataset.policySearch);
            const visible = words.every(word => text.includes(word))
                && (!sector.value || item.dataset.policySectorValue === sector.value)
                && (state === 'todas' || (state === 'vigentes' ? item.dataset.policyCurrent === '1' : item.dataset.policyExpiring === '1'));
            item.hidden = !visible;
            if (visible) count++;
        });
        workspace.querySelector('[data-policy-count]').textContent = count + (count === 1 ? ' política disponible' : ' políticas disponibles');
        workspace.querySelector('[data-policy-empty]').hidden = count > 0 || items.length === 0;
    };
    workspace.querySelector('[data-policy-reset]').addEventListener('click', () => {
        query.value = '';
        sector.value = '';
        workspace.querySelector('[name="policy-state"][value="todas"]').checked = true;
        filter();
        query.focus();
    });
    filter();
    query.addEventListener('input', filter);
    sector.addEventListener('change', filter);
    workspace.querySelectorAll('[name="policy-state"]').forEach(input => input.addEventListener('change', filter));
    workspace.querySelectorAll('[data-policy-folder]').forEach(button => button.addEventListener('click', () => {
        workspace.querySelectorAll('[data-policy-folder]').forEach(folder => folder.setAttribute('aria-pressed', String(folder === button)));
        workspace.querySelectorAll('[data-policy-folder-panel]').forEach(panel => { panel.hidden = panel.dataset.policyFolderPanel !== button.dataset.policyFolder; });
    }));
    const dashboard = workspace.querySelector('[data-policy-dashboard]');
    dashboard?.addEventListener('click', () => {
        const expanded = dashboard.getAttribute('aria-expanded') === 'true';
        dashboard.setAttribute('aria-expanded', String(!expanded));
        workspace.querySelector('#policy-dashboard-notice').hidden = expanded;
    });
}
