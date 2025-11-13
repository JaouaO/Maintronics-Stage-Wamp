// public/js/interventions_create.js
(() => {
    const qs = (selector, context) =>
        (context || document).querySelector(selector);

    const onDomReady = callback => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    };

    function getSuggestUrl() {
        const metaElement = document
            .querySelector('meta[name="suggest-endpoint"]');

        if (metaElement?.content) {
            return metaElement.content;
        }

        if (
            typeof window.CREATE_INTERVENTION_SUGGEST_URL === 'string' &&
            window.CREATE_INTERVENTION_SUGGEST_URL.length
        ) {
            return window.CREATE_INTERVENTION_SUGGEST_URL;
        }

        const scripts = document.querySelectorAll(
            'script[src*="interventions_create.js"]'
        );
        for (const scriptElement of scripts) {
            const datasetUrl = scriptElement.getAttribute('data-suggest');
            if (datasetUrl) return datasetUrl;
        }

        return '';
    }

    // Indique visuellement que l'input est en chargement (attribut data-loading)
    function setLoading(element, isLoading) {
        if (!element) return;
        element.toggleAttribute('data-loading', !!isLoading);
    }

    onDomReady(() => {
        const formElement = qs('#createForm');
        const agenceSelect = qs('#Agence');
        const dateInput = qs('#DateIntPrevu');
        const numIntInput = qs('#NumInt');
        const urgentCheckbox = qs('#Urgent');
        const submitButton =
            formElement?.querySelector('button[type="submit"]');

        if (!formElement || !agenceSelect || !numIntInput) return;

        const suggestEndpoint = getSuggestUrl();
        if (!suggestEndpoint) return;

        // ----- Sécurité client légère (ne remplace pas la validation serveur) -----
        const postalCodeInput = qs('#CPLivCli');
        if (postalCodeInput) {
            postalCodeInput.addEventListener('input', () => {
                let value = postalCodeInput.value.replace(/[^\dA-Za-z\- ]+/g, '');
                if (value.length > 10) value = value.slice(0, 10);
                postalCodeInput.value = value;
            });
        }

        const cityInput = qs('#VilleLivCli');
        if (cityInput) {
            cityInput.addEventListener('input', () => {
                let value = cityInput.value.replace(/[\x00-\x1F\x7F<>]/g, '');
                if (value.length > 80) value = value.slice(0, 80);
                cityInput.value = value;
            });
        }

        const brandInput = qs('#Marque');
        if (brandInput) {
            brandInput.addEventListener('input', () => {
                let value = brandInput.value.replace(/[\x00-\x1F\x7F<>]/g, '');
                if (value.length > 80) value = value.slice(0, 80);
                brandInput.value = value;
            });
        }

        // ----- Suggest NumInt (debounce + AbortController + anti-course) -----
        let currentAbortController = null;
        let debounceTimerId = null;
        let requestSequence = 0;

        function refreshSuggestedNumber() {
            const agenceValue = agenceSelect.value;
            const dateValue = dateInput?.value || '';

            if (!agenceValue) return;

            if (currentAbortController) currentAbortController.abort();
            currentAbortController = new AbortController();

            const mySequence = ++requestSequence;

            const url = new URL(suggestEndpoint, window.location.origin);
            url.searchParams.set('agence', agenceValue);
            if (dateValue) url.searchParams.set('date', dateValue);

            setLoading(numIntInput, true);

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: currentAbortController.signal,
                credentials: 'same-origin'
            })
                .then(response =>
                    response.ok
                        ? response.json()
                        : Promise.reject(
                            new Error('HTTP ' + response.status)
                        )
                )
                .then(payload => {
                    // On ignore si un autre appel a déjà mis à jour l’input
                    if (mySequence !== requestSequence) return;
                    if (payload && payload.ok && payload.numInt) {
                        numIntInput.value = payload.numInt;
                    }
                })
                .catch(error => {
                    if (error.name === 'AbortError') return;
                    // autres erreurs silencieuses côté UI
                })
                .finally(() => setLoading(numIntInput, false));
        }

        function debouncedRefreshSuggestedNumber() {
            clearTimeout(debounceTimerId);
            debounceTimerId = setTimeout(refreshSuggestedNumber, 180);
        }

        agenceSelect.addEventListener('change', debouncedRefreshSuggestedNumber);
        dateInput?.addEventListener('change', debouncedRefreshSuggestedNumber);
        dateInput?.addEventListener('input', debouncedRefreshSuggestedNumber);

        // ----- Double-submit guard + vérifs simples date/heure -----
        formElement.addEventListener('submit', event => {
            if (submitButton?.disabled) {
                event.preventDefault();
                return;
            }

            const dateValue = qs('#DateIntPrevu')?.value || '';
            const timeValue = qs('#HeureIntPrevu')?.value || '';

            // Règle UX : si un seul des deux champs est rempli → forcer l'autre
            if ((dateValue && !timeValue) || (!dateValue && timeValue)) {
                event.preventDefault();
                alert(
                    'Veuillez saisir à la fois la date et l’heure prévues, ou laisser les deux vides.'
                );
                return;
            }

            if (submitButton) submitButton.disabled = true;
            // Sécurité anti double-clic (réactivation après quelques secondes)
            setTimeout(() => {
                if (submitButton) submitButton.disabled = false;
            }, 5000);
        });

        // Premier suggest au chargement
        debouncedRefreshSuggestedNumber();
    });
})();
