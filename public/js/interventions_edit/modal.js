import { escapeHtml } from './utils.js';

export function initModal() {
    const m = document.getElementById('infoModal');
    const body = document.getElementById('infoModalBody');
    const xBtn = document.getElementById('infoModalClose');

    function open(html){ if(!m||!body) return; body.innerHTML = html; m.classList.add('is-open'); m.setAttribute('aria-hidden','false'); }
    function close(){ if(!m||!body) return; m.classList.remove('is-open'); m.setAttribute('aria-hidden','true'); body.innerHTML=''; }

    function renderSuivi(btn){ /* ... inchangé ... */ }
    function renderRDV(btn){ /* ... inchangé ... */ }

    function renderClient(btn){
        const d = btn?.dataset || {};
        const F = (k) => (d[k] && String(d[k]).trim()) || (window.APP?.CLIENT?.[k] ?? '');

        const adrFull = [F('adr'), F('cp'), F('ville')].filter(Boolean).join(' ').trim();
        const mapsUrl = adrFull ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(adrFull)}` : null;

        const tel   = F('tel');
        const email = F('email');

        const telHtml   = tel   ? `<a href="tel:${encodeURIComponent(tel)}">${escapeHtml(tel)}</a>`     : '—';
        const emailHtml = email ? `<a href="mailto:${encodeURIComponent(email)}">${escapeHtml(email)}</a>` : '—';

        return `
    <h3 class="modal-title">Fiche client</h3>
    <dl class="kv">
      <dt>Nom</dt><dd>${escapeHtml(F('nom') || '—')}</dd>
      <dt>Téléphone</dt><dd>${telHtml}</dd>
      <dt>Email</dt><dd>${emailHtml}</dd>
      <dt>Adresse</dt><dd>${escapeHtml(adrFull || '—')}</dd>
      <dt>Type appareil</dt><dd>${escapeHtml(F('typeapp') || '—')}</dd>
      <dt>Marque</dt><dd>${escapeHtml(F('marque') || '—')}</dd>
    </dl>
    ${mapsUrl ? `<div class="mt8"><a href="${mapsUrl}" target="_blank" rel="noopener">Voir sur la carte</a></div>` : ''}
  `;
    }

    document.addEventListener('click', (e) => {
        if (e.target === m) return close();
        const btn = e.target.closest('.info-btn'); if (!btn) return;
        const type = btn.dataset.type;

        if (type === 'suivi')  return open(renderSuivi(btn));
        if (type === 'rdv')    return open(renderRDV(btn));
        if (type === 'client') return open(renderClient(btn));

        open('<p>Pas de contenu disponible pour ce bouton.</p>');
    });


    xBtn && xBtn.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    window.MODAL = { open, close }; // export optionnel
}
