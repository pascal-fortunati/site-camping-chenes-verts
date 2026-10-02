/**
 * L'en-tête : le bouton qui passe du thème clair au sombre (gardé dans un cookie, lu par le serveur à la page
 * suivante) et le raccourci Ctrl K qui ouvre la recherche.
 */

const racine = document.documentElement;
const bouton = document.querySelector('[data-entete-theme]');

if (bouton) {
    bouton.addEventListener('click', () => {
        const sombre = racine.getAttribute('data-theme') !== 'dark';
        racine.setAttribute('data-theme', sombre ? 'dark' : 'light');
        document.cookie = 'admincamping-theme=' + (sombre ? 'sombre' : 'clair') + '; path=/; max-age=31536000; samesite=lax';
        bouton.setAttribute('aria-pressed', sombre ? 'true' : 'false');
        bouton.querySelector('icon').textContent = sombre ? 'light_mode' : 'dark_mode';
    });
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        const recherche = document.querySelector('.entete__recherche');
        if (recherche) {
            e.preventDefault();
            recherche.click();
        }
    }
});

// Le menu du compte, sous l'avatar : s'ouvre au clic, se ferme au clic ailleurs ou avec Échap.
const compte = document.querySelector('[data-entete-compte]');
const menu = document.getElementById('app-account-menu');

if (compte && menu && menu.classList.contains('entete__menu')) {
    const liens = () => [...menu.querySelectorAll('a, button')];
    const ouvrir = (oui) => {
        menu.hidden = !oui;
        compte.setAttribute('aria-expanded', oui ? 'true' : 'false');
        if (oui) requestAnimationFrame(() => liens()[0]?.focus());
    };

    compte.addEventListener('click', (e) => {
        e.stopPropagation();
        ouvrir(menu.hidden);
    });
    document.addEventListener('click', (e) => {
        if (!menu.hidden && !menu.contains(e.target)) ouvrir(false);
    });
    document.addEventListener('keydown', (e) => {
        if (menu.hidden) return;
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
    // « Mon avatar » ouvre sa fenêtre : le menu se referme derrière.
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) ouvrir(false); });
}
