/* Site → administration : si l'administration a déposé son cookie « passerelle » (seulement dans le navigateur de
   la personne connectée), un avatar avec une pastille verte ouvre l'administration dans un nouvel onglet.
   Sans ce cookie — chez tous les visiteurs — rien ne se passe. Aucune adresse de l'administration n'est écrite ici. */
(function () {
    'use strict';

    var brut = (document.cookie.match(/(?:^|;\s*)passerelle=([^;]+)/) || [])[1];
    if (!brut) {
        return;
    }

    var infos;
    try {
        infos = JSON.parse(decodeURIComponent(escape(atob(brut.replace(/-/g, '+').replace(/_/g, '/')))));
    } catch (e) {
        return;
    }
    if (!infos || typeof infos.admin !== 'string' || !/^(https?:\/\/[^\s"<>]+|\/[^\s"<>]*)$/.test(infos.admin)) {
        return;
    }

    var nom = String(infos.nom || 'Administration');

    function afficher() {
        var style = document.createElement('link');
        style.rel = 'stylesheet';
        style.href = '/assets/css/passerelle.css';
        document.head.appendChild(style);

        var lien = document.createElement('a');
        lien.className = 'passerelle';
        lien.href = infos.admin;
        lien.target = '_blank';
        lien.rel = 'noopener';
        lien.setAttribute('aria-label', 'Connecté en tant que ' + nom + ' : ouvrir l’administration dans un nouvel onglet');

        var avatar = document.createElement('span');
        avatar.className = 'passerelle__avatar';
        avatar.setAttribute('aria-hidden', 'true');
        // La photo du compte (module Avatar) si elle existe, sinon l'initiale.
        if (typeof infos.photo === 'string' && /^(https?:\/\/[^\s"<>]+|\/[^\s"<>]*)\.webp$/.test(infos.photo)) {
            var photo = document.createElement('img');
            photo.className = 'passerelle__photo';
            photo.src = infos.photo;
            photo.alt = '';
            avatar.appendChild(photo);
        } else {
            avatar.textContent = nom.trim().charAt(0).toUpperCase();
        }

        var pastille = document.createElement('span');
        pastille.className = 'passerelle__pastille';
        pastille.setAttribute('aria-hidden', 'true');
        avatar.appendChild(pastille);

        var texte = document.createElement('span');
        texte.className = 'passerelle__texte';
        texte.setAttribute('aria-hidden', 'true');
        var titre = document.createElement('b');
        titre.textContent = nom;
        texte.appendChild(titre);
        texte.appendChild(document.createTextNode('Administration'));

        lien.appendChild(avatar);
        lien.appendChild(texte);
        document.body.appendChild(lien);
    }

    // Sur la même adresse que l'administration, on vérifie que la session est toujours ouverte. Ailleurs
    // (administration sur une autre origine), la vérification échoue sans bruit et le cookie fait foi.
    fetch(infos.admin.replace(/\/?$/, '/') + 'check-session', { credentials: 'include' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (session) {
            if (session && session.status === false) {
                document.cookie = 'passerelle=; path=/; max-age=0; samesite=lax';
                return;
            }
            afficher();
        })
        .catch(afficher);
}());
