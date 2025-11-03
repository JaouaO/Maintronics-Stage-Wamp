// history.js
export function initHistory() {
    const btn = document.getElementById('openHistory');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const tpl = document.getElementById('tplHistory');
        if (!tpl) {
            console.error('[HIST] template #tplHistory introuvable');
            return;
        }

        const numInt  = btn.getAttribute('data-num-int') || 'hist';
        const winName = 'historique_' + numInt; // fenêtre réutilisable
        const w = window.open('', winName, 'width=960,height=720');
        if (!w) {
            console.error('[HIST] window.open a été bloqué');
            return;
        }

        // focus si déjà ouverte
        try { w.focus(); } catch (e) {}

        // récupère le HTML du template
        let inner = '';
        if (tpl.content && tpl.content.cloneNode) {
            const frag = tpl.content.cloneNode(true);
            inner = (frag.firstElementChild?.outerHTML || frag.textContent || '').trim();
        } else {
            inner = (tpl.innerHTML || '').trim();
        }
        if (!inner) inner = '<p class="note">Aucun contenu trouvé pour l’historique.</p>';

        // normalise la classe de table si besoin
        inner = inner.replace(/class="hist-table"/g, 'class="table"');

        // CSS de la page (même feuille si possible)
        const cssHref =
            document.querySelector('link[rel="stylesheet"][href*="intervention_edit.css"]')?.href || '';

        // HTML complet écrit dans la popup
        const html = `<!doctype html>
<html lang="fr"><head>
  <meta charset="utf-8"><title>Historique</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  ${cssHref ? `<link rel="stylesheet" href="${cssHref}">` : ''}
  <style>
    /* fallback au cas où la CSS principale ne charge pas */
    .row-details{display:none}
    .row-details.is-open{display:table-row}
  </style>
</head>
<body>
  <div class="box m-12"><div class="body"><div class="table">${inner}</div></div></div>

  <script>
  (function(){
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.hist-toggle'); if(!btn) return;
      var trMain = btn.closest('tr.row-main');    if(!trMain) return;
      var trDetails = trMain.nextElementSibling;
      if(!trDetails || !trDetails.matches('.row-details')) return;

      var open = trDetails.classList.toggle('is-open'); // ← clé : classe, pas style.display
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? '–' : '+';
    }, {passive:true});
  })();
  <\/script>
</body></html>`;

        // écriture → remplace le DOM, donc on n’attache rien AVANT
        try {
            w.document.open();
            w.document.write(html);
            w.document.close();
        } catch (err) {
            console.error('[HIST] Erreur écriture document:', err);
        }
    });
}
