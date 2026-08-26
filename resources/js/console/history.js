const STORAGE_KEY = 'aaasConsole.history';
const MAX_ENTRIES = 30;

export function loadHistory() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

export function addHistoryEntry(entry) {
    const entries = [entry, ...loadHistory()].slice(0, MAX_ENTRIES);
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(entries));
    } catch (e) {
        // localStorage indisponível ou cheio — o histórico é apenas conveniência
    }
    return entries;
}

export function clearHistory() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        // localStorage indisponível — nada a limpar
    }
    return [];
}
