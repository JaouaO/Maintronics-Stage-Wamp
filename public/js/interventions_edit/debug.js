// debug.js
export const DBG = window.__DBG || (window.__DBG = {
    ON: true,
    prefix: '[INTV]',

    log(...args)  { if (this.ON) console.log(this.prefix, ...args); },
    warn(...args) { if (this.ON) console.warn(this.prefix, ...args); },
    err(...args)  { if (this.ON) console.error(this.prefix, ...args); },

    /**
     * Vérifie la présence d'un élément dans le DOM.
     * Retourne true/false et log le résultat.
     */
    expect(selector, label) {
        const element = document.querySelector(selector);
        this.log('expect', label || selector, !!element, element);
        return !!element;
    }
});

// erreurs globales
window.addEventListener('error', event =>
    DBG.err(
        'window.onerror',
        event?.message,
        event?.filename,
        event?.lineno,
        event?.colno,
        event?.error
    )
);

window.addEventListener('unhandledrejection', event =>
    DBG.err('unhandledrejection', event?.reason)
);

export function healthCheck() {
    const modalElement = document.querySelector('.modal') || document.body;
    const zIndex = getComputedStyle(modalElement).zIndex;

    DBG.log('— HEALTH CHECK —');
    DBG.expect('#infoModal', 'modal container');
    DBG.expect('#infoModalBody', 'modal body');
    DBG.expect('#calGrid', 'agenda calGrid');
    DBG.expect('#calListRows', 'agenda list rows');
    DBG.log('modal z-index =', zIndex);
}
