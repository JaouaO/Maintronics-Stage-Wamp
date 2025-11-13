// public/js/interventions/rdv.js
import { withBtnLock, isInAgendaList } from './utils.js';
import {
    alreadyHasAnyForThisDossier,
    alreadyHasValidatedForThisDossier
} from './agenda.js';

function isFromTempModalEvent(target) {
    if (!target) return false;

    // 1) Attributs directs du bouton
    if (
        target.dataset &&
        (target.dataset.source === 'temp-modal' ||
            target.dataset.skipTempWarning === '1')
    ) {
        return true;
    }

    // 2) Attribut sur le form
    const formElement = document.getElementById('interventionForm');
    if (
        formElement &&
        formElement.dataset &&
        formElement.dataset.source === 'temp-modal'
    ) {
        return true;
    }

    // 3) Attribut sur un conteneur parent
    const nearest =
        target.closest?.(
            '[data-source="temp-modal"], [data-skip-temp-warning="1"]'
        );
    return !!nearest;
}

export function initRDV() {
    const formElement = document.getElementById('interventionForm');
    const btnCall = document.getElementById('btnPlanifierAppel');
    const btnRdvTemp = document.getElementById('btnPlanifierRdv');
    const btnValidate = document.getElementById('btnValider');
    const actionTypeInput = document.getElementById('actionType');
    const numInt =
        document.getElementById('openHistory')?.dataset.numInt || '';
    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const techSelect = document.getElementById('selAny');
    const dateInput = document.getElementById('dtPrev');
    const timeInput = document.getElementById('tmPrev');

    // Éviter les anciens onsubmit inline (double pop-up)
    if (formElement && formElement.getAttribute('onsubmit')) {
        try {
            formElement.removeAttribute('onsubmit');
        } catch {
            // silencieux
        }
    }

    // ---- helpers modale locale (si modal.js n'est pas utilisé ici) ----
    const modal = document.getElementById('infoModal');
    const modalBody = document.getElementById('infoModalBody');
    const modalCloseButton = document.getElementById('infoModalClose');

    function openModal(html) {
        if (!modal || !modalBody) return;
        modalBody.innerHTML = html;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        if (!modal || !modalBody) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }

    modalCloseButton?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    // --------------------------------------------------------------------
    // BLOQUER LE PASSÉ À LA MINUTE PRÈS (basé sur APP.serverNow si dispo)
    // --------------------------------------------------------------------
    const serverIso = (window.APP && window.APP.serverNow)
        ? String(window.APP.serverNow)
        : null;

    function nowFromServer() {
        const date = serverIso ? new Date(serverIso) : new Date();
        return isNaN(date) ? new Date() : date;
    }

    function fmtYMD(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function fmtHM(date) {
        const hour = String(date.getHours()).padStart(2, '0');
        const minute = String(date.getMinutes()).padStart(2, '0');
        return `${hour}:${minute}`;
    }

    function isPastSelection(dateStr, timeStr) {
        if (!dateStr || !timeStr) return false;

        const [year, month, day] = dateStr.split('-').map(Number);
        const [hours, minutes] = timeStr.split(':').map(Number);

        const selected = new Date(year, month - 1, day, hours, minutes, 0, 0);
        return selected.getTime() < nowFromServer().getTime();
    }

    function applyMinConstraints() {
        if (!dateInput) return;
        const now = nowFromServer();
        const today = fmtYMD(now);

        dateInput.min = today;

        if (timeInput) {
            if (dateInput.value === today) {
                timeInput.min = fmtHM(now);
            } else {
                timeInput.removeAttribute('min');
            }
        }
    }

    function guardPastOrAlert() {
        const dateStr = dateInput?.value || '';
        const timeStr = timeInput?.value || '';
        if (isPastSelection(dateStr, timeStr)) {
            alert('La date/heure choisie est dans le passé. Sélectionnez un créneau futur.');
            return true;
        }
        return false;
    }

    applyMinConstraints();
    dateInput?.addEventListener('change', applyMinConstraints);
    timeInput?.addEventListener('change', applyMinConstraints);

    // ---- Helpers provenant d'agenda.js (avec fallback window.*) ----
    function safeHasAny() {
        try {
            return !!alreadyHasAnyForThisDossier();
        } catch {
            return typeof window.__agendaHasAnyForThisDossier === 'function'
                ? !!window.__agendaHasAnyForThisDossier()
                : false;
        }
    }

    function safeHasValidated() {
        try {
            return !!alreadyHasValidatedForThisDossier();
        } catch {
            return typeof window.__agendaHasValidatedForThisDossier === 'function'
                ? !!window.__agendaHasValidatedForThisDossier()
                : false;
        }
    }

    // --- Nouvel appel (ne crée pas de RDV) ---
    if (btnCall && formElement && actionTypeInput) {
        btnCall.addEventListener('click', (event) => {
            withBtnLock(event.currentTarget, () => {
                actionTypeInput.value = 'appel';
                formElement.requestSubmit();
            });
        });
    }

    // --- RDV temporaire (upsert UNIQUE par dossier) + confirmations ---
    if (btnRdvTemp) {
        let rdvBusy = false;

        async function postRdvTemp(purgeValidated) {
            const endpoint =
                `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire`;

            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    rea_sal: techSelect?.value || '',
                    date_rdv: dateInput?.value || '',
                    heure_rdv: timeInput?.value || '',
                    code_postal:
                        document.querySelector('input[name="code_postal"]')?.value ||
                        null,
                    ville:
                        document.querySelector('input[name="ville"]')?.value ||
                        null,
                    commentaire:
                        document.querySelector('#commentaire')?.value || '',
                    purge_validated: !!purgeValidated
                })
            });

            let out = null;
            const raw = await response.text();
            try { out = JSON.parse(raw); } catch { /* silencieux */ }

            return { res: response, out, raw };
        }

        btnRdvTemp.addEventListener('click', (event) => {
            withBtnLock(event.currentTarget, async () => {
                if (rdvBusy) return;
                rdvBusy = true;

                try {
                    // Champs requis
                    const techCode = techSelect?.value || '';
                    const dateStr = dateInput?.value || '';
                    const timeStr = timeInput?.value || '';

                    if (!numInt || !techCode || !dateStr || !timeStr) {
                        alert('Sélectionne le technicien, la date et l’heure.');
                        return;
                    }
                    if (guardPastOrAlert()) return;

                    // État courant (déduit de l’agenda déjà chargé)
                    const hasAny = safeHasAny();
                    const hasValidated = safeHasValidated();

                    // 1) Avertissements avant envoi
                    let purgeValidated = false;
                    if (hasValidated) {
                        const confirmReplaceValidated = confirm(
                            'Créer un RDV TEMPORAIRE va SUPPRIMER le RDV validé en place.\n\nContinuer ?'
                        );
                        if (!confirmReplaceValidated) return;
                        purgeValidated = true;
                    } else if (hasAny) {
                        const confirmReplaceTemp = confirm(
                            'Un RDV TEMPORAIRE existe déjà pour ce dossier.\n' +
                            'Le nouveau remplacera l’actuel (l’ancien passera en obsolète).\n\nContinuer ?'
                        );
                        if (!confirmReplaceTemp) return;
                    }

                    // 2) Premier POST
                    let { res, out, raw } = await postRdvTemp(purgeValidated);

                    // 3) Gestion “409 VALIDATED_EXISTS” (handshake backend)
                    if (res.status === 409 && out && out.code === 'VALIDATED_EXISTS') {
                        const confirmPurge = confirm(
                            'Un RDV VALIDÉ est actif.\nCréer un RDV TEMPORAIRE va le SUPPRIMER.\n\n' +
                            'Confirmer la suppression du validé et poursuivre ?'
                        );
                        if (!confirmPurge) return;

                        ({ res, out, raw } = await postRdvTemp(true));
                    }

                    // 4) Gestion erreurs
                    if (!res.ok || !out || out.ok !== true) {
                        const probableCause =
                            res.status === 419 ? 'CSRF ou session expirée' :
                                res.status === 401 ? 'Non authentifié' :
                                    res.status === 422 ? 'Erreurs de validation' :
                                        res.status === 429 ? 'Trop de requêtes (throttle)' :
                                            res.status === 500 ? 'Erreur serveur (exception)' :
                                                'Inconnue';

                        const details =
                            out && (out.errmsg || out.message)
                                ? `\n${out.errmsg || out.message}`
                                : '';

                        alert([
                            '❌ Création/MàJ RDV temporaire échouée',
                            `HTTP: ${res.status}`,
                            `Cause probable: ${probableCause}`,
                            details || (raw ? `\n${raw.slice(0, 300)}` : '')
                        ].filter(Boolean).join('\n'));

                        return;
                    }

                    // 5) Succès → rafraîchir agenda si nécessaire
                    const techNormalized =
                        (techSelect?.value || '').toUpperCase().trim();

                    if (!isInAgendaList(techNormalized)) {
                        // Si le tech n’est pas dans la liste de l’agenda, on recharge tout
                        window.location.reload();
                        return;
                    }

                    // Sinon, on force un refresh de l’agenda pour ce tech
                    document
                        .getElementById('selModeTech')
                        ?.dispatchEvent(new Event('change'));

                    const commentField =
                        document.querySelector('#commentaire');
                    if (commentField) commentField.value = '';

                    alert(
                        out.mode === 'updated'
                            ? 'RDV temporaire mis à jour.'
                            : 'RDV temporaire créé.'
                    );
                } finally {
                    rdvBusy = false;
                }
            });
        });
    }

    // --- Validation RDV (écrasement éventuel de l’unique RDV du dossier) ---
    if (btnValidate && formElement) {
        btnValidate.addEventListener('click', (event) => {
            withBtnLock(event.currentTarget, () => {
                document.getElementById('actionType').value = 'rdv_valide';

                const dateStr = dateInput?.value || '';
                const timeStr = timeInput?.value || '';

                if (dateStr && timeStr && isPastSelection(dateStr, timeStr)) {
                    alert('Impossible de valider un rendez-vous dans le passé.');
                    return;
                }

                const hasAny = safeHasAny();           // temp OU validé
                const hasValidated = safeHasValidated(); // validé uniquement
                const fromTempModal = isFromTempModalEvent(event.currentTarget);

                // Cas 1 : un RDV validé existe -> toujours un avertissement
                if (hasValidated) {
                    const ok = confirm(
                        "⚠️ Un RDV VALIDÉ existe déjà pour ce dossier.\n" +
                        "Valider maintenant va ÉCRASER ce RDV validé (date, heure, technicien, etc.).\n\n" +
                        "Confirmer la validation ?"
                    );
                    if (!ok) return;
                }
                    // Cas 2 : seulement des RDV temporaires
                    // - Si l’action vient de la MODALE du RDV temp -> pas d’alerte
                // - Sinon -> on garde la sécurité
                else if (hasAny && !fromTempModal) {
                    const ok = confirm(
                        "⚠️ Un RDV TEMPORAIRE existe déjà pour ce dossier.\n" +
                        "Valider maintenant va le SUPPRIMER et le remplacer par un RDV validé.\n\n" +
                        "Confirmer la validation ?"
                    );
                    if (!ok) return;
                }

                formElement.requestSubmit();
            });
        });
    }
}
