<?php

/**
 * L'en-tête rendu par le serveur : la recherche à gauche ; à droite « Voir le site » (Passerelle), le choix du
 * thème clair ou sombre et le compte. Le nom du site reste dans la page, masqué à l'œil : Passerelle place ses
 * boutons juste après lui. Si Cockpit change ce balisage, l'en-tête d'origine est gardé tel quel.
 *
 * @return string le HTML retouché, ou tel quel
 */
return function (Lime\App $app, string $html, bool $sombre): string {

    if (!preg_match('#(<app-header\b[^>]*>\s*<kiss-container\b[^>]*>)(.*?)(</kiss-container>\s*</app-header>)#s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    [$ouverture, $contenu, $fermeture] = [$m[1][0], $m[2][0], $m[3][0]];

    $morceau = static fn (string $motif): string => preg_match($motif, $contenu, $t) ? $t[0] : '';
    $logo = $morceau('#<a\b[^>]*>\s*<img class="app-logo\b.*?</a>#s');
    $tiroir = $morceau('#<a\b[^>]*href="\#app-offcanvas"[^>]*>.*?</a>#s') !== ''
        ? '<a class="entete__tiroir kiss-hidden@m" href="#app-offcanvas" kiss-offcanvas aria-label="Menu"><icon aria-hidden="true">menu</icon></a>'
        : '';
    $compte = $morceau('#<a\b[^>]*href="\#app-account-menu"[^>]*>.*?</a>#s');
    $nom = $morceau('#<span class="kiss-text-bold">.*?</span>#s');
    if ($compte === '' || $nom === '') {
        return $html;
    }

    // Ce que Cockpit ou d'autres extensions ajoutent (licence, espace, bloc app.layout.header) est gardé.
    $ajouts = '';
    if (preg_match('#<app-license>.*?</app-license>#s', $contenu, $t)) {
        $ajouts .= $t[0];
    }
    if (preg_match('#<div class="kiss-flex-1 kiss-margin-start"></div>(.*?)<a\b[^>]*href="\#app-account-menu"#s', $contenu, $t)) {
        $ajouts .= trim($t[1]);
    }

    $theme = '<button type="button" class="entete__theme" data-entete-theme aria-pressed="'.($sombre ? 'true' : 'false').'" aria-label="Thème sombre" kiss-tooltip="bottom">'
        .'<icon aria-hidden="true">'.($sombre ? 'light_mode' : 'dark_mode').'</icon></button>';

    $nouveau = '<div class="entete__gauche">'.$logo.$tiroir
        .'<button type="button" class="entete__recherche" app-search aria-label="Rechercher">'
        .'<icon aria-hidden="true">search</icon><span class="entete__recherche-texte">Rechercher une page, une image…</span><kbd>Ctrl K</kbd></button>'
        .'</div>'
        .'<div class="entete__droite">'.$ajouts
        .'<div class="entete__site">'.str_replace('class="kiss-text-bold"', 'class="kiss-text-bold entete__nom"', $nom).'</div>'
        .$theme
        .str_replace('class="kiss-margin-start"', 'class="entete__compte"', $compte)
        .'</div>';

    $ouverture = str_replace('<kiss-container', '<kiss-container data-entete="serveur"', $ouverture);

    return substr($html, 0, $m[0][1]).$ouverture.$nouveau.$fermeture.substr($html, $m[0][1] + strlen($m[0][0]));
};
