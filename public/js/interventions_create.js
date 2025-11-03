// public/js/interventions_create.js
(() => {
    const $ = (s, ctx) => (ctx || document).querySelector(s);
    const ready = (fn) => (document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', fn)
        : fn());

    function getSuggestUrl() {
        const meta = document.querySelector('meta[name="suggest-endpoint"]')?.content;
        if (meta) return meta;
        if (typeof window.CREATE_INTERVENTION_SUGGEST_URL === 'string' && window.CREATE_INTERVENTION_SUGGEST_URL.length) {
            return window.CREATE_INTERVENTION_SUGGEST_URL;
        }
        const scripts = document.querySelectorAll('script[src*="interventions_create.js"]');
        for (const s of scripts) {
            const ds = s.getAttribute('data-suggest');
            if (ds) return ds;
        }
        return '';
    }

    // Petit helper visuel "chargement"
    function setLoading(el, on) {
        if (!el) return;
        el.toggleAttribute('data-loading', !!on);
    }

    ready(() => {
        const form   = $('#createForm');
        const agence = $('#Agence');
        const date   = $('#DateIntPrevu');
        const num    = $('#NumInt');
        const urgent = $('#Urgent');
        const submit = form?.querySelector('button[type="submit"]');

        if (!form || !agence || !num) return;

        const suggestEndpoint = getSuggestUrl();
        if (!suggestEndpoint) return;

        // ——— Sécurité client légère (ne remplace pas la validation serveur)
        const cp = $('#CPLivCli');
        if (cp) {
            cp.addEventListener('input', () => {
                let v = cp.value.replace(/[^\dA-Za-z\- ]+/g, '');
                if (v.length > 10) v = v.slice(0, 10);
                cp.value = v;
            });
        }

        const ville = $('#VilleLivCli');
        if (ville) {
            ville.addEventListener('input', () => {
                let v = ville.value.replace(/[\x00-\x1F\x7F<>]/g, '');
                if (v.length > 80) v = v.slice(0, 80);
                ville.value = v;
            });
        }

        const marque = $('#Marque');
        if (marque) {
            marque.addEventListener('input', () => {
                let v = marque.value.replace(/[\x00-\x1F\x7F<>]/g, '');
                if (v.length > 80) v = v.slice(0, 80);
                marque.value = v;
            });
        }

        // ——— Suggest NumInt (debounce + anti-course + AbortController)
        let aborter = null;
        let debounceTimer = null;
        let seq = 0;

        function refreshNum() {
            const ag = agence.value;
            const d  = date?.value || '';
            if (!ag) return;

            if (aborter) aborter.abort();
            aborter = new AbortController();

            const mySeq = ++seq;
            const url = new URL(suggestEndpoint, window.location.origin);
            url.searchParams.set('agence', ag);
            if (d) url.searchParams.set('date', d);

            setLoading(num, true);
            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: aborter.signal,
                credentials: 'same-origin'
            })
                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
                .then(js => {
                    if (mySeq !== seq) return;
                    if (js && js.ok && js.numInt) num.value = js.numInt;
                })
                .catch(err => { if (err.name !== 'AbortError') {/* silencieux */} })
                .finally(() => setLoading(num, false));
        }

        function debouncedRefresh() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(refreshNum, 180);
        }

        agence.addEventListener('change', debouncedRefresh);
        date?.addEventListener('change', debouncedRefresh);
        date?.addEventListener('input',  debouncedRefresh);

        // ——— Double-submit guard + vérifs croisées simples
        form.addEventListener('submit', (e) => {
            if (submit?.disabled) { e.preventDefault(); return; }
            // Règle UX : si l’un des deux (date, heure) est rempli → forcer l’autre
            const d = $('#DateIntPrevu')?.value || '';
            const h = $('#HeureIntPrevu')?.value || '';
            if ((d && !h) || (!d && h)) {
                e.preventDefault();
                alert('Veuillez saisir à la fois la date et l’heure prévues, ou laisser les deux vides.');
                return;
            }
            submit && (submit.disabled = true);
            setTimeout(() => { submit && (submit.disabled = false); }, 5000); // sécurité
        });

        // Premier suggest (au chargement)
        debouncedRefresh();
    });
})();
