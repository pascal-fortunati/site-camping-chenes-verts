/* Menu repliable au téléphone — Camping Les Chênes Verts.
   Sans JavaScript, rien ne se passe : le menu reste déplié et utilisable.
   Avec JavaScript, le menu est replié et le bouton « Menu » l'ouvre ou le ferme.
   À l'ordinateur (80rem et plus), client.css affiche le menu en ligne et masque le bouton. */
(function () {
    'use strict';

    var entete = document.querySelector('.en-tete');
    var bouton = entete && entete.querySelector('.menu__bouton');

    if (!bouton) {
        return;
    }

    var texte = bouton.querySelector('.menu__bouton-texte');

    entete.classList.add('js', 'menu-replie');

    function replier(oui) {
        entete.classList.toggle('menu-replie', oui);
        bouton.setAttribute('aria-expanded', oui ? 'false' : 'true');
        if (texte) {
            texte.textContent = oui ? 'Menu' : 'Fermer';
        }
    }

    bouton.addEventListener('click', function () {
        replier(!entete.classList.contains('menu-replie'));
    });

    // Échap referme le menu et rend le focus au bouton.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !entete.classList.contains('menu-replie')) {
            replier(true);
            bouton.focus();
        }
    });

    // Une ombre sous l'en-tête dès que la page défile.
    function ombre() {
        entete.classList.toggle('en-tete--defile', window.scrollY > 8);
    }
    window.addEventListener('scroll', ombre, { passive: true });
    ombre();
}());
