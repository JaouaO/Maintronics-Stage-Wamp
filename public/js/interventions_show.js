/* Interventions – UI logic (chips, recherche, accordéon, histo, nav) */
(function () {
    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    const filterForm = $('#filterForm');
    const scopeInput = $('#scope');
    const qInput     = $('#q');
    const perPage    = $('#perpage');

    /* Effacer la recherche (ne soumet pas – on attend “Appliquer”) */
    const clearBtn = $('.b-clear');
    if (clearBtn) clearBtn.addEventListener('click', () => { if (qInput) qInput.value = ''; });

    /* PAS d’autosubmit sur per-page : on soumettra avec “Appliquer” */
    // if (perPage) perPage.addEventListener('change', () => filterForm?.requestSubmit());

    /* Chips filtres -> scope (ne soumet pas) */
    const chips = $$('[data-role].b-chip');
    const readFlags = () => {
        const s = (scopeInput?.value || '').trim();
        return { urg: s === 'urgent' || s === 'both', me: s === 'me' || s === 'both' };
    };
    const paint = (urg, me) => {
        chips.forEach(el => {
            const role = el.getAttribute('data-role');
            const on = role === 'urgent' ? urg : role === 'me' ? me : false;
            el.classList.toggle('is-active', !!on);
        });
    };
    const writeScope = (urg, me) => {
        let s = '';
        if (urg && me) s = 'both';
        else if (urg)  s = 'urgent';
        else if (me)   s = 'me';
        else           s = '';
        if (scopeInput) scopeInput.value = s;
        paint(urg, me);
    };
    chips.forEach(el => {
        el.addEventListener('click', () => {
            const f = readFlags();
            const role = el.getAttribute('data-role');
            if (role === 'urgent') f.urg = !f.urg;
            if (role === 'me')     f.me  = !f.me;
            writeScope(f.urg, f.me);
            // pas de submit ici
        });
    });
    // init visuel selon scope serveur
    { const f0 = readFlags(); paint(f0.urg, f0.me); }

    /* Accordéon (chevron) */
    document.addEventListener('click', (e) => {
        const t = e.target.closest('.js-row-toggle');
        if (!t) return;
        const id  = t.getAttribute('data-row-id');
        const det = document.getElementById('det-' + id);
        if (!det) return;
        const open = !det.hasAttribute('hidden');
        if (open) {
            det.setAttribute('hidden', '');
            t.setAttribute('aria-expanded', 'false');
            t.textContent = '▾';
        } else {
            det.removeAttribute('hidden');
            t.setAttribute('aria-expanded', 'true');
            t.textContent = '▴';
        }
    });

    /* Navigation par clic ligne (sauf actions) */
    const table = $('#intervTable');
    table?.addEventListener('click', (e) => {
        if (e.target.closest('.col-actions, .js-row-toggle, .js-open, .js-open-history')) return;
        const tr = e.target.closest('tr.row[data-href]');
        if (!tr) return;
        const href = tr.getAttribute('data-href');
        if (href) window.location.href = href;
    });

    /* Historique lazy dans popup */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-open-history');
        if (!btn) return;
        const numInt = btn.getAttribute('data-num-int') || 'hist';
        const url    = btn.getAttribute('data-history-url');
        const win    = window.open('', 'historique_' + numInt, 'width=960,height=720');
        if (!win) return;

        try { win.document.open(); win.document.write('<p style="padding:12px;font:14px system-ui">Chargement…</p>'); win.document.close(); } catch(_) {}

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();
            win.document.open();
            win.document.write(html || '<p style="padding:12px;font:14px system-ui">Aucun contenu</p>');
            win.document.close();
        } catch (err) {
            win.document.open();
            win.document.write('<p style="padding:12px;color:#a00">Erreur de chargement de l’historique.</p>');
            win.document.close();
        }
    });
})();
