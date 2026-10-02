<?php

/**
 * L'en-tête rendu par le serveur, à droite : la recherche (Ctrl K), « Voir le site » (Passerelle), le choix du
 * thème clair ou sombre et le compte. Le nom du site reste dans la page, masqué à l'œil : Passerelle place ses
 * boutons juste après lui. Si Cockpit change ce balisage, l'en-tête d'origine est gardé tel quel.
 *
 * @return string le HTML retouché, ou tel quel
 */
return function (Lime\App $app, string $html, bool $sombre): string {

    if (!preg_match('#(<app-header\b[^>]*>\s*<kiss-container\b[^>]*>)(.*?)(</kiss-container>\s*</app-header>)#s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    [$ouverture, $contenu, $fermeture] = [$m[1][0], $m[2][0], $m[3][0]];

    $morceau = static fn (string $motif): string => preg_match($motif, $contenu, $t) ? $t[0] : '';
    $logo = $morceau('#<a\b[^>]*>\s*<img class="app-logo\b.*?</a>#s');
    $tiroir = $morceau('#<a\b[^>]*href="\#app-offcanvas"[^>]*>.*?</a>#s') !== ''
        ? '<a class="entete__tiroir kiss-hidden@m" href="#app-offcanvas" kiss-offcanvas aria-label="Menu"><icon aria-hidden="true">menu</icon></a>'
        : '';
    $compte = $morceau('#<a\b[^>]*href="\#app-account-menu"[^>]*>.*?</a>#s');
    $nom = $morceau('#<span class="kiss-text-bold">.*?</span>#s');
    if ($compte === '' || $nom === '') {
        return $html;
    }

    // Ce que Cockpit ou d'autres extensions ajoutent (licence, espace, bloc app.layout.header) est gardé.
    $ajouts = '';
    if (preg_match('#<app-license>.*?</app-license>#s', $contenu, $t)) {
        $ajouts .= $t[0];
    }
    if (preg_match('#<div class="kiss-flex-1 kiss-margin-start"></div>(.*?)<a\b[^>]*href="\#app-account-menu"#s', $contenu, $t)) {
        $ajouts .= trim($t[1]);
    }

    $theme = '<button type="button" class="entete__theme" data-entete-theme aria-pressed="'.($sombre ? 'true' : 'false').'" aria-label="Thème sombre" kiss-tooltip="bottom">'
        .'<icon aria-hidden="true">'.($sombre ? 'light_mode' : 'dark_mode').'</icon></button>';

    // Le compte : un menu qui se déroule sous l'avatar, à la place de la fenêtre de Cockpit. Il garde son identifiant
    // et sa liste (#app-account-menu kiss-navlist ul) : l'extension Avatar y ajoute « Mon avatar ».
    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $user = $app->helper('auth')->getUser() ?? [];
    $avatar = preg_match('#<app-avatar\b.*?</app-avatar>#s', $compte, $t) ? $t[0] : '';

    // La photo du compte (extension Avatar) est mise dès le serveur : rien ne bouge quand la page arrive.
    $chemin = !empty($user['_id']) ? (string) ($app->dataStorage->findOne('system/users', ['_id' => $user['_id']], ['avatar' => 1])['avatar'] ?? '') : '';
    $photo = static fn (string $html, int $taille): string => $html;
    if (str_starts_with($chemin, '/avatars/')) {
        $url = rtrim((string) $app->fileStorage->getURL('uploads://'), '/').$chemin;
        $photo = static fn (string $html, int $taille): string => str_replace('<app-avatar ', '<app-avatar data-avatar="1" class="avatar-cache" ', $html)
            .'<img class="avatar-photo" src="'.$e($url).'" alt="" width="'.$taille.'" height="'.$taille.'">';
    }
    $grandAvatar = $photo(preg_replace('#size="\d+"#', 'size="44"', $avatar), 44);
    $avatar = $photo($avatar, 30);

    // « Voir le site » et, sur une page, « Voir cette page » (extension Passerelle) : écrits ici plutôt qu'ajoutés
    // après coup, ce qui décalait tout l'en-tête à chaque page. passerelle-admin.js voit qu'ils sont là et s'arrête.
    $passerelle = '';
    if (isset($app['modules']['passerelle'])) {
        $env = (string) @file_get_contents(dirname(__DIR__, 4).'/.env');
        $site = preg_match('/^SITE_URL=(.*)$/m', $env, $t) && trim($t[1]) !== '' ? rtrim(trim($t[1], " \t\r\"'"), '/') : rtrim((string) $app->getSiteUrl(false), '/');
        $bouton = static fn (string $texte, string $href, string $icone): string => '<a class="kiss-button kiss-button-small passerelle-bouton" href="'.$e($href).'" target="_blank" rel="noopener">'
            .'<icon class="kiss-margin-xsmall-end">'.$icone.'</icon>'.$texte.'</a>';
        $passerelle = $bouton('Voir le site', $site.'/', 'open_in_new');
        if (preg_match('#^/content/collection/item/pages/([0-9a-f]{24})#', (string) $app->request->route, $t) && $app->helper('acl')->isAllowed('content/pages/read')) {
            $page = $app->module('content')->item('pages', ['_id' => $t[1]]);
            if (!empty($page['slug'])) {
                $accueil = preg_match('/^HOME_PAGE_SLUG=(.*)$/m', $env, $h) ? trim($h[1], " \t\r\"'") : 'accueil';
                $passerelle .= $bouton('Voir cette page', $site.($page['slug'] === ($accueil ?: 'accueil') ? '/' : '/'.$page['slug']), 'visibility');
            }
        }
        $passerelle = '<div class="kiss-flex kiss-flex-middle entete__passerelle">'.$passerelle.'</div>';
    }
    $reglages = $app->retrieve('admincamping.compteSeul') ? ''
        : '<li><a href="'.$e($app->routeUrl('/system')).'"><icon aria-hidden="true">tune</icon>Réglages</a></li>';
    $menu = '<div class="entete__compte-zone">'
        .'<button type="button" class="entete__compte" data-entete-compte aria-haspopup="true" aria-expanded="false" aria-controls="app-account-menu" aria-label="Mon compte">'.$avatar.'</button>'
        .'<div id="app-account-menu" class="entete__menu" hidden>'
        .'<div class="entete__menu-tete">'.$grandAvatar.'<span><b>'.$e($user['name'] ?? '').'</b><small>'.$e($user['email'] ?? '').'</small></span></div>'
        .'<kiss-navlist><ul>'
        .'<li><a href="'.$e($app->routeUrl('/system/users/user')).'"><icon aria-hidden="true">account_circle</icon>Mon compte</a></li>'
        .$reglages
        .'<li class="entete__menu-filet"></li>'
        .'<li><a class="entete__menu-sortie" href="'.$e($app->routeUrl('/auth/logout')).'"><icon aria-hidden="true">logout</icon>Se déconnecter</a></li>'
        .'</ul></kiss-navlist>'
        .'</div></div>';

    $nouveau = '<div class="entete__gauche">'.$logo.$tiroir.'</div>'
        .'<div class="entete__droite">'.$ajouts
        .'<button type="button" class="entete__recherche" app-search aria-label="Rechercher (Ctrl K)" kiss-tooltip="bottom">'
        .'<icon aria-hidden="true">search</icon><kbd>Ctrl K</kbd></button>'
        .'<div class="entete__site">'.str_replace('class="kiss-text-bold"', 'class="kiss-text-bold entete__nom"', $nom).'</div>'
        .$passerelle
        .$theme
        .($avatar !== '' ? $menu : str_replace('class="kiss-margin-start"', 'class="entete__compte"', $compte))
        .'</div>';

    $ouverture = str_replace('<kiss-container', '<kiss-container data-entete="serveur"', $ouverture);

    $html = substr($html, 0, $m[0][1]).$ouverture.$nouveau.$fermeture.substr($html, $m[0][1] + strlen($m[0][0]));

    // La fenêtre d'origine du compte n'a plus de raison d'être (et garderait le même identifiant).
    if ($avatar !== '') {
        $html = preg_replace('#<kiss-popout id="app-account-menu">.*?</kiss-popout>#s', '', $html, 1);
    }

    return $html;
};
