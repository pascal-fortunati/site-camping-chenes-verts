/**
 * Une icône Material à gauche des boutons qui n'en ont pas, d'après leur libellé (l'administration est en français).
 * Un bouton n'est traité qu'une fois (data-icone) : le MutationObserver ne se relance pas sans fin.
 */

const ICONES = [
    [/^enregistrer et fermer$/i, 'save'],
    [/^enregistrer/i, 'check'],
    [/^fermer$/i, 'close'],
    [/^annuler/i, 'undo'],
    [/^créer un dossier$/i, 'create_new_folder'],
    [/^envoyer une image$|^envoyer un fichier$|^envoyer une photo$/i, 'upload'],
    [/^créer un nouvel élément$|^créer$|^ajouter|^nouvel élément$/i, 'add'],
    [/^dupliquer$/i, 'content_copy'],
    [/^supprimer/i, 'delete'],
    [/^modifier la sélection$/i, 'edit_note'],
    [/^modifier/i, 'edit'],
    [/^rechercher$/i, 'search'],
    [/^recharger$/i, 'refresh'],
    [/^changer l’état$|^changer l'état$/i, 'toggle_on'],
    [/^choisir/i, 'check_circle'],
    [/^copier/i, 'content_copy'],
    [/^télécharger$/i, 'download'],
    [/^renommer$/i, 'drive_file_rename_outline'],
    [/^se connecter$/i, 'login'],
    [/^envoyer le lien$/i, 'send'],
    [/^réinitialiser/i, 'restart_alt'],
];

function iconer() {
    document.querySelectorAll('.kiss-button:not([data-icone])').forEach((bouton) => {
        bouton.dataset.icone = '1';
        if (bouton.querySelector('icon, img, svg')) return;

        const libelle = bouton.textContent.replace(/\s+/g, ' ').trim();
        const trouve = ICONES.find(([motif]) => motif.test(libelle));
        if (!trouve) return;

        const icone = document.createElement('icon');
        icone.setAttribute('aria-hidden', 'true');
        icone.textContent = trouve[1];
        bouton.prepend(icone);
    });
}

new MutationObserver(iconer).observe(document.documentElement, { childList: true, subtree: true });
iconer();
