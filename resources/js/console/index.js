import { createEndpointDropdown } from './endpointDropdown';
import { addHistoryEntry, clearHistory, loadHistory } from './history';
import { buildCurlCommand } from './curl';
import { decodeJwtPayload } from './jwt';
import { pretty, renderJson } from './jsonHighlight';
import { createIaaasKeysModal } from './keysModal';
import { extractPathParams, substitutePathParams } from './pathParams';
import {
    HTTP_METHOD_CLASSES,
    copyToClipboard,
    initLayout,
    initTheme,
    renderIbaasSessionBadge,
    renderStatusBadge,
} from './ui';

const SERVICE_IAAAS = 'iaaas';
const SERVICE_IBAAS = 'ibaas';
const IBAAS_ENDPOINT_LOGIN = '/v1/auth/login';
const IBAAS_ENDPOINT_LOGIN_2FA = '/v1/auth/login-2fa';
const IBAAS_ENDPOINT_LOGOUT = '/v1/auth/logout';

const IBAAS_LOGIN_BODY_TEMPLATE = `{
    "username": "{{testUserName}}",
    "password": "{{testPassword}}"
}`;
const IBAAS_LOGIN_2FA_BODY_TEMPLATE = `{
    "two_factor_id": "{{twoFactorId}}",
    "code": "123456"
}`;

const configEl = document.getElementById('consoleConfig');
if (configEl) {
    initConsole(JSON.parse(configEl.textContent));
}

function initConsole(config) {
    const el = (id) => document.getElementById(id);
    const refs = {
        form: el('consoleForm'),
        sendBtn: el('sendBtn'),
        statusText: el('statusText'),
        serviceInput: el('service'),
        serviceButtons: document.querySelectorAll('.service-btn'),
        baseUrlInput: el('base_url'),
        baseUrlLabel: el('baseUrlLabel'),
        baseUrlHint: el('baseUrlHint'),
        endpointInput: el('endpoint'),
        endpointDropdown: el('endpointDropdown'),
        pathParamsSection: el('pathParamsSection'),
        pathParamsFields: el('pathParamsFields'),
        methodSelect: el('method'),
        queryParamsInput: el('query_params'),
        bodyInput: el('body'),
        formatBodyBtn: el('formatBodyBtn'),
        resultSection: el('resultSection'),
        statusBadge: el('statusBadge'),
        resultRaw: el('resultRaw'),
        resultSummary: el('resultSummary'),
        copyCurlBtn: el('copyCurlBtn'),
        dumpSection: el('dumpSection'),
        dumpIframe: el('dumpIframe'),
        jwtTokenSection: el('jwtTokenSection'),
        jwtToken: el('jwtToken'),
        jwtTokenCopyBtn: el('jwtTokenCopyBtn'),
        resultRawCopyBtn: el('resultRawCopyBtn'),
        resultSummaryCopyBtn: el('resultSummaryCopyBtn'),
        jwtDecoderSection: el('jwtDecoderSection'),
        jwtPayloadInput: el('jwtPayloadInput'),
        jwtPayloadJson: el('jwtPayloadJson'),
        decodeJwtBtn: el('decodeJwtBtn'),
        historyList: el('historyList'),
        historyEmpty: el('historyEmpty'),
        clearHistoryBtn: el('clearHistoryBtn'),
        ibaasSessionBadge: el('ibaasSessionBadge'),
        ibaasLogoutBtn: el('ibaasLogoutBtn'),
        reconfigureKeysBtn: el('reconfigureKeysBtn'),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let lastIbaasTwoFactorId = '';
    let lastSentRequest = null;
    let hasIaaasKeys = Boolean(config.hasIaaasKeys);

    const iaaasKeysModal = createIaaasKeysModal({
        saveUrl: config.iaaasKeysUrl,
        csrf,
        onSaved: () => {
            hasIaaasKeys = true;
            changeService(SERVICE_IAAAS);
            refs.statusText.textContent = 'Chaves do IAaas salvas.';
        },
    });

    initTheme(el('themeToggleBtn'));
    initLayout({
        rootEl: el('consoleRoot'),
        mainEl: el('consoleMain'),
        sideBtn: el('layoutSideBtn'),
        stackedBtn: el('layoutStackedBtn'),
    });

    function buildEndpointGroups() {
        if (refs.serviceInput.value === SERVICE_IBAAS) {
            return [{ label: null, endpoints: config.ibaasEndpoints }];
        }
        return Object.values(config.iaaasGroups).map((group) => ({
            label: group.label,
            endpoints: group.endpoints,
        }));
    }

    const dropdown = createEndpointDropdown({
        inputEl: refs.endpointInput,
        dropdownEl: refs.endpointDropdown,
        getSource: buildEndpointGroups,
        onSelect: handleEndpointChange,
    });

    refs.endpointInput.addEventListener('input', (event) => handleEndpointChange(event.target.value));

    function handleEndpointChange(endpointValue) {
        maybeApplyIbaasLoginBody(endpointValue);
        renderPathParamFields();
    }

    function maybeApplyIbaasLoginBody(endpointValue) {
        if (refs.serviceInput.value !== SERVICE_IBAAS) return;

        const normalizedEndpoint = `/${String(endpointValue || '').trim().replace(/^\/+/, '')}`;
        if (normalizedEndpoint === IBAAS_ENDPOINT_LOGIN) {
            refs.bodyInput.value = IBAAS_LOGIN_BODY_TEMPLATE;
        } else if (normalizedEndpoint === IBAAS_ENDPOINT_LOGIN_2FA) {
            refs.bodyInput.value = IBAAS_LOGIN_2FA_BODY_TEMPLATE
                .replace('{{twoFactorId}}', lastIbaasTwoFactorId || '{{twoFactorId}}');
        }
    }

    function collectPathParamValues() {
        const values = {};
        refs.pathParamsFields.querySelectorAll('input[data-param-name]').forEach((input) => {
            values[input.dataset.paramName] = input.value;
        });
        return values;
    }

    function renderPathParamFields(presetValues = {}) {
        const names = extractPathParams(refs.endpointInput.value);
        const previousValues = { ...collectPathParamValues(), ...presetValues };

        refs.pathParamsFields.innerHTML = '';
        names.forEach((name) => {
            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            label.className = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300';
            label.textContent = name;
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-input';
            input.dataset.paramName = name;
            input.value = previousValues[name] || '';
            wrapper.append(label, input);
            refs.pathParamsFields.appendChild(wrapper);
        });
        refs.pathParamsSection.hidden = names.length === 0;
    }

    function refreshServiceUi() {
        const isIbaas = refs.serviceInput.value === SERVICE_IBAAS;
        refs.serviceButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.service === refs.serviceInput.value);
        });
        refs.jwtTokenSection.hidden = isIbaas;
        refs.jwtDecoderSection.hidden = isIbaas;
        refs.baseUrlLabel.textContent = isIbaas ? 'Tenant Base URL' : 'Base URL';
        refs.baseUrlHint.classList.toggle('hidden', !isIbaas);
        refs.endpointInput.placeholder = isIbaas ? '/v1/baas/…' : '/v1/aaas/…';
        refs.reconfigureKeysBtn.hidden = isIbaas || !hasIaaasKeys;
    }

    function changeService(nextService) {
        refs.serviceInput.value = nextService;
        refs.endpointInput.value = '';
        renderPathParamFields();
        dropdown.close();

        const defaults = config.serviceDefaults[nextService];
        if (defaults && defaults.baseUrl) {
            refs.baseUrlInput.value = defaults.baseUrl;
        }
        refreshServiceUi();
    }

    refs.serviceButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextService = button.dataset.service || SERVICE_IAAAS;
            if (nextService === SERVICE_IAAAS && !hasIaaasKeys) {
                iaaasKeysModal.open();
                return;
            }
            changeService(nextService);
        });
    });

    refs.reconfigureKeysBtn.addEventListener('click', () => iaaasKeysModal.open());

    refs.formatBodyBtn.addEventListener('click', () => {
        const rawBody = refs.bodyInput.value.trim();
        if (rawBody === '') return;
        try {
            refs.bodyInput.value = pretty(JSON.parse(rawBody));
            refs.statusText.textContent = '';
        } catch (e) {
            refs.statusText.textContent = 'Body não é um JSON válido.';
        }
    });

    document.querySelectorAll('button[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = el(button.dataset.copyTarget);
            const copied = await copyToClipboard(target ? target.textContent : '');
            refs.statusText.textContent = copied ? 'Conteúdo copiado.' : 'Não foi possível copiar o conteúdo.';
        });
    });

    refs.copyCurlBtn.addEventListener('click', async () => {
        const copied = await copyToClipboard(buildCurlCommand(lastSentRequest));
        refs.statusText.textContent = copied ? 'Comando cURL copiado.' : 'Não foi possível copiar o comando.';
    });

    refs.decodeJwtBtn.addEventListener('click', () => {
        const manualToken = refs.jwtPayloadInput.value.trim();
        const generatedToken = (refs.jwtToken.textContent || '').trim();
        const token = manualToken || generatedToken;

        if (!token) {
            refs.jwtPayloadJson.textContent = 'Informe um JWT ou gere um token acima.';
            return;
        }

        const result = decodeJwtPayload(token);
        if (!result.ok) {
            refs.jwtPayloadJson.textContent = result.error;
        } else if (result.payload !== undefined) {
            renderJson(refs.jwtPayloadJson, result.payload);
        } else {
            refs.jwtPayloadJson.textContent = result.text;
        }
    });

    function toggleCopyVisibility(preEl, buttonEl) {
        buttonEl.hidden = (preEl.textContent || '').trim().length === 0;
    }

    function renderHistory(entries) {
        refs.historyEmpty.hidden = entries.length > 0;
        refs.historyList.innerHTML = '';

        entries.forEach((entry) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full items-center gap-3 px-1 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60';

            const method = document.createElement('span');
            method.className = `w-14 shrink-0 font-mono text-xs font-bold ${HTTP_METHOD_CLASSES[entry.method] || ''}`;
            method.textContent = entry.method;

            const endpoint = document.createElement('span');
            endpoint.className = 'min-w-0 flex-1 truncate font-mono text-xs';
            endpoint.textContent = entry.endpointTemplate;
            endpoint.title = entry.endpointTemplate;

            const meta = document.createElement('span');
            meta.className = 'shrink-0 text-xs text-gray-400';
            const statusLabel = entry.status ? `${entry.status} · ` : '';
            meta.textContent = `${entry.service} · ${statusLabel}${new Date(entry.ts).toLocaleString('pt-BR')}`;

            button.append(method, endpoint, meta);
            button.addEventListener('click', () => restoreHistoryEntry(entry));
            item.appendChild(button);
            refs.historyList.appendChild(item);
        });
    }

    function restoreHistoryEntry(entry) {
        changeService(entry.service);
        refs.endpointInput.value = entry.endpointTemplate;
        renderPathParamFields(entry.pathParams || {});
        refs.methodSelect.value = entry.method;
        refs.queryParamsInput.value = entry.query || '';
        refs.bodyInput.value = entry.body || '';
        refs.statusText.textContent = 'Requisição restaurada do histórico.';
        refs.endpointInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    refs.clearHistoryBtn.addEventListener('click', (event) => {
        event.preventDefault();
        renderHistory(clearHistory());
    });

    function updateIbaasStatusText(data) {
        if (!data.ibaas_session) return;
        const hasToken = data.ibaas_session.has_token ? 'sim' : 'nao';
        const hasRefresh = data.ibaas_session.has_refresh_token ? 'sim' : 'nao';
        const hasTwoFactorId = data.ibaas_session.has_two_factor_id ? 'sim' : 'nao';
        if (data.body && data.body.two_factor_required) {
            refs.statusText.textContent = `Sessao IBaas: token=${hasToken}, refresh_token=${hasRefresh}, two_factor_pendente=sim. Use ${IBAAS_ENDPOINT_LOGIN_2FA} com o code.`;
        } else {
            refs.statusText.textContent = `Sessao IBaas: token=${hasToken}, refresh_token=${hasRefresh}, two_factor_id=${hasTwoFactorId}`;
        }
    }

    function renderDumpIfPresent(data, responseSummary) {
        const rawStr = typeof data.raw === 'string' ? data.raw : '';
        const isDump = rawStr.includes('Sfdump = window.Sfdump')
            || rawStr.includes('class="sf-dump"')
            || rawStr.includes('class=sf-dump');

        if (!isDump) {
            refs.dumpSection.hidden = true;
            refs.dumpIframe.srcdoc = '';
            return;
        }

        refs.dumpSection.hidden = false;
        refs.dumpSection.open = true;
        refs.dumpIframe.srcdoc = '<style>body{color:#e6edf3; margin:0; padding:12px; font-family:monospace;}</style>' + rawStr;
        responseSummary.body = '[HTML do Dump renderizado na seção "Laravel Dump"]';
        setTimeout(() => refs.dumpSection.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
    }

    function maybeDownloadPdf(data, responseSummary) {
        const headers = responseSummary.headers || {};
        const contentTypeKey = Object.keys(headers).find((key) => key.toLowerCase() === 'content-type');
        const contentType = contentTypeKey ? headers[contentTypeKey] : null;
        const isPdf = contentType && (Array.isArray(contentType)
            ? contentType.some((value) => value.includes('application/pdf'))
            : contentType.includes('application/pdf'));
        if (!isPdf) return;

        try {
            const downloadLink = document.createElement('a');
            downloadLink.href = `data:application/pdf;base64,${data.raw}`;
            downloadLink.download = `document_${Date.now()}.pdf`;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            refs.statusText.textContent = 'Download do PDF iniciado automaticamente.';
        } catch (e) {
            refs.statusText.textContent = 'Erro ao tentar baixar o PDF.';
        }
    }

    async function postToConsole(payload) {
        const response = await fetch(config.sendUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({ raw: 'no-json-response' }));
        return { response, data };
    }

    refs.ibaasLogoutBtn.addEventListener('click', async () => {
        refs.ibaasLogoutBtn.disabled = true;
        try {
            const { data } = await postToConsole({
                service: SERVICE_IBAAS,
                endpoint: IBAAS_ENDPOINT_LOGOUT,
                method: 'POST',
            });
            if (data.ibaas_session) {
                renderIbaasSessionBadge(refs.ibaasSessionBadge, refs.ibaasLogoutBtn, data.ibaas_session);
            }
            refs.statusText.textContent = data.ok ? 'Sessão IBaas encerrada.' : 'Falha ao encerrar a sessão IBaas.';
        } catch (e) {
            refs.statusText.textContent = 'Falha ao encerrar a sessão IBaas.';
        } finally {
            refs.ibaasLogoutBtn.disabled = false;
        }
    });

    refs.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const selectedService = refs.serviceInput.value;
        const isIbaas = selectedService === SERVICE_IBAAS;

        const pathParamValues = collectPathParamValues();
        const substitution = substitutePathParams(refs.endpointInput.value, pathParamValues);
        if (substitution.missing.length > 0) {
            refs.statusText.textContent = `Preencha os parâmetros do path: ${substitution.missing.join(', ')}.`;
            const firstMissing = refs.pathParamsFields.querySelector(`input[data-param-name="${substitution.missing[0]}"]`);
            if (firstMissing) firstMissing.focus();
            return;
        }

        refs.sendBtn.disabled = true;
        refs.sendBtn.textContent = 'Enviando...';
        refs.resultSummary.textContent = 'Enviando...';
        refs.resultRaw.textContent = 'Enviando...';
        refs.statusText.textContent = '';
        refs.statusBadge.hidden = true;
        refs.copyCurlBtn.hidden = true;
        if (!isIbaas) {
            refs.jwtToken.textContent = 'Gerando...';
            toggleCopyVisibility(refs.jwtToken, refs.jwtTokenCopyBtn);
        }

        const payload = {
            service: selectedService,
            base_url: refs.baseUrlInput.value,
            endpoint: substitution.endpoint,
            method: refs.methodSelect.value,
            query_params: refs.queryParamsInput.value,
            body: refs.bodyInput.value || null,
        };

        try {
            const { response, data } = await postToConsole(payload);

            lastSentRequest = data.request || null;
            refs.copyCurlBtn.hidden = !lastSentRequest;

            renderJson(refs.resultSummary, {
                request: data.request ?? payload,
                ibaas_session: data.ibaas_session ?? null,
            });
            toggleCopyVisibility(refs.resultSummary, refs.resultSummaryCopyBtn);

            refs.resultSection.open = true;
            renderStatusBadge(refs.statusBadge, data.status ?? response.status);

            if (isIbaas && data.body && data.body.two_factor_required
                && typeof data.body.two_factor_id === 'string' && data.body.two_factor_id.trim() !== '') {
                lastIbaasTwoFactorId = data.body.two_factor_id;
            }

            if (!isIbaas) {
                refs.jwtToken.textContent = data.token || '— nenhum token retornado —';
                toggleCopyVisibility(refs.jwtToken, refs.jwtTokenCopyBtn);
            }

            if (data.ibaas_session) {
                renderIbaasSessionBadge(refs.ibaasSessionBadge, refs.ibaasLogoutBtn, data.ibaas_session);
            }
            if (isIbaas) {
                updateIbaasStatusText(data);
            }

            const isWrappedApiResponse = ['status', 'request', 'headers']
                .some((key) => Object.prototype.hasOwnProperty.call(data, key));
            const responseSummary = {
                status: data.status ?? response.status,
                ok: data.ok ?? response.ok,
                headers: data.headers ?? {},
                body: data.body ?? (isWrappedApiResponse ? null : data),
            };

            renderDumpIfPresent(data, responseSummary);
            renderJson(refs.resultRaw, responseSummary);
            maybeDownloadPdf(data, responseSummary);
            toggleCopyVisibility(refs.resultRaw, refs.resultRawCopyBtn);

            renderHistory(addHistoryEntry({
                ts: Date.now(),
                service: selectedService,
                method: refs.methodSelect.value,
                endpointTemplate: refs.endpointInput.value,
                pathParams: pathParamValues,
                query: refs.queryParamsInput.value,
                body: refs.bodyInput.value,
                status: data.status ?? response.status,
            }));
        } catch (err) {
            refs.resultSummary.textContent = 'Request failed: ' + err.message;
            refs.resultRaw.textContent = '';
        } finally {
            refs.sendBtn.disabled = false;
            refs.sendBtn.textContent = 'Enviar requisição';
        }
    });

    refreshServiceUi();
    renderPathParamFields();
    renderHistory(loadHistory());
    renderIbaasSessionBadge(refs.ibaasSessionBadge, refs.ibaasLogoutBtn, config.ibaasSession);
}
