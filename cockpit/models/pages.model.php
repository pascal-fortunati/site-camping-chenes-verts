<?php

/**
 * Site pages.
 *
 * Fixed structure: the customer fills it in, never edits it. Installed to
 * public/admin/storage/content/ by bin/install-cockpit.php.
 *
 * A page is a list of blocks. One block type = one entry in the `type` list,
 * a few fields shown by a `condition`, and a Twig partial of the same name in
 * templates/blocs/. Adding a type is documented in the README.
 *
 * Publication is not a field: Cockpit tracks it natively on `_state`
 * (1 published, 0 unpublished, -1 archived) with its own button in the admin.
 * The public site only ever serves items with `_state` = 1.
 */

// Headings only, plus links and lists: the page title is the only level-one
// heading, and the toolbar must not offer anything that would break that.
$toolbar = 'format | link | listBullet listOrdered';

// Conditions are evaluated in the admin against the block being edited.
$isHero = "data.type === 'hero'";
$isTexteImage = "data.type === 'texte-image'";
$isContact = "data.type === 'contact'";
$isFormulaire = "data.type === 'formulaire'";
$isTemoignages = "data.type === 'temoignages'";
$hasImage = "['hero', 'texte-image', 'carte'].includes(data.type)";   // CAMPING LES CHÊNES VERTS : + carte
$hasTexte = "['texte-image', 'contact', 'formulaire'].includes(data.type)";

// ── CAMPING LES CHÊNES VERTS — conditions des types propres au site (étape 06) ──
$isAnnonce = "data.type === 'annonce'";
$isCartes = "data.type === 'cartes'";
$isHebergements = "data.type === 'hebergements'";
$isTarifs = "data.type === 'tarifs'";
$isReservation = "data.type === 'reservation'";
$isCarte = "data.type === 'carte'";
$isArdoise = "data.type === 'ardoise'";
$hasPresentation = "['cartes', 'hebergements', 'tarifs', 'reservation', 'carte'].includes(data.type)";
$hasMessageVide = "['cartes', 'hebergements', 'tarifs'].includes(data.type)";
// ── fin CAMPING LES CHÊNES VERTS ──────────────────────────────────────────

return [
    'name' => 'pages',
    'label' => 'Pages',
    'info' => 'Les pages du site.',
    'type' => 'collection',
    'group' => null,
    'preview' => [],
    // Dans l'administration : nom court, icône et groupe de la barre latérale (addon AdminCamping, modeles.php).
    'admin' => ['libelle' => 'Pages', 'icone' => 'description', 'groupe' => 'quotidien', 'ordre' => 10, 'element' => 'page', 'elements' => 'pages', 'nouveau' => 'Nouvelle page'],
    'meta' => [
        'unique' => ['slug'],
    ],
    '_created' => 1754179200,
    '_modified' => 1754352000,

    'fields' => [
        [
            'name' => 'titre',
            'type' => 'text',
            'label' => 'Titre de la page',
            'info' => 'Apparaît en titre principal en haut de la page et dans le menu.',
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => [],
        ],
        [
            'name' => 'slug',
            'type' => 'text',
            'label' => 'Adresse de la page',
            'info' => "Termine l'adresse de la page : « services » donne /services. Lettres minuscules et tirets.",
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => ['placeholder' => 'services'],
        ],

        // ── Blocs ─────────────────────────────────────────────────────────
        [
            'name' => 'blocs',
            'type' => 'set',
            'label' => 'Contenu de la page',
            'info' => 'Les sections affichées sous le titre, dans cet ordre.',
            'required' => false,
            'localize' => false,
            'multiple' => true,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => [
                // CAMPING LES CHÊNES VERTS — le titre, puis le type en clair : « hero » ne disait rien au client (recette, support 07).
                'display' => '${(data.titre ? data.titre + \' · \' : \'\') + (({\'hero\': \'Bandeau d’ouverture (grande photo)\', \'texte-image\': \'Texte et image\', \'contact\': \'Coordonnées\', \'formulaire\': \'Formulaire de contact\', \'temoignages\': \'Témoignages\', \'annonce\': \'Annonce de la saison\', \'cartes\': \'Cartes\', \'hebergements\': \'Fiches hébergement\', \'tarifs\': \'Grille des tarifs\', \'reservation\': \'Formulaire de réservation\', \'carte\': \'Carte d’accès\', \'ardoise\': \'Ardoise\'})[data.type] || \'Section\')}',
                'fields' => [
                    [
                        'name' => 'type',
                        'type' => 'select',
                        'label' => 'Type de section',
                        'required' => true,
                        'width' => '1-1',
                        'opts' => [
                            'options' => [
                                ['value' => 'hero', 'label' => 'Bandeau d’ouverture'],
                                ['value' => 'texte-image', 'label' => 'Texte et image'],
                                ['value' => 'contact', 'label' => 'Coordonnées'],
                                ['value' => 'formulaire', 'label' => 'Formulaire de contact'],
                                ['value' => 'temoignages', 'label' => 'Témoignages'],
                                // ── CAMPING LES CHÊNES VERTS — types propres au site ──
                                ['value' => 'annonce', 'label' => 'Annonce : saison et places disponibles'],
                                ['value' => 'cartes', 'label' => 'Cartes (services, animations, visites…)'],
                                ['value' => 'hebergements', 'label' => 'Fiches hébergement'],
                                ['value' => 'tarifs', 'label' => 'Grille des tarifs'],
                                ['value' => 'reservation', 'label' => 'Formulaire de demande de réservation'],
                                ['value' => 'carte', 'label' => 'Carte d’accès (OpenStreetMap)'],
                                ['value' => 'ardoise', 'label' => 'Ardoise (menu du jour, horaires, prix…)'],
                                // ── fin CAMPING LES CHÊNES VERTS ──
                            ],
                        ],
                    ],
                    [
                        'name' => 'titre',
                        'type' => 'text',
                        'label' => 'Titre de la section',
                        'info' => 'Apparaît en tête de la section.',
                        'width' => '1-1',
                        'opts' => [],
                    ],
                    [
                        'name' => 'accroche',
                        'type' => 'text',
                        'label' => 'Accroche',
                        'info' => 'La phrase affichée sous le titre du bandeau.',
                        'width' => '1-1',
                        'condition' => $isHero,
                        'opts' => ['multiline' => true, 'maxlength' => 200],
                    ],
                    [
                        'name' => 'texte',
                        'type' => 'wysiwyg',
                        'label' => 'Texte',
                        'info' => 'Le corps de la section.',
                        'width' => '1-1',
                        'condition' => $hasTexte,
                        'opts' => ['toolbar' => $toolbar],
                    ],
                    [
                        'name' => 'image',
                        'type' => 'asset',
                        'label' => 'Image',
                        'info' => 'Apparaît dans la section. Format paysage conseillé.',
                        'width' => '1-2',
                        'condition' => $hasImage,
                        'opts' => ['filter' => ['type' => 'image']],
                    ],
                    [
                        'name' => 'alt',
                        'type' => 'text',
                        'label' => 'Description de l’image',
                        'info' => 'Lue à voix haute par les lecteurs d’écran, et affichée si l’image ne charge pas. Décrire ce que l’on voit.',
                        'width' => '1-2',
                        'condition' => $hasImage,
                        'opts' => ['maxlength' => 150],
                    ],
                    [
                        'name' => 'positionImage',
                        'type' => 'select',
                        'label' => 'Position de l’image',
                        'width' => '1-2',
                        'condition' => $isTexteImage,
                        'opts' => [
                            'options' => [
                                ['value' => 'droite', 'label' => 'À droite du texte'],
                                ['value' => 'gauche', 'label' => 'À gauche du texte'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'boutonTexte',
                        'type' => 'text',
                        'label' => 'Texte du bouton',
                        'info' => 'Laisser vide pour ne pas afficher de bouton.',
                        'width' => '1-2',
                        'condition' => $isHero,
                        'opts' => ['maxlength' => 40],
                    ],
                    [
                        'name' => 'boutonLien',
                        'type' => 'text',
                        'label' => 'Adresse du bouton',
                        'info' => 'Adresse d’une page du site, par exemple /services.',
                        'width' => '1-2',
                        'condition' => $isHero,
                        'opts' => ['placeholder' => '/services'],
                    ],
                    // ── Témoignages ─────────────────────────────────────
                    // Exemple commenté d'un type de section : voir le partial
                    // templates/blocs/temoignages.html.twig et
                    // docs/guide-integration.md.
                    [
                        'name' => 'introduction',
                        'type' => 'text',
                        'label' => 'Phrase d’introduction',
                        'info' => 'Apparaît sous le titre, avant les témoignages.',
                        'width' => '1-1',
                        'condition' => $isTemoignages,
                        'opts' => ['multiline' => true, 'maxlength' => 200],
                    ],
                    [
                        'name' => 'temoignages',
                        'type' => 'set',
                        'label' => 'Témoignages',
                        'info' => 'Chaque entrée devient un témoignage affiché dans la section.',
                        'multiple' => true,
                        'width' => '1-1',
                        'condition' => $isTemoignages,
                        'opts' => [
                            'display' => '${data.auteur || \'Témoignage\'}',
                            'fields' => [
                                [
                                    'name' => 'citation',
                                    'type' => 'text',
                                    'label' => 'Ce que dit la personne',
                                    'required' => true,
                                    'width' => '1-1',
                                    'opts' => ['multiline' => true, 'maxlength' => 400],
                                ],
                                [
                                    'name' => 'auteur',
                                    'type' => 'text',
                                    'label' => 'Nom',
                                    'width' => '1-2',
                                    'opts' => [],
                                ],
                                [
                                    'name' => 'fonction',
                                    'type' => 'text',
                                    'label' => 'Fonction ou ville',
                                    'info' => 'Affichée sous le nom, en plus discret.',
                                    'width' => '1-2',
                                    'opts' => [],
                                ],
                                [
                                    'name' => 'portrait',
                                    'type' => 'asset',
                                    'label' => 'Portrait',
                                    'info' => 'Facultatif. Image carrée conseillée.',
                                    'width' => '1-2',
                                    'opts' => ['filter' => ['type' => 'image']],
                                ],
                                [
                                    'name' => 'alt',
                                    'type' => 'text',
                                    'label' => 'Description du portrait',
                                    'info' => 'Obligatoire dès qu’un portrait est choisi.',
                                    'width' => '1-2',
                                    'opts' => ['maxlength' => 150],
                                ],
                            ],
                        ],
                    ],

                    [
                        'name' => 'afficherHoraires',
                        'type' => 'boolean',
                        'label' => 'Afficher les horaires',
                        'info' => 'Les horaires saisis dans « Identité du site » apparaissent sous les coordonnées.',
                        'width' => '1-1',
                        'condition' => $isContact,
                        'opts' => ['default' => true],
                    ],

                    // ── CAMPING LES CHÊNES VERTS — champs des types propres au site (étape 06) ──
                    // Ajouts groupés ici pour que la fusion d'une mise à jour du socle reste lisible.
                    // Les gabarits correspondants sont dans templates-client/blocs/.

                    // Communs à plusieurs types
                    [
                        'name' => 'presentation',
                        'type' => 'text',
                        'label' => 'Texte d’introduction',
                        'info' => 'Une ou deux phrases courtes, sous le titre de la section. Facultatif.',
                        'width' => '1-1',
                        'condition' => $hasPresentation,
                        'opts' => ['multiline' => true, 'maxlength' => 400],
                    ],
                    [
                        'name' => 'messageVide',
                        'type' => 'text',
                        'label' => 'Message si la liste est vide',
                        'info' => 'Affiché à la place de la liste tant qu’elle ne contient rien. Vide : un message par défaut s’affiche.',
                        'width' => '1-1',
                        'condition' => $hasMessageVide,
                        'opts' => ['maxlength' => 200],
                    ],

                    // Annonce : les valeurs viennent de la fiche « La saison » (dates, places, heures)
                    [
                        'name' => 'annonceHeures',
                        'type' => 'boolean',
                        'label' => 'Afficher les heures d’arrivée et de départ',
                        'info' => 'Les heures sont saisies dans la fiche « La saison ».',
                        'width' => '1-2',
                        'condition' => $isAnnonce,
                        'opts' => ['default' => false],
                    ],
                    [
                        'name' => 'annonceLien',
                        'type' => 'boolean',
                        'label' => 'Afficher le lien « Demander une réservation »',
                        'info' => 'Jamais affiché sur la page de réservation elle-même.',
                        'width' => '1-2',
                        'condition' => $isAnnonce,
                        'opts' => ['default' => true],
                    ],

                    // Cartes
                    [
                        'name' => 'cartes',
                        'type' => 'set',
                        'label' => 'Cartes',
                        'info' => 'Chaque carte apparaît dans la grille, dans cet ordre. La photo est facultative.',
                        'multiple' => true,
                        'width' => '1-1',
                        'condition' => $isCartes,
                        'opts' => [
                            'display' => '${data.titre || \'Carte sans titre\'}',
                            'fields' => [
                                ['name' => 'titre', 'type' => 'text', 'label' => 'Titre', 'required' => true, 'width' => '1-1',
                                    'info' => 'Le nom du service, de l’animation ou du lieu.', 'opts' => ['maxlength' => 80]],
                                ['name' => 'repere', 'type' => 'text', 'label' => 'Repère', 'width' => '1-2',
                                    'info' => 'Court : un jour, une période, une distance. Ex. : « Le mercredi · 15 € ».', 'opts' => ['maxlength' => 60]],
                                ['name' => 'texte', 'type' => 'text', 'label' => 'Texte', 'width' => '1-2',
                                    'info' => 'Une ou deux phrases courtes. Facultatif.', 'opts' => ['multiline' => true, 'maxlength' => 240]],
                                ['name' => 'image', 'type' => 'asset', 'label' => 'Photo', 'width' => '1-2',
                                    'info' => 'Facultative. Format paysage.', 'opts' => ['filter' => ['type' => 'image']]],
                                ['name' => 'alt', 'type' => 'text', 'label' => 'Description de la photo', 'width' => '1-2',
                                    'info' => 'Obligatoire dès qu’une photo est choisie : décrire ce que l’on voit.', 'opts' => ['maxlength' => 150]],
                                ['name' => 'lienTexte', 'type' => 'text', 'label' => 'Texte du lien', 'width' => '1-2',
                                    'info' => 'Facultatif. Ex. : « Voir le détail ».', 'opts' => ['maxlength' => 40]],
                                ['name' => 'lienAdresse', 'type' => 'text', 'label' => 'Adresse du lien', 'width' => '1-2',
                                    'info' => 'Une page du site (/hebergements) ou un site extérieur.', 'opts' => ['placeholder' => '/hebergements']],
                            ],
                        ],
                    ],
                    [
                        'name' => 'finTexte',
                        'type' => 'text',
                        'label' => 'Bouton sous les cartes : texte',
                        'info' => 'Facultatif. Laisser vide pour ne pas afficher de bouton.',
                        'width' => '1-2',
                        'condition' => $isCartes,
                        'opts' => ['maxlength' => 40],
                    ],
                    [
                        'name' => 'finLien',
                        'type' => 'text',
                        'label' => 'Bouton sous les cartes : adresse',
                        'info' => 'Une page du site, par exemple /reserver.',
                        'width' => '1-2',
                        'condition' => $isCartes,
                        'opts' => ['placeholder' => '/reserver'],
                    ],

                    // Fiches hébergement
                    [
                        'name' => 'hebergements',
                        'type' => 'set',
                        'label' => 'Fiches hébergement',
                        'info' => 'Une fiche par type d’hébergement, dans cet ordre.',
                        'multiple' => true,
                        'width' => '1-1',
                        'condition' => $isHebergements,
                        'opts' => [
                            'display' => '${data.nom || \'Hébergement sans nom\'}',
                            'fields' => [
                                ['name' => 'nom', 'type' => 'text', 'label' => 'Nom', 'required' => true, 'width' => '1-2',
                                    'info' => 'Ex. : « Chalet ».', 'opts' => ['maxlength' => 80]],
                                ['name' => 'ancre', 'type' => 'text', 'label' => 'Repère de lien', 'width' => '1-2',
                                    'info' => 'Le même que dans la grille des tarifs, en minuscules et tirets (ex. : chalet). '
                                        .'Le lien « Voir les prix » y conduit.', 'opts' => ['placeholder' => 'chalet']],
                                ['name' => 'image', 'type' => 'asset', 'label' => 'Photo', 'width' => '1-2',
                                    'info' => 'Format paysage.', 'opts' => ['filter' => ['type' => 'image']]],
                                ['name' => 'alt', 'type' => 'text', 'label' => 'Description de la photo', 'width' => '1-2',
                                    'info' => 'Obligatoire dès qu’une photo est choisie.', 'opts' => ['maxlength' => 150]],
                                ['name' => 'reperes', 'type' => 'set', 'label' => 'Repères chiffrés', 'multiple' => true, 'width' => '1-1',
                                    'info' => 'Surface, personnes, chambres… Même ordre sur toutes les fiches, pour comparer.',
                                    'opts' => [
                                        'display' => '${data.libelle || \'Repère\'}',
                                        'fields' => [
                                            ['name' => 'libelle', 'type' => 'text', 'label' => 'Libellé', 'width' => '1-2', 'info' => 'À gauche dans la fiche. Ex. : « Surface ».', 'opts' => ['placeholder' => 'Surface']],
                                            ['name' => 'valeur', 'type' => 'text', 'label' => 'Valeur', 'width' => '1-2', 'info' => 'À droite, en gras. Ex. : « 35 m² ».', 'opts' => ['placeholder' => '35 m²']],
                                        ],
                                    ]],
                                ['name' => 'equipements', 'type' => 'text', 'label' => 'Équipements', 'width' => '1-1',
                                    'info' => 'Un équipement par ligne.', 'opts' => ['multiline' => true, 'maxlength' => 600]],
                                ['name' => 'mention', 'type' => 'text', 'label' => 'Mention importante', 'width' => '1-1',
                                    'info' => 'Facultatif, en évidence. Ex. : « Animaux non acceptés. »', 'opts' => ['maxlength' => 120]],
                                ['name' => 'nombre', 'type' => 'number', 'label' => 'Nombre d’unités', 'width' => '1-3',
                                    'info' => 'Dessine la vue de dessus : une case par unité. Vide ou 0 : pas de schéma.', 'opts' => []],
                                ['name' => 'nomCourt', 'type' => 'text', 'label' => 'Nom sur chaque case', 'width' => '1-3',
                                    'info' => 'Court. Ex. : « Chalet » donne Chalet 1, Chalet 2…', 'opts' => ['maxlength' => 12]],
                                ['name' => 'pluriel', 'type' => 'text', 'label' => 'Nom au pluriel', 'width' => '1-3',
                                    'info' => 'Pour le titre du schéma. Ex. : « chalets » donne « 4 chalets ».', 'opts' => ['maxlength' => 60]],
                            ],
                        ],
                    ],

                    // Grille des tarifs : exactement 4 périodes (une 5ᵉ demande une intervention)
                    [
                        'name' => 'periodes',
                        'type' => 'set',
                        'label' => 'Les 4 périodes',
                        'info' => 'Le nom et les dates de chaque période. Ils s’affichent en tête des colonnes de la grille.',
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => [
                            'fields' => [
                                ['name' => 'nom1', 'type' => 'text', 'label' => 'Période 1 : nom', 'width' => '1-2', 'info' => 'En tête de la colonne 1 de la grille.', 'opts' => ['placeholder' => 'Basse']],
                                ['name' => 'dates1', 'type' => 'text', 'label' => 'Période 1 : dates', 'width' => '1-2', 'info' => 'Sous le nom de la période 1, en plus petit.', 'opts' => ['placeholder' => 'avril, mai, septembre']],
                                ['name' => 'nom2', 'type' => 'text', 'label' => 'Période 2 : nom', 'width' => '1-2', 'info' => 'En tête de la colonne 2 de la grille.', 'opts' => ['placeholder' => 'Moyenne']],
                                ['name' => 'dates2', 'type' => 'text', 'label' => 'Période 2 : dates', 'width' => '1-2', 'info' => 'Sous le nom de la période 2, en plus petit.', 'opts' => ['placeholder' => 'juin']],
                                ['name' => 'nom3', 'type' => 'text', 'label' => 'Période 3 : nom', 'width' => '1-2', 'info' => 'En tête de la colonne 3 de la grille.', 'opts' => ['placeholder' => 'Haute']],
                                ['name' => 'dates3', 'type' => 'text', 'label' => 'Période 3 : dates', 'width' => '1-2', 'info' => 'Sous le nom de la période 3, en plus petit.', 'opts' => []],
                                ['name' => 'nom4', 'type' => 'text', 'label' => 'Période 4 : nom', 'width' => '1-2', 'info' => 'En tête de la colonne 4 de la grille.', 'opts' => ['placeholder' => 'Très haute']],
                                ['name' => 'dates4', 'type' => 'text', 'label' => 'Période 4 : dates', 'width' => '1-2', 'info' => 'Sous le nom de la période 4, en plus petit.', 'opts' => []],
                            ],
                        ],
                    ],
                    [
                        'name' => 'lignes',
                        'type' => 'set',
                        'label' => 'Lignes de la grille',
                        'info' => 'Une ligne par hébergement : son nom et ses 4 prix par nuit, en euros. À mettre à jour chaque janvier.',
                        'multiple' => true,
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => [
                            'display' => '${data.hebergement || \'Ligne sans nom\'}',
                            'fields' => [
                                ['name' => 'hebergement', 'type' => 'text', 'label' => 'Hébergement', 'required' => true, 'width' => '1-2', 'info' => 'En tête de ligne de la grille ; au téléphone, en titre du bloc de prix.', 'opts' => ['maxlength' => 80]],
                                ['name' => 'ancre', 'type' => 'text', 'label' => 'Repère de lien', 'width' => '1-2',
                                    'info' => 'Le même que sur la fiche hébergement (ex. : chalet).', 'opts' => []],
                                ['name' => 'prix1', 'type' => 'number', 'label' => 'Prix période 1 (€)', 'width' => '1-4', 'info' => 'Dans la colonne 1. Le signe € s’ajoute seul.', 'opts' => []],
                                ['name' => 'prix2', 'type' => 'number', 'label' => 'Prix période 2 (€)', 'width' => '1-4', 'info' => 'Dans la colonne 2. Le signe € s’ajoute seul.', 'opts' => []],
                                ['name' => 'prix3', 'type' => 'number', 'label' => 'Prix période 3 (€)', 'width' => '1-4', 'info' => 'Dans la colonne 3. Le signe € s’ajoute seul.', 'opts' => []],
                                ['name' => 'prix4', 'type' => 'number', 'label' => 'Prix période 4 (€)', 'width' => '1-4', 'info' => 'Dans la colonne 4. Le signe € s’ajoute seul.', 'opts' => []],
                            ],
                        ],
                    ],
                    [
                        'name' => 'precisions',
                        'type' => 'text',
                        'label' => 'Précisions sous la grille',
                        'info' => 'Ce que comprend un prix. Ex. : « Emplacement : prix pour 2 personnes… ».',
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => ['multiline' => true, 'maxlength' => 400],
                    ],
                    [
                        'name' => 'taxe',
                        'type' => 'text',
                        'label' => 'Taxe de séjour',
                        'info' => 'Obligatoire à l’affichage : l’encadré « Taxe de séjour » apparaît toujours.',
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => ['multiline' => true, 'maxlength' => 300],
                    ],
                    [
                        'name' => 'supplements',
                        'type' => 'set',
                        'label' => 'Suppléments',
                        'info' => 'Chaque supplément apparaît dans l’encadré « Suppléments », dans cet ordre.',
                        'multiple' => true,
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => [
                            'display' => '${data.libelle || \'Supplément\'}',
                            'fields' => [
                                ['name' => 'libelle', 'type' => 'text', 'label' => 'Supplément', 'width' => '1-2', 'info' => 'Dans l’encadré « Suppléments ». Ex. : « Animal ».', 'opts' => ['placeholder' => 'Animal']],
                                ['name' => 'prix', 'type' => 'text', 'label' => 'Prix', 'width' => '1-2', 'info' => 'Écrit en toutes lettres, avec l’unité. Ex. : « 4 € par nuit ».', 'opts' => ['placeholder' => '4 € par nuit']],
                            ],
                        ],
                    ],
                    [
                        'name' => 'sejour',
                        'type' => 'wysiwyg',
                        'label' => 'Durée du séjour',
                        'info' => 'Encadré « Durée du séjour, arrivée et départ ». Les heures s’ajoutent seules, depuis la fiche « La saison ».',
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => ['toolbar' => $toolbar],
                    ],
                    [
                        'name' => 'conditions',
                        'type' => 'wysiwyg',
                        'label' => 'Acompte, caution et annulation',
                        'info' => 'Obligatoire à l’affichage : l’encadré apparaît toujours, avec un lien vers les conditions générales de vente.',
                        'width' => '1-1',
                        'condition' => $isTarifs,
                        'opts' => ['toolbar' => $toolbar],
                    ],

                    // Formulaire de demande de réservation (option A)
                    [
                        'name' => 'hebergementsProposes',
                        'type' => 'text',
                        'label' => 'Hébergements proposés dans le formulaire',
                        'info' => 'Un par ligne, dans l’ordre de la liste « Hébergement souhaité ».',
                        'width' => '1-1',
                        'condition' => $isReservation,
                        'opts' => ['multiline' => true, 'maxlength' => 600],
                    ],
                    [
                        'name' => 'delaiReponse',
                        'type' => 'text',
                        'label' => 'Délai de réponse',
                        'info' => 'Repris dans le message affiché après l’envoi. Ex. : « sous 48 heures en saison, 72 heures en hiver ».',
                        'width' => '1-1',
                        'condition' => $isReservation,
                        'opts' => ['maxlength' => 120],
                    ],

                    // Carte d'accès : une image de la carte, et deux liens vers openstreetmap.org.
                    // La position vient de « Identité du site » (latitude, longitude, adresse).
                    [
                        'name' => 'carteAfficher',
                        'type' => 'boolean',
                        'label' => 'Afficher la carte',
                        'info' => 'Décocher pour masquer la section sans perdre ses réglages.',
                        'width' => '1-2',
                        'condition' => $isCarte,
                        'opts' => ['default' => true],
                    ],
                    [
                        'name' => 'carteItineraire',
                        'type' => 'boolean',
                        'label' => 'Afficher le bouton « Itinéraire »',
                        'info' => 'Ouvre le calcul d’itinéraire d’OpenStreetMap vers le lieu.',
                        'width' => '1-2',
                        'condition' => $isCarte,
                        'opts' => ['default' => true],
                    ],
                    [
                        'name' => 'carteZoom',
                        'type' => 'number',
                        'label' => 'Zoom du lien « Voir sur la carte »',
                        'info' => 'De 5 (la région) à 19 (la rue). 15 montre le village et ses abords.',
                        'width' => '1-2',
                        'condition' => $isCarte,
                        'opts' => ['min' => 5, 'max' => 19, 'step' => 1, 'default' => 15],
                    ],

                    // Ardoise : une liste libellé / détail / prix, en ardoise verte ou en fiche claire.
                    [
                        'name' => 'ardoiseStyle',
                        'type' => 'select',
                        'label' => 'Apparence',
                        'info' => 'Ardoise : fond vert, comme une pancarte (menu du jour). Fiche : fond clair (horaires, infos pratiques).',
                        'width' => '1-2',
                        'condition' => $isArdoise,
                        'opts' => ['default' => 'ardoise', 'options' => [
                            ['value' => 'ardoise', 'label' => 'Ardoise (fond vert)'],
                            ['value' => 'fiche', 'label' => 'Fiche (fond clair)'],
                        ]],
                    ],
                    [
                        'name' => 'ardoiseLignes',
                        'type' => 'set',
                        'label' => 'Lignes',
                        'info' => 'Une ligne par plat, horaire ou tarif, dans l’ordre d’affichage. Le prix est facultatif.',
                        'width' => '1-1',
                        'condition' => $isArdoise,
                        'multiple' => true,
                        'opts' => [
                            'display' => '${data.libelle || \'Ligne\'}',
                            'fields' => [
                                ['name' => 'libelle', 'type' => 'text', 'label' => 'Libellé', 'width' => '1-2',
                                    'info' => 'En gras. Ex. : « Plat du jour », « Horaires ».', 'opts' => ['maxlength' => 80]],
                                ['name' => 'prix', 'type' => 'text', 'label' => 'Prix', 'width' => '1-2',
                                    'info' => 'Facultatif, écrit tel quel, à droite. Ex. : « 12 € ».', 'opts' => ['maxlength' => 30]],
                                ['name' => 'detail', 'type' => 'text', 'label' => 'Détail', 'width' => '1-1',
                                    'info' => 'Facultatif, sous le libellé. Ex. : « caillette ardéchoise, frites maison ».', 'opts' => ['maxlength' => 200]],
                            ],
                        ],
                    ],
                    [
                        'name' => 'ardoiseNote',
                        'type' => 'text',
                        'label' => 'Note en bas de l’ardoise',
                        'info' => 'Facultatif. Ex. : « Le menu change chaque jour. »',
                        'width' => '1-1',
                        'condition' => $isArdoise,
                        'opts' => ['maxlength' => 200],
                    ],
                    // ── fin CAMPING LES CHÊNES VERTS ─────────────────────────────
                ],
            ],
        ],

        // ── Référencement ─────────────────────────────────────────────────
        [
            'name' => 'seoTitre',
            'type' => 'text',
            'label' => 'Titre dans les résultats de recherche',
            'info' => "Apparaît en bleu dans Google et dans l'onglet du navigateur. Vide, le titre de la page est repris.",
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Référencement',
            'width' => '1-1',
            'opts' => ['maxlength' => 60, 'showCount' => true],
        ],
        [
            'name' => 'seoDescription',
            'type' => 'text',
            'label' => 'Résumé dans les résultats de recherche',
            'info' => 'Apparaît sous le titre dans Google. Environ 155 caractères.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Référencement',
            'width' => '1-1',
            'opts' => ['multiline' => true, 'maxlength' => 160, 'showCount' => true],
        ],
    ],
];
