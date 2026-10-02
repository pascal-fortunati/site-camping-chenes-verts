<?php

/**
 * CAMPING LES CHÊNES VERTS — la saison : ce qui change dans l'année (dates, places restantes, heures, horaires
 * de l'accueil). Séparée de « Identité du site », qui ne garde que ce qui ne change presque jamais (demande du
 * client, 02/10/2026). Le site la lit avec l'identité (src/Content/Repository.php) : les gabarits l'utilisent
 * toujours par site.saisonOuverture, site.placesDisponibles…
 */

return [
    'name' => 'saison',
    'label' => 'La saison',
    'info' => 'Dates d’ouverture, places encore disponibles, heures d’arrivée et de départ, horaires de l’accueil.',
    'type' => 'singleton',
    'group' => null,
    'preview' => [],
    'meta' => null,
    '_created' => 1790935200,
    '_modified' => 1790935200,

    'fields' => [

        [
            'name' => 'saisonOuverture',
            'type' => 'date',
            'label' => 'Ouverture de la saison',
            'info' => 'Apparaît dans la bande « Saison » des pages et limite les dates du formulaire de réservation. À changer chaque année.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Dates et places',
            'width' => '1-2',
            'opts' => [],
        ],
        [
            'name' => 'saisonFermeture',
            'type' => 'date',
            'label' => 'Fermeture de la saison',
            'info' => 'Apparaît dans la bande « Saison » des pages et limite les dates du formulaire de réservation. À changer chaque année.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Dates et places',
            'width' => '1-2',
            'opts' => [],
        ],
        [
            'name' => 'placesDisponibles',
            'type' => 'number',
            'label' => 'Places encore disponibles',
            'info' => 'Le nombre s’affiche dans la bande « Saison » : « Il reste 5 places disponibles ». '
                .'Laisser vide ou mettre 0 pour ne rien afficher.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Dates et places',
            'width' => '1-1',
            'opts' => [],
        ],
        [
            'name' => 'heureArrivee',
            'type' => 'text',
            'label' => 'Arrivée à partir de',
            'info' => 'Locations. Apparaît sur les tarifs et les infos pratiques. Ex. : « 15 h ».',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Dates et places',
            'width' => '1-2',
            'opts' => ['maxlength' => 20, 'placeholder' => '15 h'],
        ],
        [
            'name' => 'heureDepart',
            'type' => 'text',
            'label' => 'Départ avant',
            'info' => 'Locations. Apparaît sur les tarifs et les infos pratiques. Ex. : « 10 h ».',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Dates et places',
            'width' => '1-2',
            'opts' => ['maxlength' => 20, 'placeholder' => '10 h'],
        ],
        [
            'name' => 'horairesSaisons',
            'type' => 'set',
            'label' => 'Horaires de l’accueil, par saison',
            'info' => 'Apparaissent dans le pied de page, une ligne par période.',
            'required' => false,
            'localize' => false,
            'multiple' => true,
            'group' => 'Accueil',
            'width' => '1-1',
            'opts' => [
                'display' => '${data.periode || \'Saison\'}',
                'fields' => [
                    ['name' => 'periode', 'type' => 'text', 'label' => 'Période', 'width' => '1-2', 'info' => 'En gras dans le pied de page.',
                        'opts' => ['placeholder' => 'Juillet et août']],
                    ['name' => 'horaires', 'type' => 'text', 'label' => 'Horaires', 'width' => '1-2', 'info' => 'Sous la période. Retour à la ligne possible.',
                        'opts' => ['multiline' => true, 'placeholder' => '8 h 30 – 12 h 30 et 14 h 30 – 19 h 30']],
                ],
            ],
        ],
    ],
];
