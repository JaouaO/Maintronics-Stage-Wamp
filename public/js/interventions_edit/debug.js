// debug.js
export const DBG = window.__DBG || (window.__DBG = {
    ON: true, pfx: '[INTV]',
    log(){ if (this.ON) console.log(this.pfx, ...arguments); },
    warn(){ if (this.ON) console.warn(this.pfx, ...arguments); },
    err(){ if (this.ON) console.error(this.pfx, ...arguments); },
    expect(sel, label){ const el = document.querySelector(sel); this.log('expect', label||sel, !!el, el); return !!el; }
});

// erreurs globales
window.addEventListener('error',  e => DBG.err('window.onerror', e?.message, e?.filename, e?.lineno, e?.colno, e?.error));
window.addEventListener('unhandledrejection', e => DBG.err('unhandledrejection', e?.reason));

export function healthCheck(){
    const z = getComputedStyle(document.querySelector('.modal') || document.body).zIndex;
    DBG.log('— HEALTH CHECK —');
    DBG.expect('#infoModal', 'modal container');
    DBG.expect('#infoModalBody', 'modal body');
    DBG.expect('#calGrid', 'agenda calGrid');
    DBG.expect('#calListRows', 'agenda list rows');
    DBG.log('modal z-index =', z);
}
