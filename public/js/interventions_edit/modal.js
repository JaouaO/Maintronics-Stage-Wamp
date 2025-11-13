import { escapeHtml } from './utils.js';

export function initModal() {
    const modalElement = document.getElementById('infoModal');
    const modalBody = document.getElementById('infoModalBody');
    const closeButton = document.getElementById('infoModalClose');

    function openModal(htmlContent) {
        if (!modalElement || !modalBody) return;
        modalBody.innerHTML = htmlContent;
        modalElement.classList.add('is-open');
        modalElement.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        if (!modalElement || !modalBody) return;
        modalElement.classList.remove('is-open');
        modalElement.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }

    function renderSuivi(button) {
        /* ... inchangé ... */
    }

    function renderRDV(button) {
        /* ... inchangé ... */
    }

    function renderClient(button) {
        const dataset = button?.dataset || {};
        const field = (key) =>
            (dataset[key] && String(dataset[key]).trim()) ||
            (window.APP?.CLIENT?.[key] ?? '');

        const fullAddress = [field('adr'), field('cp'), field('ville')]
            .filter(Boolean)
            .join(' ')
            .trim();

        const mapsUrl = fullAddress
            ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress)}`
            : null;

        const phone = field('tel');
        const email = field('email');

        const phoneHtml = phone
            ? `<a href="tel:${encodeURIComponent(phone)}">${escapeHtml(phone)}</a>`
            : '—';

        const emailHtml = email
            ? `<a href="mailto:${encodeURIComponent(email)}">${escapeHtml(email)}</a>`
            : '—';

        return `
    <h3 class="modal-title">Fiche client</h3>
    <dl class="kv">
      <dt>Nom</dt><dd>${escapeHtml(field('nom') || '—')}</dd>
      <dt>Téléphone</dt><dd>${phoneHtml}</dd>
      <dt>Email</dt><dd>${emailHtml}</dd>
      <dt>Adresse</dt><dd>${escapeHtml(fullAddress || '—')}</dd>
      <dt>Type appareil</dt><dd>${escapeHtml(field('typeapp') || '—')}</dd>
      <dt>Marque</dt><dd>${escapeHtml(field('marque') || '—')}</dd>
    </dl>
    ${
            mapsUrl
                ? `<div class="mt8"><a href="${mapsUrl}" target="_blank" rel="noopener">Voir sur la carte</a></div>`
                : ''
        }
  `;
    }

    // Délégation globale pour les boutons .info-btn
    document.addEventListener('click', (event) => {
        if (event.target === modalElement) {
            return closeModal();
        }

        const infoButton = event.target.closest('.info-btn');
        if (!infoButton) return;

        const type = infoButton.dataset.type;

        if (type === 'suivi')  return openModal(renderSuivi(infoButton));
        if (type === 'rdv')    return openModal(renderRDV(infoButton));
        if (type === 'client') return openModal(renderClient(infoButton));

        openModal('<p>Pas de contenu disponible pour ce bouton.</p>');
    });

    closeButton && closeButton.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    // Export optionnel pour d'autres modules
    window.MODAL = { open: openModal, close: closeModal };
}
