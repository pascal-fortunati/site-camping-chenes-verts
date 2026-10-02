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

    // Ordinateur : une bulle verte glisse sous l'entrée survolée et revient sur la page en cours.
    var menu = entete.querySelector('.menu');
    var liens = menu ? Array.prototype.slice.call(menu.querySelectorAll('.menu__liste a:not([href="/reserver"])')) : [];
    var large = window.matchMedia('(min-width: 80rem)');

    if (!menu || !liens.length) {
        return;
    }

    var bulle = document.createElement('span');
    bulle.className = 'menu__bulle';
    bulle.setAttribute('aria-hidden', 'true');
    menu.appendChild(bulle);
    menu.classList.add('menu--bulle');

    var enCours = menu.querySelector('.menu__liste a[aria-current="page"]:not([href="/reserver"])');

    function placer(lien) {
        liens.forEach(function (l) { l.classList.toggle('sur-bulle', l === lien); });
        menu.classList.toggle('menu--bulle-visible', !!lien);
        if (!lien || !large.matches) {
            return;
        }
        var m = menu.getBoundingClientRect();
        var r = lien.getBoundingClientRect();
        bulle.style.setProperty('--x', (r.left - m.left) + 'px');
        bulle.style.setProperty('--y', (r.top - m.top) + 'px');
        bulle.style.setProperty('--l', r.width + 'px');
        bulle.style.setProperty('--h', r.height + 'px');
    }

    liens.forEach(function (l) {
        l.addEventListener('mouseenter', function () { placer(l); });
        l.addEventListener('focus', function () { placer(l); });
    });
    menu.addEventListener('mouseleave', function () { placer(enCours); });
    menu.addEventListener('focusout', function (e) {
        if (!menu.contains(e.relatedTarget)) {
            placer(enCours);
        }
    });

    // Première position sans glissement, puis à chaque changement de taille.
    function recaler() {
        menu.classList.add('menu--sans-transition');
        placer(enCours);
        bulle.getBoundingClientRect();
        menu.classList.remove('menu--sans-transition');
    }
    window.addEventListener('resize', recaler);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(recaler);
    }
    recaler();
}());
