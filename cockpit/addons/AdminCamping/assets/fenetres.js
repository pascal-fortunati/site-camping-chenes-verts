/**
 * Les fenêtres de Cockpit remplacées par les nôtres, mêmes réglages et même retour : le choix d'une image
 * (champ image, texte riche, lien) ouvre choisir-image.js.
 */

const REMPLACEMENTS = {
    'assets:assets/dialogs/asset-picker.js': 'admincamping:assets/choisir-image.js',
};

const ouvrir = VueView.ui.modal.bind(VueView.ui);

VueView.ui.modal = (url, ...reste) => ouvrir(REMPLACEMENTS[url] || url, ...reste);
