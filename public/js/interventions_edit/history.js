// history.js
export function initHistory() {
    const openHistoryButton = document.getElementById('openHistory');
    if (!openHistoryButton) return;

    openHistoryButton.addEventListener('click', () => {
        const templateElement = document.getElementById('tplHistory');
        if (!templateElement) {
            console.error('[HIST] template #tplHistory introuvable');
            return;
        }

        const numInt = openHistoryButton.getAttribute('data-num-int') || 'hist';
        const windowName = 'historique_' + numInt; // fenêtre réutilisable
        const popupWindow = window.open('', windowName, 'width=960,height=720');
        if (!popupWindow) {
            console.error('[HIST] window.open a été bloqué');
            return;
        }

        try { popupWindow.focus(); } catch { /* silencieux */ }

        // Récupère le HTML du template
        let innerHtml = '';
        if (templateElement.content && templateElement.content.cloneNode) {
            const fragment = templateElement.content.cloneNode(true);
            innerHtml = (fragment.firstElementChild?.outerHTML || fragment.textContent || '').trim();
        } else {
            innerHtml = (templateElement.innerHTML || '').trim();
        }

        if (!innerHtml) {
            innerHtml = '<p class="note">Aucun contenu trouvé pour l’historique.</p>';
        }

        // Normalise la classe de table si besoin
        innerHtml = innerHtml.replace(/class="hist-table"/g, 'class="table"');

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
  <div class="box m-12"><div class="body"><div class="table">${innerHtml}</div></div></div>

  <script>
  (function(){
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.hist-toggle'); if(!btn) return;
      var trMain = btn.closest('tr.row-main');    if(!trMain) return;
      var trDetails = trMain.nextElementSibling;
      if(!trDetails || !trDetails.matches('.row-details')) return;

      var isOpen = trDetails.classList.toggle('is-open'); // ← clé : classe, pas style.display
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      btn.textContent = isOpen ? '–' : '+';
    }, {passive:true});
  })();
  <\/script>
</body></html>`;

        try {
            popupWindow.document.open();
            popupWindow.document.write(html);
            popupWindow.document.close();
        } catch (error) {
            console.error('[HIST] Erreur écriture document:', error);
        }
    });
}
