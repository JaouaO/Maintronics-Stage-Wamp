(() => {
    const tableElement = document.getElementById('intervTable');
    const tableBody = document.getElementById('rowsBody');
    const filterForm = document.getElementById('filterForm');

    if (!tableElement || !tableBody || !filterForm) return;

    // ---------- Raccourcis DOM ----------
    const headerCells = Array.from(tableElement.tHead?.rows?.[0]?.cells || []);
    const scopeInput = document.getElementById('scope');
    const chipUrgent = document.querySelector('.b-chip-urgent');
    const chipMe = document.querySelector('.b-chip-me');
    const perPageSelect = document.getElementById('perpage');
    const searchInput = document.getElementById('q');
    const clearButton = document.querySelector('.b-clear');

    // État courant du tri
    let currentSort = { columnIndex: null, direction: 'asc', type: 'text' };

    // ---------- Helpers parsing valeurs ----------
    function parseDateFR(dateString) {
        if (!dateString || dateString === '—') return null;
        const [day, month, year] = dateString.split('/');
        const timestamp = new Date(+year, (+month || 1) - 1, +day || 1).getTime();
        return Number.isFinite(timestamp) ? timestamp : null;
    }

    function parseTime(timeString) {
        if (!timeString || timeString === '—') return null;
        const [hourString, minuteString] = timeString.split(':');
        const minutesTotal = (+hourString) * 60 + (+minuteString || 0);
        return Number.isFinite(minutesTotal) ? minutesTotal : null;
    }

    function getCellValue(tableRow, columnIndex, valueType) {
        const cell = tableRow.children[columnIndex];
        const rawText = (cell?.textContent || '').trim();

        switch (valueType) {
            case 'date':
                return parseDateFR(rawText);
            case 'time':
                return parseTime(rawText);
            case 'num': {
                const numericText = rawText.replace(/\s/g, '');
                return Number.isFinite(+numericText) ? +numericText : null;
            }
            default:
                return rawText.toLowerCase();
        }
    }

    // ---------- Paire (ligne principale + ligne détail) ----------
    function getRowPairs() {
        const masterRows = Array.from(tableBody.querySelectorAll('tr.row[data-row-id]'));

        return masterRows.map((masterRow, originalIndex) => {
            const rowId = masterRow.dataset.rowId;
            const detailRowCandidate = masterRow.nextElementSibling;
            const isDetailMatch = detailRowCandidate &&
                detailRowCandidate.matches(`tr.row-detail[data-detail-for="${rowId}"]`);

            return {
                master: masterRow,
                detail: isDetailMatch ? detailRowCandidate : null,
                originalIndex // pour tri stable
            };
        });
    }

    // ---------- ARIA & persistance du tri ----------
    function applyAriaSort(thElement, direction) {
        headerCells.forEach(headerCell => headerCell.removeAttribute('aria-sort'));
        if (thElement) {
            thElement.setAttribute(
                'aria-sort',
                direction === 'asc' ? 'ascending' : 'descending'
            );
        }
    }

    function saveSortState() {
        const key = 'intv.sort';
        sessionStorage.setItem(key, JSON.stringify(currentSort));
    }

    function restoreSortState() {
        try {
            const raw = sessionStorage.getItem('intv.sort');
            if (!raw) return;

            const storedSort = JSON.parse(raw);
            if (
                storedSort &&
                Number.isInteger(storedSort.columnIndex) &&
                storedSort.direction &&
                storedSort.type
            ) {
                sortBy(storedSort.columnIndex, storedSort.type, storedSort.direction, true);
            }
        } catch {
            // silencieux
        }
    }

    // ---------- Tri ----------
    function sortBy(columnIndex, valueType, forcedDirection = null, skipSave = false) {
        headerCells.forEach(header =>
            header.classList.remove('sort-asc', 'sort-desc')
        );

        if (currentSort.columnIndex === columnIndex && !forcedDirection) {
            // Inversion asc/desc
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = {
                columnIndex,
                direction: forcedDirection || 'asc',
                type: valueType
            };
        }

        const sortedHeader = headerCells[columnIndex];
        if (sortedHeader) {
            sortedHeader.classList.add(
                currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc'
            );
        }
        applyAriaSort(sortedHeader, currentSort.direction);

        const rowPairs = getRowPairs();
        const directionMultiplier = currentSort.direction === 'asc' ? 1 : -1;

        rowPairs.sort((pairA, pairB) => {
            const valueA = getCellValue(pairA.master, columnIndex, valueType);
            const valueB = getCellValue(pairB.master, columnIndex, valueType);

            const isNullA = valueA === null || valueA === undefined;
            const isNullB = valueB === null || valueB === undefined;

            // Valeurs nulles en bas (en asc)
            if (isNullA && !isNullB) return 1 * directionMultiplier;
            if (!isNullA && isNullB) return -1 * directionMultiplier;

            if (valueA < valueB) return -1 * directionMultiplier;
            if (valueA > valueB) return 1 * directionMultiplier;

            // Tri stable : conserver l'ordre d'origine
            return pairA.originalIndex - pairB.originalIndex;
        });

        // Réinjection DOM par paire (master + détail)
        const fragment = document.createDocumentFragment();
        rowPairs.forEach(pair => {
            fragment.appendChild(pair.master);
            if (pair.detail) fragment.appendChild(pair.detail);
        });
        tableBody.appendChild(fragment);

        if (!skipSave) saveSortState();
    }

    // ---------- Clic sur les en-têtes triables ----------
    headerCells.forEach((headerCell, columnIndex) => {
        const sortType = headerCell.dataset.sort;
        if (!sortType) return;

        headerCell.tabIndex = 0; // focus clavier

        headerCell.addEventListener('click', () => sortBy(columnIndex, sortType));
        headerCell.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                sortBy(columnIndex, sortType);
            }
        });
    });

    // ---------- Lignes cliquables / détail / historique ----------
    tableBody.addEventListener('click', event => {
        // Toggle détail
        const toggleButton = event.target.closest('.js-row-toggle');
        if (toggleButton) {
            const rowId = toggleButton.dataset.rowId;
            const detailRow = document.getElementById('det-' + rowId);
            if (!detailRow) return;

            const isOpen = !detailRow.hasAttribute('hidden');
            if (isOpen) {
                detailRow.setAttribute('hidden', '');
                toggleButton.setAttribute('aria-expanded', 'false');
            } else {
                detailRow.removeAttribute('hidden');
                toggleButton.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        // Bouton historique (popup)
        const historyButton = event.target.closest('.js-open-history');
        if (historyButton) {
            const historyUrl = historyButton.dataset.historyUrl;
            if (!historyUrl) return;

            const popupWindow = window.open(
                historyUrl,
                'history_popup',
                'noopener,noreferrer,width=980,height=720'
            );
            if (!popupWindow) {
                // Popup bloquée → fallback navigation
                window.location.href = historyUrl;
            }
            return;
        }

        // Clic sur une action : ne pas naviguer
        const actionTarget = event.target.closest('button, a, input, .actions');
        if (actionTarget) return;

        // Navigation par clic sur la ligne
        const row = event.target.closest('tr[data-href]');
        if (row && row.dataset.href) {
            window.location.href = row.dataset.href;
        }
    });

    // ---------- Scope via chips (avec submit automatique) ----------
    function submitWithScope() {
        const isUrgentActive = chipUrgent?.classList.contains('is-active') ?? false;
        const isMeActive = chipMe?.classList.contains('is-active') ?? false;

        const value =
            isUrgentActive && isMeActive
                ? 'both'
                : isUrgentActive
                    ? 'urgent'
                    : isMeActive
                        ? 'me'
                        : '';

        if (scopeInput) scopeInput.value = value;
        filterForm.submit();
    }

    chipUrgent?.addEventListener('click', () => {
        chipUrgent.classList.toggle('is-active');
        submitWithScope();
    });

    chipMe?.addEventListener('click', () => {
        chipMe.classList.toggle('is-active');
        submitWithScope();
    });

    // ---------- Per-page : submit on change ----------
    perPageSelect?.addEventListener('change', () => filterForm.submit());

    // ---------- Bouton Effacer (recherche) ----------
    clearButton?.addEventListener('click', () => {
        if (!searchInput) return;
        searchInput.value = '';
        filterForm.submit();
    });

    // ---------- Restaurer le tri précédent ----------
    restoreSortState();
})();
