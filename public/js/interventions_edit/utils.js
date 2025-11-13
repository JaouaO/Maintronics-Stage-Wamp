// utils.js (module)

export function withBtnLock(buttonElement, callback) {
    if (!buttonElement) return callback();

    if (buttonElement.dataset.lock === '1') return;

    buttonElement.dataset.lock = '1';
    const previousDisabledState = buttonElement.disabled;
    buttonElement.disabled = true;

    let result;
    try {
        result = callback();
    } catch (error) {
        buttonElement.dataset.lock = '';
        buttonElement.disabled = previousDisabledState;
        throw error;
    }

    // Gestion des callbacks async (Promise)
    if (result && typeof result.then === 'function') {
        return result.finally(() => {
            buttonElement.dataset.lock = '';
            buttonElement.disabled = previousDisabledState;
        });
    }

    buttonElement.dataset.lock = '';
    buttonElement.disabled = previousDisabledState;
    return result;
}

export const pad = (value) => (value < 10 ? '0' : '') + value;


export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// --- Heure serveur centralisée ---
export const SERVER_OFFSET_MS = (() => {
    const baseServerDate = new Date(
        (window.APP && window.APP.serverNow) || Date.now()
    );
    return baseServerDate.getTime() - Date.now();
})();

/** "Maintenant" côté serveur (Date) */
export function nowServer() {
    return new Date(Date.now() + SERVER_OFFSET_MS);
}

/** Minuit local d'une Date */
export function startOfDay(date) {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);
    return copy;
}

/** true si date < aujourd'hui (selon l'heure serveur) */
export function isBeforeToday(date) {
    return startOfDay(date) < startOfDay(nowServer());
}

/** Récupère tous les codes techniciens du sélecteur d'agenda (#selModeTech), hors "_ALL" */
export function getAgendaCodes() {
    const options = document.querySelectorAll('#selModeTech option');

    return Array.from(options)
        .map(option =>
            (option.value || '').toUpperCase().trim()
        )
        .filter(value => value && value !== '_ALL');
}

/** True si le code technicien est présent dans la liste de l'agenda */
export function isInAgendaList(code) {
    const normalizedCode = (code || '').toUpperCase().trim();
    if (!normalizedCode) return false;

    try {
        return getAgendaCodes().includes(normalizedCode);
    } catch {
        return false;
    }
}
