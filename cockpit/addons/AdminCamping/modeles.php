<?php

/**
 * Comment chaque modèle se présente dans l'administration : son nom court, son icône (Material) et son groupe dans
 * la barre latérale. Réglé dans le fichier du modèle lui-même, par une clé « admin » :
 *
 *     'admin' => ['libelle' => 'Saison et places', 'icone' => 'event_seat', 'groupe' => 'quotidien'],
 *
 * Sans ce réglage, tout site s'en sort : une liste (collection) va dans « Au quotidien », une fiche unique
 * (singleton) dans « Le site », avec le nom du modèle et une icône générique.
 *
 * Pour une liste, « element », « elements » et « nouveau » donnent les mots du compte et du bouton de création
 * (« page », « pages », « Nouvelle page ») ; sinon « élément », « éléments », « Ajouter ».
 *
 * @return array{libelle: string, icone: string, groupe: string, titreGroupe: string, ordre: int, element: string, elements: string, nouveau: string}
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
    ];
};
