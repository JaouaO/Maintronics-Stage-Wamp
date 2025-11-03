// utils.js (module)
export function withBtnLock(btn, fn) {
    if (!btn) return fn();
    if (btn.dataset.lock === '1') return;
    btn.dataset.lock = '1';
    const prevDisabled = btn.disabled;
    btn.disabled = true;

    let res;
    try { res = fn(); }
    catch (e) { btn.dataset.lock = ''; btn.disabled = prevDisabled; throw e; }

    if (res && typeof res.then === 'function') {
        return res.finally(() => { btn.dataset.lock = ''; btn.disabled = prevDisabled; });
    } else {
        btn.dataset.lock = '';
        btn.disabled = prevDisabled;
        return res;
    }
}

export const pad = n => (n < 10 ? '0' : '') + n;

export function hoursOnly(iso) {
    const dt = new Date(iso);
    return `${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
}

export function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}


// --- Heure serveur centralisée ---
export const SERVER_OFFSET_MS = (() => {
    const base = new Date((window.APP && window.APP.serverNow) || Date.now());
    return base.getTime() - Date.now();
})();

/** "Maintenant" côté serveur (Date) */
export function nowServer() {
    return new Date(Date.now() + SERVER_OFFSET_MS);
}

/** Minuit local d'une Date */
export function startOfDay(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
}

/** true si d est strictement avant "aujourd'hui" (selon l'heure serveur) */
export function isBeforeToday(d) {
    return startOfDay(d) < startOfDay(nowServer());
}
export function getAgendaCodes() {
    // récupère tous les codes du sélecteur agenda (hors _ALL), normalisés
    const opts = document.querySelectorAll('#selModeTech option');
    return Array.from(opts)
        .map(o => (o.value || '').toUpperCase().trim())
        .filter(v => v && v !== '_ALL');
}
export function isInAgendaList(code) {
    const c = (code || '').toUpperCase().trim();
    if (!c) return false;
    try { return getAgendaCodes().includes(c); } catch { return false; }
}
