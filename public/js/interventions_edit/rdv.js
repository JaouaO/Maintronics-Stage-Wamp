// public/js/interventions/rdv.js
import { withBtnLock, isInAgendaList } from './utils.js';
import { alreadyHasAnyForThisDossier, alreadyHasValidatedForThisDossier } from './agenda.js';


function isFromTempModalEvent(target) {
    if (!target) return false;
    // 1) Attributs directs du bouton
    if (target.dataset && (target.dataset.source === 'temp-modal' || target.dataset.skipTempWarning === '1')) {
        return true;
    }
    // 2) Attribut sur le form
    const formEl = document.getElementById('interventionForm');
    if (formEl && formEl.dataset && formEl.dataset.source === 'temp-modal') {
        return true;
    }
    // 3) Attribut sur un conteneur parent
    const nearest = target.closest?.('[data-source="temp-modal"], [data-skip-temp-warning="1"]');
    return !!nearest;
}

export function initRDV() {
    const form       = document.getElementById('interventionForm');
    const btnCall    = document.getElementById('btnPlanifierAppel');
    const btnRdv     = document.getElementById('btnPlanifierRdv');
    const btnVal     = document.getElementById('btnValider');
    const actionType = document.getElementById('actionType');
    const numInt     = document.getElementById('openHistory')?.dataset.numInt || '';
    const csrf       = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const elTech = document.getElementById('selAny');
    const elDate = document.getElementById('dtPrev');
    const elTime = document.getElementById('tmPrev');

    // — éviter la double pop-up
    if (form && form.getAttribute('onsubmit')) {
        try { form.removeAttribute('onsubmit'); } catch {}
    }

    // ---- helpers modale locale (optionnels si tu as un module modal.js global) ----
    const modal     = document.getElementById('infoModal');
    const modalBody = document.getElementById('infoModalBody');
    const modalX    = document.getElementById('infoModalClose');

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
    modalX?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    // --------------------------------------------------------------------
    // BLOQUER LE PASSÉ À LA MINUTE PRÈS
    // --------------------------------------------------------------------
    const serverIso = (window.APP && window.APP.serverNow) ? String(window.APP.serverNow) : null;

    function nowFromServer(){
        const d = serverIso ? new Date(serverIso) : new Date();
        return isNaN(d) ? new Date() : d;
    }
    function fmtYMD(d){
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
    }
    function fmtHM(d){
        const h = String(d.getHours()).padStart(2,'0');
        const m = String(d.getMinutes()).padStart(2,'0');
        return `${h}:${m}`;
    }
    function isPastSelection(dateStr, timeStr){
        if(!dateStr || !timeStr) return false;
        const [y,m,d] = dateStr.split('-').map(Number);
        const [hh,mm] = timeStr.split(':').map(Number);
        const sel = new Date(y, (m-1), d, hh, mm, 0, 0);
        return sel.getTime() < nowFromServer().getTime();
    }
    function applyMinConstraints(){
        if(!elDate) return;
        const now = nowFromServer();
        const today = fmtYMD(now);
        elDate.min = today;
        if(elTime){
            if(elDate.value === today){
                elTime.min = fmtHM(now);
            }else{
                elTime.removeAttribute('min');
            }
        }
    }
    function guardPastOrAlert(){
        const date = elDate?.value || '';
        const time = elTime?.value || '';
        if(isPastSelection(date, time)){
            alert('La date/heure choisie est dans le passé. Sélectionnez un créneau futur.');
            return true;
        }
        return false;
    }

    applyMinConstraints();
    elDate?.addEventListener('change', applyMinConstraints);
    elTime?.addEventListener('change', applyMinConstraints);

    // ---- Helpers provenant d'agenda.js (avec fallback window.*) ----
    function safeHasAny(){
        try { return !!alreadyHasAnyForThisDossier(); }
        catch { return typeof window.__agendaHasAnyForThisDossier === 'function' ? !!window.__agendaHasAnyForThisDossier() : false; }
    }
    function safeHasValidated(){
        try { return !!alreadyHasValidatedForThisDossier(); }
        catch { return typeof window.__agendaHasValidatedForThisDossier === 'function' ? !!window.__agendaHasValidatedForThisDossier() : false; }
    }

    // --- Nouvel appel (ne crée pas de RDV)
    if (btnCall && form && actionType) {
        btnCall.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, () => {
                actionType.value = 'appel';
                form.requestSubmit();
            });
        });
    }

    // --- RDV temporaire (upsert UNIQUE par dossier)
    // ... dans initRDV(), handler du btnRdv ...

    // --- RDV temporaire (upsert UNIQUE par dossier) + confirmations
    if (btnRdv) {
        let rdvBusy = false;

        async function postRdvTemp(purgeValidated) {
            const url = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire`;
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    rea_sal: elTech?.value || '',
                    date_rdv: elDate?.value || '',
                    heure_rdv: elTime?.value || '',
                    code_postal: document.querySelector('input[name="code_postal"]')?.value || null,
                    ville: document.querySelector('input[name="ville"]')?.value || null,
                    commentaire: document.querySelector('#commentaire')?.value || '',
                    purge_validated: !!purgeValidated
                }),
            });

            // tente de parser le JSON même en erreur
            let out = null;
            const raw = await res.text();
            try { out = JSON.parse(raw); } catch {}

            return { res, out, raw };
        }

        btnRdv.addEventListener('click', async (ev) => {
            withBtnLock(ev.currentTarget, async () => {
                if (rdvBusy) return;
                rdvBusy = true;
                try {
                    // champs requis
                    const tech = elTech?.value || '';
                    const date = elDate?.value || '';
                    const time = elTime?.value || '';
                    if (!numInt || !tech || !date || !time) {
                        alert('Sélectionne le technicien, la date et l’heure.');
                        return;
                    }
                    if (guardPastOrAlert()) return;

                    // État courant (déduit de l’agenda déjà chargé)
                    const hasAny = (function(){
                        try { return !!alreadyHasAnyForThisDossier(); }
                        catch { return typeof window.__agendaHasAnyForThisDossier === 'function' ? !!window.__agendaHasAnyForThisDossier() : false; }
                    })();
                    const hasValidated = (function(){
                        try { return !!alreadyHasValidatedForThisDossier(); }
                        catch { return typeof window.__agendaHasValidatedForThisDossier === 'function' ? !!window.__agendaHasValidatedForThisDossier() : false; }
                    })();

                    // 1) Avertissements avant envoi
                    let purgeValidated = false;
                    if (hasValidated) {
                        const ok = confirm(
                            'Créer un RDV TEMPORAIRE va SUPPRIMER le RDV validé en place.\n\nContinuer ?'
                        );
                        if (!ok) return;
                        purgeValidated = true;
                    } else if (hasAny) {
                        const ok = confirm(
                            'Un RDV TEMPORAIRE existe déjà pour ce dossier.\nLe nouveau remplacera l’actuel (l’ancien passera en obsolète).\n\nContinuer ?'
                        );
                        if (!ok) return;
                    }

                    // 2) Premier POST
                    let { res, out, raw } = await postRdvTemp(purgeValidated);

                    // 3) Gestion “409 VALIDATED_EXISTS” (handshake backend)
                    if (res.status === 409 && out && out.code === 'VALIDATED_EXISTS') {
                        const ok = confirm(
                            'Un RDV VALIDÉ est actif.\nCréer un RDV TEMPORAIRE va le SUPPRIMER.\n\nConfirmer la suppression du validé et poursuivre ?'
                        );
                        if (!ok) return;

                        ({ res, out, raw } = await postRdvTemp(true));
                    }

                    // 4) Gestion erreurs
                    if (!res.ok || !out || out.ok !== true) {
                        const probable =
                            res.status === 419 ? 'CSRF ou session expirée' :
                                res.status === 401 ? 'Non authentifié' :
                                    res.status === 422 ? 'Erreurs de validation' :
                                        res.status === 429 ? 'Trop de requêtes (throttle)' :
                                            res.status === 500 ? 'Erreur serveur (exception)' :
                                                'Inconnue';
                        const details = out && (out.errmsg || out.message) ? `\n${out.errmsg || out.message}` : '';
                        alert([
                            '❌ Création/MàJ RDV temporaire échouée',
                            `HTTP: ${res.status}`,
                            `Cause probable: ${probable}`,
                            details || (raw ? `\n${raw.slice(0, 300)}` : '')
                        ].filter(Boolean).join('\n'));
                        return;
                    }

                    // 5) Succès → rafraîchir agenda si nécessaire
                    const techNorm = (elTech?.value || '').toUpperCase().trim();
                    if (!isInAgendaList(techNorm)) {
                        window.location.reload();
                        return;
                    }
                    document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                    const c = document.querySelector('#commentaire'); if (c) c.value = '';
                    alert(out.mode === 'updated' ? 'RDV temporaire mis à jour.' : 'RDV temporaire créé.');
                } finally {
                    rdvBusy = false;
                }
            });
        });
    }



    // --- Valider RDV (écrasement éventuel de l’unique RDV du dossier)
    if (btnVal && form) {
        btnVal.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, () => {
                document.getElementById('actionType').value = 'rdv_valide';

                const date  = elDate?.value || '';
                const heure = elTime?.value || '';
                if (date && heure && isPastSelection(date, heure)) {
                    alert('Impossible de valider un rendez-vous dans le passé.');
                    return;
                }

                const hasAny       = safeHasAny();       // temp OU validé
                const hasValidated = safeHasValidated(); // validé
                const fromTempModal = isFromTempModalEvent(ev.currentTarget);

                // Cas 1 : un RDV validé existe -> on prévient toujours
                if (hasValidated) {
                    const ok = confirm(
                        "⚠️ Un RDV VALIDÉ existe déjà pour ce dossier.\n"
                        + "Valider maintenant va ÉCRASER ce RDV validé (date, heure, technicien, etc.).\n\n"
                        + "Confirmer la validation ?"
                    );
                    if (!ok) return;
                }
                    // Cas 2 : il existe un (ou des) RDV temporaire(s) actif(s)
                    // - Si l’action vient de la MODALE du RDV temp -> pas d’alerte
                // - Sinon -> on garde la sécurité
                else if (hasAny && !fromTempModal) {
                    const ok = confirm(
                        "⚠️ Un RDV TEMPORAIRE existe déjà pour ce dossier.\n"
                        + "Valider maintenant va le SUPPRIMER et le remplacer par un RDV validé.\n\n"
                        + "Confirmer la validation ?"
                    );
                    if (!ok) return;
                }

                form.requestSubmit();
            });
        });
    }
}
