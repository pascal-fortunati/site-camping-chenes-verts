<?php

/**
 * Présentation d'un modèle dans l'administration, réglée par sa clé « admin » :
 *
 *     'admin' => ['libelle' => 'Saison et places', 'icone' => 'event_seat', 'groupe' => 'quotidien', 'ordre' => 10,
 *                 'element' => 'page', 'elements' => 'pages', 'nouveau' => 'Nouvelle page',
 *                 'resume' => ['{email}', '{arrivee} → {depart}']],
 *
 * « resume » : la ligne de détail d'un élément dans les listes, chaque morceau affiché si tous ses champs sont remplis.
 * « publication » : false pour une collection jamais publiée (messages reçus, demandes) : ni pastille « En ligne »
 * ni carte de publication, et la liste se trie par date de réception. Par défaut, faux pour « messages » seulement.
 * Sans réglage : une collection va dans « Au quotidien », une fiche unique dans « Le site ».
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  array $modele définition du modèle (Cockpit)
 * @return array{libelle: string, icone: string, groupe: string, titreGroupe: string, ordre: int, element: string, elements: string, nouveau: string, resume: list<string>, publication: bool}
 */
return static function (array $modele): array {

    $admin = is_array($modele['admin'] ?? null) ? $modele['admin'] : [];
    $singleton = ($modele['type'] ?? '') === 'singleton';
    $groupe = in_array($admin['groupe'] ?? null, ['quotidien', 'site'], true) ? $admin['groupe'] : ($singleton ? 'site' : 'quotidien');

    return [
        'libelle' => (string) ($admin['libelle'] ?? (($modele['label'] ?? '') ?: ($modele['name'] ?? ''))),
        'icone' => (string) ($admin['icone'] ?? ($singleton ? 'tune' : 'folder')),
        'groupe' => $groupe,
        'titreGroupe' => $groupe === 'quotidien' ? 'Au quotidien' : 'Le site',
        'ordre' => (int) ($admin['ordre'] ?? 50),
        'element' => (string) ($admin['element'] ?? 'élément'),
        'elements' => (string) ($admin['elements'] ?? 'éléments'),
        'nouveau' => (string) ($admin['nouveau'] ?? 'Ajouter'),
        'resume' => array_values(array_filter((array) ($admin['resume'] ?? []), 'is_string')),
        'publication' => (bool) ($admin['publication'] ?? (($modele['name'] ?? '') !== 'messages')),
    ];
};
