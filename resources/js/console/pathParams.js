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

export function substitutePathParams(endpoint, values) {
    const missing = [];
    const result = String(endpoint || '').replace(PATH_PARAM_REGEX, (placeholder, name) => {
        const value = (values[name] || '').trim();
        if (value === '') {
            missing.push(name);
            return placeholder;
        }
        return encodeURIComponent(value);
    });

    return { endpoint: result, missing };
}
