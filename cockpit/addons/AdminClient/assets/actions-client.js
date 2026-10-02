/**
 * Hides from the customer the actions on the site's structure that their role
 * does not allow: Cockpit shows them to everyone and refuses them only once
 * clicked (« Unauthorized request »).
 *
 * The page is changed only when an element is not hidden yet, so the
 * MutationObserver never wakes itself up endlessly (see the fix to
 * contraste-couleurs.js in 2.0.7).
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
    // Editing or creating a model, and the divider before the entry.
    document.querySelectorAll('a[href*="/content/models/"]').forEach((lien) => {
        const ligne = lien.closest('li');
        masquer(ligne || lien);
        const avant = ligne && ligne.previousElementSibling;
        if (avant && avant.classList.contains('kiss-nav-divider')) masquer(avant);
    });

    // The raw JSON object of an item: of no use to the customer.
    document.querySelectorAll('kiss-popout icon').forEach((icone) => {
        if (iconeVaut(icone, 'manage_search')) masquer(icone.closest('li'));
    });

    // On the content overview, the « ⋮ » menu of each model only holds actions on the structure.
    if (/\/content\/?$/.test(location.pathname)) {
        document.querySelectorAll('icon').forEach((icone) => {
            if (iconeVaut(icone, 'more_vert')) masquer(icone.closest('a'));
        });
    }

    // A « … » menu left with nothing to offer disappears.
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
