<?php

/**
 * Barre latérale, menu du téléphone et en-tête, écrits dans la page avant son envoi : rien ne bouge au chargement.
 * Si le balisage de Cockpit (modules/App/layouts/app.php) change, la partie concernée est laissée telle quelle.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @param  string   $html   la page rendue par Cockpit
 * @param  bool     $sombre thème sombre choisi
 * @return string           la page retouchée
 */
return function (Lime\App $app, string $html, bool $sombre): string {

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $route = rtrim((string) $app->request->route, '/');
    $compteSeul = (bool) $app->retrieve('dashboard.compteSeul');
    $compte = $app->routeUrl('/system/users/user');
    $reduite = ($_COOKIE['dashboard-sidebar'] ?? '') === 'reduite';

    $credit = '<a class="sidebar__credit" href="https://github.com/pascal-fortunati" target="_blank" rel="noopener" aria-label="Codé avec amour par Pascal Fortunati (GitHub)" kiss-tooltip="right">'
        .'<span class="sidebar__coeur" aria-hidden="true">♥</span>'
        .'<span class="sidebar__libelle">Codé avec <span class="sidebar__coeur">♥</span> par Pascal Fortunati</span></a>';

    /**
     * Une entrée de la barre ou du tiroir.
     *
     * @param  string $href
     * @param  string $libelle
     * @param  string $icone   nom de l'icône Material
     * @param  bool   $actif   page courante
     * @param  string $badge   pastille HTML, facultative
     * @return string
     */
    $entree = static function (string $href, string $libelle, string $icone, bool $actif, string $badge = '') use ($e): string {
        return '<li'.($actif ? ' class="active"' : '').'><a href="'.$e($href).'" aria-label="'.$e($libelle).'" kiss-tooltip="right"'.($actif ? ' aria-current="page"' : '').'>'
            .'<icon aria-hidden="true">'.$icone.'</icon><span class="sidebar__libelle">'.$e($libelle).'</span>'.$badge.'</a></li>';
    };

    /* ── Les modèles lisibles, en deux groupes : « Au quotidien » et « Le site » ── */

    $acl = $app->helper('acl');
    $presenter = include __DIR__.'/modeles.php';
    $groupes = ['quotidien' => [], 'site' => []];
    $modeles = $app->module('content')->models();
    uasort($modeles, static fn (array $a, array $b): int => $presenter($a)['ordre'] <=> $presenter($b)['ordre']);

    foreach ($modeles as $nom => $m) {
        if (!$acl->isAllowed("content/{$nom}/read")) {
            continue;
        }
        $singleton = $m['type'] === 'singleton';
        $href = $app->routeUrl($singleton ? "/content/singleton/item/{$nom}" : "/content/{$m['type']}/items/{$nom}");
        $actif = (bool) preg_match('#^/content/(singleton|collection|tree)/(item|items|clone)/'.preg_quote($nom, '#').'(/|$)#', $route);
        $badge = '';
        // Collection avec une case « lu » : pastille des non lus, tenue à jour par dashboard.js.
        if (!$singleton && in_array('lu', array_column(array_filter($m['fields'] ?? [], static fn (array $c): bool => ($c['type'] ?? '') === 'boolean'), 'name'), true)) {
            $nonLus = count(array_filter($app->module('content')->items($nom, ['fields' => ['lu' => 1]]), static fn (array $i): bool => empty($i['lu'])));
            $badge = '<span class="sidebar__badge" data-non-lus="'.$e($nom).'"'.($nonLus ? '' : ' hidden').' aria-label="'.$nonLus.' non lu'.($nonLus > 1 ? 's' : '').'">'.$nonLus.'</span>';
        }
        $vue = $presenter($m);
        $groupes[$vue['groupe']][] = $entree($href, $vue['libelle'], $vue['icone'], $actif, $badge);
    }
    if ($acl->isAllowed('assets/upload') || $acl->isAllowed('assets/edit') || $acl->isSuperAdmin()) {
        $groupes['quotidien'][] = $entree($app->routeUrl('/assets'), 'Médias', 'perm_media', str_starts_with($route, '/assets'));
    }
    if ($acl->isAllowed('content/:models/manage')) {
        $groupes['site'][] = $entree($app->routeUrl('/content'), 'Modèles de contenu', 'schema', $route === '/content' || str_starts_with($route, '/content/models'));
    }
    $titre = static fn (string $t): string => '<li class="sidebar__groupe" aria-hidden="true"><span class="sidebar__libelle">'.$t.'</span></li>';
    $bloc = ($groupes['quotidien'] ? $titre('Au quotidien').implode('', $groupes['quotidien']) : '')
        .($groupes['site'] ? $titre('Le site').implode('', $groupes['site']) : '');

    $marque = '<a class="sidebar__marque" href="'.$e($app->routeUrl('/')).'">'
        .'<img src="'.$e($app->helper('theme')->logo()).'" alt="" width="36" height="36" decoding="sync">'
        .'<span class="sidebar__libelle sidebar__nom">'.$e($app['app.name']).'</span></a>';

    /* ── Barre latérale ── */

    $debut = strpos($html, '<div class="app-container-aside-menu">');
    $fin = $debut === false ? false : strpos($html, '<div class="app-container-aside-panel">', $debut);
    $finAside = $debut === false ? false : strpos($html, '</aside>', $debut);
    $fin = ($fin !== false && $fin < $finAside) ? $fin : $finAside;

    if ($debut !== false && $fin !== false) {
        $menu = substr($html, $debut, $fin - $debut);
        $accueil = rtrim($app->routeUrl('/'), '/');
        $reglages = rtrim($app->routeUrl('/system'), '/');
        $icones = [
            '#/content#' => 'article',
            '#/assets#' => 'photo_library',
            '#/finder#' => 'folder_open',
            '#/system/api#' => 'key',
            '#/system/users#' => 'group',
            '#/system#' => 'tune',
        ];

        // La recherche est dans l'en-tête : son entrée et le filet qui la précède sont retirés.
        $menu = preg_replace('#<li class="kiss-nav-divider"></li>\s*<li>\s*<a\b[^>]*\bapp-search\b.*?</li>#s', '', $menu);

        $menu = preg_replace_callback('#<a\b([^>]*\baria-label="([^"]*)"[^>]*)>(.*?)</a>#s', static function (array $m) use ($app, $accueil, $icones, $e, $compteSeul, $reglages, $compte): string {
            [, $attributs, $libelle, $contenu] = $m;
            preg_match('#\bhref="([^"]*)"#', $attributs, $h);
            $href = $h[1] ?? '';

            if ($compteSeul && rtrim($href, '/') === $reglages) {
                $attributs = str_replace(['href="'.$href.'"', 'aria-label="'.$libelle.'"'], ['href="'.$e($compte).'"', 'aria-label="Mon compte"'], $attributs);
                $ici = str_starts_with((string) $app->request->route, '/system/users/user') ? ' aria-current="page"' : '';
                return '<a'.str_replace('kiss-flex-center', '', $attributs).$ici.'><icon aria-hidden="true">account_circle</icon><span class="sidebar__libelle">Mon compte</span></a>';
            }

            $nom = null;
            if ($href !== '' && rtrim($href, '/') === $accueil) {
                $nom = 'space_dashboard';
            } else {
                foreach ($icones as $motif => $icone) {
                    if (preg_match($motif, $href)) {
                        $nom = $icone;
                        break;
                    }
                }
            }
            $icone = $nom !== null
                ? '<icon aria-hidden="true">'.$nom.'</icon>'
                : (trim(preg_replace('#<kiss-svg\b.*?</kiss-svg>#s', '', $contenu)) ?: $contenu);
            $texte = preg_replace('#\s*\(.*\)$#', '', html_entity_decode($libelle, ENT_QUOTES, 'UTF-8'));

            return '<a'.str_replace('kiss-flex-center', '', $attributs).'>'.$icone.'<span class="sidebar__libelle">'.$e($texte).'</span></a>';
        }, $menu);

        // « Contenu » laisse la place aux groupes ; « Médias » est déjà dans le bloc, son lien d'origine est retiré d'abord.
        $lien = static fn (string $href): string => '#(?:<li class="kiss-nav-divider"></li>\s*)?<li\b[^>]*>\s*<a\b[^>]*\bhref="'.preg_quote($e($href), '#').'"[^>]*>.*?</a>\s*</li>#s';
        if ($bloc !== '' && preg_match($lien($app->routeUrl('/content')), $menu)) {
            $menu = preg_replace($lien($app->routeUrl('/assets')), '', $menu, 1);
            $menu = preg_replace($lien($app->routeUrl('/content')), $bloc, $menu, 1);
        }

        $bouton = '<button type="button" class="sidebar__basculer" aria-expanded="'.($reduite ? 'false' : 'true').'" aria-label="'
            .($reduite ? 'Déplier le menu' : 'Réduire le menu').'"><icon aria-hidden="true">'.($reduite ? 'left_panel_open' : 'left_panel_close')
            .'</icon><span class="sidebar__libelle">Réduire le menu</span></button>';

        $menu = preg_replace('#^<div class="app-container-aside-menu">#', '<div class="app-container-aside-menu" data-sidebar="serveur">'.$marque, $menu, 1);
        $dernier = strrpos($menu, '</div>');
        if ($dernier !== false) {
            $menu = substr($menu, 0, $dernier).$bouton.$credit.substr($menu, $dernier);
        }
        $html = substr($html, 0, $debut).$menu.substr($html, $fin);
    }

    /* ── Menu du téléphone : les mêmes entrées, en tiroir ── */

    if ($bloc !== '' && preg_match('#<kiss-offcanvas id="app-offcanvas">.*?</kiss-offcanvas>#s', $html, $t, PREG_OFFSET_CAPTURE)) {
        $user = $app->helper('auth')->getUser() ?? [];
        $tiroir = '<kiss-offcanvas id="app-offcanvas"><kiss-content class="tiroir">'
            .'<div class="tiroir__tete">'.$marque.'<button type="button" class="tiroir__fermer" kiss-offcanvas-close aria-label="Fermer le menu"><icon aria-hidden="true">close</icon></button></div>'
            .'<kiss-navlist class="tiroir__nav"><ul>'
            .$entree($app->routeUrl('/'), 'Tableau de bord', 'space_dashboard', $route === '')
            .$bloc
            .'</ul></kiss-navlist>'
            .'<kiss-navlist class="tiroir__nav tiroir__bas"><ul>'
            .$entree($compte, 'Mon compte', 'account_circle', str_starts_with($route, '/system/users/user'))
            .'<li><a class="tiroir__sortie" href="'.$e($app->routeUrl('/auth/logout')).'"><icon aria-hidden="true">logout</icon><span class="sidebar__libelle">Se déconnecter</span></a></li>'
            .'</ul></kiss-navlist>'
            .'<p class="tiroir__compte">'.$e($user['name'] ?? '').'<small>'.$e($user['email'] ?? '').'</small></p>'
            .$credit
            .'</kiss-content></kiss-offcanvas>';
        $html = substr($html, 0, $t[0][1]).$tiroir.substr($html, $t[0][1] + strlen($t[0][0]));
    }

    // dashboard-client : dashboard.js masque alors les actions sur la structure du site.
    $classes = trim(($reduite ? 'sidebar-reduite ' : '').((($app->helper('auth')->getUser()['role'] ?? '') !== 'admin') ? 'dashboard-client' : ''));
    if ($classes !== '') {
        $html = preg_replace('#(<html\b[^>]*?\bclass=")#', '$1'.$classes.' ', $html, 1);
    }

    /* ── En-tête : recherche, « Voir le site », thème, compte ── */

    if (!preg_match('#(<app-header\b[^>]*>\s*<kiss-container\b[^>]*>)(.*?)(</kiss-container>\s*</app-header>)#s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    [$ouverture, $contenu, $fermeture] = [$m[1][0], $m[2][0], $m[3][0]];

    $morceau = static fn (string $motif): string => preg_match($motif, $contenu, $t) ? $t[0] : '';
    $logo = $morceau('#<a\b[^>]*>\s*<img class="app-logo\b.*?</a>#s');
    $boutonTiroir = $morceau('#<a\b[^>]*href="\#app-offcanvas"[^>]*>.*?</a>#s') !== ''
        ? '<a class="entete__tiroir kiss-hidden@m" href="#app-offcanvas" kiss-offcanvas aria-label="Menu"><icon aria-hidden="true">menu</icon></a>'
        : '';
    $lienCompte = $morceau('#<a\b[^>]*href="\#app-account-menu"[^>]*>.*?</a>#s');
    $nom = $morceau('#<span class="kiss-text-bold">.*?</span>#s');
    if ($lienCompte === '' || $nom === '') {
        return $html;
    }

    // Ajouts de Cockpit ou d'autres extensions (licence, bloc app.layout.header) gardés.
    $ajouts = '';
    if (preg_match('#<app-license>.*?</app-license>#s', $contenu, $t)) {
        $ajouts .= $t[0];
    }
    if (preg_match('#<div class="kiss-flex-1 kiss-margin-start"></div>(.*?)<a\b[^>]*href="\#app-account-menu"#s', $contenu, $t)) {
        $ajouts .= trim($t[1]);
    }

    $theme = '<button type="button" class="entete__theme" data-entete-theme aria-pressed="'.($sombre ? 'true' : 'false').'" aria-label="Thème sombre" kiss-tooltip="bottom">'
        .'<icon aria-hidden="true">'.($sombre ? 'light_mode' : 'dark_mode').'</icon></button>';

    // Photo du compte (extension Avatar) posée dès le serveur.
    $user = $app->helper('auth')->getUser() ?? [];
    $avatar = preg_match('#<app-avatar\b.*?</app-avatar>#s', $lienCompte, $t) ? $t[0] : '';
    $chemin = !empty($user['_id']) ? (string) ($app->dataStorage->findOne('system/users', ['_id' => $user['_id']], ['avatar' => 1])['avatar'] ?? '') : '';
    $photo = static fn (string $html, int $taille): string => $html;
    if (str_starts_with($chemin, '/avatars/')) {
        $url = rtrim((string) $app->fileStorage->getURL('uploads://'), '/').$chemin;
        $photo = static fn (string $html, int $taille): string => str_replace('<app-avatar ', '<app-avatar data-avatar="1" class="avatar-cache" ', $html)
            .'<img class="avatar-photo" src="'.$e($url).'" alt="" width="'.$taille.'" height="'.$taille.'">';
    }
    $grandAvatar = $photo(preg_replace('#size="\d+"#', 'size="44"', $avatar), 44);
    $avatar = $photo($avatar, 30);

    // Boutons de l'extension Passerelle écrits ici : passerelle-admin.js les trouve et ne les ajoute pas.
    $passerelle = '';
    if (isset($app['modules']['passerelle'])) {
        $env = (string) @file_get_contents(dirname(__DIR__, 5).'/.env');
        $site = preg_match('/^SITE_URL=(.*)$/m', $env, $t) && trim($t[1]) !== '' ? rtrim(trim($t[1], " \t\r\"'"), '/') : rtrim((string) $app->getSiteUrl(false), '/');
        $lienSite = static fn (string $texte, string $href, string $icone): string => '<a class="kiss-button kiss-button-small passerelle-bouton" href="'.$e($href).'" target="_blank" rel="noopener">'
            .'<icon class="kiss-margin-xsmall-end">'.$icone.'</icon>'.$texte.'</a>';
        $passerelle = $lienSite('Voir le site', $site.'/', 'open_in_new');
        if (preg_match('#^/content/collection/item/pages/([0-9a-f]{24})#', (string) $app->request->route, $t) && $acl->isAllowed('content/pages/read')) {
            $page = $app->module('content')->item('pages', ['_id' => $t[1]]);
            if (!empty($page['slug'])) {
                $accueil = preg_match('/^HOME_PAGE_SLUG=(.*)$/m', $env, $h) ? trim($h[1], " \t\r\"'") : 'accueil';
                $passerelle .= $lienSite('Voir cette page', $site.($page['slug'] === ($accueil ?: 'accueil') ? '/' : '/'.$page['slug']), 'visibility');
            }
        }
        $passerelle = '<div class="kiss-flex kiss-flex-middle entete__passerelle">'.$passerelle.'</div>';
    }

    // Menu du compte sous l'avatar ; il garde l'identifiant #app-account-menu, où Avatar ajoute « Mon avatar ».
    $menuCompte = '<div class="entete__compte-zone">'
        .'<button type="button" class="entete__compte" data-entete-compte aria-haspopup="true" aria-expanded="false" aria-controls="app-account-menu" aria-label="Mon compte">'.$avatar.'</button>'
        .'<div id="app-account-menu" class="entete__menu" hidden>'
        .'<div class="entete__menu-tete">'.$grandAvatar.'<span><b>'.$e($user['name'] ?? '').'</b><small>'.$e($user['email'] ?? '').'</small></span></div>'
        .'<kiss-navlist><ul>'
        .'<li><a href="'.$e($compte).'"><icon aria-hidden="true">account_circle</icon>Mon compte</a></li>'
        .($compteSeul ? '' : '<li><a href="'.$e($app->routeUrl('/system')).'"><icon aria-hidden="true">tune</icon>Réglages</a></li>')
        .'<li class="entete__menu-filet"></li>'
        .'<li><a class="entete__menu-sortie" href="'.$e($app->routeUrl('/auth/logout')).'"><icon aria-hidden="true">logout</icon>Se déconnecter</a></li>'
        .'</ul></kiss-navlist>'
        .'</div></div>';

    $nouveau = '<div class="entete__gauche">'.$logo.$boutonTiroir.'</div>'
        .'<div class="entete__droite">'.$ajouts
        .'<button type="button" class="entete__recherche" app-search aria-label="Rechercher (Ctrl K)" kiss-tooltip="bottom">'
        .'<icon aria-hidden="true">search</icon><kbd>Ctrl K</kbd></button>'
        .'<div class="entete__site">'.str_replace('class="kiss-text-bold"', 'class="kiss-text-bold entete__nom"', $nom).'</div>'
        .$passerelle
        .$theme
        .($avatar !== '' ? $menuCompte : str_replace('class="kiss-margin-start"', 'class="entete__compte"', $lienCompte))
        .'</div>';

    $ouverture = str_replace('<kiss-container', '<kiss-container data-entete="serveur"', $ouverture);
    $html = substr($html, 0, $m[0][1]).$ouverture.$nouveau.$fermeture.substr($html, $m[0][1] + strlen($m[0][0]));

    if ($avatar !== '') {
        $html = preg_replace('#<kiss-popout id="app-account-menu">.*?</kiss-popout>#s', '', $html, 1);
    }

    return $html;
};
