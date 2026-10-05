<?php

/**
 * Habillage de secours d'un écran de Cockpit que le Dashboard ne remplace pas (celui d'un autre addon) : son fil
 * d'Ariane prend l'allure du Dashboard. Une entrée de $ecrans donne en plus l'en-tête complet.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @param  string   $html la page rendue
 * @return string         la page habillée
 */
return function (Lime\App $app, string $html): string {

    if (!preg_match('#<ul class="kiss-breadcrumbs">(.*?)</ul>#s', $html, $fil, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $route = rtrim((string) $app->request->route, '/');

    // [motif de la route, icône, titre (null : nom du modèle), texte, titre d'origine à retirer]
    $ecrans = [];

    $ecran = null;
    foreach ($ecrans as $candidat) {
        if (preg_match($candidat[0], $route, $m)) {
            $ecran = $candidat;
            if ($ecran[2] === null) {
                $modele = $app->module('content')->model($m[1]);
                $ecran[2] = (string) (($modele['label'] ?? '') ?: $m[1]);
            }
            break;
        }
    }

    preg_match_all('#<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>#s', $fil[1][0], $liens, PREG_SET_ORDER);
    $reglages = rtrim($app->routeUrl('/system'), '/');
    $chemin = implode('<icon>chevron_right</icon>', array_map(static fn (array $l): string => '<a href="'.$l[1].'">'
        .(rtrim(html_entity_decode($l[1]), '/') === $reglages ? 'Réglages' : trim(strip_tags($l[2]))).'</a>', $liens));
    if (str_starts_with($route, '/content/models')) {
        $chemin = '<a href="'.$e($app->routeUrl('/system')).'">Réglages</a><icon>chevron_right</icon><a href="'.$e($app->routeUrl('/content')).'">Modèles de contenu</a>';
    }

    if ($ecran === null) {
        $tete = '<nav class="fiche-tete__chemin habillage-chemin" aria-label="Fil d’Ariane">'.$chemin.'</nav>';
    } else {
        $tete = '<section class="fiche-tete habillage-tete"><span class="fiche-tete__icone"><icon>'.$ecran[1].'</icon></span>'
            .'<div class="fiche-tete__texte"><nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">'.$chemin.'</nav>'
            .'<h1>'.$e($ecran[2]).'</h1><p class="fiche-tete__info">'.$e($ecran[3]).'</p></div></section>';
    }

    $html = substr($html, 0, $fil[0][1]).$tete.substr($html, $fil[0][1] + strlen($fil[0][0]));

    return $ecran === null ? $html : (string) preg_replace($ecran[4], '', $html, 1);
};
