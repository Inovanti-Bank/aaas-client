// Remove aspas acidentais copiadas de arquivos .env
function stripSurroundingQuotes(value) {
    return value.trim().replace(/^["']|["']$/g, '');
}

// Modal secundário com o passo a passo de geração do par de chaves
function initKeysHelpModal() {
    const helpModal = document.getElementById('keysHelpModal');
    const helpContent = document.getElementById('keysHelpModalContent');
    const helpBtn = document.getElementById('keysHelpBtn');
    const helpCloseBtn = document.getElementById('keysHelpCloseBtn');

    helpBtn.addEventListener('click', () => {
        helpModal.hidden = false;
    });
    helpCloseBtn.addEventListener('click', () => {
        helpModal.hidden = true;
    });
    helpModal.addEventListener('click', (event) => {
        if (!helpContent.contains(event.target)) {
            helpModal.hidden = true;
        }
    });
}

/**
 * Modal de configuração das credenciais do IAaas (API Key + chave privada).
 * `onSaved` é chamado após o backend confirmar o salvamento.
 */
export function createIaaasKeysModal({ saveUrl, csrf, onSaved }) {
    const modal = document.getElementById('iaaasKeysModal');
    const content = document.getElementById('iaaasKeysModalContent');
    const apiKeyInput = document.getElementById('iaaasApiKeyInput');
    const privateKeyInput = document.getElementById('iaaasPrivateKeyInput');
    const statusEl = document.getElementById('iaaasKeysModalStatus');
    const cancelBtn = document.getElementById('iaaasKeysCancelBtn');
    const saveBtn = document.getElementById('iaaasKeysSaveBtn');

    function open() {
        apiKeyInput.value = '';
        privateKeyInput.value = '';
        statusEl.hidden = true;
        modal.hidden = false;
        apiKeyInput.focus();
    }

    function close() {
        modal.hidden = true;
    }

    function showError(message) {
        statusEl.textContent = message;
        statusEl.hidden = false;
    }

    async function save() {
        const apiKey = stripSurroundingQuotes(apiKeyInput.value);
        const privateKey = stripSurroundingQuotes(privateKeyInput.value);

        if (!apiKey || !privateKey) {
            showError('Por favor, insira a chave da API e a chave privada.');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Salvando...';
        statusEl.hidden = true;

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ api_key: apiKey, private_key: privateKey }),
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                close();
                onSaved();
            } else {
                showError(data.error || 'Erro ao salvar as chaves.');
            }
        } catch (e) {
            showError(e && e.message ? e.message : 'Erro ao salvar as chaves.');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salvar';
        }
    }

    cancelBtn.addEventListener('click', close);
    saveBtn.addEventListener('click', save);
    modal.addEventListener('click', (event) => {
        if (!content.contains(event.target)) {
            close();
        }
    });
    initKeysHelpModal();

    return { open };
}
