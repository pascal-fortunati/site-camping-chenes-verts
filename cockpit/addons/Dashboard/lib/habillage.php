<?php

/**
 * Habillage des écrans de Cockpit gardés tels quels (éditeur de modèle, fichiers, espaces, tâches, console, mise
 * à jour) : le fil d'Ariane et le titre deviennent l'en-tête de fiche du Dashboard. Un écran inconnu garde son
 * titre ; seul son fil d'Ariane change.
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

    // [motif de la route, icône, titre, texte, titre d'origine à retirer]
    $ecrans = [
        ['#^/content/models/create#', 'schema', 'Nouveau modèle', 'Choisissez les champs, leur ordre et leurs réglages.', '#<div class="kiss-margin-large-bottom kiss-size-4">\s*<strong v-if="!isUpdate">.*?</div>#s'],
        ['#^/content/models/edit/([\w-]+)#', 'schema', null, 'Les champs du modèle, leur ordre et leurs réglages.', '#<div class="kiss-margin-large-bottom kiss-size-4">\s*<strong v-if="!isUpdate">.*?</div>#s'],
        ['#^/finder#', 'folder_open', 'Fichiers', 'Les fichiers de l’administration sur le serveur : à manier avec précaution.', '#<div class="kiss-margin-large-bottom kiss-size-3 kiss-text-bold">\s*[^<]*</div>#'],
        ['#^/system/spaces#', 'workspaces', 'Espaces', 'Des administrations séparées sur la même installation.', '#<span class="kiss-size-4 kiss-text-bold">[^<]*</span>#'],
        ['#^/system/worker#', 'pending_actions', 'Tâches', 'Les tâches lancées en arrière-plan.', '#<div class="kiss-margin-large-bottom kiss-flex kiss-flex-middle" gap="small">\s*<div class="kiss-size-4"><strong>[^<]*</strong></div>\s*<span class="kiss-badge">BETA</span>\s*</div>#'],
        ['#^/system/tower#', 'terminal', 'Console', 'Les commandes de Cockpit, dans le navigateur.', '#<icon class="kiss-size-4 kiss-margin-small-end" size="larger">terminal</icon>\s*<div class="kiss-size-4 kiss-flex-1">\s*<strong>Tower</strong>\s*</div>#'],
        ['#^/updater#', 'system_update', 'Mise à jour de Cockpit', 'Ce site installe Cockpit avec bin/install-cockpit.php : préférez ce script.', '#<div class="kiss-margin-large-bottom kiss-size-4"><strong>[^<]*</strong></div>#'],
    ];

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
