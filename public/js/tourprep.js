"use strict";

/* =========================
   Bootstrap & constantes
   ========================= */
const agencyStart = (window.TP && window.TP.start) || null;
const technicianTours = (window.TP && window.TP.tours) || [];
const CSRF_TOKEN =
    document.querySelector('meta[name="csrf-token"]')?.content || '';

/* =========================
   Carte Leaflet
   ========================= */
(function initMap() {
    if (!Array.isArray(technicianTours) || technicianTours.length === 0) return;

    const map = L.map('map');

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const colorPalette = [
        '#007AFF',
        '#34C759',
        '#FF9500',
        '#AF52DE',
        '#FF2D55',
        '#5856D6',
        '#FF3B30',
        '#5AC8FA'
    ];

    const allBounds = [];

    // Marqueur Agence
    if (
        agencyStart &&
        typeof agencyStart.lat === 'number' &&
        typeof agencyStart.lon === 'number'
    ) {
        const agencyMarker = L.marker(
            [agencyStart.lat, agencyStart.lon],
            { title: 'Agence' }
        ).addTo(map);

        agencyMarker.bindPopup(
            `<b>Agence</b><br>${agencyStart.label || ''}`
        );
        allBounds.push([agencyStart.lat, agencyStart.lon]);
    }

    function buildPopupHtml(tour, stopIndex, stop) {
        const urgentBadge = stop.urgent
            ? ' — <b class="popup-urgent">URGENT</b>'
            : '';
        const postalCity = `${stop.cp || '—'} - ${stop.city || '—'}`;
        const timeText =
            typeof stop.heure === 'string' && stop.heure.length >= 5
                ? stop.heure.slice(0, 5)
                : stop.heure || '—';

        return `<b>${tour.tech} #${stopIndex + 1}</b>${urgentBadge}<br>${postalCity}<br>Heure: ${timeText}`;
    }

    function drawRouteForTour(color, tour) {
        const geometry = tour.geometry;

        if (geometry && geometry.type && geometry.coordinates) {
            const geometryType = geometry.type;

            if (geometryType === 'LineString') {
                const latLngs = geometry.coordinates.map(([lon, lat]) => [
                    lat,
                    lon
                ]);
                L.polyline(latLngs, {
                    weight: 4,
                    opacity: 0.85,
                    color
                }).addTo(map);
                latLngs.forEach(latLng => allBounds.push(latLng));
                return;
            }

            if (geometryType === 'MultiLineString') {
                geometry.coordinates.forEach(line => {
                    const latLngs = line.map(([lon, lat]) => [lat, lon]);
                    L.polyline(latLngs, {
                        weight: 4,
                        opacity: 0.85,
                        color
                    }).addTo(map);
                    latLngs.forEach(latLng => allBounds.push(latLng));
                });
                return;
            }
        }

        // Fallback “vol d’oiseau” si pas de géométrie fournie
        const latLngsFallback = [];
        if (agencyStart) {
            latLngsFallback.push([agencyStart.lat, agencyStart.lon]);
        }
        (tour.stops || []).forEach(stop =>
            latLngsFallback.push([stop.lat, stop.lon])
        );
        if (agencyStart) {
            latLngsFallback.push([agencyStart.lat, agencyStart.lon]);
        }

        L.polyline(latLngsFallback, {
            weight: 2,
            opacity: 0.75,
            color,
            dashArray: '4 6'
        }).addTo(map);

        latLngsFallback.forEach(latLng => allBounds.push(latLng));
    }

    technicianTours.forEach((tour, tourIndex) => {
        const color = colorPalette[tourIndex % colorPalette.length];

        (tour.stops || []).forEach((stop, stopIndex) => {
            if (
                typeof stop.lat !== 'number' ||
                typeof stop.lon !== 'number'
            ) {
                return;
            }

            const stopMarker = L.circleMarker(
                [stop.lat, stop.lon],
                { radius: 6, weight: 2, color, fillOpacity: 0.6 }
            ).addTo(map);

            stopMarker.bindPopup(
                buildPopupHtml(tour, stopIndex, stop)
            );
            allBounds.push([stop.lat, stop.lon]);
        });

        drawRouteForTour(color, tour);
    });

    if (allBounds.length) {
        map.fitBounds(allBounds, { padding: [20, 20] });
    }
})();

/* =========================
   Modale replanification
   ========================= */
(function () {
    const wrapHtml = `
<div id="replanWrap" class="hidden">
  <div class="modal-backdrop"></div>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rp_title">
    <div class="panel">
      <h3 id="rp_title">Reprogrammer le RDV</h3>
      <form id="rp_form">
        <input type="hidden" id="rp_endpoint" value="">
        <input type="hidden" id="rp_numint" value="">
        <input type="hidden" id="rp_has_temp" value="0">
        <input type="hidden" id="rp_has_validated" value="0">

        <label>Date du RDV
          <input type="date" id="rp_date" required>
        </label>
        <label>Heure du RDV
          <input type="time" id="rp_time" required>
        </label>

        <label>Technicien (code)
          <select id="rp_tech"></select>
        </label>

        <label>Commentaire
          <textarea id="rp_comment" rows="3" placeholder="(optionnel)"></textarea>
        </label>

        <label>Type de RDV</label>
        <div class="rdv-type-toggle" role="radiogroup" aria-label="Type de RDV">
          <label class="rdv-chip rdv-chip--valide">
            <input class="seg" type="radio" name="rp_mode" id="rp_mode_valide" value="valide" checked>
            <span>Validé</span>
          </label>
          <label class="rdv-chip rdv-chip--temp">
            <input class="seg" type="radio" name="rp_mode" id="rp_mode_temp" value="temp">
            <span>Temporaire</span>
          </label>
        </div>

        <div class="actions">
          <button type="button" class="btn" id="rp_cancel">Annuler</button>
          <button type="submit" class="btn primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>`;

    document.body.insertAdjacentHTML('beforeend', wrapHtml);

    const wrapElement = document.getElementById('replanWrap');
    const formElement = document.getElementById('rp_form');
    const endpointInput = document.getElementById('rp_endpoint');
    const numIntInput = document.getElementById('rp_numint');
    const dateInput = document.getElementById('rp_date');
    const timeInput = document.getElementById('rp_time');
    const techSelect = document.getElementById('rp_tech');
    const commentInput = document.getElementById('rp_comment');
    const hasTempInput = document.getElementById('rp_has_temp');
    const hasValidatedInput = document.getElementById('rp_has_validated');
    const cancelButton = document.getElementById('rp_cancel');

    function openReplanModal({
                                 endpoint,
                                 numInt,
                                 date,
                                 time,
                                 tech,
                                 people,
                                 hasTemp = '0',
                                 hasValidated = '0'
                             }) {
        endpointInput.value = endpoint || '';
        numIntInput.value = numInt || '';
        dateInput.value = (date || '').slice(0, 10);
        timeInput.value = (time || '').slice(0, 5);

        let peopleList = [];
        try {
            peopleList = JSON.parse(people || '[]');
        } catch {
            peopleList = [];
        }

        techSelect.innerHTML = '<option value="">— choisir —</option>';
        peopleList.forEach(person => {
            if (!person || !person.code) return;
            const optionElement = document.createElement('option');
            optionElement.value = person.code;
            optionElement.textContent = `${person.code} — ${person.name || ''}`;
            techSelect.appendChild(optionElement);
        });
        if (tech) techSelect.value = tech;

        hasTempInput.value = String(hasTemp);
        hasValidatedInput.value = String(hasValidated);

        const radioValidated = document.getElementById('rp_mode_valide');
        const radioTemporary = document.getElementById('rp_mode_temp');

        if (hasValidated === '1') {
            radioValidated.checked = true;
            radioTemporary.checked = false;
        } else if (hasTemp === '1') {
            radioValidated.checked = false;
            radioTemporary.checked = true;
        } else {
            radioValidated.checked = true;
            radioTemporary.checked = false;
        }

        commentInput.value = '';
        wrapElement.classList.remove('hidden');
    }

    function closeReplanModal() {
        wrapElement.classList.add('hidden');
    }

    // Ouverture de la modale
    document.addEventListener('click', event => {
        const button = event.target.closest('.btn-replan');
        if (!button) return;
        event.preventDefault();

        openReplanModal({
            endpoint: button.dataset.endpoint || '',
            numInt: button.dataset.numint || '',
            date: button.dataset.date || '',
            time: button.dataset.time || '',
            tech: button.dataset.tech || '',
            people: button.dataset.people || '[]',
            hasTemp: button.dataset.hasTemp || '0',
            hasValidated: button.dataset.hasValidated || '0'
        });
    });

    // Fermeture
    cancelButton.addEventListener('click', closeReplanModal);
    wrapElement.addEventListener('click', event => {
        if (
            event.target === wrapElement ||
            event.target.classList.contains('modal-backdrop')
        ) {
            closeReplanModal();
        }
    });

    // Soumission (temporaire ou validé)
    formElement.addEventListener('submit', async event => {
        event.preventDefault();

        const endpoint = endpointInput.value;
        if (!endpoint) {
            alert('Endpoint manquant');
            return;
        }

        const rdvDate = dateInput.value;
        const rdvTime = timeInput.value;
        if (!rdvDate || !rdvTime) {
            alert('Date/heure requis.');
            return;
        }

        const techCode = (techSelect.value || '').trim();
        const mode =
            document.querySelector('input[name="rp_mode"]:checked')?.value ||
            'valide';

        const hasTempFlag = hasTempInput.value === '1';
        const hasValidatedFlag = hasValidatedInput.value === '1';

        // ----- Mode RDV temporaire -----
        if (mode === 'temp') {
            const tempEndpoint = endpoint.replace(
                /\/replanifier$/,
                '/rdv/temporaire'
            );

            const basePayload = {
                date_rdv: rdvDate,
                heure_rdv: rdvTime,
                rea_sal: techCode || null,
                commentaire: (commentInput.value || '').trim() || null
            };

            async function postTemporary(payload) {
                const response = await fetch(tempEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        Accept: 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json().catch(() => ({}));
                return { response, data };
            }

            let { response, data } = await postTemporary(basePayload);

            // Conflit avec RDV VALIDÉ → confirmation remplacement
            if (
                response.status === 409 &&
                (data.code === 'VALIDATED_EXISTS' ||
                    /VALIDATED_EXISTS/.test(data.msg || ''))
            ) {
                const confirmReplace = window.confirm(
                    'Un rendez-vous VALIDÉ existe déjà pour ce dossier.\n' +
                    'Souhaitez-vous le remplacer par un RDV TEMPORAIRE ?'
                );
                if (!confirmReplace) return;

                const retryResult = await postTemporary({
                    ...basePayload,
                    purge_validated: true
                });

                const retryResponse = retryResult.response;
                const retryData = retryResult.data;

                if (!retryResponse.ok || retryData.ok === false) {
                    const errorMessage =
                        retryData.msg ||
                        retryData.error ||
                        retryData.message ||
                        (retryData.errors
                            ? Object.values(retryData.errors)
                                .flat()
                                .join('\n')
                            : retryResponse.status +
                            ' ' +
                            retryResponse.statusText);

                    alert(
                        'Échec replanification (temp) : ' + errorMessage
                    );
                    console.error(
                        '[rdv.temporaire/confirm] payload =',
                        { ...basePayload, purge_validated: true },
                        'resp =',
                        retryData
                    );
                    return;
                }

                closeReplanModal();
                location.reload();
                return;
            }

            if (!response.ok || data.ok === false) {
                const errorMessage =
                    data.msg ||
                    data.error ||
                    data.message ||
                    (data.errors
                        ? Object.values(data.errors).flat().join('\n')
                        : response.status + ' ' + response.statusText);

                alert('Échec replanification (temp) : ' + errorMessage);
                console.error(
                    '[rdv.temporaire] payload =',
                    basePayload,
                    'resp =',
                    data
                );
                return;
            }

            closeReplanModal();
            location.reload();
            return;
        }

        // ----- Mode RDV VALIDÉ -----
        if (mode === 'valide' && hasTempFlag) {
            const confirmReplaceTemp = window.confirm(
                'Un rendez-vous TEMPORAIRE existe déjà pour ce dossier.\n' +
                'Souhaitez-vous le remplacer par un RDV VALIDÉ ?'
            );
            if (!confirmReplaceTemp) return;
        }

        const validatedPayload = {
            rdv_at: rdvDate + ' ' + rdvTime + ':00',
            tech_code: techCode || null,
            comment: (commentInput.value || '').trim() || null
        };

        const response = await fetch(endpointInput.value, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                Accept: 'application/json'
            },
            body: JSON.stringify(validatedPayload)
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.ok === false) {
            const errorMessage =
                data.msg ||
                data.error ||
                data.message ||
                (data.errors
                    ? Object.values(data.errors).flat().join('\n')
                    : response.status + ' ' + response.statusText);

            alert('Échec replanification : ' + errorMessage);
            console.error(
                '[replanifier] payload =',
                validatedPayload,
                'resp =',
                data
            );
            return;
        }

        closeReplanModal();
        location.reload();
    });
})();

/* =========================
   Toggle + / –
   ========================= */
document.addEventListener('click', function (event) {
    const toggleButton = event.target.closest('.btn-toggle');
    if (!toggleButton) return;

    event.preventDefault();
    const controlledId = toggleButton.getAttribute('aria-controls');
    const extraElement = document.getElementById(controlledId);
    if (!extraElement) return;

    const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
    toggleButton.setAttribute(
        'aria-expanded',
        isExpanded ? 'false' : 'true'
    );
    toggleButton.textContent = isExpanded ? '+' : '–';
    extraElement.style.display = isExpanded ? 'none' : 'block';
});

/* =========================
   Autoplanning (preview + commit)
   ========================= */
const AUTOPLAN = {
    generateUrl:
        document.querySelector('meta[name="autoplan-generate"]')?.content ||
        '',
    commitUrl:
        document.querySelector('meta[name="autoplan-commit"]')?.content || ''
};

// Spinner : utilise le <span id="autoplanSpinner" hidden> s’il existe,
// sinon overlay de secours
function showAutoplanSpinner(show, text = 'Génération en cours…') {
    const inlineSpinner = document.getElementById('autoplanSpinner');
    if (
        inlineSpinner &&
        inlineSpinner.classList.contains('autoplan-spinner')
    ) {
        inlineSpinner.textContent = text;
        inlineSpinner.hidden = !show;
        return;
    }

    let overlaySpinner = document.getElementById('autoplanSpinnerOverlay');
    if (!overlaySpinner) {
        overlaySpinner = document.createElement('div');
        overlaySpinner.id = 'autoplanSpinnerOverlay';
        overlaySpinner.style.cssText =
            'position:fixed;right:14px;bottom:14px;z-index:10050;background:#111;color:#fff;padding:8px 12px;border-radius:10px;font:600 13px/1.2 system-ui';
        document.body.appendChild(overlaySpinner);
    }

    overlaySpinner.textContent = text;
    overlaySpinner.style.display = show ? 'block' : 'none';
}

function humanizeFetchError(error, url) {
    if (error && error.name === 'ReferenceError') return error.message;

    const currentLocation = window.location;
    if (!url) return 'URL vide (endpoint non initialisé).';

    try {
        const parsedUrl = new URL(url, currentLocation.origin);
        if (
            currentLocation.protocol === 'https:' &&
            parsedUrl.protocol === 'http:'
        ) {
            return 'Contenu mixte bloqué (page en https, appel en http).';
        }
        if (parsedUrl.origin !== currentLocation.origin) {
            return 'Appel cross-origin bloqué (CORS). Utilisez une URL du même domaine.';
        }
    } catch {
        // ignore
    }

    if (!navigator.onLine) return 'Hors ligne (navigator.offline).';
    return error?.message || 'Échec de connexion (requête n’atteint pas le serveur).';
}

/* ---- Helpers de preview (exposés globalement) ---- */
(function exposePreviewHelpers() {
    let AUTOPLAN_PROPOSAL = null; // {assignments, meta, ctx:{agref,date}}

    function hhmmFromSeconds(totalSeconds) {
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.round((totalSeconds % 3600) / 60);
        return `${String(hours).padStart(2, '0')}h${String(minutes).padStart(
            2,
            '0'
        )}`;
    }

    function markStop(numInt, toTech, rdvAt) {
        const button = document.querySelector(
            `.btn-replan[data-numint="${CSS.escape(numInt)}"]`
        );
        if (!button) return;

        const line = button.closest('.stop-line');
        if (!line) return;

        line.classList.add('ap-proposed');

        let badge = line.querySelector('.ap-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'ap-badge';
            line.appendChild(badge);
        }

        const timeText = (rdvAt || '').slice(11, 16) || '??:??';
        badge.textContent = `→ ${toTech} • ${timeText}`;
    }

    function unmarkAllStops() {
        document
            .querySelectorAll('.stop-line.ap-proposed')
            .forEach(element =>
                element.classList.remove('ap-proposed')
            );
        document
            .querySelectorAll('.stop-line .ap-badge')
            .forEach(element => element.remove());
    }

    function renderPreview(assignments, meta, ctx) {
        AUTOPLAN_PROPOSAL = { assignments, meta, ctx };

        unmarkAllStops();
        assignments.forEach(assignment =>
            markStop(
                assignment.numInt,
                assignment.toTech,
                assignment.rdv_at
            )
        );

        const ribbon = document.getElementById('autoplanRibbon');
        const footer = document.getElementById('autoplanFooter');
        const statsElement = document.getElementById('apStats');

        if (statsElement && meta && meta.per_tech) {
            const statsItems = Object.entries(meta.per_tech).map(
                ([tech, info]) => {
                    const stopsCount = info.stops ?? 0;

                    // Durée de journée (trajets + interventions)
                    let dayLabel = '—';
                    if (
                        typeof info.day_s === 'number' &&
                        info.day_s > 0
                    ) {
                        dayLabel = hhmmFromSeconds(info.day_s);
                    } else if (typeof info.travel_s === 'number') {
                        dayLabel = hhmmFromSeconds(info.travel_s);
                    }

                    let timeSpan = '';
                    if (info.depart_at && info.return_at) {
                        const departTime = String(info.depart_at).slice(
                            11,
                            16
                        );
                        const returnTime = String(info.return_at).slice(
                            11,
                            16
                        );
                        timeSpan = `${departTime} → ${returnTime}`;
                    }

                    return (
                        `${tech}: ${stopsCount} rdv • journée ${dayLabel}` +
                        (timeSpan ? ` • ${timeSpan}` : '')
                    );
                }
            );

            statsElement.textContent = statsItems.join('  |  ');
        }

        if (ribbon) {
            ribbon.hidden = false;
            ribbon.style.display = 'block';
        }
        if (footer) {
            footer.hidden = false;
            footer.style.display = 'block';
        }

        if (!document.getElementById('apPreviewStyle')) {
            const styleElement = document.createElement('style');
            styleElement.id = 'apPreviewStyle';
            styleElement.textContent = `
                .stop-line.ap-proposed {
                    outline: 2px dashed #0ea5e9;
                    outline-offset: 2px;
                    background: rgba(14,165,233,.06);
                }
                .stop-line .ap-badge {
                    margin-left: 8px;
                    font-weight: 600;
                    font-size: 12px;
                    color: #075985;
                    background: #e0f2fe;
                    padding: 2px 6px;
                    border-radius: 10px;
                }
            `;
            document.head.appendChild(styleElement);
        }
    }

    function clearPreviewUI() {
        const ribbon = document.getElementById('autoplanRibbon');
        const footer = document.getElementById('autoplanFooter');

        if (ribbon) {
            ribbon.hidden = true;
            ribbon.style.display = 'none';
        }
        if (footer) {
            footer.hidden = true;
            footer.style.display = 'none';
        }

        unmarkAllStops();
        AUTOPLAN_PROPOSAL = null;
    }

    // Init : forcer caché si la page arrive sans preview
    (function initAutoplanUI() {
        const ribbon = document.getElementById('autoplanRibbon');
        const footer = document.getElementById('autoplanFooter');
        if (ribbon) {
            ribbon.hidden = true;
            ribbon.style.display = 'none';
        }
        if (footer) {
            footer.hidden = true;
            footer.style.display = 'none';
        }
    })();

    // Boutons Annuler / Commit
    document.getElementById('apCancel')?.addEventListener('click', () => {
        clearPreviewUI();
    });

    document.getElementById('apCommit')?.addEventListener('click', async () => {
        if (
            !AUTOPLAN_PROPOSAL ||
            !AUTOPLAN_PROPOSAL.assignments?.length
        ) {
            alert('Aucune proposition à valider.');
            return;
        }
        if (!AUTOPLAN.commitUrl) {
            alert('URL de commit introuvable.');
            return;
        }

        const confirmCommit = window.confirm(
            'Valider ce planning ? Les RDV seront enregistrés (validés).'
        );
        if (!confirmCommit) return;

        showAutoplanSpinner(true, 'Validation en cours…');

        const { assignments, ctx } = AUTOPLAN_PROPOSAL;
        try {
            const response = await fetch(AUTOPLAN.commitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    agref: ctx.agref,
                    date: ctx.date,
                    assignments
                })
            });
            const data = await response.json().catch(() => ({}));
            showAutoplanSpinner(false);

            if (!response.ok || data.ok === false) {
                const detail =
                    data.message ||
                    data.msg ||
                    (response.status + ' ' + response.statusText);
                alert('Commit autoplanning : échec.\n' + detail);
                return;
            }
            clearPreviewUI();
            location.reload();
        } catch (error) {
            showAutoplanSpinner(false);
            alert('Commit autoplanning : erreur réseau.');
            console.error(error);
        }
    });

    // Expose global
    window.renderPreview = renderPreview;
    window.clearPreviewUI = clearPreviewUI;
})();

/* ---- Click handler du bouton Autoplan ---- */
document.addEventListener('click', async event => {
    const button = event.target.closest('#btnAutoplan');
    if (!button) return;

    showAutoplanSpinner(true, 'Génération en cours…');

    try {
        const dateValue =
            button.dataset.date ||
            document.querySelector('[name="date"]')?.value ||
            '';
        const agencyRef =
            button.dataset.agref ||
            document.querySelector('[name="agref"]')?.value ||
            '';
        const mode =
            button.dataset.mode || 'fast';
        const optionRaw =
            button.dataset.opt || '1';

        if (!AUTOPLAN.generateUrl) {
            throw new Error('URL generate non définie.');
        }
        if (!dateValue || !agencyRef) {
            throw new Error('Paramètres manquants (date/agref).');
        }

        const response = await fetch(AUTOPLAN.generateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                Accept: 'application/json'
            },
            body: JSON.stringify({
                date: dateValue,
                agref: agencyRef,
                mode,
                opt: optionRaw === '1'
            })
        });

        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }

        showAutoplanSpinner(false);

        if (!response.ok || data.ok === false) {
            const detail =
                data.message ||
                data.msg ||
                (response.status + ' ' + response.statusText);
            alert('Autoplanning : échec serveur.\n' + detail);
            console.error('[Autoplan/generate] HTTP error', response.status, data);
            return;
        }

        const assignments = data.assignments || [];
        if (assignments.length === 0) {
            window.clearPreviewUI?.();
            const message =
                data.meta && (data.meta.message || data.meta.note)
                    ? data.meta.message || data.meta.note
                    : 'Aucune proposition générée pour ce jour/agence.';
            alert(message);
            return;
        }

        window.renderPreview(
            assignments,
            data.meta || {},
            { agref: agencyRef, date: dateValue }
        );
    } catch (error) {
        const hint = humanizeFetchError(error, AUTOPLAN.generateUrl);
        showAutoplanSpinner(false);
        alert('Autoplanning : ' + hint);
        console.error('[Autoplan] error', error);
    }
});
