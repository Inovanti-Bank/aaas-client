export function pretty(value) {
    try {
        return JSON.stringify(value, null, 2);
    } catch (e) {
        return String(value);
    }
}

// Escapa apenas &, < e > — aspas precisam sobreviver para o regex de tokens JSON
function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

const JSON_TOKEN_REGEX = /("(?:\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(?:\s*:)?|\b(?:true|false)\b|\bnull\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g;

function classifyToken(token) {
    if (token.startsWith('"')) {
        return token.endsWith(':') ? 'json-key' : 'json-str';
    }
    if (token === 'true' || token === 'false') {
        return 'json-bool';
    }
    if (token === 'null') {
        return 'json-null';
    }
    return 'json-num';
}

export function highlightJson(jsonText) {
    return escapeHtml(jsonText).replace(JSON_TOKEN_REGEX, (token) => {
        if (classifyToken(token) === 'json-key') {
            const colonIndex = token.lastIndexOf(':');
            const key = token.slice(0, token.lastIndexOf('"') + 1);
            const suffix = colonIndex >= 0 ? token.slice(token.lastIndexOf('"') + 1) : '';
            return `<span class="json-key">${key}</span>${suffix}`;
        }
        return `<span class="${classifyToken(token)}">${token}</span>`;
    });
}

// Renderiza um valor como JSON com destaque de sintaxe dentro de um <pre>
export function renderJson(element, value) {
    if (!element) return;
    element.innerHTML = highlightJson(pretty(value));
}
