"use strict";

// Récup données bootstrap (définies par la vue)
const agencyStart = (window.TP && window.TP.start) || null;
const technicianTours = (window.TP && window.TP.tours) || [];

/* ---------- Carte ---------- */
(function initMap() {
    if (!Array.isArray(technicianTours) || technicianTours.length === 0) return;

    const map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap' }).addTo(map);

    const palette   = ['#007AFF','#34C759','#FF9500','#AF52DE','#FF2D55','#5856D6','#FF3B30','#5AC8FA'];
    const allBounds = [];

    // Agence
    if (agencyStart && typeof agencyStart.lat === 'number' && typeof agencyStart.lon === 'number') {
        const m = L.marker([agencyStart.lat, agencyStart.lon], { title: 'Agence' }).addTo(map);
        m.bindPopup(`<b>Agence</b><br>${agencyStart.label || ''}`);
        allBounds.push([agencyStart.lat, agencyStart.lon]);
    }

    function popupHtml(tour, index, stop) {
        const urgentBadge = stop.urgent ? ' — <b style="color:#b91c1c">URGENT</b>' : '';
        const cpCity = `${(stop.cp || '—')} - ${(stop.city || '—')}`;
        const heure  = (typeof stop.heure === 'string' && stop.heure.length >= 5) ? stop.heure.slice(0,5) : (stop.heure || '—');
        return `<b>${tour.tech} #${index + 1}</b>${urgentBadge}<br>${cpCity}<br>Heure: ${heure}`;
    }

    function drawRoute(color, tour) {
        if (tour.geometry && tour.geometry.type && tour.geometry.coordinates) {
            const type = tour.geometry.type;
            if (type === 'LineString') {
                const latlngs = tour.geometry.coordinates.map(([lon, lat]) => [lat, lon]);
                L.polyline(latlngs, { weight: 4, opacity: 0.85, color }).addTo(map);
                latlngs.forEach(ll => allBounds.push(ll));
                return;
            }
            if (type === 'MultiLineString') {
                tour.geometry.coordinates.forEach(line => {
                    const latlngs = line.map(([lon, lat]) => [lat, lon]);
                    L.polyline(latlngs, { weight: 4, opacity: 0.85, color }).addTo(map);
                    latlngs.forEach(ll => allBounds.push(ll));
                });
                return;
            }
        }
        // Fallback “vol d’oiseau”
        const latlngs = [];
        if (agencyStart) { latlngs.push([agencyStart.lat, agencyStart.lon]); }
        (tour.stops || []).forEach(s => latlngs.push([s.lat, s.lon]));
        if (agencyStart) { latlngs.push([agencyStart.lat, agencyStart.lon]); }
        L.polyline(latlngs, { weight: 2, opacity: 0.75, color, dashArray: '4 6' }).addTo(map);
        latlngs.forEach(ll => allBounds.push(ll));
    }

    technicianTours.forEach((tour, tIndex) => {
        const color = palette[tIndex % palette.length];

        (tour.stops || []).forEach((stop, i) => {
            if (typeof stop.lat !== 'number' || typeof stop.lon !== 'number') return;
            const mk = L.circleMarker([stop.lat, stop.lon], { radius: 6, weight: 2, color, fillOpacity: 0.6 }).addTo(map);
            mk.bindPopup(popupHtml(tour, i, stop));
            allBounds.push([stop.lat, stop.lon]);
        });

        drawRoute(color, tour);
    });

    if (allBounds.length) {
        map.fitBounds(allBounds, { padding: [20, 20] });
    }
})();

/* ---------- Modale replanification ---------- */
(function () {
    const wrapHTML = `
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
        <div class="segmented" role="tablist" aria-label="Type de RDV">
          <input class="seg" type="radio" name="rp_mode" id="rp_mode_valide" value="valide" checked>
          <label for="rp_mode_valide">Validé</label>
          <input class="seg" type="radio" name="rp_mode" id="rp_mode_temp" value="temp">
          <label for="rp_mode_temp">Temporaire</label>
        </div>

        <div class="actions">
          <button type="button" class="btn" id="rp_cancel">Annuler</button>
          <button type="submit" class="btn primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>`;
    document.body.insertAdjacentHTML('beforeend', wrapHTML);

    const wrap      = document.getElementById('replanWrap');
    const form      = document.getElementById('rp_form');
    const fEndpoint = document.getElementById('rp_endpoint');
    const fNumInt   = document.getElementById('rp_numint');
    const fDate     = document.getElementById('rp_date');
    const fTime     = document.getElementById('rp_time');
    const fTech     = document.getElementById('rp_tech');
    const fComment  = document.getElementById('rp_comment');
    const fHasTemp  = document.getElementById('rp_has_temp');
    const fHasVal   = document.getElementById('rp_has_validated');
    const btnCancel = document.getElementById('rp_cancel');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function openModal({endpoint, numInt, date, time, tech, people, hasTemp='0', hasValidated='0'}) {
        fEndpoint.value = endpoint || '';
        fNumInt.value   = numInt   || '';
        fDate.value     = (date || '').slice(0,10);
        fTime.value     = (time || '').slice(0,5);

        // Select techniciens
        let arr = [];
        try { arr = JSON.parse(people || '[]'); } catch(_) {}
        fTech.innerHTML = '<option value="">— choisir —</option>';
        arr.forEach(p => {
            if (!p || !p.code) return;
            const opt = document.createElement('option');
            opt.value = p.code;
            opt.textContent = `${p.code} — ${p.name || ''}`;
            fTech.appendChild(opt);
        });
        if (tech) fTech.value = tech;

        // Mémorise l’état
        fHasTemp.value = String(hasTemp);
        fHasVal.value  = String(hasValidated);

        // Pré-sélection type
        const rVal = document.getElementById('rp_mode_valide');
        const rTmp = document.getElementById('rp_mode_temp');
        if (hasValidated === '1')      { rVal.checked = true;  rTmp.checked = false; }
        else if (hasTemp === '1')      { rVal.checked = false; rTmp.checked = true;  }
        else                           { rVal.checked = true;  rTmp.checked = false; }

        fComment.value = '';
        wrap.classList.remove('hidden');
    }

    function closeModal() { wrap.classList.add('hidden'); }

    // Ouvrir la modale
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btn-replan');
        if (!btn) return;
        e.preventDefault();
        openModal({
            endpoint:      btn.dataset.endpoint      || '',
            numInt:        btn.dataset.numint        || '',
            date:          btn.dataset.date          || '',
            time:          btn.dataset.time          || '',
            tech:          btn.dataset.tech          || '',
            people:        btn.dataset.people        || '[]',
            hasTemp:       btn.dataset.hasTemp       || '0',
            hasValidated:  btn.dataset.hasValidated  || '0'
        });
    });

    // Fermer
    btnCancel.addEventListener('click', closeModal);
    wrap.addEventListener('click', function (e) {
        if (e.target === wrap || e.target.classList.contains('modal-backdrop')) closeModal();
    });

    // Soumission
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const endpoint = fEndpoint.value;
        if (!endpoint) { alert("Endpoint manquant"); return; }

        const rdvDate = fDate.value;
        const rdvTime = fTime.value;
        if (!rdvDate || !rdvTime) { alert("Date/heure requis."); return; }

        const tech = (fTech.value || '').trim();
        const mode = (document.querySelector('input[name="rp_mode"]:checked')?.value || 'valide');

        const hasTempFlag = (fHasTemp.value === '1');
        const hasValFlag  = (fHasVal.value  === '1');

        // TEMP
        if (mode === 'temp') {
            const url = fEndpoint.value.replace(/\/replanifier$/, '/rdv/temporaire');

            const basePayload = {
                date_rdv:   rdvDate,
                heure_rdv:  rdvTime,
                rea_sal:    (tech || null),
                commentaire:(fComment.value || '').trim() || null
            };

            async function postTemp(payload) {
                const res  = await fetch(url, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(()=>({}));
                return { res, data };
            }

            let {res, data} = await postTemp(basePayload);

            // Conflit VALIDÉ → confirmation
            if (res.status === 409 && (data.code === 'VALIDATED_EXISTS' || /VALIDATED_EXISTS/.test(data.msg||''))) {
                const ok = window.confirm(
                    "Un rendez-vous VALIDÉ existe déjà pour ce dossier.\n" +
                    "Souhaitez-vous le remplacer par un RDV TEMPORAIRE ?"
                );
                if (!ok) return;

                const retry = await postTemp({ ...basePayload, purge_validated: true });
                if (!retry.res.ok || retry.data.ok === false) {
                    const msg = retry.data.msg || retry.data.error || retry.data.message
                        || (retry.data.errors ? Object.values(retry.data.errors).flat().join('\n')
                            :  (retry.res.status + ' ' + retry.res.statusText));
                    alert("Échec replanification (temp) : " + msg);
                    console.error('[rdv.temporaire/confirm] payload =', { ...basePayload, purge_validated:true }, 'resp =', retry.data);
                    return;
                }
                closeModal(); location.reload();
                return;
            }

            if (!res.ok || data.ok === false) {
                const msg = data.msg || data.error || data.message
                    || (data.errors ? Object.values(data.errors).flat().join('\n')
                        :  (res.status + ' ' + res.statusText));
                alert("Échec replanification (temp) : " + msg);
                console.error('[rdv.temporaire] payload =', basePayload, 'resp =', data);
                return;
            }

            closeModal(); location.reload();
            return;
        }

        // VALIDÉ
        if (mode === 'valide' && hasTempFlag) {
            const ok = window.confirm(
                "Un rendez-vous TEMPORAIRE existe déjà pour ce dossier.\n" +
                "Souhaitez-vous le remplacer par un RDV VALIDÉ ?"
            );
            if (!ok) return;
        }

        const payload = {
            rdv_at:    rdvDate + ' ' + rdvTime + ':00',
            tech_code: tech || null,
            comment:   (fComment.value || '').trim() || null
        };

        const res  = await fetch(fEndpoint.value, {
            method:'POST',
            headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json().catch(()=>({}));

        if (!res.ok || data.ok === false) {
            const msg = data.msg || data.error || data.message
                || (data.errors ? Object.values(data.errors).flat().join('\n')
                    :  (res.status + ' ' + res.statusText));
            alert("Échec replanification : " + msg);
            console.error('[replanifier] payload =', payload, 'resp =', data);
            return;
        }

        closeModal(); location.reload();
    });
})();

/* ---------- Toggle + / – ---------- */
document.addEventListener('click', function (e) {
    const t = e.target.closest('.btn-toggle');
    if (!t) return;
    e.preventDefault();
    const id = t.getAttribute('aria-controls');
    const extra = document.getElementById(id);
    if (!extra) return;

    const expanded = t.getAttribute('aria-expanded') === 'true';
    t.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    t.textContent = expanded ? '+' : '–';
    extra.style.display = expanded ? 'none' : 'block';
});
