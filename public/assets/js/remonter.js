/* Le gland « Revenir en haut » : visible une fois la page descendue ; ses feuilles bougent
   comme sous le vent, dans un sens quand on descend, dans l'autre quand on remonte.
   Sans JavaScript, le lien reste visible en bas de page et ramène en haut. */
(function () {
    'use strict';

    var gland = document.querySelector('.remonter');

    if (!gland) {
        return;
    }

    gland.classList.add('remonter--js');

    var dernier = window.scrollY;
    var repos = 0;

    function souffler(sens) {
        gland.classList.remove('remonter--vent-bas', 'remonter--vent-haut');
        void gland.offsetWidth;                         // relance l'animation
        gland.classList.add(sens > 0 ? 'remonter--vent-bas' : 'remonter--vent-haut');
    }

    window.addEventListener('scroll', function () {
        var y = window.scrollY;
        var sens = y - dernier;
        dernier = y;

        gland.classList.toggle('remonter--visible', y > 500);

        // Une rafale au plus toutes les 0,7 s, sinon les feuilles trembleraient sans cesse.
        var maintenant = Date.now();
        if (Math.abs(sens) > 2 && maintenant - repos > 700) {
            repos = maintenant;
            souffler(sens);
        }
    }, { passive: true });

    // Le retour en haut se fait en douceur (scroll-behavior), puis le focus repart du début de la page.
    gland.addEventListener('click', function () {
        souffler(-1);
    });
}());
