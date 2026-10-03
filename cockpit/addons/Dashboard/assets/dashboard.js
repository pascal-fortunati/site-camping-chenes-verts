/**
 * Script commun à toutes les pages de l'administration : aiguillage des composants de Cockpit vers ceux du
 * Dashboard, notifications traduites, icônes des boutons, en-tête, barre latérale et actions masquées au client.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const racine = document.documentElement;

/* ── Aiguillage ──────────────────────────────────────────────────────────────────────────────────────────────── */

/** Fenêtres et morceaux de Cockpit remplacés : « fichier#export », l'export étant une fabrique de composant. */
const AIGUILLAGE = {
    'assets:assets/dialogs/asset-picker.js': 'dashboard:assets/vue/fenetres.js#choisirImage',
    'app:assets/dialog/app-search.js': 'dashboard:assets/vue/fenetres.js#recherche',
    'app:assets/vue-components/fields/richtext/dialogs/link.js': 'dashboard:assets/vue/fenetres.js#lienTexte',
    'app:assets/vue-components/fields/richtext/components/format.js': 'dashboard:assets/vue/fenetres.js#formatTexte',
};

/** Composants globaux remplacés par ceux de vue/champs.js. */
const CHAMPS = {
    'fields-renderer': 'editeur',
    'field-asset': 'image',
    'field-contentItemLink': 'lien',
    'field-select': 'choix',
    'field-date': 'date',
    'field-number': 'nombre',
    'field-color': 'couleur',
    'field-boolean': 'ouiNon',
};

const importer = App.utils.import.bind(App.utils);
const fabriques = new Map();

App.utils.import = (uri, ...reste) => {
    const cible = AIGUILLAGE[uri] || uri;
    const [fichier, nom] = cible.split('#');
    if (!nom) {
        return importer(fichier, ...reste);
    }
    if (!fabriques.has(cible)) {
        // __esModule : Vue.defineAsyncComponent prend alors « default » comme composant.
        fabriques.set(cible, importer(fichier).then(async (module) => ({ __esModule: true, default: await module[nom]() })));
    }
    return fabriques.get(cible);
};

Object.entries(CHAMPS).forEach(([composant, nom]) => VueView.component(composant, `dashboard:assets/vue/champs.js#${nom}`));

/* ── Notifications : Cockpit ne les fait pas passer par le dictionnaire ─────────────────────────────────────────── */

const notifier = App.ui.notify.bind(App.ui);
App.ui.notify = (message, ...reste) => notifier(typeof message === 'string' ? App.i18n.get(message) : message, ...reste);

/* ── Icônes des boutons, d'après leur libellé ─────────────────────────────────────────────────────────────────── */

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

/**
 * Ajoute une icône aux boutons qui n'en ont pas ; data-icone évite de traiter deux fois le même bouton.
 */
function iconer() {
    document.querySelectorAll('.kiss-button:not([data-icone])').forEach((bouton) => {
        bouton.dataset.icone = '1';
        if (bouton.querySelector('icon, img, svg')) return;
        const libelle = bouton.textContent.replace(/\s+/g, ' ').trim();
        const trouve = ICONES.find(([motif]) => motif.test(libelle));
        if (trouve) bouton.prepend(icone(trouve[1]));
    });
}

/**
 * @param {string} nom nom de l'icône Material
 * @returns {HTMLElement}
 */
function icone(nom) {
    const i = document.createElement('icon');
    i.setAttribute('aria-hidden', 'true');
    i.textContent = nom;
    return i;
}

/* ── Actions de structure masquées au client (affichage seulement : les droits du rôle font foi) ────────────── */

const MASQUE = 'dashboard-masque';

/**
 * @param {Element|null} element
 */
function masquer(element) {
    if (element && !element.classList.contains(MASQUE)) element.classList.add(MASQUE);
}

/**
 * Masque la modification des modèles, le JSON brut d'un élément et les menus « … » restés vides.
 */
function masquerActions() {
    document.querySelectorAll('a[href*="/content/models/"]').forEach((lien) => {
        const ligne = lien.closest('li');
        masquer(ligne || lien);
        const avant = ligne && ligne.previousElementSibling;
        if (avant && avant.classList.contains('kiss-nav-divider')) masquer(avant);
    });
    document.querySelectorAll('kiss-popout icon').forEach((i) => {
        if (i.textContent.trim() === 'manage_search') masquer(i.closest('li'));
    });
    document.querySelectorAll('[kiss-popout^="#"]').forEach((bouton) => {
        const menu = document.querySelector(bouton.getAttribute('kiss-popout'));
        if (!menu) return;
        const actions = [...menu.querySelectorAll('li')].filter((li) =>
            !li.classList.contains(MASQUE) && !li.classList.contains('kiss-nav-header') && !li.classList.contains('kiss-nav-divider'));
        if (!actions.length) masquer(bouton);
    });
}

const client = racine.classList.contains('dashboard-client');
const surveiller = () => {
    iconer();
    if (client) masquerActions();
};
new MutationObserver(surveiller).observe(racine, { childList: true, subtree: true });
surveiller();

/* ── En-tête : thème, recherche, menu du compte ───────────────────────────────────────────────────────────────── */

/**
 * Applique le thème et le garde dans un cookie, lu par le serveur à la page suivante.
 *
 * @param {boolean} sombre
 */
function choisirTheme(sombre) {
    racine.setAttribute('data-theme', sombre ? 'dark' : 'light');
    document.cookie = 'dashboard-theme=' + (sombre ? 'sombre' : 'clair') + '; path=/; max-age=31536000; samesite=lax';
    const bouton = document.querySelector('[data-entete-theme]');
    if (bouton) {
        bouton.setAttribute('aria-pressed', sombre ? 'true' : 'false');
        bouton.querySelector('icon').textContent = sombre ? 'light_mode' : 'dark_mode';
    }
}

window.Dashboard = { choisirTheme };

document.querySelector('[data-entete-theme]')?.addEventListener('click', () => choisirTheme(racine.getAttribute('data-theme') !== 'dark'));

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        const recherche = document.querySelector('.entete__recherche');
        if (recherche) {
            e.preventDefault();
            recherche.click();
        }
    }
});

const compte = document.querySelector('[data-entete-compte]');
const menuCompte = document.getElementById('app-account-menu');

if (compte && menuCompte && menuCompte.classList.contains('entete__menu')) {
    const liens = () => [...menuCompte.querySelectorAll('a, button')];
    const ouvrir = (oui) => {
        menuCompte.hidden = !oui;
        compte.setAttribute('aria-expanded', oui ? 'true' : 'false');
        if (oui) requestAnimationFrame(() => liens()[0]?.focus());
    };

    compte.addEventListener('click', (e) => {
        e.stopPropagation();
        ouvrir(menuCompte.hidden);
    });
    document.addEventListener('click', (e) => {
        if (!menuCompte.hidden && !menuCompte.contains(e.target)) ouvrir(false);
    });
    document.addEventListener('keydown', (e) => {
        if (menuCompte.hidden) return;
        if (e.key === 'Escape') {
            ouvrir(false);
            compte.focus();
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const l = liens();
            const i = l.indexOf(document.activeElement);
            l[(i + (e.key === 'ArrowDown' ? 1 : -1) + l.length) % l.length]?.focus();
        }
    });
    menuCompte.addEventListener('click', (e) => { if (e.target.closest('a')) ouvrir(false); });
}

/* ── Barre latérale : réduire / déplier, pastille des non lus ─────────────────────────────────────────────────── */

document.querySelector('.sidebar__basculer')?.addEventListener('click', (e) => {
    const bouton = e.currentTarget;
    const reduite = racine.classList.toggle('sidebar-reduite');
    racine.style.setProperty('--sidebar-l', reduite ? '72px' : '248px');
    document.cookie = 'dashboard-sidebar=' + (reduite ? 'reduite' : 'depliee') + '; path=/; max-age=31536000; samesite=lax';
    bouton.querySelector('icon').textContent = reduite ? 'left_panel_open' : 'left_panel_close';
    bouton.setAttribute('aria-label', reduite ? 'Déplier le menu' : 'Réduire le menu');
    bouton.setAttribute('aria-expanded', reduite ? 'false' : 'true');
});

const pastilles = () => [...document.querySelectorAll('[data-non-lus]')];

if (pastilles().length) {
    const titre = document.title.replace(/^\(\d+\)\s*/, '');
    const echapper = (t) => String(t || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    let connus = null;

    /**
     * Met à jour les pastilles et le titre de l'onglet, et annonce un message arrivé depuis le dernier passage.
     *
     * @param {Object<string, {nombre: number, dernier: ?{nom: string, lien: string}}>} donnees par modèle
     */
    const afficher = (donnees) => {
        let total = 0;
        pastilles().forEach((p) => {
            const n = (donnees[p.dataset.nonLus] || {}).nombre || 0;
            total += n;
            p.textContent = n;
            p.hidden = n === 0;
            p.setAttribute('aria-label', `${n} non lu${n > 1 ? 's' : ''}`);
        });
        document.title = (total ? `(${total}) ` : '') + titre;

        Object.entries(donnees).forEach(([modele, d]) => {
            const avant = connus && connus[modele] ? connus[modele].nombre : null;
            if (avant !== null && d.nombre > avant && d.dernier) {
                const nom = echapper(d.dernier.nom);
                App.ui.notify(`Nouveau message${nom ? ' de ' + nom : ''} : <a href="${encodeURI(d.dernier.lien)}">le lire</a>`, 'info', { timeout: 8000 });
            }
        });
        connus = donnees;
    };

    const demander = () => {
        if (document.hidden) return;
        fetch(App.route('/dashboard/non-lus'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => { if (d) afficher(d); })
            .catch(() => {});
    };

    demander();
    setInterval(demander, 30000);
    document.addEventListener('visibilitychange', demander);
}
