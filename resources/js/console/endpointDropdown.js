function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Autocomplete de endpoints com suporte a grupos (cabeçalhos não clicáveis).
 * `getSource` deve retornar [{ label: string|null, endpoints: string[] }].
 */
export function createEndpointDropdown({ inputEl, dropdownEl, getSource, onSelect }) {
    function render(filterValue) {
        const term = (filterValue || '').toLowerCase().trim();
        let html = '';
        let totalMatches = 0;

        getSource().forEach((group) => {
            const matches = term
                ? group.endpoints.filter((endpoint) => endpoint.toLowerCase().includes(term))
                : group.endpoints;
            if (!matches.length) return;

            totalMatches += matches.length;
            if (group.label) {
                html += `<div class="endpoint-group-label">${escapeHtml(group.label)}</div>`;
            }
            matches.forEach((endpoint) => {
                const safeValue = escapeHtml(endpoint);
                html += `<button type="button" class="endpoint-option" data-endpoint="${safeValue}">${safeValue}</button>`;
            });
        });

        dropdownEl.innerHTML = html;
        dropdownEl.hidden = totalMatches === 0;
    }

    function close() {
        dropdownEl.hidden = true;
        dropdownEl.innerHTML = '';
    }

    inputEl.addEventListener('focus', () => render(''));
    inputEl.addEventListener('input', (event) => render(event.target.value));

    dropdownEl.addEventListener('click', (event) => {
        const optionButton = event.target.closest('button[data-endpoint]');
        if (!optionButton) return;
        inputEl.value = optionButton.getAttribute('data-endpoint');
        close();
        inputEl.focus();
        onSelect(inputEl.value);
    });

    document.addEventListener('click', (event) => {
        if (!dropdownEl.contains(event.target) && event.target !== inputEl) {
            close();
        }
    });

    return { render, close };
}
