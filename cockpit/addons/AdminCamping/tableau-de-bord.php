<?php

/**
 * CAMPING LES CHÊNES VERTS — le tableau de bord du client, à la place de celui de Cockpit : un bandeau d'accueil
 * sur la photo du site, quatre indicateurs, les gestes du quotidien, les dernières demandes et modifications.
 * Construit côté serveur à chaque ouverture ; rien n'y est saisi.
 *
 * @return string le HTML du tableau de bord
 */
return function (Lime\App $app): string {

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $route = static fn (string $chemin): string => $app->routeUrl($chemin);
    $content = $app->module('content');
    $acl = $app->helper('acl');
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $jour = static fn (int $t): string => date('j', $t).(date('j', $t) === '1' ? 'er' : '').' '.$mois[(int) date('n', $t) - 1];

    $user = $app->helper('auth')->getUser() ?? [];
    $identite = $content->item('settings') ?? [];
    $saison = $content->item('saison') ?? [];
    $medias = rtrim((string) $app->fileStorage->getURL('uploads://'), '/');
    $photo = !empty($identite['imagePartage']['path']) ? $medias.$identite['imagePartage']['path'] : '';

    // ── La saison, en une phrase ──
    $maintenant = time();
    $ouverture = !empty($saison['saisonOuverture']) ? strtotime($saison['saisonOuverture']) : null;
    $fermeture = !empty($saison['saisonFermeture']) ? strtotime($saison['saisonFermeture'].' 23:59') : null;
    if ($ouverture && $maintenant < $ouverture) {
        $jours = (int) ceil(($ouverture - $maintenant) / 86400);
        $phrase = "La saison ouvre le {$jour($ouverture)}, dans {$jours} jour".($jours > 1 ? 's' : '').'.';
    } elseif ($fermeture && $maintenant <= $fermeture) {
        $phrase = "Le camping est ouvert jusqu’au {$jour($fermeture)}.";
    } else {
        $phrase = 'Pensez à indiquer les dates de la prochaine saison.';
    }
    $heure = (int) date('G');
    $salut = $heure < 18 ? 'Bonjour' : 'Bonsoir';
    $prenom = trim((string) ($user['name'] ?? '')) ?: 'à vous';

    // ── Les indicateurs ──
    $places = (int) ($saison['placesDisponibles'] ?? 0);
    $lireMessages = $acl->isAllowed('content/messages/read');
    $messages = $lireMessages ? $content->items('messages', ['sort' => ['_created' => -1]]) : [];
    $nonLus = count(array_filter($messages, static fn (array $m): bool => empty($m['lu'])));
    $pages = $content->items('pages', ['fields' => ['titre' => 1, 'slug' => 1, '_state' => 1, '_modified' => 1, '_id' => 1], 'sort' => ['_modified' => -1]]);
    $enLigne = count(array_filter($pages, static fn (array $p): bool => ($p['_state'] ?? 0) === 1));
    $images = $app->dataStorage->count('assets');
    $idPage = [];
    foreach ($pages as $p) {
        $idPage[$p['slug'] ?? ''] = $p['_id'];
    }
    $page = static fn (string $slug): string => isset($idPage[$slug]) ? $route('/content/collection/item/pages/'.$idPage[$slug]) : $route('/content/collection/items/pages');

    $tuile = static fn (string $icone, string $valeur, string $libelle, string $detail, string $lien, string $ton = '') =>
        "<a class=\"tdb-indicateur {$ton}\" href=\"{$lien}\"><span class=\"tdb-indicateur__icone\"><icon>{$icone}</icon></span>"
        ."<span class=\"tdb-indicateur__valeur\">{$valeur}</span><span class=\"tdb-indicateur__libelle\">{$libelle}</span>"
        ."<span class=\"tdb-indicateur__detail\">{$detail}</span></a>";

    $indicateurs = $tuile('event_available', (string) $places, 'Places restantes', $places > 0 ? 'Affiché sur le site' : 'Rien d’affiché sur le site', $route('/content/singleton/item/saison'), 'tdb--vert')
        .($lireMessages ? $tuile('mark_email_unread', (string) $nonLus, $nonLus > 1 ? 'Demandes non lues' : 'Demande non lue', count($messages).' reçue'.(count($messages) > 1 ? 's' : '').' au total', $route('/content/collection/items/messages'), $nonLus ? 'tdb--alerte' : '') : '')
        .$tuile('web', (string) $enLigne, 'Pages en ligne', (count($pages) - $enLigne) > 0 ? (count($pages) - $enLigne).' hors ligne (brouillons, modèles)' : 'Toutes publiées', $route('/content/collection/items/pages'))
        .$tuile('photo_library', (string) $images, 'Images', 'Photos du site et cartes', $route('/assets'));

    // ── Les gestes du quotidien ──
    $raccourci = static fn (string $icone, string $titre, string $texte, string $lien, bool $externe = false) =>
        "<a class=\"tdb-raccourci\" href=\"{$lien}\"".($externe ? ' target="_blank" rel="noopener"' : '')."><span class=\"tdb-raccourci__icone\"><icon>{$icone}</icon></span>"
        ."<span><b>{$titre}</b><span>{$texte}</span></span><icon class=\"tdb-raccourci__fleche\">".($externe ? 'open_in_new' : 'arrow_forward')."</icon></a>";

    $site = '';
    $env = (string) @file_get_contents(dirname(__DIR__, 4).'/.env');
    if (preg_match('/^SITE_URL=(.*)$/m', $env, $m)) {
        $site = rtrim(trim($m[1], " \t\"'"), '/');
    }

    $raccourcis = $raccourci('event_seat', 'Places et dates', 'Places restantes, ouverture, horaires', $route('/content/singleton/item/saison'))
        .$raccourci('euro', 'Les tarifs', 'Prix par nuit, suppléments', $page('tarifs'))
        .$raccourci('restaurant_menu', 'Menu du snack', 'Le plat du jour, les prix', $page('snack'))
        .$raccourci('add_photo_alternate', 'Photo d’accueil', 'La grande image de la page d’accueil', $page('accueil'))
        .$raccourci('badge', 'Identité du site', 'Coordonnées, logo, couleurs', $route('/content/singleton/item/settings'))
        .($site !== '' ? $raccourci('travel_explore', 'Voir le site', 'Dans un nouvel onglet', $e($site).'/', true) : '');

    // ── Dernières demandes ──
    $demandes = '';
    foreach (array_slice($messages, 0, 5) as $m) {
        $sejour = trim(($m['arrivee'] ?? '').(!empty($m['depart']) ? ' → '.$m['depart'] : ''));
        $details = array_filter([$sejour, $m['hebergement'] ?? '', !empty($m['personnes']) ? $m['personnes'].' pers.' : '']);
        $demandes .= '<a class="tdb-ligne" href="'.$route('/content/collection/item/messages/'.$m['_id']).'">'
            .'<span class="tdb-ligne__avatar">'.$e(mb_strtoupper(mb_substr(trim((string) ($m['nom'] ?? '?')), 0, 1))).'</span>'
            .'<span class="tdb-ligne__texte"><b>'.$e($m['nom'] ?? 'Sans nom').'</b><span>'.$e(implode(' · ', $details) ?: 'Message').'</span></span>'
            .(empty($m['lu']) ? '<span class="tdb-pastille">Nouveau</span>' : '<span class="tdb-ligne__date">'.$e($m['envoyeLe'] ?? '').'</span>')
            .'</a>';
    }
    if ($lireMessages && $demandes === '') {
        $demandes = '<p class="tdb-vide"><icon>inbox</icon>Aucune demande pour l’instant. Elles arriveront ici, et par e-mail.</p>';
    }

    // ── Dernières modifications ──
    $modifs = '';
    foreach (array_slice(array_values(array_filter($pages, static fn (array $p): bool => ($p['_state'] ?? 0) === 1)), 0, 5) as $p) {
        $il = $maintenant - (int) ($p['_modified'] ?? 0);
        $quand = $il < 3600 ? 'il y a '.max(1, intdiv($il, 60)).' min' : ($il < 86400 ? 'il y a '.intdiv($il, 3600).' h' : 'le '.$jour((int) $p['_modified']));
        $modifs .= '<a class="tdb-ligne" href="'.$route('/content/collection/item/pages/'.$p['_id']).'">'
            .'<span class="tdb-ligne__avatar tdb-ligne__avatar--page"><icon>description</icon></span>'
            .'<span class="tdb-ligne__texte"><b>'.$e($p['titre'] ?? '').'</b><span>/'.$e(($p['slug'] ?? '') === 'accueil' ? '' : ($p['slug'] ?? '')).'</span></span>'
            .'<span class="tdb-ligne__date">'.$e($quand).'</span></a>';
    }

    $fond = $photo !== '' ? ' style="background-image: linear-gradient(110deg, rgb(24 58 35 / 92%) 0%, rgb(24 58 35 / 70%) 45%, rgb(24 58 35 / 15%) 100%), url(\''.$e($photo).'\')"' : '';

    return '<div class="camping-tableau">'
        .'<section class="tdb-accueil"'.$fond.'>'
        .'<p class="tdb-accueil__date">'.$e(ucfirst(['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w')]).' '.$jour($maintenant).' '.date('Y')).'</p>'
        .'<h1 class="tdb-accueil__titre">'.$e($salut).' '.$e($prenom).'</h1>'
        .'<p class="tdb-accueil__phrase">'.$e($phrase).'</p>'
        .'</section>'
        .'<section class="tdb-indicateurs">'.$indicateurs.'</section>'
        .'<div class="tdb-grille">'
        .'<section class="tdb-carte tdb-carte--large"><h2><icon>bolt</icon>Les gestes du quotidien</h2><div class="tdb-raccourcis">'.$raccourcis.'</div></section>'
        .($lireMessages ? '<section class="tdb-carte"><h2><icon>forum</icon>Dernières demandes</h2>'.$demandes
            .'<a class="tdb-tout" href="'.$route('/content/collection/items/messages').'">Toutes les demandes <icon>arrow_forward</icon></a></section>' : '')
        .'<section class="tdb-carte"><h2><icon>history</icon>Dernières modifications</h2>'.$modifs
        .'<a class="tdb-tout" href="'.$route('/content/collection/items/pages').'">Toutes les pages <icon>arrow_forward</icon></a></section>'
        .'</div></div>';
};
