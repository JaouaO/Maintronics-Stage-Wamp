(function(){
    const table = document.getElementById('intervTable');
    const tbody = document.getElementById('rowsBody');

    // ---- Tri client-side ----
    let currentSort = { idx: null, dir: 'asc', type: 'text' };

    function parseDateFR(s) {
        if (!s || s === '—') return null;
        const [d,m,y] = s.split('/');
        return new Date(+y, +m - 1, +d).getTime();
    }
    function parseTime(s) {
        if (!s || s === '—') return null;
        const [H,M] = s.split(':');
        return (+H)*60 + (+M);
    }
    function getCellValue(tr, idx, type) {
        const txt = (tr.children[idx].textContent || '').trim();
        switch(type){
            case 'date': return parseDateFR(txt) ?? Infinity;
            case 'time': return parseTime(txt) ?? Infinity;
            default:     return txt.toLowerCase();
        }
    }
    function sortBy(idx, type) {
        const ths = table.tHead.rows[0].cells;
        for (let th of ths) th.classList.remove('sort-asc','sort-desc');

        if (currentSort.idx === idx) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = { idx, dir:'asc', type };
        }
        ths[idx].classList.add(currentSort.dir === 'asc' ? 'sort-asc' : 'sort-desc');

        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a,b) => {
            const va = getCellValue(a, idx, type);
            const vb = getCellValue(b, idx, type);
            if (va < vb) return currentSort.dir === 'asc' ? -1 : 1;
            if (va > vb) return currentSort.dir === 'asc' ? 1 : -1;
            return 0;
        });
        rows.forEach(r => tbody.appendChild(r));
    }

    Array.from(table.tHead.rows[0].cells).forEach((th, idx) => {
        const type = th.dataset.sort;
        if (!type) return;
        th.addEventListener('click', () => sortBy(idx, type));
    });

    // ---- Ligne cliquable (optionnel) ----
    tbody.addEventListener('click', (e)=>{
        const tr = e.target.closest('tr[data-href]');
        if (tr) window.location.href = tr.dataset.href;
    });
})();
