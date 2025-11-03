export function initGuards() {
    // no-past pour date/heure
    const d = document.getElementById('dtPrev');
    const t = document.getElementById('tmPrev');
    if (d && t){
        const now = new Date();
        const pad = n => (n < 10 ? '0' : '') + n;
        const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const hhmm = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
        d.min = today;
        function applyTimeMin(){
            if (d.value === today) {
                t.min = hhmm;
                if (t.value && t.value < hhmm) t.value = hhmm;
            } else t.removeAttribute('min');
        }
        d.addEventListener('change', applyTimeMin);
        applyTimeMin();
    }

    // lock submit anti double clic
    const form = document.getElementById('interventionForm');
    if (form){
        form.addEventListener('submit', (e) => {
            if (form.dataset.lock === '1') { e.preventDefault(); return; }
            form.dataset.lock = '1';
            setTimeout(()=>{ form.dataset.lock = ''; }, 1500);
        });
    }
}
