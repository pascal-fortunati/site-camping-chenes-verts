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
