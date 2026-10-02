/**
 * Les fenêtres de Cockpit remplacées par les nôtres, mêmes réglages et même retour : le choix d'une image
 * (champ image, texte riche, lien) ouvre choisir-image.js, la recherche (Ctrl K) recherche.js.
 */

const REMPLACEMENTS = {
    'assets:assets/dialogs/asset-picker.js': 'admincamping:assets/choisir-image.js',
    'app:assets/dialog/app-search.js': 'admincamping:assets/recherche.js',
};

const ouvrir = VueView.ui.modal.bind(VueView.ui);

VueView.ui.modal = (url, ...reste) => ouvrir(REMPLACEMENTS[url] || url, ...reste);

// L'éditeur de champs des fiches : les listes d'éléments se modifient sur place (champs.js), sans fenêtre.
VueView.component('fields-renderer', 'admincamping:assets/champs.js');
// Le champ image : une carte compacte, et l'envoi d'une photo par glisser-déposer (champ-image.js).
VueView.component('field-asset', 'admincamping:assets/champ-image.js');
