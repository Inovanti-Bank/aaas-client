import { getStoredPreference, setStoredPreference } from './preferences';

const THEME_STORAGE_KEY = 'aaasConsole.theme';
const LAYOUT_STORAGE_KEY = 'aaasConsole.layout';

export const LAYOUT_SIDE = 'side';
export const LAYOUT_STACKED = 'stacked';

const STATUS_BADGE_CLASSES = {
    success: 'bg-energia/20 text-energia-dark dark:bg-energia/20 dark:text-energia',
    redirect: 'bg-cobalto/10 text-cobalto dark:bg-cobalto/40 dark:text-cobalto-light',
    clientError: 'bg-ambar/25 text-ambar-dark dark:bg-ambar/15 dark:text-ambar',
    serverError: 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300',
};

const BADGE_BASE_CLASS = 'rounded-full px-2.5 py-0.5 text-xs font-bold';

export const HTTP_METHOD_CLASSES = {
    GET: 'text-energia-dark dark:text-energia',
    POST: 'text-cobalto dark:text-cobalto-light',
    PUT: 'text-ambar-dark dark:text-ambar',
    PATCH: 'text-urbano',
    DELETE: 'text-red-600 dark:text-red-400',
};

function statusBadgeClass(status) {
    if (status >= 500) return STATUS_BADGE_CLASSES.serverError;
    if (status >= 400) return STATUS_BADGE_CLASSES.clientError;
    if (status >= 300) return STATUS_BADGE_CLASSES.redirect;
    return STATUS_BADGE_CLASSES.success;
}

export function renderStatusBadge(badgeEl, status) {
    if (!badgeEl) return;
    badgeEl.textContent = String(status);
    badgeEl.className = `${BADGE_BASE_CLASS} ${statusBadgeClass(Number(status))}`;
    badgeEl.hidden = false;
}

const SESSION_BADGE_STATES = {
    authenticated: { label: 'Autenticado', classes: 'bg-energia/20 text-energia-dark dark:bg-energia/20 dark:text-energia' },
    twoFactorPending: { label: '2FA pendente', classes: 'bg-ambar/25 text-ambar-dark dark:bg-ambar/15 dark:text-ambar' },
    anonymous: { label: 'Sem sessão', classes: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-urbano' },
};

export function renderIbaasSessionBadge(badgeEl, logoutBtn, sessionState) {
    if (!badgeEl) return;

    let state = SESSION_BADGE_STATES.anonymous;
    if (sessionState && sessionState.has_token) {
        state = SESSION_BADGE_STATES.authenticated;
    } else if (sessionState && sessionState.has_two_factor_id) {
        state = SESSION_BADGE_STATES.twoFactorPending;
    }

    badgeEl.textContent = state.label;
    badgeEl.className = `rounded-full px-2 py-0.5 text-xs font-semibold ${state.classes}`;
    if (logoutBtn) {
        logoutBtn.hidden = state !== SESSION_BADGE_STATES.authenticated;
    }
}

export function initTheme(toggleBtn) {
    if (!toggleBtn) return;
    toggleBtn.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');
        setStoredPreference(THEME_STORAGE_KEY, isDark ? 'dark' : 'light');
    });
}

export function initLayout({ rootEl, mainEl, sideBtn, stackedBtn }) {
    function apply(layout) {
        const isSideLayout = layout === LAYOUT_SIDE;
        mainEl.classList.toggle('xl:grid-cols-2', isSideLayout);
        rootEl.classList.toggle('max-w-[96rem]', isSideLayout);
        rootEl.classList.toggle('max-w-4xl', !isSideLayout);
        sideBtn.classList.toggle('active', isSideLayout);
        stackedBtn.classList.toggle('active', !isSideLayout);
        setStoredPreference(LAYOUT_STORAGE_KEY, layout);
    }

    sideBtn.addEventListener('click', () => apply(LAYOUT_SIDE));
    stackedBtn.addEventListener('click', () => apply(LAYOUT_STACKED));
    apply(getStoredPreference(LAYOUT_STORAGE_KEY, LAYOUT_SIDE));
}

export async function copyToClipboard(text) {
    if (!text) return false;

    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return true;
    } catch (e) {
        return false;
    }
}
