<?php

/**
 * Tableau de bord : bandeau d'accueil, indicateurs, raccourcis, dernières demandes et dernières modifications.
 * Il s'appuie sur les modèles du socle (pages, messages) ; le propre au site vient de cockpit/dashboard.php :
 *
 *     return [
 *         'phrase'      => fn (Lime\App $app, Closure $jour): string,   // sous le bonjour
 *         'indicateurs' => fn (Lime\App $app, Closure $page): array,    // ajoutés en tête : icone, valeur, libelle, detail, lien, ton
 *         'raccourcis'  => fn (Lime\App $app, Closure $page): array,    // icone, titre, texte, lien
 *     ];
 *
 * $jour(timestamp) écrit « 3 octobre » ; $page(slug) donne le lien de la fiche d'une page. Le détail d'une demande
 * suit la clé « resume » du modèle messages (lib/modeles.php).
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @return string   le HTML du tableau de bord
 */
return function (Lime\App $app): string {

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $route = static fn (string $chemin): string => $app->routeUrl($chemin);
    $content = $app->module('content');
    $acl = $app->helper('acl');
    $modeles = $content->models();
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $jour = static fn (int $t): string => date('j', $t).(date('j', $t) === '1' ? 'er' : '').' '.$mois[(int) date('n', $t) - 1];
    $maintenant = time();

    $fichier = dirname(__DIR__, 5).'/cockpit/dashboard.php';
    $reglage = is_file($fichier) ? (include $fichier) : [];
    $reglage = is_array($reglage) ? $reglage : [];

    $lirePages = isset($modeles['pages']) && $acl->isAllowed('content/pages/read');
    $pages = $lirePages ? $content->items('pages', ['fields' => ['titre' => 1, 'slug' => 1, '_state' => 1, '_modified' => 1, '_id' => 1], 'sort' => ['_modified' => -1]]) : [];
    $idPage = array_column($pages, '_id', 'slug');
    $page = static fn (string $slug): string => isset($idPage[$slug]) ? $route('/content/collection/item/pages/'.$idPage[$slug]) : $route('/content/collection/items/pages');

    $lireMessages = isset($modeles['messages']) && $acl->isAllowed('content/messages/read');
    $messages = $lireMessages ? $content->items('messages', ['sort' => ['_created' => -1]]) : [];
    $nonLus = count(array_filter($messages, static fn (array $m): bool => empty($m['lu'])));

    /* ── Bandeau ── */

    $user = $app->helper('auth')->getUser() ?? [];
    $salut = (int) date('G') < 18 ? 'Bonjour' : 'Bonsoir';
    $prenom = trim((string) ($user['name'] ?? '')) ?: 'à vous';
    $phrase = isset($reglage['phrase']) ? (string) $reglage['phrase']($app, $jour) : 'Que souhaitez-vous modifier aujourd’hui ?';

    /* ── Indicateurs ── */

    $tuile = static fn (array $t): string => '<a class="tdb-indicateur '.$e($t['ton'] ?? '').'" href="'.$e($t['lien']).'">'
        .'<span class="tdb-indicateur__icone"><icon>'.$e($t['icone']).'</icon></span>'
        .'<span class="tdb-indicateur__valeur">'.$e($t['valeur']).'</span><span class="tdb-indicateur__libelle">'.$e($t['libelle']).'</span>'
        .'<span class="tdb-indicateur__detail">'.$e($t['detail']).'</span></a>';

    $indicateurs = isset($reglage['indicateurs']) ? $reglage['indicateurs']($app, $page) : [];
    if ($lireMessages) {
        $indicateurs[] = ['icone' => 'mark_email_unread', 'valeur' => $nonLus, 'libelle' => $nonLus > 1 ? 'Demandes non lues' : 'Demande non lue',
            'detail' => count($messages).' reçue'.(count($messages) > 1 ? 's' : '').' au total', 'lien' => $route('/content/collection/items/messages'), 'ton' => $nonLus ? 'tdb--alerte' : ''];
    }
    if ($lirePages) {
        $enLigne = count(array_filter($pages, static fn (array $p): bool => ($p['_state'] ?? 0) === 1));
        $horsLigne = count($pages) - $enLigne;
        $indicateurs[] = ['icone' => 'web', 'valeur' => $enLigne, 'libelle' => 'Pages en ligne',
            'detail' => $horsLigne > 0 ? $horsLigne.' hors ligne (brouillons, modèles)' : 'Toutes publiées', 'lien' => $route('/content/collection/items/pages')];
    }
    $indicateurs[] = ['icone' => 'perm_media', 'valeur' => $app->dataStorage->count('assets'), 'libelle' => 'Médias', 'detail' => 'Photos et documents du site', 'lien' => $route('/assets')];

    /* ── Raccourcis ── */

    $raccourci = static fn (array $r): string => '<a class="tdb-raccourci" href="'.$e($r['lien']).'"'.(!empty($r['externe']) ? ' target="_blank" rel="noopener"' : '').'>'
        .'<span class="tdb-raccourci__icone"><icon>'.$e($r['icone']).'</icon></span>'
        .'<span><b>'.$e($r['titre']).'</b><span>'.$e($r['texte']).'</span></span>'
        .'<icon class="tdb-raccourci__fleche">'.(!empty($r['externe']) ? 'open_in_new' : 'arrow_forward').'</icon></a>';

    $raccourcis = isset($reglage['raccourcis']) ? $reglage['raccourcis']($app, $page) : array_values(array_filter([
        $lirePages ? ['icone' => 'web', 'titre' => 'Les pages', 'texte' => 'Textes et photos du site', 'lien' => $route('/content/collection/items/pages')] : null,
        isset($modeles['settings']) && $acl->isAllowed('content/settings/read') ? ['icone' => 'badge', 'titre' => 'Identité du site', 'texte' => 'Coordonnées, logo, couleurs', 'lien' => $route('/content/singleton/item/settings')] : null,
    ]));
    $env = (string) @file_get_contents(dirname(__DIR__, 5).'/.env');
    if (preg_match('/^SITE_URL=(.*)$/m', $env, $m) && ($site = rtrim(trim($m[1], " \t\r\"'"), '/')) !== '') {
        $raccourcis[] = ['icone' => 'travel_explore', 'titre' => 'Voir le site', 'texte' => 'Dans un nouvel onglet', 'lien' => $site.'/', 'externe' => true];
    }

    /* ── Dernières demandes ── */

    $resume = $lireMessages ? (include __DIR__.'/modeles.php')($modeles['messages'])['resume'] : [];
    $detailDemande = static fn (array $m): string => implode(' · ', array_filter(array_map(static function (string $morceau) use ($m): string {
        $vide = false;
        $texte = preg_replace_callback('/\{(\w+)\}/', static function (array $c) use ($m, &$vide): string {
            $v = $m[$c[1]] ?? '';
            $vide = $vide || $v === '' || $v === null;
            return is_scalar($v) ? (string) $v : '';
        }, $morceau);
        return $vide ? '' : (string) $texte;
    }, $resume))) ?: (string) ($m['email'] ?? '');
    $demandes = '';
    foreach (array_slice($messages, 0, 5) as $m) {
        $demandes .= '<a class="tdb-ligne" href="'.$route('/content/collection/item/messages/'.$m['_id']).'">'
            .'<span class="tdb-ligne__avatar">'.$e(mb_strtoupper(mb_substr(trim((string) ($m['nom'] ?? '?')), 0, 1))).'</span>'
            .'<span class="tdb-ligne__texte"><b>'.$e($m['nom'] ?? 'Sans nom').'</b><span>'.$e($detailDemande($m) ?: 'Message').'</span></span>'
            .(empty($m['lu']) ? '<span class="tdb-pastille">Nouveau</span>' : '<span class="tdb-ligne__date">'.$e($m['envoyeLe'] ?? '').'</span>')
            .'</a>';
    }
    if ($lireMessages && $demandes === '') {
        $demandes = '<p class="tdb-vide"><icon>inbox</icon>Aucune demande pour l’instant. Elles arriveront ici, et par e-mail.</p>';
    }

    /* ── Dernières modifications (pages en ligne) ── */

    $modifs = '';
    foreach (array_slice(array_values(array_filter($pages, static fn (array $p): bool => ($p['_state'] ?? 0) === 1)), 0, 5) as $p) {
        $il = $maintenant - (int) ($p['_modified'] ?? 0);
        $quand = $il < 3600 ? 'il y a '.max(1, intdiv($il, 60)).' min' : ($il < 86400 ? 'il y a '.intdiv($il, 3600).' h' : 'le '.$jour((int) $p['_modified']));
        $modifs .= '<a class="tdb-ligne" href="'.$route('/content/collection/item/pages/'.$p['_id']).'">'
            .'<span class="tdb-ligne__avatar tdb-ligne__avatar--page"><icon>description</icon></span>'
            .'<span class="tdb-ligne__texte"><b>'.$e($p['titre'] ?? '').'</b><span>/'.$e(($p['slug'] ?? '') === 'accueil' ? '' : ($p['slug'] ?? '')).'</span></span>'
            .'<span class="tdb-ligne__date">'.$e($quand).'</span></a>';
    }

    $semaine = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    return '<div class="dashboard-tableau">'
        .'<section class="tdb-accueil">'
        .'<p class="tdb-accueil__date">'.$e(ucfirst($semaine[(int) date('w')]).' '.$jour($maintenant).' '.date('Y')).'</p>'
        .'<h1 class="tdb-accueil__titre">'.$e($salut).' '.$e($prenom).'</h1>'
        .'<p class="tdb-accueil__phrase">'.$e($phrase).'</p>'
        .'</section>'
        .'<section class="tdb-indicateurs">'.implode('', array_map($tuile, array_slice($indicateurs, 0, 4))).'</section>'
        .'<div class="tdb-grille">'
        .'<section class="tdb-carte tdb-carte--large"><h2><icon>bolt</icon>Les gestes du quotidien</h2><div class="tdb-raccourcis">'.implode('', array_map($raccourci, $raccourcis)).'</div></section>'
        .($lireMessages ? '<section class="tdb-carte"><h2><icon>forum</icon>Dernières demandes</h2>'.$demandes
            .'<a class="tdb-tout" href="'.$route('/content/collection/items/messages').'">Toutes les demandes <icon>arrow_forward</icon></a></section>' : '')
        .($lirePages ? '<section class="tdb-carte"><h2><icon>history</icon>Dernières modifications</h2>'.$modifs
            .'<a class="tdb-tout" href="'.$route('/content/collection/items/pages').'">Toutes les pages <icon>arrow_forward</icon></a></section>' : '')
        .'</div></div>';
};
