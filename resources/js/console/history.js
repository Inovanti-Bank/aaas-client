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
    let entries = [entry, ...loadHistory()].slice(0, MAX_ENTRIES);
    while (entries.length > 0) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(entries));
            break;
        } catch (e) {
            entries = entries.slice(0, Math.max(1, entries.length - 5));
            if (entries.length === 1) {
                break;
            }
        }
    }
    return entries;
}

export function clearHistory() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}
    return [];
}
