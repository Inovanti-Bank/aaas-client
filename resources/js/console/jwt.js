export function base64UrlDecode(text) {
    let output = text.replace(/-/g, '+').replace(/_/g, '/');
    const pad = output.length % 4;
    if (pad) {
        output += '='.repeat(4 - pad);
    }
    return atob(output);
}

const JWT_PARTS_COUNT = 3;

/**
 * Decodifica o payload de um JWT sem validar assinatura.
 * Retorna { ok, payload?, text?, error? }.
 */
export function decodeJwtPayload(token) {
    const parts = token.split('.');
    if (parts.length !== JWT_PARTS_COUNT) {
        return { ok: false, error: 'Formato de JWT inválido. Esperado: header.payload.signature' };
    }

    try {
        const jsonText = base64UrlDecode(parts[1]);
        try {
            return { ok: true, payload: JSON.parse(jsonText) };
        } catch (e) {
            return { ok: true, text: jsonText };
        }
    } catch (e) {
        const message = e && e.message ? e.message : String(e);
        return { ok: false, error: `Erro ao decodificar payload: ${message}` };
    }
}
