(() => {
    document.querySelectorAll('[data-definition-manager]').forEach(manager => {
        const title = manager.querySelector(':scope > summary')?.textContent.trim() || '';
        const grid = document.createElement('div');
        grid.className = 'framework-grid';
        manager.querySelectorAll(':scope > form.definition-row').forEach(form => {
            const code = form.querySelector('.stable-code-field input')?.value.trim() || '';
            const label = form.querySelector('[name="label_text"]')?.value.trim() || '';
            const card = document.createElement('details');
            const summary = document.createElement('summary');
            card.className = 'framework-card' + (code ? '' : ' framework-add');
            card.open = !code;
            card.dataset.writerNew = code ? '0' : '1';
            summary.textContent = code ? label + ' - ' + code : 'Add ' + title.replace(/s$/, '');
            card.append(summary, form);
            grid.append(card);
        });
        manager.append(grid);
    });
    document.querySelectorAll('[data-writer-filter-toolbar]').forEach(toolbar => {
        const scope = toolbar.parentElement;
        const selector = 'details.record, details.mapnode, details.framework-card, .media-item, .artwork-assignment-table tbody tr, .artwork-thumb';
        const entries = [...scope.querySelectorAll(selector)].filter(entry => {
            const parent = entry.parentElement.closest(selector);
            return !parent || !scope.contains(parent);
        });
        const search = toolbar.querySelector('input[type="search"]');
        const empty = toolbar.nextElementSibling;
        const isNew = entry => entry.dataset.writerNew === '1' ||
            /^Add\b/i.test(entry.querySelector(':scope > summary')?.textContent.trim() || '');
        const identity = entry => {
            const fields = [...entry.querySelectorAll('input[name],select[name]')]
                .filter(field => /(?:name|label_text|title_text|headline|code)\]?$/.test(field.name))
                .map(field => field.value);
            return [entry.querySelector(':scope > summary')?.textContent || entry.textContent, ...fields].join(' ').toLowerCase();
        };
        const apply = () => {
            const query = search.value.trim().toLowerCase();
            let matches = 0;
            entries.forEach(entry => {
                entry.hidden = !isNew(entry) && query !== '' && !identity(entry).includes(query);
                if (!entry.hidden && !isNew(entry)) matches++;
            });
            empty.hidden = query === '' || matches > 0;
        };
        search.addEventListener('input', apply);
        toolbar.addEventListener('click', event => {
            const action = event.target.closest('[data-record-action]')?.dataset.recordAction;
            if (!action) return;
            entries.filter(entry => !entry.hidden && entry.matches('details'))
                .forEach(entry => entry.open = action === 'expand');
        });
        toolbar.querySelectorAll('[data-record-action]').forEach(button => {
            button.hidden = !entries.some(entry => entry.matches('details'));
        });
    });
})();
