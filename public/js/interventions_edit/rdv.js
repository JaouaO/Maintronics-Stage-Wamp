// public/js/interventions/rdv.js
import { withBtnLock } from './utils.js';

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

    // --- helpers modale locale (vous pouvez remplacer par import modal.js si dispo)
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
    // BLOQUER LE PASSÉ À LA MINUTE PRÈS (simple, sans arrondir/modifier)
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
        elDate.min = today; // interdit les jours avant aujourd'hui
        if(elTime){
            if(elDate.value === today){
                elTime.min = fmtHM(now); // interdit les heures < maintenant (à la minute)
            }else{
                elTime.removeAttribute('min'); // dates futures: pas de min sur l'heure
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

    // --- Nouvel appel (ne crée pas de RDV)
    if (btnCall && form && actionType) {
        btnCall.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, () => {
                actionType.value = 'appel';
                form.requestSubmit();
            });
        });
    }

    // --- RDV temporaire
    if (btnRdv) {
        btnRdv.addEventListener('click', async (ev) => {
            withBtnLock(ev.currentTarget, async () => {
                document.getElementById('actionType').value = 'rdv_temporaire';
                const tech = elTech?.value || '';
                const date = elDate?.value || '';
                const time = elTime?.value || '';
                if (!numInt || !tech || !date || !time) { alert('Sélectionne le technicien, la date et l’heure.'); return; }
                if (guardPastOrAlert()) return; // empêche passé

                const url = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire`;
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        rea_sal: tech, date_rdv: date, heure_rdv: time,
                        code_postal: document.querySelector('input[name="code_postal"]')?.value || null,
                        ville: document.querySelector('input[name="ville"]')?.value || null,
                        commentaire: document.querySelector('#commentaire')?.value || ''
                    }),
                });

                const raw = await res.text();
                let out = null; try { out = JSON.parse(raw); } catch {}

                if (!res.ok || !out || out.ok !== true) {
                    const ct = res.headers.get('content-type') || '';
                    const probable =
                        res.status === 419 ? 'CSRF ou session expirée' :
                            res.status === 401 ? 'Non authentifié' :
                                res.status === 422 ? 'Erreurs de validation' :
                                    res.status === 500 ? 'Erreur serveur (exception)' :
                                        !ct.includes('application/json') ? 'Réponse non-JSON (souvent une page HTML de login)' : 'Inconnue';
                    let details = '';
                    if (out && out.type === 'QueryException') details = `\nSQLSTATE: ${out.sqlstate}\nErrno: ${out.errno}\nMessage: ${out.errmsg}\n@ ${out.file}`;
                    else if (out && out.errmsg) details = `\nMessage: ${out.errmsg}\n@ ${out.file || ''}`;
                    alert(['❌ Création RDV temporaire échouée', `HTTP: ${res.status}`, `Cause probable: ${probable}`, details || (ct.includes('application/json') ? '' : `\nHTML/Corps:\n${raw.slice(0,400)}`)].filter(Boolean).join('\n'));
                    return;
                }

                document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                const c = document.querySelector('#commentaire'); if (c) c.value = '';
                alert(out.mode === 'updated' ? 'RDV temporaire mis à jour.' : 'RDV temporaire créé.');
            });
        });
    }

    // --- Valider RDV (avec choix si temporaires existants)
    if (btnVal && form) {
        btnVal.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, async () => {
                document.getElementById('actionType').value = 'rdv_valide';

                const tech  = elTech?.value || '';
                const date  = elDate?.value || '';
                const heure = elTime?.value || '';

                // refuse validation si passe
                if (date && heure && isPastSelection(date, heure)) {
                    alert('Impossible de valider un rendez-vous dans le passé.');
                    return;
                }

                // manques → submit classique (laissez la validation serveur faire le reste)
                if (!numInt || !tech || !date || !heure) { form.requestSubmit(); return; }

                const urlCheck = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/check`;
                const urlPurge = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/purge`;
                const timeSeconds = (heure.length === 5 ? `${heure}:00` : heure);

                try {
                    const r1 = await fetch(urlCheck, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({
                            exclude: {
                                codeTech: tech || null,
                                startDate: date || null,
                                startTime: timeSeconds  || null
                            }
                        })
                    });
                    const j1 = await r1.json().catch(() => ({ ok:false, count:0, items:[] }));
                    const hasTemps = !!(j1 && j1.ok && (j1.count || 0) > 0);
                    if (!hasTemps) { form.requestSubmit(); return; }

                    const listHtml = (j1.items || []).map(it => {
                        const hhmm = (it.StartTime || '').slice(0,5);
                        const dfr  = (it.StartDate || '').split('-').reverse().join('/');
                        const t    = it.CodeTech || '';
                        const lab  = (it.Label || '').replace(/[<>&"]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[s]));
                        return `<li><code>${dfr} ${hhmm}</code> · <strong>${t}</strong> — ${lab}</li>`;
                    }).join('');

                    const html = `
                      <div>
                        <h3 class="modal-title">RDV temporaires existants</h3>
                        <p>Des rendez-vous <em>temporaires</em> sont présents sur ce dossier&nbsp;:</p>
                        <ul class="modal-list">${listHtml}</ul>
                        <p>Que souhaitez-vous faire&nbsp;?</p>
                        <div class="hstack-8 flex-wrap">
                          <button id="optPurgeThenValidate" class="btn" type="button">Supprimer les temporaires puis valider</button>
                          <button id="optValidateAnyway" class="btn" type="button">Valider sans supprimer</button>
                          <button id="optCancel" class="btn" type="button">Annuler</button>
                        </div>
                      </div>`;
                    openModal(html);

                    modalBody.querySelector('#optPurgeThenValidate')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, async () => {
                            try {
                                const r2 = await fetch(urlPurge, {
                                    method:'POST', credentials:'same-origin',
                                    headers:{ 'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                                    body: JSON.stringify({})
                                });
                                const j2 = await r2.json().catch(()=>({ ok:false, deleted:0 }));
                                if (!j2.ok) { alert('❌ Échec de la suppression des RDV temporaires.'); return; }
                                document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                                closeModal();
                                form.requestSubmit();
                            } catch (err) {
                                console.error('[purge temporaires] erreur', err);
                                alert('❌ Erreur lors de la suppression des RDV temporaires.');
                            }
                        });
                    }, { once:true });

                    modalBody.querySelector('#optValidateAnyway')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, () => { closeModal(); form.requestSubmit(); });
                    }, { once:true });

                    modalBody.querySelector('#optCancel')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, () => closeModal());
                    }, { once:true });

                } catch (err) {
                    console.error('[Valider RDV] erreur', err);
                    form.requestSubmit();
                }
            });
        });
    }
}
