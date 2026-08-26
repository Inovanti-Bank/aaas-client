export function getStoredPreference(key, fallback) {
    try {
        return localStorage.getItem(key) || fallback;
    } catch (e) {
        return fallback;
    }
}

export function setStoredPreference(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (e) {}
}
