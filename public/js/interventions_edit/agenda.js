// public/js/interventions/agenda.js
// Affichage agenda (mois + liste jour) avec badges URGENT
import {pad, escapeHtml, isBeforeToday} from './utils.js';

// --- API exportée (déférée) pour les autres modules (ex: rdv.js)
let _hasAnyFn = () => false;
let _hasValidatedFn = () => false;

export function alreadyHasAnyForThisDossier() { return _hasAnyFn(); }
export function alreadyHasValidatedForThisDossier() { return _hasValidatedFn(); }

export function initAgenda() {
    const elSel = document.getElementById('selModeTech');
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

    if (!elSel || !calWrap || !calGrid || !listRows) return;

    const currentNumInt =
        (window.APP && window.APP.numInt) ||
        document.getElementById('openHistory')?.dataset.numInt || '';

    const state = {
        cur: startOfMonth(new Date()),
        mode: 'month',
        day: null,
        tech: '_ALL',
        cache: new Map(), // key `${tech}|${from_to}` -> {events, byDay}
        reqToken: 0,
    };

    // ---- inject closures
    const computeHasAny = () => {
        for (const [, payload] of state.cache) {
            if (payload?.events?.some(x => x.num_int === currentNumInt)) return true;
        }
        return false;
    };
    const computeHasValidated = () => {
        for (const [, payload] of state.cache) {
            if (payload?.events?.some(x => (x.is_validated === 1 || x.is_validated === true) && x.num_int === currentNumInt)) return true;
        }
        return false;
    };
    _hasAnyFn = computeHasAny;
    _hasValidatedFn = computeHasValidated;
    window.__agendaHasAnyForThisDossier = _hasAnyFn;
    window.__agendaHasValidatedForThisDossier = _hasValidatedFn;

    // init
    state.tech = elSel.value || '_ALL';
    paintMonth();

    // events
    elSel.addEventListener('change', () => { state.tech = elSel.value || '_ALL'; state.cache.clear(); paintMonth(); });
    calPrev.addEventListener('click', () => { state.cur = addMonths(state.cur, -1); paintMonth(); });
    calNext.addEventListener('click', () => { state.cur = addMonths(state.cur, +1); paintMonth(); });
    calToggle.addEventListener('click', () => {
        const expanded = calToggle.getAttribute('aria-expanded') !== 'true';
        calToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (expanded) { calWrap.classList.remove('collapsed'); state.mode = 'month'; }
        else { calWrap.classList.add('collapsed'); state.mode = 'day'; }
    });
    dayPrev.addEventListener('click', () => { if (!state.day) return; state.day = addDays(state.day, -1); openDay(state.day); });
    dayNext.addEventListener('click', () => { if (!state.day) return; state.day = addDays(state.day, +1); openDay(state.day); });

    modalCloseBtn?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    // === helpers dates ===
    function ymd(d){ const yyyy=d.getFullYear(), mm=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0'); return `${yyyy}-${mm}-${dd}`; }
    function startOfMonth(d){ const x=new Date(d); x.setDate(1); x.setHours(0,0,0,0); return x; }
    function addMonths(d,n){ const x=new Date(d); x.setMonth(x.getMonth()+n); return x; }
    function addDays(d,n){ const x=new Date(d); x.setDate(x.getDate()+n); return x; }
    function fmtMonthYear(d){ return d.toLocaleDateString('fr-FR',{month:'long',year:'numeric'}); }
    function fmtDayTitle(d){ return d.toLocaleDateString('fr-FR',{weekday:'long',day:'2-digit',month:'long',year:'numeric'}); }
    function timeHHMM(iso){ if (!iso) return ''; const t=(iso.split('T')[1]||'00:00:00'); return t.slice(0,5); }
    function pad2(n){ return String(n).padStart(2,'0'); }
    function agRefOf(numInt){ if (!numInt) return ''; return String(numInt).slice(0,4).toUpperCase(); }

    async function fetchMonthData(d){
        const first = startOfMonth(d);
        const gridStart = (() => {
            const s = new Date(first);
            const dow = (s.getDay()+6)%7; // lundi=0
            s.setDate(s.getDate()-dow);
            s.setHours(0,0,0,0);
            return s;
        })();
        const gridEnd = addDays(gridStart, 41);
        gridEnd.setHours(23,59,59,999);

        const k = `${state.tech}|${ymd(gridStart)}_${ymd(gridEnd)}`;
        if (state.cache.has(k)) return state.cache.get(k);

        calGrid.classList.add('is-loading');
        const myToken = ++state.reqToken;

        const url = (window.APP?.apiPlanningRoute || '').replace('__X__', encodeURIComponent(state.tech));
        const qs  = `from=${ymd(gridStart)}&to=${ymd(gridEnd)}&numInt=${encodeURIComponent(currentNumInt)}&debug=1`;

        let payload = {events:[], byDay:new Map()};
        try{
            const res = await fetch(`${url}?${qs}`, {credentials:'same-origin', headers:{'Accept':'application/json'}});
            const out = await res.json().catch(()=>({ok:false,events:[]}));
            const eventsRaw = (out && out.ok && Array.isArray(out.events)) ? out.events : [];

            const eventsRawFixed = eventsRaw.map(ev => ({
                ...ev,
                code_tech: String(ev.code_tech || '').trim().toUpperCase()
            }));

            let events = eventsRawFixed;
            if (state.tech === '_ALL') {
                const allowed = new Set(((window.APP && window.APP.agendaAllowedCodes) || [])
                    .map(c => String(c).trim().toUpperCase()));
                if (allowed.size) {
                    events = eventsRawFixed.filter(ev => {
                        if (allowed.has(ev.code_tech)) return true;
                        for (const a of allowed) {
                            if (a.endsWith('*') && ev.code_tech.startsWith(a.slice(0,-1))) return true;
                        }
                        return false;
                    });
                }
            }

            const byDay = new Map();
            for (const ev of events) {
                const dKey = (ev.start_datetime || '').slice(0,10);
                if (!dKey) continue;
                const bucket = byDay.get(dKey) || { list:[], urgentCount:0, count:0, hasMyTemp:false, hasMyValidated:false };
                bucket.list.push(ev);
                bucket.count++;
                if (ev.is_urgent) bucket.urgentCount++;
                const isTemp  = (ev.is_validated === 0 || ev.is_validated === false);
                const isValid = (ev.is_validated === 1 || ev.is_validated === true);
                if (ev.num_int === currentNumInt) {
                    if (isTemp)  bucket.hasMyTemp = true;
                    if (isValid) bucket.hasMyValidated = true;
                }
                byDay.set(dKey, bucket);
            }
            payload = {events, byDay};
            state.cache.set(k, payload);
        } finally {
            if (myToken === state.reqToken) calGrid.classList.remove('is-loading');
        }
        return payload;
    }

    async function paintMonth(){
        calTitle.textContent = fmtMonthYear(state.cur);
        listWrap.classList.add('is-hidden');

        const data = await fetchMonthData(state.cur);
        const first = startOfMonth(state.cur);

        let maxCount = 0;
        for (const [,bucket] of data.byDay) maxCount = Math.max(maxCount, bucket.count||0);
        const heatOf = (count) => !maxCount ? 0 : Math.max(0, Math.min(10, Math.round((count/maxCount)*10)));

        const startGrid = new Date(first);
        const dayOfWeek = (startGrid.getDay()+6)%7; // lundi=0
        startGrid.setDate(startGrid.getDate()-dayOfWeek);

        const cells = [];
        for (let i=0;i<42;i++){
            const d = addDays(startGrid,i);
            const inMonth = (d.getMonth() === state.cur.getMonth());
            const key = ymd(d);
            const info = data.byDay.get(key) || {list:[], urgentCount:0, count:0, hasMyTemp:false, hasMyValidated:false};

            const cell = document.createElement('div');
            cell.className = 'cal-cell';

            const heat = heatOf(info.count);
            cell.classList.add(`heat-${heat}`);

            if (info.count > 0) cell.classList.add('has-events');
            if (info.urgentCount > 0) cell.classList.add('has-urgent');
            if (!inMonth && !isBeforeToday(d)) cell.classList.add('muted2');
            if (isBeforeToday(d)) cell.classList.add('muted');

            const signals = [];
            if (info.hasMyValidated) signals.push('green');
            if (info.urgentCount > 0) signals.push('red');
            if (info.hasMyTemp) signals.push('blue');

            let dotsHtml = '';
            if (signals.length === 0) {
                dotsHtml = `<span class="dot-base" aria-hidden="true"></span>`;
            } else {
                const firstDot = `<span class="dot ${signals[0]}" aria-hidden="true"></span>`;
                const rest = signals.slice(1).map(c => `<span class="dot ${c}" aria-hidden="true"></span>`).join('');
                dotsHtml = `${firstDot}${rest}`;
            }

            cell.setAttribute('data-date', key);
            cell.innerHTML = `
  <span class="d">${String(d.getDate()).padStart(2,'0')}</span>
  <span class="dots">${dotsHtml}</span>`;
            cell.addEventListener('click', () => { state.day = d; openDay(state.day); });
            cells.push(cell);
        }

        calGrid.replaceChildren(...cells);
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded','true');
        state.mode = 'month';

        // prefetch
        fetchMonthData(addMonths(state.cur,+1)).catch(()=>{});
        fetchMonthData(addMonths(state.cur,-1)).catch(()=>{});
    }

    async function openDay(d){
        const monthData = await fetchMonthData(d);
        const key = ymd(d);
        const bucket = monthData.byDay.get(key) || {list:[]};

        listTitle.textContent = fmtDayTitle(d);
        listRows.innerHTML = '';

        // bouton "Tournée"
        const ymdStr = ymd(d);
        const agRefFromCtx = agRefOf(window.APP?.numInt);
        const agRefFromList = () => {
            const firstWithNumInt = (bucket.list || []).find(x => x?.num_int);
            return agRefOf(firstWithNumInt?.num_int || '');
        };
        const agRef = agRefFromCtx || agRefFromList();

        // enlever ancien bouton
        let oldBtn = document.getElementById('btnTournee');
        oldBtn?.parentNode?.removeChild(oldBtn);

        const btn = document.createElement('a');
        btn.id = 'btnTournee';
// mêmes classes qu’un bouton "Planifier un nouvel appel"
        btn.className = 'btn btn-sm btn-plan-call btn-tournee';

        btn.href = `/tournee?date=${encodeURIComponent(ymdStr)}&agref=${encodeURIComponent(agRef)}&mode=fast&opt=0`;
        btn.target = '_blank';
        btn.rel = 'noopener';
        btn.textContent = agRef ? `📍 Tournée ${agRef}` : '📍 Tournée';

        listTitle.appendChild(btn);

        if (!bucket.list.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="5" class="note cell-p8-10">Aucun évènement</td>`;
            listRows.appendChild(tr);
        } else {
            const rows = bucket.list
                .sort((a,b) => String(a.start_datetime).localeCompare(String(b.start_datetime)))
                .map(ev => makeRow(ev));
            listRows.replaceChildren(...rows);
        }

        listWrap.classList.remove('is-hidden');
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded','true');
        state.mode = 'month';
        listWrap.scrollIntoView({behavior:'smooth', block:'start'});
    }

    function makeRow(ev){
        const tr = document.createElement('tr');
        const isTemp  = (ev && (ev.is_validated === 0 || ev.is_validated === false));
        const isValid = (ev && (ev.is_validated === 1 || ev.is_validated === true));
        const sameThis = (ev && ev.num_int === currentNumInt);

        if (ev && ev.is_urgent) tr.classList.add('urgent');
        if (isTemp) tr.classList.add('temporaire');
        if (isTemp && sameThis) tr.classList.add('same-dossier');
        if (isValid && sameThis) tr.classList.add('valide');

        const hhmm = timeHHMM(ev.start_datetime);
        const code = ev.code_tech || '';
        const client = ev.client || ev.contact || '—';
        const label = ev.label || ev.num_int || '';

        const btn = document.createElement('button');
        btn.className = 'icon-btn';
        btn.type = 'button';
        btn.textContent = 'i';
        btn.title = 'Détails';
        btn.addEventListener('click', () => showEventModal(ev));

        const tdIcon = document.createElement('td');
        tdIcon.className = 'col-icon';
        tdIcon.appendChild(btn);

        const tdLab = document.createElement('td');
        tdLab.textContent = label;

        if (ev.is_urgent) {
            const b = document.createElement('span');
            b.className = 'badge badge-urgent';
            b.textContent = 'URGENT';
            b.style.marginLeft = '6px';
            tdLab.appendChild(b);
        }
        if (isTemp) {
            const t = document.createElement('span');
            t.className = 'badge badge-temp';
            t.textContent = 'TEMP';
            t.style.marginLeft = '6px';
            tdLab.appendChild(t);
        }

        tr.innerHTML = `
      <td>${hhmm}</td>
      <td>${escapeHtml(code)}</td>
      <td>${escapeHtml(client)}</td>
    `;
        tr.appendChild(tdLab);
        tr.appendChild(tdIcon);
        return tr;
    }

    function showEventModal(ev){
        if (!modal || !modalBody) return;

        const isTemp = (ev && (ev.is_validated === 0 || ev.is_validated === false));
        const sameThis = (ev && ev.num_int === currentNumInt);

        // Présentation propre : titres en gras, contenus non gras
        const client = ev.client || ev.contact || null;
        const lieu = [ev.cp, ev.ville].filter(Boolean).join(' ');

        const parts = [];
        parts.push(`<h3 class="modal-title">Détails du rendez-vous</h3>`);
        parts.push(`
          <dl class="kv">
            <dt><strong>Intervention</strong></dt><dd>${escapeHtml(ev.num_int || '—')}</dd>
            <dt><strong>Quand</strong></dt><dd>${escapeHtml(fmtDateTimeFR(ev.start_datetime))}</dd>
            <dt><strong>Technicien</strong></dt><dd>${escapeHtml(ev.code_tech || '—')}</dd>
            ${client ? `<dt><strong>Client</strong></dt><dd>${escapeHtml(client)}</dd>` : ''}
            ${lieu ? `<dt><strong>Lieu</strong></dt><dd>${escapeHtml(lieu)}</dd>` : ''}
            ${ev.marque ? `<dt><strong>Marque</strong></dt><dd>${escapeHtml(ev.marque)}</dd>` : ''}
          </dl>
        `);

        if (ev.commentaire) {
            parts.push(`<div class="section"><div class="section-title"><strong>Commentaire</strong></div><div class="prewrap">${escapeHtml(ev.commentaire)}</div></div>`);
        }
        const badges = [];
        if (ev.is_urgent) badges.push('<span class="badge badge-urgent">URGENT</span>');
        if (isTemp)      badges.push('<span class="badge badge-temp">TEMP</span>');
        if (badges.length) parts.push(`<div class="section">${badges.join(' ')}</div>`);

        // Actions si RDV temporaire du dossier courant
        if (isTemp && sameThis) {
            parts.push(`
              <div class="modal-actions">
                <button id="modalValidate" data-source="temp-modal" class="btn btn-validate" type="button">Valider ce RDV</button>
                <button id="modalDelete"  class="btn btn-danger"   type="button">Supprimer ce RDV</button>
              </div>
            `);
        }

        modalBody.innerHTML = parts.join('');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');

        if (isTemp && sameThis) {
            modalBody.querySelector('#modalValidate')?.addEventListener('click', () => onValidateFromModal(ev), {once:true});
            modalBody.querySelector('#modalDelete')?.addEventListener('click',  () => onDeleteFromModal(ev),  {once:true});
        }
    }

    function closeModal(){
        if (!modal || !modalBody) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
        modalBody.innerHTML = '';
    }

    function onValidateFromModal(ev){
        const form = document.getElementById('interventionForm');
        const btnVal = document.getElementById('btnValider');
        const selAny = document.getElementById('selAny');
        const dtPrev = document.getElementById('dtPrev');
        const tmPrev = document.getElementById('tmPrev');

        if (!form || !btnVal || !selAny || !dtPrev || !tmPrev) { alert("Formulaire de validation introuvable."); return; }

        selAny.value = ev.code_tech || '';
        const d = new Date(String(ev.start_datetime || ''));
        const yyyy=d.getFullYear(), mm=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0');
        const hh=String(d.getHours()).padStart(2,'0'), mi=String(d.getMinutes()).padStart(2,'0');
        dtPrev.value = `${yyyy}-${mm}-${dd}`;
        tmPrev.value = `${hh}:${mi}`;

        closeModal();
        btnVal.click();
    }

    async function onDeleteFromModal(ev){
        const sure = confirm("Supprimer ce rendez-vous temporaire ?");
        if (!sure) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const numInt = currentNumInt;
        const id = ev.id;
        if (!id) { alert("Impossible de trouver l'identifiant du RDV."); return; }

        const urlDelete = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/${encodeURIComponent(id)}`;
        try{
            const res = await fetch(urlDelete, { method:'DELETE', credentials:'same-origin', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf} });
            const j = await res.json().catch(()=>({ok:false}));
            if (!res.ok || !j.ok) throw new Error(j?.msg || 'Suppression échouée');

            closeModal();
            state.cache.clear();
            await paintMonth();
            if (state.day) await openDay(state.day);
        } catch(e){ console.error(e); alert(e.message || "Suppression échouée."); }
    }

    function fmtDateTimeFR(iso){
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d)) return iso;
        const dd=String(d.getDate()).padStart(2,'0');
        const mm=String(d.getMonth()+1).padStart(2,'0');
        const yyyy=d.getFullYear();
        const hh=String(d.getHours()).padStart(2,'0');
        const mi=String(d.getMinutes()).padStart(2,'0');
        return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
    }
}
