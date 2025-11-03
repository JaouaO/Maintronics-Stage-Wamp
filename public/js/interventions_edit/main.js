// interventions_edit_main.js
import './debug.js';                // installe window.__DBG + handlers erreurs
import { initHorloge } from './horloge.js';
import {initModal} from "./modal.js";
import { initGuards } from './guards.js';
import { initHistory } from './history.js';
import { initAgenda } from './agenda.js';
import { initRDV } from './rdv.js';
// Si tu as une modale générique : import { openModal, closeModal } from './modal.js';

document.addEventListener('DOMContentLoaded', () => {
    initHorloge();
    initGuards();
    initModal();
    initHistory();
    initAgenda();
    initRDV();
});
