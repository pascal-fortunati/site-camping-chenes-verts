<?php

/**
 * L'accueil du contenu, à la place de celui de Cockpit : les informations du site, les listes avec leurs chiffres et
 * les dernières modifications, dans l'esprit du tableau de bord. L'administrateur garde la vue de Cockpit (?cockpit=1).
 *
 * @return string le HTML de la page
 */
return function (Lime\App $app): string {

    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $route = static fn (string $chemin): string => $app->routeUrl($chemin);
    $content = $app->module('content');
    $acl = $app->helper('acl');
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $jour = static fn (int $t): string => date('j', $t).(date('j', $t) === '1' ? 'er' : '').' '.$mois[(int) date('n', $t) - 1];
    $maintenant = time();
    $quand = static function (int $t) use ($maintenant, $jour): string {
        $il = $maintenant - $t;
        return $il < 3600 ? 'il y a '.max(1, intdiv($il, 60)).' min' : ($il < 86400 ? 'il y a '.intdiv($il, 3600).' h' : 'le '.$jour($t));
    };
    $pluriel = static fn (int $n, string $mot): string => $n.' '.$mot.($n > 1 ? 's' : '');

    $icones = [
        'settings' => 'badge', 'saison' => 'event_seat', 'legal' => 'gavel', 'menu' => 'menu_open',
        'pages' => 'description', 'articles' => 'newspaper', 'messages' => 'mail',
    ];
    $ordre = array_flip(array_keys($icones));

    $modeles = array_filter($content->models(), static fn (array $m): bool => $acl->isAllowed("content/{$m['name']}/read"));
    uasort($modeles, static fn (array $a, array $b): int => ($ordre[$a['name']] ?? 99) <=> ($ordre[$b['name']] ?? 99));

    $carte = static fn (string $icone, string $titre, string $texte, string $detail, string $lien, string $ton = '') =>
        "<a class=\"tdb-raccourci ctn-modele {$ton}\" href=\"{$lien}\"><span class=\"tdb-raccourci__icone\"><icon>{$icone}</icon></span>"
        ."<span><b>{$titre}</b><span>{$texte}</span>".($detail !== '' ? "<span class=\"ctn-modele__detail\">{$detail}</span>" : '')."</span>"
        ."<icon class=\"tdb-raccourci__fleche\">arrow_forward</icon></a>";

    $fiches = '';
    $listes = '';
    $recents = [];
    foreach ($modeles as $nom => $m) {
        $icone = $icones[$nom] ?? ($m['type'] === 'singleton' ? 'tune' : 'folder');
        $titre = $e($m['label'] ?: $nom);
        $texte = $e($m['info'] ?? '');

        if ($m['type'] === 'singleton') {
            $item = $content->item($nom) ?? [];
            $detail = !empty($item['_modified']) ? 'Modifié '.$quand((int) $item['_modified']) : 'Pas encore rempli';
            $fiches .= $carte($icone, $titre, $texte, $e($detail), $route('/content/singleton/item/'.$nom));
            continue;
        }

        $items = $content->items($nom, ['fields' => ['titre' => 1, 'nom' => 1, 'lu' => 1, '_state' => 1, '_modified' => 1, '_id' => 1], 'sort' => ['_modified' => -1]]);
        $total = count($items);
        $ton = '';
        if ($nom === 'messages') {
            $nonLus = count(array_filter($items, static fn (array $i): bool => empty($i['lu'])));
            $detail = $nonLus ? $pluriel($nonLus, 'non lu').' sur '.$total : ($total ? 'Tous lus · '.$total.' au total' : 'Aucun message pour l’instant');
            $ton = $nonLus ? 'ctn-modele--alerte' : '';
        } else {
            $horsLigne = count(array_filter($items, static fn (array $i): bool => ($i['_state'] ?? 0) !== 1));
            $mot = $nom === 'pages' ? 'page' : ($nom === 'articles' ? 'actualité' : 'élément');
            $detail = $total ? $pluriel($total, $mot).($horsLigne ? ' · '.$horsLigne.' hors ligne' : ' · tout est en ligne') : 'Aucune '.$mot.' pour l’instant';
            foreach (array_slice($items, 0, 6) as $i) {
                $recents[] = ['modele' => $nom, 'libelle' => $m['label'] ?: $nom, 'type' => $m['type']] + $i;
            }
        }
        $listes .= $carte($icone, $titre, $texte, $e($detail), $route("/content/{$m['type']}/items/{$nom}"), $ton);
    }

    usort($recents, static fn (array $a, array $b): int => ($b['_modified'] ?? 0) <=> ($a['_modified'] ?? 0));
    $lignes = '';
    foreach (array_slice($recents, 0, 7) as $r) {
        $lignes .= '<a class="tdb-ligne" href="'.$route("/content/{$r['type']}/item/{$r['modele']}/{$r['_id']}").'">'
            .'<span class="tdb-ligne__avatar tdb-ligne__avatar--page"><icon>'.($icones[$r['modele']] ?? 'description').'</icon></span>'
            .'<span class="tdb-ligne__texte"><b>'.$e($r['titre'] ?? $r['nom'] ?? 'Sans titre').'</b><span>'.$e($r['libelle'])
            .(($r['_state'] ?? 1) !== 1 ? ' · hors ligne' : '').'</span></span>'
            .'<span class="tdb-ligne__date">'.$e($quand((int) ($r['_modified'] ?? 0))).'</span></a>';
    }
    if ($lignes === '') {
        $lignes = '<p class="tdb-vide"><icon>history</icon>Rien n’a encore été modifié.</p>';
    }

    $identite = $content->item('settings') ?? [];
    $medias = rtrim((string) $app->fileStorage->getURL('uploads://'), '/');
    $photo = !empty($identite['imagePartage']['path']) ? $medias.$identite['imagePartage']['path'] : '';
    $fond = $photo !== '' ? ' style="background-image: linear-gradient(110deg, rgb(24 58 35 / 92%) 0%, rgb(24 58 35 / 70%) 45%, rgb(24 58 35 / 15%) 100%), url(\''.$e($photo).'\')"' : '';

    $admin = $acl->isAllowed('content/:models/manage')
        ? '<a class="ctn-admin" href="'.$route('/content').'?cockpit=1"><icon>schema</icon>Gérer les modèles</a>'
        : '';

    return '<div class="camping-tableau camping-contenu">'
        .'<section class="tdb-accueil tdb-accueil--page"'.$fond.'>'
        .'<h1 class="tdb-accueil__titre">Le contenu du site</h1>'
        .'<p class="tdb-accueil__phrase">Choisissez ce que vous voulez modifier : tout ce qui s’affiche sur le site se règle ici.</p>'
        .$admin
        .'</section>'
        .'<div class="tdb-grille ctn-grille">'
        .'<div class="ctn-colonne">'
        .($fiches !== '' ? '<section class="tdb-carte"><h2><icon>info</icon>Les informations du site</h2><div class="tdb-raccourcis">'.$fiches.'</div></section>' : '')
        .($listes !== '' ? '<section class="tdb-carte"><h2><icon>list_alt</icon>Les listes</h2><div class="tdb-raccourcis">'.$listes.'</div></section>' : '')
        .'</div>'
        .'<section class="tdb-carte"><h2><icon>history</icon>Dernières modifications</h2>'.$lignes.'</section>'
        .'</div></div>';
};
