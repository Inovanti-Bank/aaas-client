function shellQuote(value) {
    return `'${String(value).replace(/'/g, "'\\''")}'`;
}

export function buildCurlCommand(sentRequest) {
    if (!sentRequest || !sentRequest.url) {
        return '';
    }

    const lines = [`curl -X ${sentRequest.method || 'GET'} ${shellQuote(sentRequest.url)}`];

    const headers = sentRequest.headers || {};
    Object.keys(headers).forEach((name) => {
        lines.push(`  -H ${shellQuote(`${name}: ${headers[name]}`)}`);
    });

    if (sentRequest.body !== null && sentRequest.body !== undefined) {
        lines.push(`  --data ${shellQuote(JSON.stringify(sentRequest.body))}`);
    }

    return lines.join(' \\\n');
}
