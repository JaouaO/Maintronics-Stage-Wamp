(() => {
    const table = document.getElementById('intervTable');
    const tbody = document.getElementById('rowsBody');
    const form  = document.getElementById('filterForm');
    if (!table || !tbody || !form) return;

    // ---------- Utils ----------
    const ths = Array.from(table.tHead?.rows?.[0]?.cells || []);
    const scopeInput = document.getElementById('scope');
    const chipUrgent = document.querySelector('.b-chip-urgent');
    const chipMe     = document.querySelector('.b-chip-me');
    const perPageSel = document.getElementById('perpage');
    const searchInput= document.getElementById('q');
    const clearBtn   = document.querySelector('.b-clear');

    let currentSort = { idx: null, dir: 'asc', type: 'text' };

    function parseDateFR(s) {
        if (!s || s === '—') return null;
        const [d, m, y] = s.split('/');
        const t = new Date(+y, (+m || 1) - 1, +d || 1).getTime();
        return Number.isFinite(t) ? t : null;
    }
    function parseTime(s) {
        if (!s || s === '—') return null;
        const [H, M] = s.split(':');
        const t = (+H)*60 + (+M || 0);
        return Number.isFinite(t) ? t : null;
    }
    function getCellValue(tr, idx, type) {
        const cell = tr.children[idx];
        const txt  = (cell?.textContent || '').trim();
        switch (type) {
            case 'date': return parseDateFR(txt);
            case 'time': return parseTime(txt);
            case 'num':  return Number.isFinite(+txt.replace(/\s/g,'')) ? +txt.replace(/\s/g,'') : null;
            default:     return txt.toLowerCase();
        }
    }

    // Associer <tr.master> (ligne principale) et <tr.row-detail> (détail)
    function getPairs() {
        const masters = Array.from(tbody.querySelectorAll('tr.row[data-row-id]'));
        return masters.map((m, i) => {
            const id = m.dataset.rowId;
            const det = m.nextElementSibling;
            const isDetail = det && det.matches(`tr.row-detail[data-detail-for="${id}"]`);
            return { master: m, detail: isDetail ? det : null, idx: i }; // idx = tri stable
        });
    }

    function applyAriaSort(th, dir) {
        ths.forEach(t => t.removeAttribute('aria-sort'));
        if (th) th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
    }

    function saveSort() {
        const k = 'intv.sort';
        sessionStorage.setItem(k, JSON.stringify(currentSort));
    }
    function restoreSort() {
        try {
            const raw = sessionStorage.getItem('intv.sort');
            if (!raw) return;
            const s = JSON.parse(raw);
            if (s && Number.isInteger(s.idx) && s.dir && s.type) {
                sortBy(s.idx, s.type, s.dir, /*skipSave*/true);
            }
        } catch {}
    }

    // ---------- Tri ----------
    function sortBy(idx, type, forceDir = null, skipSave = false) {
        ths.forEach(th => th.classList.remove('sort-asc','sort-desc'));

        if (currentSort.idx === idx && !forceDir) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = { idx, dir: forceDir || 'asc', type };
        }
        const th = ths[idx];
        th?.classList.add(currentSort.dir === 'asc' ? 'sort-asc' : 'sort-desc');
        applyAriaSort(th, currentSort.dir);

        const pairs = getPairs();
        const dirMul = currentSort.dir === 'asc' ? 1 : -1;

        pairs.sort((A, B) => {
            const a = getCellValue(A.master, idx, type);
            const b = getCellValue(B.master, idx, type);

            // nulls (vides/—) vont en bas en asc
            const an = (a === null || a === undefined);
            const bn = (b === null || b === undefined);
            if (an && !bn) return  1 * dirMul;
            if (!an && bn) return -1 * dirMul;

            if (a < b) return -1 * dirMul;
            if (a > b) return  1 * dirMul;
            // tri stable : conserver ordre original
            return A.idx - B.idx;
        });

        // Réinjection DOM par paire
        const frag = document.createDocumentFragment();
        pairs.forEach(p => {
            frag.appendChild(p.master);
            if (p.detail) frag.appendChild(p.detail);
        });
        tbody.appendChild(frag);

        if (!skipSave) saveSort();
    }

    // Clic sur en-têtes triables
    ths.forEach((th, idx) => {
        const type = th.dataset.sort;
        if (!type) return;
        th.tabIndex = 0; // focusable
        th.addEventListener('click', () => sortBy(idx, type));
        th.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                sortBy(idx, type);
            }
        });
    });

    // ---------- Lignes cliquables / Toggle détail / Historique ----------
    tbody.addEventListener('click', (e) => {
        const btnToggle = e.target.closest('.js-row-toggle');
        if (btnToggle) {
            const rowId = btnToggle.dataset.rowId;
            const det = document.getElementById('det-' + rowId);
            if (!det) return;
            const isOpen = !det.hasAttribute('hidden');
            if (isOpen) {
                det.setAttribute('hidden', '');
                btnToggle.setAttribute('aria-expanded', 'false');
            } else {
                det.removeAttribute('hidden');
                btnToggle.setAttribute('aria-expanded', 'true');
            }
            return; // ne pas naviguer
        }

        const histBtn = e.target.closest('.js-open-history');
        if (histBtn) {
            const url = histBtn.dataset.historyUrl;
            if (!url) return;
            const w = window.open(url, 'history_popup', 'noopener,noreferrer,width=980,height=720');
            if (!w) window.location.href = url; // fallback si popup bloquée
            return;
        }

        const onAction = e.target.closest('button, a, input, .actions');
        if (onAction) return; // ne pas déclencher la navigation si clic sur action

        const tr = e.target.closest('tr[data-href]');
        if (tr) window.location.href = tr.dataset.href;
    });

    // ---------- Chips (scope) ----------
    function submitWithScope() {
        const urgent = chipUrgent?.classList.contains('is-active') ?? false;
        const me     = chipMe?.classList.contains('is-active') ?? false;
        const val = urgent && me ? 'both' : urgent ? 'urgent' : me ? 'me' : '';
        if (scopeInput) scopeInput.value = val;
        form.submit();
    }
    chipUrgent?.addEventListener('click', () => {
        chipUrgent.classList.toggle('is-active');
        submitWithScope();
    });
    chipMe?.addEventListener('click', () => {
        chipMe.classList.toggle('is-active');
        submitWithScope();
    });

    // ---------- Per-page : submit on change (vous aviez "pas d’autosubmit" pour per-page ; si vous voulez garder manuel, commentez) ----------
    perPageSel?.addEventListener('change', () => form.submit());

    // ---------- Bouton Effacer ----------
    clearBtn?.addEventListener('click', () => {
        if (!searchInput) return;
        searchInput.value = '';
        form.submit();
    });

    // ---------- Restaurer tri précédent ----------
    restoreSort();
})();
