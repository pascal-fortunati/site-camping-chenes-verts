/**
 * Masque au client les actions sur la structure du site, que son rôle ne permet pas :
 * Cockpit les affiche à tous et ne les refuse qu'au clic (« Unauthorized request »).
 *
 * Ne modifie la page que lorsqu'un élément n'est pas encore masqué : le MutationObserver ne se
 * relance donc pas sans fin (voir le correctif de contraste-couleurs.js, socle v2.0.7).
 */

const MASQUE = 'adminclient-masque';

function masquer(element) {
    if (element && !element.classList.contains(MASQUE)) {
        element.classList.add(MASQUE);
    }
}

function iconeVaut(icone, nom) {
    return icone.textContent.trim() === nom;
}

function scan() {
    // Modifier ou créer un modèle, et le séparateur qui précède l'entrée.
    document.querySelectorAll('a[href*="/content/models/"]').forEach((lien) => {
        const ligne = lien.closest('li');
        masquer(ligne || lien);
        const avant = ligne && ligne.previousElementSibling;
        if (avant && avant.classList.contains('kiss-nav-divider')) masquer(avant);
    });

    // L'objet JSON brut d'une fiche : inutile au client.
    document.querySelectorAll('kiss-popout icon').forEach((icone) => {
        if (iconeVaut(icone, 'manage_search')) masquer(icone.closest('li'));
    });

    // Sur la liste des contenus, le menu « ⋮ » de chaque modèle ne porte que des actions sur la structure.
    if (/\/content\/?$/.test(location.pathname)) {
        document.querySelectorAll('icon').forEach((icone) => {
            if (iconeVaut(icone, 'more_vert')) masquer(icone.closest('a'));
        });
    }

    // Un menu « … » qui n'a plus rien à proposer disparaît.
    document.querySelectorAll('[kiss-popout^="#"]').forEach((bouton) => {
        const menu = document.querySelector(bouton.getAttribute('kiss-popout'));
        if (!menu) return;
        const actions = [...menu.querySelectorAll('li')].filter((li) =>
            !li.classList.contains(MASQUE) && !li.classList.contains('kiss-nav-header') && !li.classList.contains('kiss-nav-divider'));
        if (!actions.length) masquer(bouton);
    });
}

new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
scan();
