<?php

/**
 * Tableau de bord de l'administration (module Dashboard) : ce qui est propre au camping.
 * Les clés sont décrites dans cockpit/addons/Dashboard/lib/accueil.php.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

return [

    'phrase' => static function (Lime\App $app, Closure $jour): string {
        $saison = $app->module('content')->item('saison') ?? [];
        $ouverture = !empty($saison['saisonOuverture']) ? strtotime($saison['saisonOuverture']) : null;
        $fermeture = !empty($saison['saisonFermeture']) ? strtotime($saison['saisonFermeture'].' 23:59') : null;

        if ($ouverture && time() < $ouverture) {
            $jours = (int) ceil(($ouverture - time()) / 86400);
            return "La saison ouvre le {$jour($ouverture)}, dans {$jours} jour".($jours > 1 ? 's' : '').'.';
        }
        if ($fermeture && time() <= $fermeture) {
            return "Le camping est ouvert jusqu’au {$jour($fermeture)}.";
        }

        return 'Pensez à indiquer les dates de la prochaine saison.';
    },

    'indicateurs' => static function (Lime\App $app, Closure $page): array {
        $places = (int) (($app->module('content')->item('saison') ?? [])['placesDisponibles'] ?? 0);

        return [[
            'icone' => 'event_available',
            'valeur' => $places,
            'libelle' => 'Places restantes',
            'detail' => $places > 0 ? 'Affiché sur le site' : 'Rien d’affiché sur le site',
            'lien' => $app->routeUrl('/content/singleton/item/saison'),
            'ton' => 'tdb--vert',
        ]];
    },

    'raccourcis' => static fn (Lime\App $app, Closure $page): array => [
        ['icone' => 'event_seat', 'titre' => 'Places et dates', 'texte' => 'Places restantes, ouverture, horaires', 'lien' => $app->routeUrl('/content/singleton/item/saison')],
        ['icone' => 'euro', 'titre' => 'Les tarifs', 'texte' => 'Prix par nuit, suppléments', 'lien' => $page('tarifs')],
        ['icone' => 'restaurant_menu', 'titre' => 'Menu du snack', 'texte' => 'Le plat du jour, les prix', 'lien' => $page('snack')],
        ['icone' => 'add_photo_alternate', 'titre' => 'Photo d’accueil', 'texte' => 'La grande image de la page d’accueil', 'lien' => $page('accueil')],
        ['icone' => 'badge', 'titre' => 'Identité du site', 'texte' => 'Coordonnées, logo, couleurs', 'lien' => $app->routeUrl('/content/singleton/item/settings')],
    ],
];
