import { escapeHtml } from './utils.js';

export function initModal() {
    const m = document.getElementById('infoModal');
    const body = document.getElementById('infoModalBody');
    const xBtn = document.getElementById('infoModalClose');

    function open(html){ if(!m||!body) return; body.innerHTML = html; m.classList.add('is-open'); m.setAttribute('aria-hidden','false'); }
    function close(){ if(!m||!body) return; m.classList.remove('is-open'); m.setAttribute('aria-hidden','true'); body.innerHTML=''; }

    function renderSuivi(btn){
        const tr = btn.closest('tr'); const tds = tr ? tr.children : [];
        const date = (tds?.[0]?.textContent || '—').trim();
        const html = (tds?.[1]?.innerHTML || '').trim();
        return `<h3 class="modal-title">Suivi du ${escapeHtml(date)}</h3><div>${html}</div>`;
    }

    function renderRDV(btn){
        const d = btn.dataset || {};
        const status = d.temp === '1' ? '<span class="badge badge-temp">Temporaire</span>' : '<span class="badge badge-valid">Validé</span>';
        const adr = (d.cp || d.ville) ? `${escapeHtml(d.cp || '')} ${escapeHtml(d.ville || '')}`.trim() : '—';
        const marque = d.marque ? escapeHtml(d.marque) : '—';
        const commentaire = (d.commentaire || '').trim();
        return `
      <h3 class="modal-title">Détail du rendez-vous</h3>
      <div class="hstack-8" role="group" aria-label="Statut du rendez-vous">${status}</div>
      <div class="meta"><strong>Heure&nbsp;:</strong> ${escapeHtml(d.heure || '—')}</div>
      <div class="meta"><strong>Technicien&nbsp;:</strong> ${escapeHtml(d.tech || '—')}</div>
      <div class="meta"><strong>Contact&nbsp;:</strong> ${escapeHtml(d.contact || '—')}</div>
      <div class="meta"><strong>Marque&nbsp;:</strong> ${marque}</div>
      <div class="meta"><strong>Ville / CP&nbsp;:</strong> ${adr || '—'}</div>
      <div class="section"><div class="section-title">Commentaire (complet)</div><div class="prewrap">${escapeHtml(commentaire)}</div></div>
    `;
    }

    document.addEventListener('click', (e) => {
        if (e.target === m) return close();
        const btn = e.target.closest('.info-btn'); if (!btn) return;
        const type = btn.dataset.type;
        if (type === 'suivi') return open(renderSuivi(btn));
        if (type === 'rdv')   return open(renderRDV(btn));
        open('<p>Pas de contenu disponible pour ce bouton.</p>');
    });

    xBtn && xBtn.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    // export pour d'autres modules si besoin
    window.MODAL = {open, close};
}
