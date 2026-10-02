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
    // vient d'un modèle que la personne peut lire ; un modèle inconnu va dans le groupe qui correspond à son type.
    $route = rtrim((string) $app->request->route, '/');
    $acl = $app->helper('acl');
    $libelles = ['pages' => 'Pages', 'messages' => 'Messages', 'saison' => 'Saison et places', 'settings' => 'Identité du site', 'menu' => 'Menu du site', 'legal' => 'Mentions légales', 'articles' => 'Actualités'];
    $iconesModeles = ['pages' => 'description', 'messages' => 'mail', 'saison' => 'event_seat', 'settings' => 'badge', 'menu' => 'menu_open', 'legal' => 'gavel', 'articles' => 'newspaper'];
    $quotidien = ['pages', 'messages', 'saison', 'articles'];
    $ordre = array_flip(['pages', 'messages', 'saison', 'articles', 'settings', 'menu', 'legal']);

    $entree = static function (string $href, string $libelle, string $icone, bool $actif, string $badge = '') use ($e): string {
        return '<li'.($actif ? ' class="active"' : '').'><a href="'.$e($href).'" aria-label="'.$e($libelle).'" kiss-tooltip="right"'.($actif ? ' aria-current="page"' : '').'>'
            .'<icon aria-hidden="true">'.$icone.'</icon><span class="sidebar__libelle">'.$e($libelle).'</span>'.$badge.'</a></li>';
    };
    $groupes = ['quotidien' => [], 'site' => []];
    $modeles = $app->module('content')->models();
    uasort($modeles, static fn (array $a, array $b): int => ($ordre[$a['name']] ?? 50) <=> ($ordre[$b['name']] ?? 50));
    foreach ($modeles as $nom => $m) {
        if (!$acl->isAllowed("content/{$nom}/read")) {
            continue;
        }
        $singleton = $m['type'] === 'singleton';
        $href = $app->routeUrl($singleton ? "/content/singleton/item/{$nom}" : "/content/{$m['type']}/items/{$nom}");
        $actif = (bool) preg_match('#^/content/(singleton|collection|tree)/(item|items|clone)/'.preg_quote($nom, '#').'(/|$)#', $route);
        $badge = '';
        if ($nom === 'messages') {
            $nonLus = count(array_filter($app->module('content')->items('messages', ['fields' => ['lu' => 1]]), static fn (array $i): bool => empty($i['lu'])));
            $badge = $nonLus ? '<span class="sidebar__badge" aria-label="'.$nonLus.' non lu'.($nonLus > 1 ? 's' : '').'">'.$nonLus.'</span>' : '';
        }
        $groupe = in_array($nom, $quotidien, true) || (!$singleton && !isset($libelles[$nom])) ? 'quotidien' : 'site';
        $groupes[$groupe][] = $entree($href, $libelles[$nom] ?? ($m['label'] ?: $nom), $iconesModeles[$nom] ?? ($singleton ? 'tune' : 'folder'), $actif, $badge);
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

    // L'état réduit est posé sur <html> dès l'envoi.
    if ($reduite) {
        $html = preg_replace('#(<html\b[^>]*?\bclass=")#', '$1sidebar-reduite ', $html, 1);
    }

    return $html;
};
