<?php

/**
 * Filtre des éléments qu'un compte peut voir dans un modèle, au-delà des droits de son rôle.
 *
 * Cockpit accorde ses droits par modèle ; un projet peut restreindre par élément (par exemple « ses propres
 * éléments ») en écoutant l'événement « dashboard.filtre » :
 *
 *     $app->on('dashboard.filtre', function (string $modele, array &$filtre) { $filtre['_cby'] = ...; });
 *
 * Le Dashboard l'applique partout où il lit des éléments lui-même : barre latérale, pastille des non-lus,
 * tableau de bord, recherche et usages des médias.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @param  string   $modele nom du modèle
 * @return array    le filtre (vide : aucun)
 */
return function (Lime\App $app, string $modele): array {
    $filtre = [];
    $app->trigger('dashboard.filtre', [$modele, &$filtre]);

    return $filtre;
};
