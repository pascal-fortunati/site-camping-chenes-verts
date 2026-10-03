<?php

/**
 * Recherche dans le contenu (fiches, éléments des collections) et les médias, selon les droits du compte.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  Lime\App $app
 * @param  string   $q   texte cherché
 * @return list<array{groupe: string, titre: string, detail: string, lien: string, icone: string}>
 */
return function (Lime\App $app, string $q): array {

    // Sans accents (si intl est présente) ni majuscules.
    $plat = static fn (string $t): string => mb_strtolower(class_exists('Normalizer')
        ? (string) preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($t, \Normalizer::FORM_D) ?: $t)
        : $t);
    $mots = array_values(array_filter(preg_split('/\s+/', $plat(trim($q))) ?: [], static fn (string $m): bool => $m !== ''));
    if ($mots === [] || mb_strlen(implode('', $mots)) < 2) {
        return [];
    }
    $trouve = static function (string $texte) use ($mots, $plat): bool {
        $texte = $plat($texte);
        foreach ($mots as $m) {
            if (!str_contains($texte, $m)) {
                return false;
            }
        }
        return true;
    };

    // Le texte d'une valeur de champ, quelle qu'elle soit (texte, texte riche, liste, sections d'une page).
    $texte = static function (mixed $v) use (&$texte): string {
        if (is_string($v)) {
            // Adresse interne ou réglage d'affichage : pas du texte.
            if (preg_match('#^(/[\w/-]*|gauche|droite|centre|haut|bas|clair|sombre)$#u', trim($v))) {
                return '';
            }
            return trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $v))));
        }
        if (is_array($v)) {
            // Un média ou un lien vers un autre élément : ses métadonnées (empreinte, couleurs, chemins) ne sont pas du texte.
            if (isset($v['path'], $v['mime']) || isset($v['_model'])) {
                return '';
            }
            // Clés techniques ignorées.
            $ignorer = ['type', 'component', 'lien', 'url', 'href', 'slug', 'id', 'style', 'variante', 'icone', 'path', 'mime'];
            return implode(' ', array_filter(array_map($texte, array_filter($v, static fn ($k): bool => !is_string($k) || ($k[0] !== '_' && !in_array($k, $ignorer, true)), ARRAY_FILTER_USE_KEY))));
        }
        return '';
    };
    // Un extrait autour du premier mot trouvé.
    $extrait = static function (string $t) use ($mots, $plat): string {
        $pos = mb_strpos($plat($t), $mots[0]);
        if ($pos === false) {
            return mb_substr($t, 0, 90);
        }
        $debut = max(0, $pos - 35);
        return ($debut > 0 ? '…' : '').mb_substr($t, $debut, 100).(mb_strlen($t) > $debut + 100 ? '…' : '');
    };

    $content = $app->module('content');
    $acl = $app->helper('acl');
    $presenter = include __DIR__.'/modeles.php';
    $resultats = [];

    foreach ($content->models() as $nom => $m) {
        if (!$acl->isAllowed("content/{$nom}/read")) {
            continue;
        }
        $libelle = $m['label'] ?: $nom;
        $icone = $presenter($m)['icone'];
        $champs = array_column($m['fields'] ?? [], 'label', 'name');

        if ($m['type'] === 'singleton') {
            $item = $content->item($nom) ?? [];
            $detail = '';
            foreach ($champs as $champ => $etiquette) {
                $valeur = $texte($item[$champ] ?? '');
                if ($valeur !== '' && $trouve($valeur)) {
                    $detail = ($etiquette ?: $champ).' : '.$extrait($valeur);
                    break;
                }
            }
            if ($detail !== '' || $trouve($libelle)) {
                $resultats[] = ['groupe' => 'Informations du site', 'titre' => $libelle, 'detail' => $detail ?: ($m['info'] ?? ''), 'lien' => $app->routeUrl("/content/singleton/item/{$nom}"), 'icone' => $icone];
            }
            continue;
        }

        $trouves = [];
        foreach ($content->items($nom, ['filter' => (include __DIR__.'/filtre.php')($app, $nom), 'sort' => ['_modified' => -1]]) as $item) {
            $titre = (string) ($item['titre'] ?? $item['title'] ?? $item['nom'] ?? $item['name'] ?? 'Sans titre');
            $tout = $texte(array_intersect_key($item, $champs));
            if (!$trouve($titre.' '.$tout)) {
                continue;
            }
            $detail = $trouve($titre) ? (!empty($item['slug']) ? '/'.$item['slug'] : $extrait($tout)) : $extrait($tout);
            if ($nom === 'pages' && ($item['_state'] ?? 1) !== 1) {
                $detail .= ' · hors ligne';
            }
            $trouves[] = ['groupe' => $libelle, 'titre' => $titre, 'detail' => $detail, 'lien' => $app->routeUrl("/content/{$m['type']}/item/{$nom}/{$item['_id']}"), 'icone' => $icone, 'rang' => $trouve($titre) ? 0 : 1];
        }
        // Ceux dont le titre correspond d'abord, puis les autres ; huit au plus.
        usort($trouves, static fn (array $a, array $b): int => $a['rang'] <=> $b['rang']);
        foreach (array_slice($trouves, 0, 8) as $r) {
            unset($r['rang']);
            $resultats[] = $r;
        }
    }

    $n = 0;
    foreach ($app->dataStorage->find('assets', ['fields' => ['title' => 1, 'description' => 1, 'tags' => 1, 'type' => 1, 'size' => 1, 'width' => 1, 'height' => 1], 'sort' => ['_created' => -1]])->toArray() as $a) {
        if (!$trouve(implode(' ', [$a['title'] ?? '', $a['description'] ?? '', implode(' ', (array) ($a['tags'] ?? []))]))) {
            continue;
        }
        $resultats[] = [
            'groupe' => 'Médias',
            'titre' => (string) ($a['title'] ?? ''),
            'detail' => !empty($a['width']) ? $a['width'].' × '.$a['height'] : (string) ($a['type'] ?? ''),
            'lien' => $app->routeUrl('/assets').'?image='.$a['_id'],
            'icone' => ($a['type'] ?? '') === 'image' ? 'image' : 'description',
        ];
        if (++$n >= 8) {
            break;
        }
    }

    return $resultats;
};
