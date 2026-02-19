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
        <p class="muted">Preencha os campos abaixo, gere o token assinado e envie a requisição para a API. O resultado será exibido em JSON.</p>

        <form id="jwtForm" style="margin-top:16px">
            @csrf
            <div style="margin-bottom:12px">
                <label for="base_url">Base URL</label>
                <input id="base_url" name="base_url" type="text" value="{{ $baseUrl }}" />
            </div>

            <div style="margin-bottom:12px">
                <label for="endpoint">Endpoint (path)</label>
                <input id="endpoint" name="endpoint" type="text" placeholder="/v1/aaas/…" required />
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

        <h2 style="margin-top:18px;margin-bottom:8px">Resultado</h2>
        <div class="result-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
                <h3 style="margin:0 0 6px">Status</h3>
                <pre id="resultSummary" style="min-height:160px;white-space:pre-wrap"></pre>
            </div>
            <div>
                <h3 style="margin:0 0 6px">Response</h3>
                <pre id="resultRaw" style="min-height:160px;white-space:pre-wrap"></pre>
            </div>
        </div>
    </div>

<script>
const form = document.getElementById('jwtForm');
const resultSummaryEl = document.getElementById('resultSummary');
const resultRawEl = document.getElementById('resultRaw');
const statusText = document.getElementById('statusText');
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function pretty(v){ try{ return JSON.stringify(v, null, 2) }catch(e){ return String(v) } }

form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    resultSummaryEl.textContent = 'Sending...';
    resultRawEl.textContent = 'Sending...';
    statusText.textContent = '';

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
    } catch(err){
        resultSummaryEl.textContent = 'Request failed: ' + err.message;
        resultRawEl.textContent = '';
    }
});
</script>
</body>
</html>
