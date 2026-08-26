<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Humu Service</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('aaasConsole.theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (theme === 'dark' || (!theme && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-carvao dark:text-gray-100">
    <script type="application/json" id="consoleConfig">@json($consoleConfig)</script>

    <div id="consoleRoot" class="mx-auto px-4 py-8 transition-all sm:px-6">
        <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1>
                    <img src="{{ asset('logo-black.png') }}" alt="Humu Service" class="h-9 w-auto dark:hidden">
                    <img src="{{ asset('logo-white.png') }}" alt="Humu Service" class="hidden h-9 w-auto dark:inline">
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Sessão IBaas:</span>
                    <span id="ibaasSessionBadge" class="rounded-full px-2 py-0.5 text-xs font-semibold"></span>
                    <button id="ibaasLogoutBtn" type="button" hidden
                            class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                        Encerrar
                    </button>
                </div>

                <div class="flex rounded-lg border border-gray-200 bg-white p-0.5 dark:border-gray-800 dark:bg-gray-900" role="group" aria-label="Layout da resposta">
                    <button id="layoutSideBtn" type="button" title="Resposta ao lado do formulário"
                            class="layout-btn rounded-md px-2.5 py-1 text-xs font-medium">◫ Ao lado</button>
                    <button id="layoutStackedBtn" type="button" title="Resposta abaixo do formulário"
                            class="layout-btn rounded-md px-2.5 py-1 text-xs font-medium">⬒ Abaixo</button>
                </div>

                <button id="themeToggleBtn" type="button" title="Alternar tema claro/escuro"
                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
                </button>
            </div>
        </header>

        <div class="mb-5 flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">Serviço:</span>
            <button type="button" class="service-btn" data-service="iaaas">IAaas</button>
            <button type="button" class="service-btn" data-service="ibaas">IBaas</button>
            <button type="button" id="reconfigureKeysBtn" hidden
                    title="Configurar chaves (IAaas)" aria-label="Configurar chaves (IAaas)"
                    class="ml-1 rounded-full border border-gray-300 bg-white p-1.5 text-gray-500 transition hover:border-energia hover:text-energia-dark dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-energia dark:hover:text-energia">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.109-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </button>
        </div>

        <main id="consoleMain" class="grid items-start gap-6">
            <section class="space-y-6">
                <form id="consoleForm" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    @csrf
                    <input id="service" name="service" type="hidden" value="ibaas" />

                    <div class="mb-4">
                        <label for="base_url" id="baseUrlLabel" class="mb-1.5 block text-sm font-semibold">Base URL</label>
                        <input id="base_url" name="base_url" type="text" value="{{ $initialBaseUrl }}"
                               class="form-input" />
                        <p class="mt-1.5 hidden text-xs text-gray-500 dark:text-gray-400" id="baseUrlHint">
                            No IBaas, endpoints de auth usam a base fixa de autenticação configurada no backend.
                        </p>
                    </div>

                    <div class="relative mb-4">
                        <label for="endpoint" class="mb-1.5 block text-sm font-semibold">Endpoint (path)</label>
                        <input id="endpoint" name="endpoint" type="text" placeholder="/v1/aaas/…" autocomplete="off" required
                               class="form-input" />
                        <div id="endpointDropdown" hidden
                             class="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white text-sm shadow-xl dark:border-gray-700 dark:bg-gray-900"></div>
                    </div>

                    <div id="pathParamsSection" hidden class="mb-4 rounded-lg border border-dashed border-gray-300 p-3 dark:border-gray-700">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Parâmetros do path</p>
                        <div id="pathParamsFields" class="grid gap-3 sm:grid-cols-2"></div>
                    </div>

                    <div class="mb-4 grid gap-4 sm:grid-cols-[10rem_1fr]">
                        <div>
                            <label for="method" class="mb-1.5 block text-sm font-semibold">Method</label>
                            <select id="method" name="method" class="form-input">
                                <option>GET</option>
                                <option>POST</option>
                                <option>PUT</option>
                                <option>PATCH</option>
                                <option>DELETE</option>
                            </select>
                        </div>
                        <div>
                            <label for="query_params" class="mb-1.5 block text-sm font-semibold">Query params</label>
                            <input id="query_params" name="query_params" type="text"
                                   placeholder="start_date=2025-01-01&end_date=2025-01-30" class="form-input" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="body" class="text-sm font-semibold">Body (JSON) — deixe vazio se não houver</label>
                            <button id="formatBodyBtn" type="button" class="text-xs font-medium text-cobalto hover:underline dark:text-energia">
                                Formatar JSON
                            </button>
                        </div>
                        <textarea id="body" name="body" rows="6" placeholder='{"foo":"bar"}'
                                  class="form-input font-mono text-[13px]"></textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button id="sendBtn" type="submit"
                                class="rounded-lg bg-energia px-4 py-2 text-sm font-semibold text-carvao shadow-sm transition hover:bg-energia/85 disabled:cursor-not-allowed disabled:opacity-60">
                            Enviar requisição
                        </button>
                        <span class="text-sm text-gray-500 dark:text-gray-400" id="statusText"></span>
                    </div>
                </form>

                <details id="historySection" class="console-card">
                    <summary class="console-summary">
                        <span>Histórico de requisições</span>
                        <span class="flex items-center gap-3">
                            <button id="clearHistoryBtn" type="button"
                                    class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Limpar</button>
                            <span class="chevron">▾</span>
                        </span>
                    </summary>
                    <div class="px-5 pb-5">
                        <p id="historyEmpty" class="text-sm text-gray-500 dark:text-gray-400">Nenhuma requisição enviada ainda.</p>
                        <ul id="historyList" class="divide-y divide-gray-100 dark:divide-gray-800"></ul>
                    </div>
                </details>
            </section>

            <section class="space-y-6">
                <details id="resultSection" class="console-card">
                    <summary class="console-summary">
                        <span class="flex items-center gap-3">
                            <span>Resultados da requisição</span>
                            <span id="statusBadge" hidden class="rounded-full px-2.5 py-0.5 text-xs font-bold"></span>
                        </span>
                        <span class="chevron">▾</span>
                    </summary>
                    <div class="space-y-4 px-5 pb-5">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <h3 class="text-sm font-semibold">Response</h3>
                                <button id="resultRawCopyBtn" type="button" hidden class="copy-btn" data-copy-target="resultRaw">Copiar</button>
                            </div>
                            <pre id="resultRaw" class="console-pre min-h-40"></pre>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <h3 class="text-sm font-semibold">Request</h3>
                                <div class="flex items-center gap-2">
                                    <button id="copyCurlBtn" type="button" hidden class="copy-btn">Copiar como cURL</button>
                                    <button id="resultSummaryCopyBtn" type="button" hidden class="copy-btn" data-copy-target="resultSummary">Copiar</button>
                                </div>
                            </div>
                            <pre id="resultSummary" class="console-pre min-h-40"></pre>
                        </div>
                    </div>
                </details>

                <details id="dumpSection" hidden class="console-card">
                    <summary class="console-summary">
                        <span>Laravel Dump (dd/dump)</span>
                        <span class="chevron">▾</span>
                    </summary>
                    <div class="px-5 pb-5">
                        <iframe id="dumpIframe" title="Laravel dump" class="h-96 w-full rounded-lg border-0 bg-carvao" srcdoc=""></iframe>
                    </div>
                </details>

                <details id="jwtTokenSection" class="console-card">
                    <summary class="console-summary">
                        <span>JWT gerado</span>
                        <span class="chevron">▾</span>
                    </summary>
                    <div class="px-5 pb-5">
                        <div class="mb-1.5 flex justify-end">
                            <button id="jwtTokenCopyBtn" type="button" hidden class="copy-btn" data-copy-target="jwtToken">Copiar</button>
                        </div>
                        <pre id="jwtToken" class="console-pre min-h-20"></pre>
                    </div>
                </details>

                <details id="jwtDecoderSection" class="console-card">
                    <summary class="console-summary">
                        <span>Decodificador de JWT</span>
                        <span class="chevron">▾</span>
                    </summary>
                    <div class="px-5 pb-5">
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                            Cole um JWT abaixo ou deixe vazio para usar o JWT gerado acima. O payload será exibido como JSON.
                        </p>
                        <textarea id="jwtPayloadInput" rows="4" class="form-input font-mono text-[13px]"
                                  placeholder="eyJhbGciOiJFUzUxMiIsInR5cCI6IkpXVCJ9.eyJmb28iOiJiYXIifQ.SignatureAqui"></textarea>
                        <div class="mt-2 flex items-center gap-3">
                            <button id="decodeJwtBtn" type="button"
                                    class="rounded-lg bg-carvao px-3 py-1.5 text-sm font-medium text-white transition hover:bg-carvao/80 dark:bg-white dark:text-carvao dark:hover:bg-gray-200">
                                Ver payload (JSON)
                            </button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Não é necessária nenhuma chave para visualizar o conteúdo.</span>
                        </div>
                        <h3 class="mb-1.5 mt-4 text-sm font-semibold">Payload como JSON</h3>
                        <pre id="jwtPayloadJson" class="console-pre min-h-20"></pre>
                    </div>
                </details>
            </section>
        </main>
    </div>

    <div id="iaaasKeysModal" hidden
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div id="iaaasKeysModalContent"
             class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold">Configurar chaves (IAaas)</h3>
            <p class="mb-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                Informe a sua API Key e a sua chave privada para utilizar o serviço IAaas.
                Elas ficam salvas na sessão e em cookie por 7 dias; a assinatura é gerada no servidor da ferramenta.
            </p>

            <label for="iaaasApiKeyInput" class="mb-1.5 block text-sm font-semibold">API Key</label>
            <input id="iaaasApiKeyInput" type="text" placeholder="Chave da API" class="form-input mb-4" />

            <label for="iaaasPrivateKeyInput" class="mb-1.5 block text-sm font-semibold">Private Key (ECDSA)</label>
            <textarea id="iaaasPrivateKeyInput" rows="6" class="form-input font-mono text-xs"
                      placeholder="-----BEGIN EC PRIVATE KEY-----&#10;...&#10;-----END EC PRIVATE KEY-----"></textarea>

            <p id="iaaasKeysModalStatus" hidden class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <button id="keysHelpBtn" type="button"
                        class="text-sm font-medium text-cobalto underline hover:text-cobalto/70 dark:text-energia dark:hover:text-energia/70">
                    Como gerar o par de chaves?
                </button>
                <div class="flex gap-2">
                    <button id="iaaasKeysCancelBtn" type="button"
                            class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        Cancelar
                    </button>
                    <button id="iaaasKeysSaveBtn" type="button"
                            class="rounded-lg bg-energia px-4 py-2 text-sm font-semibold text-carvao shadow-sm transition hover:bg-energia/85 disabled:cursor-not-allowed disabled:opacity-60">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="keysHelpModal" hidden
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div id="keysHelpModalContent"
             class="max-h-[85vh] w-full max-w-xl overflow-auto rounded-xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold">Como gerar o par de chaves (IAaas)</h3>

            <ol class="mt-3 list-decimal space-y-4 pl-5 text-sm">
                <li>
                    <p class="mb-1.5">Gere a <strong>chave privada</strong> ECDSA:</p>
                    <div class="mb-1.5 flex justify-end">
                        <button type="button" class="copy-btn" data-copy-target="keysHelpPrivateCmd">Copiar</button>
                    </div>
                    <pre id="keysHelpPrivateCmd" class="console-pre text-xs">ssh-keygen -t ecdsa -b 521 -m PEM -f jwtECDSASHA512.key</pre>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Quando for perguntada uma passphrase, deixe em branco.</p>
                </li>
                <li>
                    <p class="mb-1.5">A partir dela, gere a <strong>chave pública</strong>:</p>
                    <div class="mb-1.5 flex justify-end">
                        <button type="button" class="copy-btn" data-copy-target="keysHelpPublicCmd">Copiar</button>
                    </div>
                    <pre id="keysHelpPublicCmd" class="console-pre text-xs">openssl ec -in jwtECDSASHA512.key -pubout -outform PEM -out jwtECDSASHA512.key.pub</pre>
                </li>
                <li>
                    <p>Cadastre a <strong>chave pública</strong> no painel de controle: ative a dupla autenticação TOTP em
                    <em>Suas informações → Dupla autenticação</em>, depois acesse
                    <em>Tenants → Ver tenant → Adicionar Chave Pública</em> e cole o conteúdo de
                    <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">jwtECDSASHA512.key.pub</code>.
                    Após o cadastro, a <strong>API Key</strong> será disponibilizada.</p>
                </li>
                <li>
                    <p>Volte aqui e informe a <strong>API Key</strong> e o conteúdo da
                    <strong>chave privada</strong> (<code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">jwtECDSASHA512.key</code>),
                    incluindo as linhas BEGIN/END.</p>
                </li>
            </ol>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                O passo a passo com capturas de tela está no README do projeto.
            </p>

            <div class="mt-4 flex justify-end">
                <button id="keysHelpCloseBtn" type="button"
                        class="rounded-lg bg-carvao px-4 py-2 text-sm font-medium text-white transition hover:bg-carvao/80 dark:bg-white dark:text-carvao dark:hover:bg-gray-200">
                    Entendi
                </button>
            </div>
        </div>
    </div>
</body>
</html>
