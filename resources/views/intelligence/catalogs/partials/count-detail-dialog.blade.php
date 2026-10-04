{{-- Modal compartido para el detalle de los conteos. Espera: $catalog. Incluir una sola vez, después de los botones. --}}
<style>
    .count-detail-trigger { background: none; border: 0; padding: 0; cursor: pointer; text-align: left; text-decoration: underline; text-underline-offset: 3px; }
    .count-detail-trigger:focus-visible { outline: 2px solid #4338ca; outline-offset: 2px; border-radius: 2px; }
    #count-detail-dialog { width: min(64rem, calc(100vw - 2rem)); max-height: 85vh; margin: auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgb(15 23 42 / 0.35); color: #0f172a; }
    #count-detail-dialog[open] { display: flex; flex-direction: column; }
    #count-detail-dialog::backdrop { background: rgb(15 23 42 / 0.45); }
    #count-detail-dialog .cd-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
    #count-detail-dialog .cd-eyebrow { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 0; }
    #count-detail-dialog .cd-title { font-size: 1.125rem; font-weight: 600; margin: .125rem 0 0; }
    #count-detail-dialog .cd-summary { font-size: .875rem; color: #475569; margin: .25rem 0 0; }
    #count-detail-dialog .cd-body { overflow-y: auto; padding: 1rem 1.25rem 1.25rem; }
    #count-detail-dialog .cd-status { font-size: .875rem; color: #64748b; padding: 1rem 0; text-align: center; }
    #count-detail-dialog .cd-status.is-error { color: #b91c1c; }
    #count-detail-dialog .cd-group { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; }
    #count-detail-dialog .cd-group + .cd-group { margin-top: .75rem; }
    #count-detail-dialog .cd-group-title { display: flex; justify-content: space-between; gap: .75rem; background: #f8fafc; padding: .625rem .875rem; font-size: .875rem; font-weight: 600; margin: 0; }
    #count-detail-dialog .cd-group-count { font-weight: 400; color: #64748b; white-space: nowrap; }
    #count-detail-dialog .cd-items { list-style: none; margin: 0; padding: 0; }
    #count-detail-dialog .cd-item { padding: .625rem .875rem; font-size: .875rem; border-top: 1px solid #f1f5f9; }
    #count-detail-dialog .cd-empty { padding: .625rem .875rem; font-size: .8125rem; color: #64748b; border-top: 1px solid #f1f5f9; margin: 0; }
    #count-detail-dialog .cd-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 600; color: #3730a3; margin-right: .5rem; }
    #count-detail-dialog .cd-code-link { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #3730a3; margin-right: .5rem; text-decoration: underline; text-underline-offset: 3px; }
    #count-detail-dialog .cd-extra { margin: .25rem 0 0; color: #475569; font-size: .8125rem; }
    #count-detail-dialog .cd-extra-label { font-weight: 600; color: #334155; }
    #count-detail-dialog .cd-summary-table-wrap { overflow-x: auto; border-top: 1px solid #f1f5f9; }
    #count-detail-dialog .cd-summary-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    #count-detail-dialog .cd-summary-table th { background: #f8fafc; color: #475569; font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; text-align: left; padding: .625rem .875rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    #count-detail-dialog .cd-summary-table td { padding: .625rem .875rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    #count-detail-dialog .cd-summary-table td:last-child,
    #count-detail-dialog .cd-summary-table th:last-child { text-align: right; }
</style>

<dialog id="count-detail-dialog" aria-labelledby="count-detail-title">
    <div class="cd-header">
        <div>
            <p class="cd-eyebrow" data-cd-eyebrow>{{ $catalog->title() }}</p>
            <h2 class="cd-title" id="count-detail-title" data-cd-title>Detalle</h2>
            <p class="cd-summary" data-cd-summary></p>
        </div>
        <button type="button" class="btn-secondary" data-cd-close>Cerrar</button>
    </div>
    <div class="cd-body" data-cd-body></div>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('count-detail-dialog');
        if (!dialog) return;

        const eyebrow = dialog.querySelector('[data-cd-eyebrow]');
        const title = dialog.querySelector('[data-cd-title]');
        const summary = dialog.querySelector('[data-cd-summary]');
        const body = dialog.querySelector('[data-cd-body]');
        const defaultEyebrow = eyebrow.textContent;
        let controller = null;
        let lastTrigger = null;

        // Todo el contenido se inserta con textContent: nunca se interpreta como HTML.
        const el = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined && text !== null) node.textContent = String(text);
            return node;
        };

        const codeNode = (item) => {
            if (!item.codigo) return null;

            if (item.url) {
                const link = el('a', 'cd-code-link', item.codigo);
                link.href = String(item.url);
                return link;
            }

            return el('span', 'cd-code', item.codigo);
        };

        const status = (text, isError = false) => {
            body.replaceChildren(el('p', 'cd-status' + (isError ? ' is-error' : ''), text));
        };

        const render = (data) => {
            const registro = data.registro || {};
            eyebrow.textContent = defaultEyebrow + ' · ' + (data.titulo || 'Detalle');
            title.textContent = [registro.codigo, registro.nombre].filter((part) => part !== null && part !== undefined && part !== '').join(' — ') || 'Detalle';
            summary.textContent = (data.resumen || []).map((item) => item.valor + ' ' + item.label).join(' · ');

            const grupos = data.grupos || [];
            if (grupos.length === 0) {
                status('No hay registros asociados.');
                return;
            }

            const fragment = document.createDocumentFragment();
            grupos.forEach((grupo) => {
                const items = grupo.items || [];
                const section = el('section', 'cd-group');
                const heading = el('h3', 'cd-group-title');
                const nota = grupo.nota || (items.length === 1 ? '1 registro' : items.length + ' registros');
                heading.append(el('span', null, grupo.titulo), el('span', 'cd-group-count', nota));
                section.append(heading);

                if (grupo.tabla_resumen) {
                    const tableWrap = el('div', 'cd-summary-table-wrap');
                    const table = el('table', 'cd-summary-table');
                    const thead = el('thead');
                    const headerRow = el('tr');
                    (grupo.tabla_resumen.columns || []).forEach((column) => headerRow.append(el('th', null, column)));
                    thead.append(headerRow);
                    table.append(thead);

                    const tbody = el('tbody');
                    (grupo.tabla_resumen.rows || []).forEach((row) => {
                        const tr = el('tr');
                        row.forEach((cell) => tr.append(el('td', null, cell)));
                        tbody.append(tr);
                    });
                    table.append(tbody);
                    tableWrap.append(table);
                    section.append(tableWrap);
                }

                if (items.length === 0) {
                    section.append(el('p', 'cd-empty', grupo.vacio || 'Sin registros.'));
                } else {
                    const list = el('ul', 'cd-items');
                    items.forEach((item) => {
                        const li = el('li', 'cd-item');
                        const line = el('div');
                        const code = codeNode(item);
                        if (code) line.append(code);
                        line.append(el('span', null, item.nombre || '—'));
                        li.append(line);
                        (item.extra || []).forEach((extra) => {
                            const p = el('p', 'cd-extra');
                            p.append(el('span', 'cd-extra-label', extra.label + ': '), el('span', null, extra.valor));
                            li.append(p);
                        });
                        list.append(li);
                    });
                    section.append(list);
                }
                fragment.append(section);
            });
            body.replaceChildren(fragment);
        };

        const open = async (trigger) => {
            lastTrigger = trigger;
            eyebrow.textContent = defaultEyebrow;
            title.textContent = 'Cargando…';
            summary.textContent = '';
            status('Cargando detalle…');
            if (typeof dialog.showModal === 'function') {
                if (!dialog.open) dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }

            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const response = await fetch(trigger.dataset.countDetailUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                render(await response.json());
            } catch (error) {
                if (error.name === 'AbortError') return;
                title.textContent = 'Detalle';
                status('No se pudo cargar el detalle. Intente de nuevo.', true);
            }
        };

        const close = () => {
            if (controller) controller.abort();
            if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
        };

        document.querySelectorAll('[data-count-detail-url]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                open(trigger);
            });
        });

        dialog.querySelector('[data-cd-close]').addEventListener('click', close);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) close();
        });
        dialog.addEventListener('close', () => {
            if (lastTrigger) lastTrigger.focus();
        });
    })();
</script>
