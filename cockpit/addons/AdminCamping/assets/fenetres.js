/**
 * Les fenêtres de Cockpit remplacées par les nôtres, mêmes réglages et même retour : le choix d'une image
 * (champ image, texte riche, lien) ouvre choisir-image.js, la recherche (Ctrl K) recherche.js.
 */

const REMPLACEMENTS = {
    'assets:assets/dialogs/asset-picker.js': 'admincamping:assets/choisir-image.js',
    'app:assets/dialog/app-search.js': 'admincamping:assets/recherche.js',
    'app:assets/vue-components/fields/richtext/dialogs/link.js': 'admincamping:assets/lien-texte.js',
};

const ouvrir = VueView.ui.modal.bind(VueView.ui);

VueView.ui.modal = (url, ...reste) => ouvrir(REMPLACEMENTS[url] || url, ...reste);

// L'éditeur de champs des fiches : les listes d'éléments se modifient sur place (champs.js), sans fenêtre.
VueView.component('fields-renderer', 'admincamping:assets/champs.js');
// Le champ image : une carte compacte, et l'envoi d'une photo par glisser-déposer (champ-image.js).
VueView.component('field-asset', 'admincamping:assets/champ-image.js');
// Le lien vers un contenu (la page d'une entrée du menu) : une liste déroulante avec recherche (champ-lien.js).
VueView.component('field-contentItemLink', 'admincamping:assets/champ-lien.js');

// Les morceaux que Cockpit charge lui-même (App.utils.import) : le menu « format » du texte riche (format-texte.js).
const MORCEAUX = {
    'app:assets/vue-components/fields/richtext/components/format.js': 'admincamping:assets/format-texte.js',
};
const importer = App.utils.import.bind(App.utils);
App.utils.import = (uri, ...reste) => importer(MORCEAUX[uri] || uri, ...reste);
// La liste de choix (réseau social, type de section…) : la même liste déroulante que le choix d'une page (champ-choix.js).
VueView.component('field-select', 'admincamping:assets/champ-choix.js');
// La date : un calendrier aux couleurs du site, ouvert par tout le champ (champ-date.js).
VueView.component('field-date', 'admincamping:assets/champ-date.js');
// Le nombre : des boutons − et + plutôt que les flèches du navigateur (champ-nombre.js).
VueView.component('field-number', 'admincamping:assets/champ-nombre.js');
// La couleur : une palette lisible et un curseur de clarté, aux couleurs du site (champ-couleur.js).
VueView.component('field-color', 'admincamping:assets/champ-couleur.js');
// Le oui / non : un interrupteur clair, « Oui » ou « Non » écrit à côté (champ-oui-non.js).
VueView.component('field-boolean', 'admincamping:assets/champ-oui-non.js');
