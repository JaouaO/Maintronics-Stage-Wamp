/* Interventions – UI logic (chips, recherche, accordéon, historique, navigation) */
(function () {
    const qs = (selector, context) =>
        (context || document).querySelector(selector);
    const qsa = (selector, context) =>
        Array.from((context || document).querySelectorAll(selector));

    const scopeInput = qs('#scope');
    const searchInput = qs('#q');

    // Effacer la recherche (sans submit automatique : l’utilisateur clique sur "Appliquer")
    const clearButton = qs('.b-clear');
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
        });
    }

    // ----- Chips filtres -> scope (sans autosubmit) -----
    const chipElements = qsa('[data-role].b-chip');

    function readScopeFlags() {
        const rawScope = (scopeInput?.value || '').trim();
        return {
            urgent: rawScope === 'urgent' || rawScope === 'both',
            me: rawScope === 'me' || rawScope === 'both'
        };
    }

    function paintChips(isUrgent, isMe) {
        chipElements.forEach(chipElement => {
            const role = chipElement.getAttribute('data-role');
            const isActive =
                role === 'urgent' ? isUrgent : role === 'me' ? isMe : false;
            chipElement.classList.toggle('is-active', !!isActive);
        });
    }

    function writeScopeFromFlags(isUrgent, isMe) {
        let scopeValue = '';
        if (isUrgent && isMe) scopeValue = 'both';
        else if (isUrgent) scopeValue = 'urgent';
        else if (isMe) scopeValue = 'me';
        else scopeValue = '';

        if (scopeInput) scopeInput.value = scopeValue;
        paintChips(isUrgent, isMe);
    }

    chipElements.forEach(chipElement => {
        chipElement.addEventListener('click', () => {
            const flags = readScopeFlags();
            const role = chipElement.getAttribute('data-role');

            if (role === 'urgent') flags.urgent = !flags.urgent;
            if (role === 'me') flags.me = !flags.me;

            writeScopeFromFlags(flags.urgent, flags.me);
            // Pas de submit ici : c’est le bouton "Appliquer" qui envoie.
        });
    });

    // Initialisation visuelle des chips à partir du scope serveur
    {
        const initialFlags = readScopeFlags();
        paintChips(initialFlags.urgent, initialFlags.me);
    }

    // ----- Accordéon (chevron) -----
    document.addEventListener('click', event => {
        const toggleButton = event.target.closest('.js-row-toggle');
        if (!toggleButton) return;

        const rowId = toggleButton.getAttribute('data-row-id');
        const detailRow = document.getElementById('det-' + rowId);
        if (!detailRow) return;

        const isOpen = !detailRow.hasAttribute('hidden');
        if (isOpen) {
            detailRow.setAttribute('hidden', '');
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.textContent = '▾';
        } else {
            detailRow.removeAttribute('hidden');
            toggleButton.setAttribute('aria-expanded', 'true');
            toggleButton.textContent = '▴';
        }
    });

    // ----- Navigation par clic sur ligne (sauf actions) -----
    const tableElement = qs('#intervTable');
    tableElement?.addEventListener('click', event => {
        // Clic sur une action → on ne déclenche pas la navigation automatique
        if (
            event.target.closest(
                '.col-actions, .js-row-toggle, .js-open, .js-open-history'
            )
        ) {
            return;
        }

        const row = event.target.closest('tr.row[data-href]');
        if (!row) return;

        const href = row.getAttribute('data-href');
        if (href) window.location.href = href;
    });

    // ----- Historique lazy dans popup (sans styles inline) -----
    document.addEventListener('click', async event => {
        const historyButton = event.target.closest('.js-open-history');
        if (!historyButton) return;

        const numInt = historyButton.getAttribute('data-num-int') || 'hist';
        const historyUrl = historyButton.getAttribute('data-history-url');
        if (!historyUrl) return;

        const popupWindow = window.open(
            '',
            'historique_' + numInt,
            'width=960,height=720'
        );
        if (!popupWindow) return;

        try {
            popupWindow.document.open();
            popupWindow.document.write(
                '<p class="history-loading">Chargement…</p>'
            );
            popupWindow.document.close();
        } catch {
            // silencieux
        }

        try {
            const response = await fetch(historyUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const htmlContent = await response.text();

            popupWindow.document.open();
            popupWindow.document.write(
                htmlContent || '<p class="history-empty">Aucun contenu</p>'
            );
            popupWindow.document.close();
        } catch (error) {
            popupWindow.document.open();
            popupWindow.document.write(
                '<p class="history-error">Erreur de chargement de l’historique.</p>'
            );
            popupWindow.document.close();
        }
    });
})();

// === Modale "infos utilisateur" (session) ===
(() => {
    const openButton = document.getElementById('openUserModal');
    const modalElement = document.getElementById('userInfoModal');
    if (!openButton || !modalElement) return;

    const modalPanel = modalElement.querySelector('.modal-panel');

    function closeUserInfoModal() {
        modalElement.setAttribute('hidden', '');
        document.body.style.overflow = '';
        openButton.focus();
    }

    function openUserInfoModal() {
        modalElement.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
        if (modalPanel) modalPanel.focus();
    }

    openButton.addEventListener('click', openUserInfoModal);

    modalElement.addEventListener('click', event => {
        if (
            event.target.matches('[data-close]') ||
            event.target.classList.contains('modal-backdrop')
        ) {
            closeUserInfoModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modalElement.hasAttribute('hidden')) {
            closeUserInfoModal();
        }
    });
})();

// === Tri "datetime" strict sur data-ts du <tr> ; dates nulles toujours en bas ===
(() => {
    const tableElement = document.getElementById('intervTable');
    if (!tableElement) return;

    const tableBody = tableElement.tBodies[0];

    function getRowPairsForDatetimeSort() {
        const masterRows = Array.from(
            tableBody.querySelectorAll('tr.row[data-row-id]')
        );

        return masterRows.map(masterRow => {
            const rowId = masterRow.getAttribute('data-row-id');
            const detailRow = rowId
                ? tableBody.querySelector(
                    `tr.row-detail[data-detail-for="${rowId}"]`
                )
                : null;
            const timestamp = Number(masterRow.dataset.ts); // NaN si vide
            const intervNumber =
                masterRow.querySelector('.col-id')?.textContent.trim() ?? '';

            return { masterRow, detailRow, timestamp, intervNumber };
        });
    }

    function sortDatetimeAscending(isAscending) {
        const rowPairs = getRowPairsForDatetimeSort();

        rowPairs.sort((pairA, pairB) => {
            const isFiniteA = Number.isFinite(pairA.timestamp);
            const isFiniteB = Number.isFinite(pairB.timestamp);

            // 1) Les lignes AVEC date passent avant les lignes SANS date
            if (isFiniteA !== isFiniteB) return isFiniteA ? -1 : 1;

            // 2) Les deux ont une date → comparer la valeur
            if (pairA.timestamp !== pairB.timestamp) {
                return isAscending
                    ? pairA.timestamp - pairB.timestamp
                    : pairB.timestamp - pairA.timestamp;
            }

            // 3) Égalité → fallback stable sur le N° d'intervention
            return isAscending
                ? pairA.intervNumber.localeCompare(
                    pairB.intervNumber,
                    'fr',
                    { numeric: true }
                )
                : pairB.intervNumber.localeCompare(
                    pairA.intervNumber,
                    'fr',
                    { numeric: true }
                );
        });

        // Réinsertion (ligne + détail juste après)
        rowPairs.forEach(({ masterRow, detailRow }) => {
            tableBody.appendChild(masterRow);
            if (detailRow) tableBody.appendChild(detailRow);
        });
    }

    // Écoute uniquement sur le TH "Date / Heure"
    tableElement.tHead?.addEventListener('click', event => {
        const headerCell = event.target.closest('th.col-dt[data-sort="datetime"]');
        if (!headerCell) return;

        const sortAscending = !(headerCell.dataset.order === 'asc');
        headerCell.dataset.order = sortAscending ? 'asc' : 'desc';

        // Visuel : n’afficher l’état de tri que sur cette colonne
        tableElement
            .querySelectorAll('th[data-sort]')
            .forEach(otherHeaderCell => {
                if (otherHeaderCell !== headerCell) {
                    otherHeaderCell.removeAttribute('data-order');
                }
            });

        sortDatetimeAscending(sortAscending);
    });
})();
