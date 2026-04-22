<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Humu Service</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; background:#FAFAFA; color:#111827; }
        .container{max-width:980px;margin:32px auto;padding:24px 32px;background:#fff;border:1px solid #e6e6e6;border-radius:8px}
        label{display:block;font-weight:600;margin-bottom:6px}
        input[type=text], select, textarea{width:100%;box-sizing:border-box;max-width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
        button{background:#111827;color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
        pre{background:#0f1724;color:#e6edf3;padding:12px;border-radius:6px;overflow:auto;max-width:100%;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;box-sizing:border-box}
        .muted{color:#6b7280;font-size:13px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .header-row{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
        .service-switch{display:flex;gap:8px;align-items:center}
        .service-btn{background:#fff;border:1px solid #d1d5db;color:#111827;padding:8px 12px;border-radius:999px}
        .service-btn.active{background:#111827;color:#fff;border-color:#111827}
    </style>
</head>
<body>
    <div class="container">
        <div class="header-row">
            <div>
                <h1 style="margin:0 0 12px">Humu Service</h1>
                <p class="muted">IAaas usa JWT assinado. IBaas usa login com token de sessão e refresh token.</p>
            </div>
            <div class="service-switch">
                <span class="muted">Serviço:</span>
                <button type="button" id="serviceIAaasBtn" class="service-btn active" data-service="iaaas">IAaas</button>
                <button type="button" id="serviceIBaasBtn" class="service-btn" data-service="ibaas">IBaas</button>
            </div>
        </div>

        <form id="jwtForm" style="margin-top:16px">
            @csrf
            <input id="service" name="service" type="hidden" value="iaaas" />
            <div style="margin-bottom:12px">
                <label for="base_url" id="baseUrlLabel">Base URL</label>
                <input id="base_url" name="base_url" type="text" value="{{ $baseUrl }}" />
                <p class="muted" id="baseUrlHint" style="margin:6px 0 0;display:none">No IBaas, endpoints de auth usam a base fixa de autenticação configurada no backend.</p>
            </div>

            <div style="margin-bottom:12px; position:relative">
                <label for="endpoint">Endpoint (path)</label>
                <input id="endpoint" name="endpoint" type="text" placeholder="/v1/aaas/…" autocomplete="off" required />
                <div id="endpointDropdown" style="position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;max-height:220px;overflow:auto;display:none;box-shadow:0 10px 25px rgba(15,23,42,.12);font-size:13px"></div>
            </div>
            <div style="margin-bottom:12px">
                <label for="method">Method</label>
                <select id="method" name="method">
                    <option>GET</option>
                    <option>POST</option>
                    <option>PUT</option>
                    <option>PATCH</option>
                    <option>DELETE</option>
                </select>
            </div>
            <div style="margin-bottom:12px">
                <label for="query_params">Query params (raw, ex: start_date=2025-01-01&end_date=2025-01-30)</label>
                <input id="query_params" name="query_params" type="text" />
            </div>

            <div style="margin-bottom:12px">
                <label for="body">Body (JSON) — deixe vazio se não houver</label>
                <textarea id="body" name="body" rows="6" placeholder='{"foo":"bar"}'></textarea>
            </div>

            <div style="display:flex;gap:8px;align-items:center">
                <button id="sendBtn" type="submit">Send request</button>
                <span class="muted" id="statusText"></span>
            </div>
        </form>

        <div id="jwtTokenSection" style="margin-top:18px;margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                 onclick="toggleCollapse('jwtTokenBody','jwtTokenChevron')">
                <h2 style="margin:0 0 6px">JWT gerado</h2>
                <span id="jwtTokenChevron" style="font-size:18px;line-height:1">▼</span>
            </div>
            <div id="jwtTokenBody" style="margin-top:8px;display:none">
                <div style="position:relative">
                    <button id="jwtTokenCopyBtn" type="button"
                            onclick="copyCardContent('jwtToken')"
                            style="position:absolute;top:6px;right:6px;z-index:1;background:rgba(15,23,42,0.9);color:#e5e7eb;border:none;padding:4px 8px;font-size:11px;border-radius:4px;cursor:pointer;display:none">
                        Copiar
                    </button>
                    <pre id="jwtToken" style="min-height:80px;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;margin:0"></pre>
                </div>
            </div>
        </div>

        <div id="jwtDecoderSection" style="margin-top:18px;margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                 onclick="toggleCollapse('jwtDecoderBody','jwtDecoderChevron')">
                <h2 style="margin:0 0 6px">Decodificador de JWT</h2>
                <span id="jwtDecoderChevron" style="font-size:18px;line-height:1">▼</span>
            </div>
            <div id="jwtDecoderBody" style="margin-top:8px;display:none">
                <p class="muted">Cole um JWT abaixo ou deixe vazio para usar o JWT gerado acima. O payload será exibido como JSON.</p>
                <textarea id="jwtPayloadInput" rows="4" placeholder="eyJhbGciOiJFUzUxMiIsInR5cCI6IkpXVCJ9.eyJmb28iOiJiYXIifQ.SignatureAqui"></textarea>
                <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                    <button type="button" onclick="decodeJwtPayloadToJson()">Ver payload (JSON)</button>
                    <span class="muted" style="font-size:12px">Não é necessário nenhuma chave para visualizar o conteúdo.</span>
                </div>
                <h3 style="margin:12px 0 6px">Payload como JSON</h3>
                <pre id="jwtPayloadJson" style="min-height:80px;white-space:pre-wrap"></pre>
            </div>
        </div>

        <div style="margin-top:18px;margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                 onclick="toggleCollapse('resultSectionBody','resultSectionChevron')">
                <h2 style="margin:0 0 6px">Resultados da requisição</h2>
                <span id="resultSectionChevron" style="font-size:18px;line-height:1">▼</span>
            </div>
            <div id="resultSectionBody" style="margin-top:8px;display:none">
                <div class="result-grid" style="display:grid;grid-template-columns:1fr;gap:12px">
                    <div>
                        <h3 style="margin:0 0 6px;">Response</h3>
                        <div style="position:relative">
                            <button id="resultRawCopyBtn" type="button"
                                    onclick="copyCardContent('resultRaw')"
                                    style="position:absolute;top:6px;right:6px;z-index:1;background:rgba(15,23,42,0.9);color:#e5e7eb;border:none;padding:4px 8px;font-size:11px;border-radius:4px;cursor:pointer;display:none">
                                Copiar
                            </button>
                            <pre id="resultRaw" style="min-height:160px;white-space:pre-wrap;margin:0"></pre>
                        </div>
                    </div>
                    <div>
                        <h3 style="margin:0 0 6px;">Request</h3>
                        <div style="position:relative">
                            <button id="resultSummaryCopyBtn" type="button"
                                    onclick="copyCardContent('resultSummary')"
                                    style="position:absolute;top:6px;right:6px;z-index:1;background:rgba(15,23,42,0.9);color:#e5e7eb;border:none;padding:4px 8px;font-size:11px;border-radius:4px;cursor:pointer;display:none">
                                Copiar
                            </button>
                            <pre id="resultSummary" style="min-height:160px;white-space:pre-wrap;margin:0"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
const form = document.getElementById('jwtForm');
const sendBtn = document.getElementById('sendBtn');
const resultSummaryEl = document.getElementById('resultSummary');
const resultRawEl = document.getElementById('resultRaw');
const statusText = document.getElementById('statusText');
const jwtTokenEl = document.getElementById('jwtToken');
const serviceInput = document.getElementById('service');
const baseUrlInput = document.getElementById('base_url');
const baseUrlLabel = document.getElementById('baseUrlLabel');
const baseUrlHint = document.getElementById('baseUrlHint');
const jwtTokenSection = document.getElementById('jwtTokenSection');
const jwtDecoderSection = document.getElementById('jwtDecoderSection');
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const serviceButtons = document.querySelectorAll('.service-btn');

const SERVICE_DEFAULTS = {
    iaaas: {
        baseUrl: @json($baseUrl),
    },
    ibaas: {
        baseUrl: @json($baseUrl),
    },
};

const IAAS_GROUPS = @json(($endpoints['iaaas_groups'] ?? []));
const IBAAS_ENDPOINTS = @json(array_values(array_unique($endpoints['ibaas_endpoints'] ?? [])));

const endpointInput = document.getElementById('endpoint');
const endpointDropdown = document.getElementById('endpointDropdown');
const bodyInput = document.getElementById('body');
const IBAAS_LOGIN_BODY_TEMPLATE = `{
    "username": "@{{testUserName}}",
    "password": "@{{testPassword}}"
}`;
const IBAAS_LOGIN_2FA_BODY_TEMPLATE = `{
    "two_factor_id": "@{{twoFactorId}}",
    "code": "123456"
}`;
let lastIbaasTwoFactorId = '';

function buildEndpointList() {
    const selectedService = serviceInput ? serviceInput.value : 'iaaas';
    if (selectedService === 'ibaas') {
        return IBAAS_ENDPOINTS;
    }

    const groups = IAAS_GROUPS;
    const list = [];
    Object.keys(groups).forEach((key) => {
        const group = groups[key];
        group.endpoints.forEach((ep) => {
            list.push(ep);
        });
    });
    return list;
}

let ALL_ENDPOINTS = buildEndpointList();

function renderEndpointDropdown(filterValue) {
    const term = (filterValue || '').toLowerCase().trim();
    const filtered = term
        ? ALL_ENDPOINTS.filter((ep) => ep.toLowerCase().includes(term))
        : ALL_ENDPOINTS;

    if (!filtered.length) {
        endpointDropdown.style.display = 'none';
        endpointDropdown.innerHTML = '';
        return;
    }

    let html = '';
    filtered.forEach((ep) => {
        const safeValue = ep.replace(/"/g, '&quot;');
        html += `<button
            type="button"
            data-endpoint="${safeValue}"
            style="display:block;width:100%;text-align:left;padding:6px 10px;border:0;background:#ffffff;cursor:pointer;font-size:13px;color:#111827;border-bottom:1px solid #f3f4f6;box-sizing:border-box"
            onmouseover="this.style.backgroundColor='#f3f4f6'"
            onmouseout="this.style.backgroundColor='#ffffff'"
        >
            <span style="color:#111827;display:inline-block;">${safeValue}</span>
        </button>`;
    });

    endpointDropdown.innerHTML = html;
    endpointDropdown.style.display = 'block';
}

endpointInput.addEventListener('focus', () => {
    renderEndpointDropdown('');
});

endpointInput.addEventListener('input', (e) => {
    maybeApplyIbaasLoginBody(e.target.value);
    renderEndpointDropdown(e.target.value);
});

document.addEventListener('click', (e) => {
    if (!endpointDropdown.contains(e.target) && e.target !== endpointInput) {
        endpointDropdown.style.display = 'none';
    }
});

endpointDropdown.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-endpoint]');
    if (!btn) return;
    const value = btn.getAttribute('data-endpoint');
    endpointInput.value = value;
    maybeApplyIbaasLoginBody(value);
    endpointDropdown.style.display = 'none';
    endpointInput.focus();
});

function maybeApplyIbaasLoginBody(endpointValue) {
    const isIbaas = serviceInput && serviceInput.value === 'ibaas';
    if (!isIbaas || !bodyInput) return;

    const normalizedEndpoint = `/${String(endpointValue || '').trim().replace(/^\/+/, '')}`;
    if (normalizedEndpoint === '/v1/auth/login') {
        bodyInput.value = IBAAS_LOGIN_BODY_TEMPLATE;
        return;
    }
    if (normalizedEndpoint === '/v1/auth/login-2fa') {
        const template = IBAAS_LOGIN_2FA_BODY_TEMPLATE.replace('@{{twoFactorId}}', lastIbaasTwoFactorId || '@{{twoFactorId}}');
        bodyInput.value = template;
    }
}

function refreshServiceButtons() {
    serviceButtons.forEach((button) => {
        const isActive = button.dataset.service === serviceInput.value;
        button.classList.toggle('active', isActive);
    });
}

function refreshServiceUi() {
    const isIbaas = serviceInput && serviceInput.value === 'ibaas';

    if (jwtTokenSection) jwtTokenSection.style.display = isIbaas ? 'none' : 'block';
    if (jwtDecoderSection) jwtDecoderSection.style.display = isIbaas ? 'none' : 'block';

    if (baseUrlLabel) baseUrlLabel.textContent = isIbaas ? 'Tenant Base URL' : 'Base URL';
    if (baseUrlHint) baseUrlHint.style.display = isIbaas ? 'block' : 'none';

    if (endpointInput) {
        endpointInput.placeholder = isIbaas ? '/v1/baas/…' : '/v1/aaas/…';
    }
}

function changeService(nextService) {
    if (!serviceInput || !baseUrlInput) return;

    serviceInput.value = nextService;
    ALL_ENDPOINTS = buildEndpointList();
    endpointInput.value = '';
    renderEndpointDropdown('');
    refreshServiceButtons();

    const defaults = SERVICE_DEFAULTS[nextService];
    if (defaults && defaults.baseUrl) {
        baseUrlInput.value = defaults.baseUrl;
    }

    refreshServiceUi();
}

serviceButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const nextService = button.dataset.service || 'iaaas';
        changeService(nextService);
    });
});

function pretty(v){ try{ return JSON.stringify(v, null, 2) }catch(e){ return String(v) } }

function toggleCopyVisibility(preId, btnId) {
    const pre = document.getElementById(preId);
    const btn = document.getElementById(btnId);
    if (!pre || !btn) return;
    const hasContent = (pre.textContent || '').trim().length > 0;
    btn.style.display = hasContent ? 'inline-block' : 'none';
}

function toggleCollapse(bodyId, chevronId) {
    const body = document.getElementById(bodyId);
    const chev = document.getElementById(chevronId);
    if (!body) return;
    const isHidden = body.style.display === 'none' || body.style.display === '';
    body.style.display = isHidden ? 'block' : 'none';
    if (chev) {
        chev.textContent = isHidden ? '▲' : '▼';
    }
}

function expandResultSection() {
    const body = document.getElementById('resultSectionBody');
    const chev = document.getElementById('resultSectionChevron');
    if (body) body.style.display = 'block';
    if (chev) chev.textContent = '▲';
}

function base64UrlDecode(str) {
    let output = str.replace(/-/g, '+').replace(/_/g, '/');
    const pad = output.length % 4;
    if (pad) {
        output += '='.repeat(4 - pad);
    }
    return atob(output);
}

function decodeJwtPayloadToJson() {
    const inputEl = document.getElementById('jwtPayloadInput');
    const outputEl = document.getElementById('jwtPayloadJson');
    if (!outputEl) return;

    const manualToken = inputEl ? inputEl.value.trim() : '';
    const autoToken = jwtTokenEl ? (jwtTokenEl.textContent || '').trim() : '';
    const token = manualToken || autoToken;

    if (!token) {
        outputEl.textContent = 'Informe um JWT ou gere um token acima.';
        return;
    }

    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            outputEl.textContent = 'Formato de JWT inválido. Esperado: header.payload.signature';
            return;
        }

        const payloadB64 = parts[1];
        const jsonStr = base64UrlDecode(payloadB64);

        try {
            const obj = JSON.parse(jsonStr);
            outputEl.textContent = pretty(obj);
        } catch (e) {
            outputEl.textContent = jsonStr;
        }
    } catch (e) {
        outputEl.textContent = 'Erro ao decodificar payload: ' + (e && e.message ? e.message : String(e));
    }
}

async function copyCardContent(preId) {
    const el = document.getElementById(preId);
    if (!el) return;
    const text = el.textContent || '';
    if (!text) return;

    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        statusText.textContent = 'Conteúdo copiado.';
    } catch (e) {
        statusText.textContent = 'Não foi possível copiar o conteúdo.';
    }
}

form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const selectedService = document.getElementById('service').value;
    const isIbaas = selectedService === 'ibaas';

    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';
    }

    resultSummaryEl.textContent = 'Sending...';
    resultRawEl.textContent = 'Sending...';
    statusText.textContent = '';
    if (jwtTokenEl && !isIbaas) {
        jwtTokenEl.textContent = 'Gerando...';
        toggleCopyVisibility('jwtToken', 'jwtTokenCopyBtn');
    }

    const payload = {
        service: document.getElementById('service').value,
        base_url: document.getElementById('base_url').value,
        api_key: (document.getElementById('api_key') ? document.getElementById('api_key').value : null),
        endpoint: document.getElementById('endpoint').value,
        method: document.getElementById('method').value,
        query_params: document.getElementById('query_params').value,
        body: (document.getElementById('body').value || null)
    };

    try{
        const res = await fetch('/jwt/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({ raw: 'no-json-response' }));

        const summary = {
            status: data.status ?? res.status,
            ok: data.ok ?? null,
            headers: data.headers ?? null,
            body: data.body ?? null,
            ibaas_session: data.ibaas_session ?? null,
        };
        resultSummaryEl.textContent = pretty(summary);
        toggleCopyVisibility('resultSummary', 'resultSummaryCopyBtn');

        expandResultSection();

        if (isIbaas && data.body && data.body.two_factor_required && typeof data.body.two_factor_id === 'string' && data.body.two_factor_id.trim() !== '') {
            lastIbaasTwoFactorId = data.body.two_factor_id;
        }

        if (jwtTokenEl && !isIbaas) {
            if (data.token) {
                jwtTokenEl.textContent = data.token;
            } else {
                jwtTokenEl.textContent = '— nenhum token retornado —';
            }
            toggleCopyVisibility('jwtToken', 'jwtTokenCopyBtn');
        }

        if (isIbaas && data.ibaas_session) {
            const hasToken = data.ibaas_session.has_token ? 'sim' : 'nao';
            const hasRefresh = data.ibaas_session.has_refresh_token ? 'sim' : 'nao';
            const hasTwoFactorId = data.ibaas_session.has_two_factor_id ? 'sim' : 'nao';
            if (data.body && data.body.two_factor_required) {
                statusText.textContent = `Sessao IBaas: token=${hasToken}, refresh_token=${hasRefresh}, two_factor_pendente=sim. Use /v1/auth/login-2fa com o code.`;
            } else {
                statusText.textContent = `Sessao IBaas: token=${hasToken}, refresh_token=${hasRefresh}, two_factor_id=${hasTwoFactorId}`;
            }
        }

        const rawCandidate = data.raw ?? data.body ?? null;
        if (rawCandidate === null || rawCandidate === undefined) {
            resultRawEl.textContent = '— no raw response —';
        } else if (typeof rawCandidate === 'string') {
            try {
                const parsed = JSON.parse(rawCandidate);
                resultRawEl.textContent = pretty(parsed);
            } catch (e) {
                resultRawEl.textContent = rawCandidate;
            }
        } else {
            resultRawEl.textContent = pretty(rawCandidate);
        }
        toggleCopyVisibility('resultRaw', 'resultRawCopyBtn');
    } catch(err){
        resultSummaryEl.textContent = 'Request failed: ' + err.message;
        resultRawEl.textContent = '';
    } finally {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send request';
        }
    }
});

refreshServiceButtons();
refreshServiceUi();
</script>
</body>
</html>
