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

    // En tête : le logo et le nom du site ; en bas : le bouton qui réduit ou déplie la barre.
    $reduite = ($_COOKIE['admincamping-sidebar'] ?? '') === 'reduite';
    $marque = '<a class="sidebar__marque" href="'.$e($app->routeUrl('/')).'">'
        .'<img src="'.$e($app->helper('theme')->logo()).'" alt="">'
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
