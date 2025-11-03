// horloge.js
import { nowServer, pad } from './utils.js';

export function initHorloge() {
    const el = document.getElementById('srvDateTimeText');
    if (!el) return;

    function format(d) {
        const dateTxt = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
        const timeTxt = `${pad(d.getHours())}:${pad(d.getMinutes())}`; // minutes uniquement
        return `${dateTxt} ${timeTxt}`;
    }

    function draw() {
        el.textContent = format(nowServer());
    }

    function scheduleNextTick() {
        const t = nowServer();
        const ms = (60 - t.getSeconds()) * 1000 - t.getMilliseconds(); // prochaine minute pile
        setTimeout(() => {
            draw();
            scheduleNextTick();
        }, Math.max(0, ms));
    }

    draw();
    scheduleNextTick();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) draw();
    });
}
