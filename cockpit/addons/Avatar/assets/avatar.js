/**
 * Shows the account photos in place of Cockpit's initials, and adds « Mon avatar » to the account menu.
 *
 * An element is changed only once (data-avatar), so the MutationObserver never
 * wakes itself up endlessly.
 */

let photos = {};

function remplacer() {
    document.querySelectorAll('app-avatar:not([data-avatar])').forEach((avatar) => {
        const url = photos[(avatar.getAttribute('name') || '').trim()];
        if (!url) return;
        const taille = parseInt(avatar.getAttribute('size') || '40', 10);
        const img = document.createElement('img');
        img.className = 'avatar-photo';
        img.src = url;
        img.alt = '';
        img.width = taille;
        img.height = taille;
        avatar.dataset.avatar = '1';
        avatar.classList.add('avatar-cache');
        avatar.after(img);
    });

    const menu = document.querySelector('#app-account-menu kiss-navlist ul');
    if (menu && !menu.querySelector('[data-avatar-menu]')) {
        const compte = [...menu.querySelectorAll('li')].find((li) => li.querySelector('a[href*="/system/users/user"]'));
        const ligne = document.createElement('li');
        ligne.dataset.avatarMenu = '1';
        ligne.innerHTML = '<a class="kiss-flex kiss-flex-middle" href="#"><icon class="kiss-margin-small-end">face</icon> Mon avatar</a>';
        ligne.querySelector('a').addEventListener('click', (e) => {
            e.preventDefault();
            VueView.ui.modal('avatar:assets/dialog-avatar.js', { actuel: photos[App.user?.name] || null });
        });
        (compte || menu.firstElementChild).after(ligne);
    }

    // On the login page nobody is signed in yet, so the list is empty: once the account card appears (signed
    // in, just before the redirect), it is asked again, a few times at most.
    if (!charge && essais < 3 && document.querySelector('app-avatar:not([data-avatar])')) {
        charger();
    }
}

let charge = false;
let essais = 0;
let demande = null;

function charger() {
    if (demande) return demande;
    essais++;
    demande = fetch(App.route('/avatar/liste'), { credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
            const comptes = (data && data.comptes) || [];
            // Signed out, the list comes back empty: it is not taken as loaded.
            charge = comptes.length > 0;
            comptes.forEach((c) => { photos[c.nom] = c.url; });
        })
        .catch(() => {})
        .finally(() => {
            demande = null;
            remplacer();
        });
    return demande;
}

charger().finally(() => {
    new MutationObserver(remplacer).observe(document.documentElement, { childList: true, subtree: true });
});
