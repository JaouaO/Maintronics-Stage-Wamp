// guard.js
export function initGuards() {
    // --- Interdiction de planifier dans le passé (date/heure) ---
    const dateInput = document.getElementById('dtPrev');
    const timeInput = document.getElementById('tmPrev');

    if (dateInput && timeInput) {
        const now = new Date();
        const pad = (n) => (n < 10 ? '0' : '') + n;

        const todayIso = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const nowHHMM = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

        dateInput.min = todayIso;

        function applyTimeMin() {
            if (dateInput.value === todayIso) {
                timeInput.min = nowHHMM;
                if (timeInput.value && timeInput.value < nowHHMM) {
                    timeInput.value = nowHHMM;
                }
            } else {
                timeInput.removeAttribute('min');
            }
        }

        dateInput.addEventListener('change', applyTimeMin);
        applyTimeMin();
    }

    // --- Verrou submit anti double-clic sur le formulaire principal ---
    const formElement = document.getElementById('interventionForm');
    if (formElement) {
        formElement.addEventListener('submit', (event) => {
            if (formElement.dataset.lock === '1') {
                event.preventDefault();
                return;
            }
            formElement.dataset.lock = '1';
            setTimeout(() => {
                formElement.dataset.lock = '';
            }, 1500);
        });
    }
}
