/**
 * Une vraie barre latérale : le logo et le nom du site, chaque entrée avec son icône Material et son libellé,
 * un bouton pour la réduire aux seules icônes. Le choix est gardé dans un cookie, que le serveur lit pour
 * charger tout de suite la bonne largeur (sidebar-reduite.css) : la page ne se décale pas au chargement.
 */

const ICONES = [
    [/\/content/, 'article'],
    [/\/assets/, 'photo_library'],
    [/\/finder/, 'folder_open'],
    [/\/system\/api/, 'key'],
    [/\/system\/users/, 'group'],
    [/\/system/, 'tune'],
];

const racine = document.documentElement;
if (/(?:^|;\s*)admincamping-sidebar=reduite/.test(document.cookie)) racine.classList.add('sidebar-reduite');

function icone(nom) {
    const i = document.createElement('icon');
    i.setAttribute('aria-hidden', 'true');
    i.textContent = nom;
    return i;
}

function construire() {
    const menu = document.querySelector('.app-container-aside-menu');
    if (!menu || menu.dataset.sidebar) return;
    menu.dataset.sidebar = '1';
    racine.classList.add('avec-sidebar');

    // En tête : le logo et le nom du site, repris de l'en-tête de Cockpit.
    const logo = document.querySelector('app-header .app-logo');
    const nom = document.querySelector('app-header .kiss-text-bold');
    const tete = document.createElement('a');
    tete.className = 'sidebar__marque';
    tete.href = App.route('/');
    if (logo) {
        const img = document.createElement('img');
        img.src = logo.src;
        img.alt = '';
        tete.append(img);
    }
    const titre = document.createElement('span');
    titre.className = 'sidebar__libelle sidebar__nom';
    titre.textContent = nom ? nom.textContent.trim() : 'Administration';
    tete.append(titre);
    menu.prepend(tete);

    // Chaque entrée : une icône Material (les SVG de Cockpit manquent de contraste sur le vert) et un libellé.
    menu.querySelectorAll('kiss-navlist a[aria-label]').forEach((lien) => {
        const href = lien.getAttribute('href') || '';
        let nomIcone = null;
        if (lien.hasAttribute('app-search')) nomIcone = 'search';
        else if (href && new URL(href, location.href).pathname.replace(/\/+$/, '') === new URL(App.route('/'), location.href).pathname.replace(/\/+$/, '')) nomIcone = 'space_dashboard';
        else {
            const trouve = ICONES.find(([motif]) => motif.test(href));
            if (trouve) nomIcone = trouve[1];
        }

        const svg = lien.querySelector('kiss-svg');
        if (nomIcone) {
            if (svg) svg.replaceWith(icone(nomIcone));
            else if (!lien.querySelector('icon')) lien.prepend(icone(nomIcone));
        }
        lien.classList.remove('kiss-flex-center');
        const libelle = document.createElement('span');
        libelle.className = 'sidebar__libelle';
        libelle.textContent = lien.getAttribute('aria-label').replace(/\s*\(.*\)$/, '');
        lien.append(libelle);
    });

    // En bas : le bouton qui réduit ou déplie la barre.
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'sidebar__basculer';
    const maj = () => {
        const reduite = racine.classList.contains('sidebar-reduite');
        bouton.replaceChildren(icone(reduite ? 'left_panel_open' : 'left_panel_close'));
        const texte = document.createElement('span');
        texte.className = 'sidebar__libelle';
        texte.textContent = 'Réduire le menu';
        bouton.append(texte);
        bouton.setAttribute('aria-label', reduite ? 'Déplier le menu' : 'Réduire le menu');
        bouton.setAttribute('aria-expanded', reduite ? 'false' : 'true');
    };
    bouton.addEventListener('click', () => {
        const reduite = racine.classList.toggle('sidebar-reduite');
        racine.style.setProperty('--sidebar-l', reduite ? '72px' : '248px');
        document.cookie = 'admincamping-sidebar=' + (reduite ? 'reduite' : 'depliee') + '; path=/; max-age=31536000; samesite=lax';
        maj();
    });
    maj();
    menu.append(bouton);
}

construire();
new MutationObserver(construire).observe(document.documentElement, { childList: true, subtree: true });
