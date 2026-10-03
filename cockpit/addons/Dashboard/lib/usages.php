<?php

/**
 * Où chaque média est utilisé : son identifiant est cherché dans toutes les fiches lisibles par le compte.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @return array<string, list<array{libelle: string, lien: string}>> les usages, par identifiant de média
 */
return function (Lime\App $app): array {

    $content = $app->module('content');
    $acl = $app->helper('acl');
    $ids = array_column($app->dataStorage->find('assets', ['fields' => ['_id' => 1]])->toArray(), '_id');
    $usages = array_fill_keys($ids, []);

    $noter = static function (array $item, string $libelle, string $lien) use ($ids, &$usages): void {
        $texte = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        foreach ($ids as $id) {
            if (str_contains($texte, $id)) {
                $usages[$id][] = ['libelle' => $libelle, 'lien' => $lien];
            }
        }
    };

    foreach ($content->models() as $nom => $m) {
        if (!$acl->isAllowed("content/{$nom}/read")) {
            continue;
        }
        $modele = $m['label'] ?: $nom;

        if ($m['type'] === 'singleton') {
            if ($item = $content->item($nom)) {
                $noter($item, $modele, $app->routeUrl('/content/singleton/item/'.$nom));
            }
            continue;
        }

        foreach ($content->items($nom, ['filter' => (include __DIR__.'/filtre.php')($app, $nom)]) as $item) {
            $titre = $item['titre'] ?? $item['title'] ?? $item['nom'] ?? $item['name'] ?? $modele;
            $libelle = is_string($titre) && $titre !== $modele ? $titre.' ('.$modele.')' : $modele;
            $noter($item, $libelle, $app->routeUrl("/content/{$m['type']}/item/{$nom}/{$item['_id']}"));
        }
    }

    return $usages;
};
