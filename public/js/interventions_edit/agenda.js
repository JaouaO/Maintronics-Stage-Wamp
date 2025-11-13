// public/js/interventions/agenda.js
// Affichage agenda (mois + liste jour) avec badges URGENT
import { pad, escapeHtml, isBeforeToday } from './utils.js';

// --- API exportée (déférée) pour les autres modules (ex: rdv.js)
let _hasAnyFn = () => false;
let _hasValidatedFn = () => false;

export function alreadyHasAnyForThisDossier() { return _hasAnyFn(); }
export function alreadyHasValidatedForThisDossier() { return _hasValidatedFn(); }

export function initAgenda() {
    const techSelect = document.getElementById('selModeTech');

    const calWrap = document.getElementById('calWrap');
    const calGrid = document.getElementById('calGrid');
    const calTitle = document.getElementById('calTitle');
    const calPrev = document.getElementById('calPrev');
    const calNext = document.getElementById('calNext');
    const calToggle = document.getElementById('calToggle');

    const listWrap = document.getElementById('calList');
    const listTitle = document.getElementById('calListTitle');
    const dayPrev = document.getElementById('dayPrev');
    const dayNext = document.getElementById('dayNext');
    const listRows = document.getElementById('calListRows');

    const modal = document.getElementById('infoModal');
    const modalBody = document.getElementById('infoModalBody');
    const modalCloseBtn = document.getElementById('infoModalClose');

    if (!techSelect || !calWrap || !calGrid || !listRows) return;

    const currentNumInt =
        (window.APP && window.APP.numInt) ||
        document.getElementById('openHistory')?.dataset.numInt ||
        '';

    const state = {
        currentMonth: startOfMonth(new Date()),
        mode: 'month',                   // 'month' | 'day'
        selectedDay: null,               // Date
        selectedTech: '_ALL',
        cache: new Map(),                // key `${tech}|${from_to}` -> {events, byDay}
        requestToken: 0,                 // anti course réseau
    };

    // ---- closures exposées à rdv.js (hasAny / hasValidated) ----
    const computeHasAny = () => {
        for (const [, payload] of state.cache) {
            if (payload?.events?.some(ev => ev.num_int === currentNumInt)) {
                return true;
            }
        }
        return false;
    };

    const computeHasValidated = () => {
        for (const [, payload] of state.cache) {
            if (
                payload?.events?.some(ev =>
                    (ev.is_validated === 1 || ev.is_validated === true) &&
                    ev.num_int === currentNumInt
                )
            ) {
                return true;
            }
        }
        return false;
    };

    _hasAnyFn = computeHasAny;
    _hasValidatedFn = computeHasValidated;
    // Fallback global si l'import ESM ne passe pas partout
    window.__agendaHasAnyForThisDossier = _hasAnyFn;
    window.__agendaHasValidatedForThisDossier = _hasValidatedFn;

    // init
    state.selectedTech = techSelect.value || '_ALL';
    paintMonth();

    // --- Écouteurs principaux ---
    techSelect.addEventListener('change', () => {
        state.selectedTech = techSelect.value || '_ALL';
        state.cache.clear();
        paintMonth();
    });

    calPrev.addEventListener('click', () => {
        state.currentMonth = addMonths(state.currentMonth, -1);
        paintMonth();
    });

    calNext.addEventListener('click', () => {
        state.currentMonth = addMonths(state.currentMonth, +1);
        paintMonth();
    });

    calToggle.addEventListener('click', () => {
        const expanded = calToggle.getAttribute('aria-expanded') !== 'true';
        calToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        if (expanded) {
            calWrap.classList.remove('collapsed');
            state.mode = 'month';
        } else {
            calWrap.classList.add('collapsed');
            state.mode = 'day';
        }
    });

    dayPrev.addEventListener('click', () => {
        if (!state.selectedDay) return;
        state.selectedDay = addDays(state.selectedDay, -1);
        openDay(state.selectedDay);
    });

    dayNext.addEventListener('click', () => {
        if (!state.selectedDay) return;
        state.selectedDay = addDays(state.selectedDay, +1);
        openDay(state.selectedDay);
    });

    modalCloseBtn?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    // === helpers dates ===
    function ymd(date) {
        const year = date.getFullYear();
        const month = pad(date.getMonth() + 1);
        const day = pad(date.getDate());
        return `${year}-${month}-${day}`;
    }

    function startOfMonth(date) {
        const copy = new Date(date);
        copy.setDate(1);
        copy.setHours(0, 0, 0, 0);
        return copy;
    }

    function addMonths(date, offset) {
        const copy = new Date(date);
        copy.setMonth(copy.getMonth() + offset);
        return copy;
    }

    function addDays(date, offset) {
        const copy = new Date(date);
        copy.setDate(copy.getDate() + offset);
        return copy;
    }

    function fmtMonthYear(date) {
        return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    }

    function fmtDayTitle(date) {
        return date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    }

    function timeHHMM(iso) {
        if (!iso) return '';
        const timePart = (iso.split('T')[1] || '00:00:00');
        return timePart.slice(0, 5);
    }

    function agRefOf(numInt) {
        if (!numInt) return '';
        return String(numInt).slice(0, 4).toUpperCase();
    }

    // --- Chargement des évènements (mois) ---
    async function fetchMonthData(date) {
        const firstOfMonth = startOfMonth(date);

        // Début de la grille (lundi de la première semaine affichée)
        const gridStart = (() => {
            const start = new Date(firstOfMonth);
            const dow = (start.getDay() + 6) % 7; // lundi=0
            start.setDate(start.getDate() - dow);
            start.setHours(0, 0, 0, 0);
            return start;
        })();

        const gridEnd = addDays(gridStart, 41);
        gridEnd.setHours(23, 59, 59, 999);

        const cacheKey = `${state.selectedTech}|${ymd(gridStart)}_${ymd(gridEnd)}`;
        if (state.cache.has(cacheKey)) return state.cache.get(cacheKey);

        calGrid.classList.add('is-loading');
        const currentToken = ++state.requestToken;

        const baseUrl = (window.APP?.apiPlanningRoute || '').replace(
            '__X__',
            encodeURIComponent(state.selectedTech)
        );
        const queryString =
            `from=${ymd(gridStart)}&to=${ymd(gridEnd)}&numInt=${encodeURIComponent(currentNumInt)}&debug=1`;

        let payload = { events: [], byDay: new Map() };

        try {
            const response = await fetch(`${baseUrl}?${queryString}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const json = await response.json().catch(() => ({ ok: false, events: [] }));

            const eventsRaw = (json && json.ok && Array.isArray(json.events)) ? json.events : [];

            // Normalisation des codes techniciens
            const eventsRawFixed = eventsRaw.map(ev => ({
                ...ev,
                code_tech: String(ev.code_tech || '').trim().toUpperCase()
            }));

            let events = eventsRawFixed;

            // Filtrage côté client si mode "_ALL"
            if (state.selectedTech === '_ALL') {
                const allowedSet = new Set(
                    ((window.APP && window.APP.agendaAllowedCodes) || [])
                        .map(code => String(code).trim().toUpperCase())
                );

                if (allowedSet.size) {
                    events = eventsRawFixed.filter(ev => {
                        if (allowedSet.has(ev.code_tech)) return true;
                        for (const allowed of allowedSet) {
                            if (allowed.endsWith('*') &&
                                ev.code_tech.startsWith(allowed.slice(0, -1))) {
                                return true;
                            }
                        }
                        return false;
                    });
                }
            }

            const byDay = new Map();

            for (const ev of events) {
                const dayKey = (ev.start_datetime || '').slice(0, 10);
                if (!dayKey) continue;

                const bucket = byDay.get(dayKey) || {
                    list: [],
                    urgentCount: 0,
                    count: 0,
                    hasMyTemp: false,
                    hasMyValidated: false
                };

                bucket.list.push(ev);
                bucket.count++;

                if (ev.is_urgent) bucket.urgentCount++;

                const isTemp = (ev.is_validated === 0 || ev.is_validated === false);
                const isValid = (ev.is_validated === 1 || ev.is_validated === true);

                if (ev.num_int === currentNumInt) {
                    if (isTemp) bucket.hasMyTemp = true;
                    if (isValid) bucket.hasMyValidated = true;
                }

                byDay.set(dayKey, bucket);
            }

            payload = { events, byDay };
            state.cache.set(cacheKey, payload);
        } finally {
            if (currentToken === state.requestToken) {
                calGrid.classList.remove('is-loading');
            }
        }

        return payload;
    }

    // --- Rendu de la grille mensuelle ---
    async function paintMonth() {
        calTitle.textContent = fmtMonthYear(state.currentMonth);
        listWrap.classList.add('is-hidden');

        const monthData = await fetchMonthData(state.currentMonth);
        const firstOfMonth = startOfMonth(state.currentMonth);

        let maxCount = 0;
        for (const [, bucket] of monthData.byDay) {
            maxCount = Math.max(maxCount, bucket.count || 0);
        }

        const heatOf = (count) =>
            !maxCount
                ? 0
                : Math.max(0, Math.min(10, Math.round((count / maxCount) * 10)));

        const startGrid = new Date(firstOfMonth);
        const dayOfWeek = (startGrid.getDay() + 6) % 7; // lundi=0
        startGrid.setDate(startGrid.getDate() - dayOfWeek);

        const cells = [];
        for (let i = 0; i < 42; i++) {
            const currentDate = addDays(startGrid, i);
            const isInMonth = (currentDate.getMonth() === state.currentMonth.getMonth());
            const dateKey = ymd(currentDate);
            const info = monthData.byDay.get(dateKey) || {
                list: [],
                urgentCount: 0,
                count: 0,
                hasMyTemp: false,
                hasMyValidated: false
            };

            const cell = document.createElement('div');
            cell.className = 'cal-cell';

            const heat = heatOf(info.count);
            cell.classList.add(`heat-${heat}`);

            if (info.count > 0) cell.classList.add('has-events');
            if (info.urgentCount > 0) cell.classList.add('has-urgent');

            if (!isInMonth && !isBeforeToday(currentDate)) {
                cell.classList.add('muted2');
            }
            if (isBeforeToday(currentDate)) {
                cell.classList.add('muted');
            }

            // Signaux (points colorés) : validé, urgent, temporaire
            const signals = [];
            if (info.hasMyValidated) signals.push('green');
            if (info.urgentCount > 0) signals.push('red');
            if (info.hasMyTemp) signals.push('blue');

            let dotsHtml = '';
            if (signals.length === 0) {
                dotsHtml = `<span class="dot-base" aria-hidden="true"></span>`;
            } else {
                const firstDot = `<span class="dot ${signals[0]}" aria-hidden="true"></span>`;
                const restDots = signals
                    .slice(1)
                    .map(color => `<span class="dot ${color}" aria-hidden="true"></span>`)
                    .join('');
                dotsHtml = `${firstDot}${restDots}`;
            }

            cell.setAttribute('data-date', dateKey);
            cell.innerHTML = `
  <span class="d">${pad(currentDate.getDate())}</span>
  <span class="dots">${dotsHtml}</span>`;

            cell.addEventListener('click', () => {
                state.selectedDay = currentDate;
                openDay(state.selectedDay);
            });

            cells.push(cell);
        }

        calGrid.replaceChildren(...cells);
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded', 'true');
        state.mode = 'month';

        // prefetch mois suivant / précédent
        fetchMonthData(addMonths(state.currentMonth, +1)).catch(() => {});
        fetchMonthData(addMonths(state.currentMonth, -1)).catch(() => {});
    }

    // --- Vue "liste" d'un jour ---
    async function openDay(date) {
        const monthData = await fetchMonthData(date);
        const dateKey = ymd(date);
        const bucket = monthData.byDay.get(dateKey) || { list: [] };

        listTitle.textContent = fmtDayTitle(date);
        listRows.innerHTML = '';

        // bouton "Tournée"
        const ymdStr = ymd(date);
        const agRefFromContext = agRefOf(window.APP?.numInt);
        const agRefFromList = () => {
            const firstWithNumInt = (bucket.list || []).find(ev => ev?.num_int);
            return agRefOf(firstWithNumInt?.num_int || '');
        };
        const agRef = agRefFromContext || agRefFromList();

        // enlever ancien bouton
        const oldTourButton = document.getElementById('btnTournee');
        oldTourButton?.parentNode?.removeChild(oldTourButton);

        const tourButton = document.createElement('a');
        tourButton.id = 'btnTournee';
        // mêmes classes qu’un bouton "Planifier un nouvel appel"
        tourButton.className = 'btn btn-sm btn-plan-call btn-tournee';
        tourButton.href =
            `/tournee?date=${encodeURIComponent(ymdStr)}&agref=${encodeURIComponent(agRef)}&mode=fast&opt=0`;
        tourButton.target = '_blank';
        tourButton.rel = 'noopener';
        tourButton.textContent = agRef ? `📍 Tournée ${agRef}` : '📍 Tournée';

        listTitle.appendChild(tourButton);

        if (!bucket.list.length) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                `<td colspan="5" class="note cell-p8-10">Aucun évènement</td>`;
            listRows.appendChild(tr);
        } else {
            const rows = bucket.list
                .sort((a, b) =>
                    String(a.start_datetime).localeCompare(String(b.start_datetime))
                )
                .map(ev => makeRow(ev));

            listRows.replaceChildren(...rows);
        }

        listWrap.classList.remove('is-hidden');
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded', 'true');
        state.mode = 'month';
        listWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // --- Ligne d'un évènement dans la liste jour ---
    function makeRow(ev) {
        const row = document.createElement('tr');

        const isTemp = (ev && (ev.is_validated === 0 || ev.is_validated === false));
        const isValid = (ev && (ev.is_validated === 1 || ev.is_validated === true));
        const sameDossier = (ev && ev.num_int === currentNumInt);

        if (ev && ev.is_urgent) row.classList.add('urgent');
        if (isTemp) row.classList.add('temporaire');
        if (isTemp && sameDossier) row.classList.add('same-dossier');
        if (isValid && sameDossier) row.classList.add('valide');

        const hhmmText = timeHHMM(ev.start_datetime);
        const techCode = ev.code_tech || '';
        const clientName = ev.client || ev.contact || '—';
        const label = ev.label || ev.num_int || '';

        const infoButton = document.createElement('button');
        infoButton.className = 'icon-btn';
        infoButton.type = 'button';
        infoButton.textContent = 'i';
        infoButton.title = 'Détails';
        infoButton.addEventListener('click', () => showEventModal(ev));

        const tdIcon = document.createElement('td');
        tdIcon.className = 'col-icon';
        tdIcon.appendChild(infoButton);

        const tdLabel = document.createElement('td');
        tdLabel.textContent = label;

        if (ev.is_urgent) {
            const badgeUrgent = document.createElement('span');
            badgeUrgent.className = 'badge badge-urgent';
            badgeUrgent.textContent = 'URGENT';
            badgeUrgent.style.marginLeft = '6px';
            tdLabel.appendChild(badgeUrgent);
        }

        if (isTemp) {
            const badgeTemp = document.createElement('span');
            badgeTemp.className = 'badge badge-temp';
            badgeTemp.textContent = 'TEMP';
            badgeTemp.style.marginLeft = '6px';
            tdLabel.appendChild(badgeTemp);
        }

        row.innerHTML = `
      <td>${hhmmText}</td>
      <td>${escapeHtml(techCode)}</td>
      <td>${escapeHtml(clientName)}</td>
    `;
        row.appendChild(tdLabel);
        row.appendChild(tdIcon);

        return row;
    }

    // --- Contenu de la modale "détails RDV" ---
    function showEventModal(ev) {
        if (!modal || !modalBody) return;

        const isTemp = (ev && (ev.is_validated === 0 || ev.is_validated === false));
        const sameDossier = (ev && ev.num_int === currentNumInt);

        const clientName = ev.client || ev.contact || null;
        const locationText = [ev.cp, ev.ville].filter(Boolean).join(' ');

        const parts = [];
        parts.push('<h3 class="modal-title">Détails du rendez-vous</h3>');
        parts.push(`
          <dl class="kv">
            <dt><strong>Intervention</strong></dt><dd>${escapeHtml(ev.num_int || '—')}</dd>
            <dt><strong>Quand</strong></dt><dd>${escapeHtml(fmtDateTimeFR(ev.start_datetime))}</dd>
            <dt><strong>Technicien</strong></dt><dd>${escapeHtml(ev.code_tech || '—')}</dd>
            ${clientName ? `<dt><strong>Client</strong></dt><dd>${escapeHtml(clientName)}</dd>` : ''}
            ${locationText ? `<dt><strong>Lieu</strong></dt><dd>${escapeHtml(locationText)}</dd>` : ''}
            ${ev.marque ? `<dt><strong>Marque</strong></dt><dd>${escapeHtml(ev.marque)}</dd>` : ''}
          </dl>
        `);

        if (ev.commentaire) {
            parts.push(
                `<div class="section">
                    <div class="section-title"><strong>Commentaire</strong></div>
                    <div class="prewrap">${escapeHtml(ev.commentaire)}</div>
                </div>`
            );
        }

        const badges = [];
        if (ev.is_urgent) badges.push('<span class="badge badge-urgent">URGENT</span>');
        if (isTemp)      badges.push('<span class="badge badge-temp">TEMP</span>');
        if (badges.length) {
            parts.push(`<div class="section">${badges.join(' ')}</div>`);
        }

        // Actions si RDV temporaire du dossier courant
        if (isTemp && sameDossier) {
            parts.push(`
              <div class="modal-actions">
                <button id="modalValidate" data-source="temp-modal" class="btn btn-validate" type="button">Valider ce RDV</button>
                <button id="modalDelete"  class="btn btn-danger"   type="button">Supprimer ce RDV</button>
              </div>
            `);
        }

        modalBody.innerHTML = parts.join('');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        if (isTemp && sameDossier) {
            modalBody
                .querySelector('#modalValidate')
                ?.addEventListener('click', () => onValidateFromModal(ev), { once: true });

            modalBody
                .querySelector('#modalDelete')
                ?.addEventListener('click', () => onDeleteFromModal(ev), { once: true });
        }
    }

    function closeModal() {
        if (!modal || !modalBody) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }

    // --- Depuis la modale : "Valider ce RDV" (pré-remplit les champs puis clique sur Valider) ---
    function onValidateFromModal(ev) {
        const formElement = document.getElementById('interventionForm');
        const validateButton = document.getElementById('btnValider');
        const techSelectInput = document.getElementById('selAny');
        const dateInput = document.getElementById('dtPrev');
        const timeInput = document.getElementById('tmPrev');

        if (!formElement || !validateButton || !techSelectInput || !dateInput || !timeInput) {
            alert('Formulaire de validation introuvable.');
            return;
        }

        techSelectInput.value = ev.code_tech || '';

        const dateObj = new Date(String(ev.start_datetime || ''));
        const yyyy = dateObj.getFullYear();
        const mm = pad(dateObj.getMonth() + 1);
        const dd = pad(dateObj.getDate());
        const hh = pad(dateObj.getHours());
        const mi = pad(dateObj.getMinutes());

        dateInput.value = `${yyyy}-${mm}-${dd}`;
        timeInput.value = `${hh}:${mi}`;

        closeModal();
        validateButton.click();
    }

    // --- Depuis la modale : "Supprimer ce RDV" (DELETE sur l'API) ---
    async function onDeleteFromModal(ev) {
        const confirmDelete = confirm('Supprimer ce rendez-vous temporaire ?');
        if (!confirmDelete) return;

        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.content || '';
        const numInt = currentNumInt;
        const rdvId = ev.id;

        if (!rdvId) {
            alert("Impossible de trouver l'identifiant du RDV.");
            return;
        }

        const deleteUrl =
            `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/${encodeURIComponent(rdvId)}`;

        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const json = await response.json().catch(() => ({ ok: false }));

            if (!response.ok || !json.ok) {
                throw new Error(json?.msg || 'Suppression échouée');
            }

            closeModal();
            state.cache.clear();
            await paintMonth();
            if (state.selectedDay) await openDay(state.selectedDay);
        } catch (error) {
            console.error(error);
            alert(error.message || 'Suppression échouée.');
        }
    }

    function fmtDateTimeFR(iso) {
        if (!iso) return '';
        const date = new Date(iso);
        if (isNaN(date)) return iso;

        const dd = pad(date.getDate());
        const mm = pad(date.getMonth() + 1);
        const yyyy = date.getFullYear();
        const hh = pad(date.getHours());
        const mi = pad(date.getMinutes());

        return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
    }
}
