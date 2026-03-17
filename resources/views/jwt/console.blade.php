<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AaaS</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; background:#FAFAFA; color:#111827; }
        .container{max-width:980px;margin:32px auto;padding:24px 32px;background:#fff;border:1px solid #e6e6e6;border-radius:8px}
        label{display:block;font-weight:600;margin-bottom:6px}
        input[type=text], select, textarea{width:100%;box-sizing:border-box;max-width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
        button{background:#111827;color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
        pre{background:#0f1724;color:#e6edf3;padding:12px;border-radius:6px;overflow:auto}
        .muted{color:#6b7280;font-size:13px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    </style>
</head>
<body>
    <div class="container">
        <h1 style="margin:0 0 12px">AaaS</h1>
        <p class="muted">Preencha os campos abaixo, gere o token assinado e envie a requisição para a API. O resultado e o JWT serão exibidos em JSON.</p>

        <form id="jwtForm" style="margin-top:16px">
            @csrf
            <div style="margin-bottom:12px">
                <label for="base_url">Base URL</label>
                <input id="base_url" name="base_url" type="text" value="{{ $baseUrl }}" />
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

        <div style="margin-top:18px;margin-bottom:8px;">
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
                    <pre id="jwtToken" style="min-height:80px;white-space:pre-wrap;margin:0"></pre>
                </div>
            </div>
        </div>

        <div style="margin-top:18px;margin-bottom:8px;">
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
                        <h3 style="margin:0 0 6px;">Status</h3>
                        <div style="position:relative">
                            <button id="resultSummaryCopyBtn" type="button"
                                    onclick="copyCardContent('resultSummary')"
                                    style="position:absolute;top:6px;right:6px;z-index:1;background:rgba(15,23,42,0.9);color:#e5e7eb;border:none;padding:4px 8px;font-size:11px;border-radius:4px;cursor:pointer;display:none">
                                Copiar
                            </button>
                            <pre id="resultSummary" style="min-height:160px;white-space:pre-wrap;margin:0"></pre>
                        </div>
                    </div>
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
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const IAAS_GROUPS = {
    account: {
        label: 'Account',
        endpoints: [
            '/v1/aaas/account',
            '/v1/aaas/account/list',
            '/v1/aaas/account/{account_id}',
            '/v1/aaas/account/{account_id}/details',
            '/v1/aaas/account/{account_id}/phone/unlink',
            '/v1/aaas/account/{account_id}/phone',
            '/v1/aaas/account/{account_id}/statement',
            '/v1/aaas/account/{account_id}/balance/lock',
            '/v1/aaas/account/{account_id}/balance/unlock',
            '/v1/aaas/account/{account_id}/payment/refund',
        ],
    },
    cashIn: {
        label: 'Cash In',
        endpoints: [
            '/v1/aaas/cash-in/{account_id}/pix/static-qr-code',
            '/v1/aaas/cash-in/{account_id}/pix/dynamic-qr-code',
        ],
    },
    cashOut: {
        label: 'Cash Out',
        endpoints: [
            '/v1/aaas/cash-out/make-pix-transfer',
            '/v1/aaas/cash-out/make-non-priority-pix-transfer',
            '/v1/aaas/cash-out/make-pix-transfer-only-with-alias',
            '/v1/aaas/cash-out/decode-qr-code',
            '/v1/aaas/cash-out/make-bank-transfer',
            '/v1/aaas/cash-out/make-bank-slip-payment',
            '/v1/aaas/cash-out/make-utilities-payment',
            '/v1/aaas/cash-out/make-internal-transfer',
            '/v1/aaas/cash-out/return-internal-transfer',
        ],
    },
    transaction: {
        label: 'Transaction',
        endpoints: [
            '/v1/aaas/transaction/{transaction_id}',
            '/v1/aaas/transaction/withdraw/{withdraw_id}',
        ],
    },
    webhook: {
        label: 'Webhook',
        endpoints: [
            '/v1/aaas/webhooks',
            '/v1/aaas/webhooks/list',
            '/v1/aaas/webhooks/{webhook_id}',
            '/v1/aaas/webhooks/{webhook_id}/update',
            '/v1/aaas/webhooks/{webhook_id}/delete',
        ],
    },
    batchesAndBillings: {
        label: 'Batches and Billings',
        endpoints: [
            '/v1/aaas/process/{file_type}/validate-shipment',
            '/v1/validate/cnab400',
            '/v1/aaas/process/{account_id}/file-type/{file_type}/send-shipment',
            '/v1/aaas/process/{account_id}/send-invoice',
            '/v1/aaas/process/{account_id}/send-recharge',
            '/v1/aaas/process/{account_id}/{payment_slip_number}/get-payment-slip/pdf',
            '/v1/aaas/process/{account_id}/batches/{uuid}/slips/zip',
            '/v1/aaas/process/{account_id}/{payment_slip_number}/get-payment-slip',
            '/v1/aaas/process/{account_id}/batch/{batch_id}/return-file/{format}',
            '/v1/aaas/process/{account_id}/return-file/{format}',
            '/v1/aaas/process/{account_id}/batches',
            '/v1/aaas/process/{account_id}/batches/{uuid}',
            '/v1/aaas/process/{account_id}/billings',
            '/v1/aaas/process/{account_id}/billings/{uuid}',
            '/v1/aaas/process/{account_id}/billings/batch/{batch_uuid}',
            '/v1/aaas/process/{uuid}/status-billing',
            '/v1/aaas/process/{account_id}/billings/{uuid}',
        ],
    },
};

const endpointInput = document.getElementById('endpoint');
const endpointDropdown = document.getElementById('endpointDropdown');

function buildEndpointList() {
    const list = [];
    Object.keys(IAAS_GROUPS).forEach((key) => {
        const group = IAAS_GROUPS[key];
        group.endpoints.forEach((ep) => {
            list.push(ep);
        });
    });
    return list;
}

const ALL_ENDPOINTS = buildEndpointList();

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
    endpointDropdown.style.display = 'none';
    endpointInput.focus();
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

    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';
    }

    resultSummaryEl.textContent = 'Sending...';
    resultRawEl.textContent = 'Sending...';
    statusText.textContent = '';
    if (jwtTokenEl) {
        jwtTokenEl.textContent = 'Gerando...';
        toggleCopyVisibility('jwtToken', 'jwtTokenCopyBtn');
    }

    const payload = {
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
        };
        resultSummaryEl.textContent = pretty(summary);
        toggleCopyVisibility('resultSummary', 'resultSummaryCopyBtn');

        expandResultSection();

        if (jwtTokenEl) {
            if (data.token) {
                jwtTokenEl.textContent = data.token;
            } else {
                jwtTokenEl.textContent = '— nenhum token retornado —';
            }
            toggleCopyVisibility('jwtToken', 'jwtTokenCopyBtn');
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
</script>
</body>
</html>
