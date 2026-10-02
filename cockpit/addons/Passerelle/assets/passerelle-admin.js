/**
 * Admin → site: « Voir le site » in the header, « Voir cette page » while a page is being edited.
 * Both open the public site in a new tab.
 */

const page = (location.pathname.match(/\/content\/collection\/item\/pages\/([0-9a-f]{24})/) || [])[1] || '';
const adresse = App.route('/passerelle/infos') + (page ? `?page=${page}` : '');

function bouton(texte, href, icone) {
    const lien = document.createElement('a');
    lien.className = 'kiss-button kiss-button-small passerelle-bouton';
    lien.href = href;
    lien.target = '_blank';
    lien.rel = 'noopener';
    lien.innerHTML = `<icon class="kiss-margin-xsmall-end">${icone}</icon>`;
    lien.append(texte);
    return lien;
}

fetch(adresse, { credentials: 'same-origin' })
    .then((reponse) => (reponse.ok ? reponse.json() : null))
    .then((infos) => {
        if (!infos || !infos.site) return;

        const nom = document.querySelector('main .kiss-text-bold');
        const ligne = nom && nom.closest('kiss-container');
        if (!ligne || ligne.querySelector('.passerelle-bouton')) return;

        const place = document.createElement('div');
        place.className = 'kiss-flex kiss-flex-middle kiss-margin-small-start';
        place.style.gap = '.5rem';
        place.append(bouton('Voir le site', infos.site + '/', 'open_in_new'));
        if (infos.page) place.append(bouton('Voir cette page', infos.site + infos.page, 'visibility'));

        nom.closest('a, div').after(place);
    })
    .catch(() => {});
