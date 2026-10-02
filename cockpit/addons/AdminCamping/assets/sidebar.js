/**
 * La barre latérale : le serveur l'envoie déjà construite (barre-laterale.php) ; ce script ne fait que brancher le
 * bouton qui la réduit ou la déplie. Le choix est gardé dans un cookie, lu par le serveur à la page suivante.
 * Si le serveur n'a pas pu la construire (balisage de Cockpit changé), elle est construite ici, comme avant.
 */

const racine = document.documentElement;

const ICONES = [
    [/\/content/, 'article'],
    [/\/assets/, 'photo_library'],
    [/\/finder/, 'folder_open'],
    [/\/system\/api/, 'key'],
    [/\/system\/users/, 'group'],
    [/\/system/, 'tune'],
];

function icone(nom) {
    const i = document.createElement('icon');
    i.setAttribute('aria-hidden', 'true');
    i.textContent = nom;
    return i;
}

function brancher(bouton) {
    if (!bouton || bouton.dataset.branche) return;
    bouton.dataset.branche = '1';
    bouton.addEventListener('click', () => {
        const reduite = racine.classList.toggle('sidebar-reduite');
        racine.style.setProperty('--sidebar-l', reduite ? '72px' : '248px');
        document.cookie = 'admincamping-sidebar=' + (reduite ? 'reduite' : 'depliee') + '; path=/; max-age=31536000; samesite=lax';
        bouton.querySelector('icon').textContent = reduite ? 'left_panel_open' : 'left_panel_close';
        bouton.setAttribute('aria-label', reduite ? 'Déplier le menu' : 'Réduire le menu');
        bouton.setAttribute('aria-expanded', reduite ? 'false' : 'true');
    });
}

/** Secours : la même barre, construite dans le navigateur. */
function construire(menu) {
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

    const reduite = racine.classList.contains('sidebar-reduite');
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'sidebar__basculer';
    bouton.append(icone(reduite ? 'left_panel_open' : 'left_panel_close'));
    const texte = document.createElement('span');
    texte.className = 'sidebar__libelle';
    texte.textContent = 'Réduire le menu';
    bouton.append(texte);
    bouton.setAttribute('aria-label', reduite ? 'Déplier le menu' : 'Réduire le menu');
    bouton.setAttribute('aria-expanded', reduite ? 'false' : 'true');
    menu.append(bouton);
}

function preparer() {
    const menu = document.querySelector('.app-container-aside-menu');
    if (!menu) return;
    if (!menu.dataset.sidebar) {
        menu.dataset.sidebar = 'navigateur';
        if (/(?:^|;\s*)admincamping-sidebar=reduite/.test(document.cookie)) racine.classList.add('sidebar-reduite');
        construire(menu);
    }
    brancher(menu.querySelector('.sidebar__basculer'));
}

preparer();
new MutationObserver(preparer).observe(document.documentElement, { childList: true, subtree: true });

// La pastille des non lus (messages reçus) suit l'arrivée des messages sans recharger la page : on demande au
// serveur toutes les 30 secondes, et dès qu'on revient sur l'onglet. Un nouveau message est annoncé.
const pastilles = () => [...document.querySelectorAll('[data-non-lus]')];
if (pastilles().length) {
    const titre = document.title.replace(/^\(\d+\)\s*/, '');
    let connus = null;

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

        // Un message de plus qu'au dernier passage : on le dit.
        Object.entries(donnees).forEach(([modele, d]) => {
            const avant = connus && connus[modele] ? connus[modele].nombre : null;
            if (avant !== null && d.nombre > avant && d.dernier) {
                const nom = String(d.dernier.nom || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);   // vient du formulaire du site
                App.ui.notify(`Nouveau message${nom ? ' de ' + nom : ''} : <a href="${encodeURI(d.dernier.lien)}">le lire</a>`, 'info', { timeout: 8000 });
            }
        });
        connus = donnees;
    };

    const demander = () => {
        if (document.hidden) return;
        fetch(App.route('/admincamping/non-lus'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => { if (d) afficher(d); })
            .catch(() => {});
    };

    demander();
    setInterval(demander, 30000);
    document.addEventListener('visibilitychange', demander);
}
