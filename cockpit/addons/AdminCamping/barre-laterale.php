<?php

/**
 * CAMPING LES CHÊNES VERTS — la barre latérale rendue par le serveur : la page envoyée contient déjà la marque, les
 * icônes Material, les libellés et l'état réduit ou déplié. Rien ne bouge au chargement d'une page.
 *
 * La mise en page de Cockpit (modules/App/layouts/app.php) n'est pas modifiée : son HTML est retouché juste avant
 * l'envoi. Si Cockpit change ce balisage, la retouche ne trouve rien et sidebar.js construit la barre comme avant.
 *
 * @return string le HTML retouché, ou tel quel
 */
return function (Lime\App $app, string $html): string {

    $debut = strpos($html, '<div class="app-container-aside-menu">');
    if ($debut === false) {
        return $html;
    }
    $fin = strpos($html, '<div class="app-container-aside-panel">', $debut);
    $finAside = strpos($html, '</aside>', $debut);
    $fin = ($fin !== false && $fin < $finAside) ? $fin : $finAside;
    if ($fin === false) {
        return $html;
    }

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $accueil = rtrim($app->routeUrl('/'), '/');
    $icones = [
        '#/content#' => 'article',
        '#/assets#' => 'photo_library',
        '#/finder#' => 'folder_open',
        '#/system/api#' => 'key',
        '#/system/users#' => 'group',
        '#/system#' => 'tune',
    ];

    $menu = substr($html, $debut, $fin - $debut);

    // La recherche est dans l'en-tête (Ctrl K) : son entrée de la barre, et le filet qui la précède, feraient doublon.
    $menu = preg_replace('#<li class="kiss-nav-divider"></li>\s*<li>\s*<a\b[^>]*\bapp-search\b.*?</li>#s', '', $menu);
    $compteSeul = (bool) $app->retrieve('admincamping.compteSeul');
    $reglages = rtrim($app->routeUrl('/system'), '/');
    $compte = $app->routeUrl('/system/users/user');

    // Chaque lien : une icône Material à la place du SVG de Cockpit, et son libellé.
    $menu = preg_replace_callback('#<a\b([^>]*\baria-label="([^"]*)"[^>]*)>(.*?)</a>#s', static function (array $m) use ($app, $accueil, $icones, $e, $compteSeul, $reglages, $compte): string {
        [$tout, $attributs, $libelle, $contenu] = $m;
        preg_match('#\bhref="([^"]*)"#', $attributs, $h);
        $href = $h[1] ?? '';

        // « Réglages » ne contiendrait que le compte : le lien y mène directement.
        if ($compteSeul && rtrim($href, '/') === $reglages) {
            $attributs = str_replace(['href="'.$href.'"', 'aria-label="'.$libelle.'"'], ['href="'.$e($compte).'"', 'aria-label="Mon compte"'], $attributs);
            $ici = str_starts_with((string) $app->request->route, '/system/users/user') ? ' aria-current="page"' : '';
            return '<a'.str_replace('kiss-flex-center', '', $attributs).$ici.'><icon aria-hidden="true">account_circle</icon><span class="sidebar__libelle">Mon compte</span></a>';
        }

        $nom = null;
        if (str_contains($attributs, 'app-search')) {
            $nom = 'search';
        } elseif ($href !== '' && rtrim($href, '/') === $accueil) {
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
        $attributs = str_replace('kiss-flex-center', '', $attributs);

        return '<a'.$attributs.'>'.$icone.'<span class="sidebar__libelle">'.$e($texte).'</span></a>';
    }, $menu);

    // « Contenu » et « Images et fichiers » (Cockpit) laissent la place à des accès directs, en deux groupes : ce qu'on change
    // souvent (pages, messages, saison, médias) et les réglages du site (identité, menu, mentions). Chaque entrée
    // vient d'un modèle que la personne peut lire ; son nom, son icône et son groupe sont réglés dans le modèle
    // (clé « admin », voir modeles.php), sinon déduits de son type.
    $route = rtrim((string) $app->request->route, '/');
    $acl = $app->helper('acl');
    $presenter = include __DIR__.'/modeles.php';

    $entree = static function (string $href, string $libelle, string $icone, bool $actif, string $badge = '') use ($e): string {
        return '<li'.($actif ? ' class="active"' : '').'><a href="'.$e($href).'" aria-label="'.$e($libelle).'" kiss-tooltip="right"'.($actif ? ' aria-current="page"' : '').'>'
            .'<icon aria-hidden="true">'.$icone.'</icon><span class="sidebar__libelle">'.$e($libelle).'</span>'.$badge.'</a></li>';
    };
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
        // Une liste avec une case « lu » (les messages reçus) : une pastille compte ceux qui ne le sont pas.
        // Toujours présente, masquée à zéro : sidebar.js la met à jour sans recharger la page.
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
    $titre = static fn (string $t): string => '<li class="sidebar__groupe" aria-hidden="true"><span class="sidebar__libelle">'.$t.'</span></li>';
    $bloc = ($groupes['quotidien'] ? $titre('Au quotidien').implode('', $groupes['quotidien']) : '')
        .($groupes['site'] ? $titre('Le site').implode('', $groupes['site']) : '');

    $contenuHref = preg_quote($e($app->routeUrl('/content')), '#');
    $mediasHref = preg_quote($e($app->routeUrl('/assets')), '#');
    $lien = static fn (string $href): string => '#(?:<li class="kiss-nav-divider"></li>\s*)?<li\b[^>]*>\s*<a\b[^>]*\bhref="'.$href.'"[^>]*>.*?</a>\s*</li>#s';
    if ($bloc !== '' && preg_match($lien($contenuHref), $menu)) {
        $menu = preg_replace($lien($mediasHref), '', $menu, 1);   // d'abord : le bloc a son propre lien « Médias »
        $menu = preg_replace($lien($contenuHref), $bloc, $menu, 1);
    }

    // En tête : le logo et le nom du site ; en bas : le bouton qui réduit ou déplie la barre.
    $reduite = ($_COOKIE['admincamping-sidebar'] ?? '') === 'reduite';
    $marque = '<a class="sidebar__marque" href="'.$e($app->routeUrl('/')).'">'
        .'<img src="'.$e($app->helper('theme')->logo()).'" alt="" width="36" height="36" decoding="sync">'
        .'<span class="sidebar__libelle sidebar__nom">'.$e($app['app.name']).'</span></a>';
    $bouton = '<button type="button" class="sidebar__basculer" aria-expanded="'.($reduite ? 'false' : 'true').'" aria-label="'
        .($reduite ? 'Déplier le menu' : 'Réduire le menu').'"><icon aria-hidden="true">'.($reduite ? 'left_panel_open' : 'left_panel_close')
        .'</icon><span class="sidebar__libelle">Réduire le menu</span></button>';

    $menu = preg_replace('#^<div class="app-container-aside-menu">#', '<div class="app-container-aside-menu" data-sidebar="serveur">'.$marque, $menu, 1);
    $dernier = strrpos($menu, '</div>');
    if ($dernier !== false) {
        $menu = substr($menu, 0, $dernier).$bouton.substr($menu, $dernier);
    }

    $html = substr($html, 0, $debut).$menu.substr($html, $fin);

    // Le menu du téléphone (le tiroir de Cockpit) : les mêmes entrées que la barre, sur le même fond.
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
            .'</kiss-content></kiss-offcanvas>';
        $html = substr($html, 0, $t[0][1]).$tiroir.substr($html, $t[0][1] + strlen($t[0][0]));
    }

    // L'état réduit est posé sur <html> dès l'envoi.
    if ($reduite) {
        $html = preg_replace('#(<html\b[^>]*?\bclass=")#', '$1sidebar-reduite ', $html, 1);
    }

    return $html;
};
