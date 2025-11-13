// horloge.js
import { nowServer, pad } from './utils.js';

export function initHorloge() {
    const clockElement = document.getElementById('srvDateTimeText');
    if (!clockElement) return;

    function formatServerDateTime(date) {
        const dateText =
            `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
        const timeText =
            `${pad(date.getHours())}:${pad(date.getMinutes())}`; // minutes uniquement
        return `${dateText} ${timeText}`;
    }

    function renderClock() {
        clockElement.textContent = formatServerDateTime(nowServer());
    }

    function scheduleNextTick() {
        const serverNow = nowServer();
        const millisecondsToNextMinute =
            (60 - serverNow.getSeconds()) * 1000 - serverNow.getMilliseconds(); // prochaine minute pile

        setTimeout(() => {
            renderClock();
            scheduleNextTick();
        }, Math.max(0, millisecondsToNextMinute));
    }

    renderClock();
    scheduleNextTick();

    // Resynchronisation simple quand l’onglet redevient visible
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) renderClock();
    });
}
