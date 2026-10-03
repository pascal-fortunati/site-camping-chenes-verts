<?php

/**
 * Pages d'erreur 401, 404 et 500, aux couleurs du site.
 *
 * @var int $code code HTTP
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$code = (int) ($code ?? 404);
$messages = [
    401 => ['lock', 'Accès refusé', 'Votre compte ne permet pas d’ouvrir cette page.'],
    404 => ['explore_off', 'Page introuvable', 'Cette page n’existe pas, ou elle a été déplacée.'],
    500 => ['construction', 'Une erreur est survenue', 'Réessayez dans un instant. Si le problème continue, prévenez la personne qui s’occupe du site.'],
];
[$icone, $titre, $texte] = $messages[$code] ?? $messages[404];
$connecte = (bool) $this->helper('auth')->getUser();
$feuilles = ['dashboard:assets/dashboard.css'];
if ($theme = $this->retrieve('dashboard.theme')) {
    $feuilles[] = $theme;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= $this->escape($this->helper('theme')->theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $this->escape($titre.' · '.$this['app.name']) ?></title>
    <link rel="icon" href="<?= $this->escape($this->helper('theme')->favicon()) ?>">
    <?= $this->assets(array_merge(['app:assets/css/app.css'], $feuilles), $this->retrieve('app.version')) ?>
</head>
<body class="erreur-page">
    <main class="erreur">
        <img class="erreur__logo" src="<?= $this->escape($this->helper('theme')->logo()) ?>" alt="" width="64" height="64">
        <span class="erreur__icone" aria-hidden="true"><icon><?= $icone ?></icon></span>
        <p class="erreur__code"><?= $code ?></p>
        <h1><?= $titre ?></h1>
        <p class="erreur__texte"><?= $texte ?></p>
        <div class="erreur__actions">
            <a class="kiss-button" href="javascript:history.back()"><icon>arrow_back</icon>Revenir</a>
            <a class="kiss-button kiss-button-primary" href="<?= $this->escape($this->routeUrl($connecte ? '/' : '/auth/login')) ?>"><icon><?= $connecte ? 'space_dashboard' : 'login' ?></icon><?= $connecte ? 'Tableau de bord' : 'Se connecter' ?></a>
        </div>
    </main>
</body>
</html>
