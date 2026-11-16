const PATH_PARAM_REGEX = /\{([^{}]+)\}/g;

export function extractPathParams(endpoint) {
    const names = [];
    for (const match of String(endpoint || '').matchAll(PATH_PARAM_REGEX)) {
        if (!names.includes(match[1])) {
            names.push(match[1]);
        }
    }
    return names;
}

export function normalizeParamName(name) {
    return String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

export function resolveParamValue(name, values) {
    if (!values || typeof values !== 'object') {
        return '';
    }
    if (Object.prototype.hasOwnProperty.call(values, name)) {
        const direct = values[name];
        if (direct !== null && direct !== undefined && String(direct).trim() !== '') {
            return String(direct);
        }
    }
    const targetKey = normalizeParamName(name);
    if (!targetKey) {
        return '';
    }
    for (const [key, value] of Object.entries(values)) {
        if (normalizeParamName(key) === targetKey) {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                return String(value);
            }
        }
    }
    return '';
}

export function substitutePathParams(endpoint, values) {
    const missing = [];
    const result = String(endpoint || '').replace(PATH_PARAM_REGEX, (placeholder, name) => {
        const rawValue = resolveParamValue(name, values);
        const value = (rawValue || '').trim();
        if (value === '') {
            missing.push(name);
            return placeholder;
        }
        return encodeURIComponent(value);
    });

    return { endpoint: result, missing };
}
