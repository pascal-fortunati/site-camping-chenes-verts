<?php

/**
 * CAMPING LES CHÊNES VERTS — l'administration aux couleurs du site, en clair ou en sombre : nom, logo, couleurs
 * et image de partage lus dans « Identité du site » ; les deux palettes dans theme.php, les styles dans assets/.
 * Propre à ce site (le module AdminClient, lui, est proposé au socle).
 */

$this->on('before', function () {

    if (str_starts_with((string) ($this->request->route ?? ''), '/api')) {
        return;
    }

    // Clair par défaut, sombre si on l'a choisi dans l'en-tête (cookie). Le choix du profil n'est pas modifié.
    $theme = ($_COOKIE['admincamping-theme'] ?? '') === 'sombre' ? 'dark' : 'light';
    $this->set('theme/default', $theme);
    $user = $this->helper('auth')->getUser();
    if ($user && ($user['theme'] ?? '') !== $theme) {
        $this->helper('auth')->setUser(array_merge($user, ['theme' => $theme]), false);
    }

    $identite = $this->module('content')->item('settings') ?? [];
    $couleur = strtoupper(trim((string) ($identite['couleurPrincipale'] ?? '')));
    $texte = strtoupper(trim((string) ($identite['couleurTexte'] ?? '')));
    $logo = (string) ($identite['logo']['path'] ?? '');
    $image = (string) ($identite['imagePartage']['path'] ?? '');

    if (!empty($identite['nom'])) {
        $this['app.name'] = $identite['nom'];
    }

    // Même règle que le site : une couleur sur laquelle un texte blanc serait illisible n'est pas reprise.
    $lisible = static function (string $hex): bool {
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return false;
        }
        $canal = static function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map(static fn (string $p): int => (int) hexdec($p), str_split(substr($hex, 1), 2));

        return 1.05 / (0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b) + 0.05) >= 4.5;
    };

    // Cockpit n'accepte qu'un logo qui soit un de ses fichiers, et la page de connexion n'a pas de crochet pour
    // un style en ligne : les deux sont écrits dans generated/, sous un nom qui change avec l'identité.
    $dossier = __DIR__.'/generated';
    $uploads = rtrim((string) $this->path('#uploads:'), '/\\');
    $medias = rtrim((string) $this->fileStorage->getURL('uploads://'), '/');
    $signature = substr(md5(implode('|', [$couleur, $texte, $logo, $image, $medias, filemtime(__DIR__.'/theme.php')])), 0, 10);
    $css = "{$dossier}/theme-{$signature}.css";

    if (!is_file($css)) {
        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }
        foreach (glob("{$dossier}/*") ?: [] as $ancien) {
            unlink($ancien);
        }

        $palette = include __DIR__.'/theme.php';
        $regles = [$palette($lisible($couleur) ? $couleur : '#2B5A16', preg_match('/^#[0-9A-F]{6}$/', $texte) ? $texte : '#1F2A22')];
        if ($image !== '' && $medias !== '') {
            $regles[] = "html[data-theme]:root { --admin-photo: url(\"{$medias}{$image}\"); }";
            $regles[] = "html[data-theme]:has(.auth-wrapper) { background: linear-gradient(var(--admin-voile-photo), var(--admin-voile-photo)), var(--admin-barre) var(--admin-photo) center / cover no-repeat fixed !important; }";
        }
        file_put_contents($css, implode("\n", $regles)."\n");

        if ($logo !== '' && is_file($uploads.$logo)) {
            copy($uploads.$logo, "{$dossier}/logo-{$signature}.".pathinfo($logo, PATHINFO_EXTENSION));
        }
    }

    foreach (glob("{$dossier}/logo-{$signature}.*") ?: [] as $copie) {
        $this->helper('theme')->logo('admincamping:generated/'.basename($copie));
        $this->helper('theme')->favicon('admincamping:generated/'.basename($copie));
    }

    $this->set('admincamping.theme', 'admincamping:generated/'.basename($css));
});

// Quand « Réglages » ne contiendrait que le compte (le client), la barre mène droit au compte et /system y renvoie.
$this->on('before', function () {
    if (!$this->helper('auth')->getUser()) {
        return;
    }
    $permis = 0;
    foreach ($this->helper('settings')->groups(true) as $elements) {
        foreach ($elements as $e) {
            if (($e['route'] ?? '') !== '/system/users/user' && (!isset($e['permission']) || $this->helper('acl')->isAllowed($e['permission']))) {
                $permis++;
            }
        }
    }
    if ($permis === 0) {
        $this->set('admincamping.compteSeul', true);
        if (rtrim((string) $this->request->route, '/') === '/system') {
            try {
                $this->reroute('/system/users/user');
            } catch (\Lime\StopException) {
                // La page n'est pas construite ; Lime envoie la redirection en fin de requête.
            }
        }
    }
});

// La barre latérale arrive construite dans la page (barre-laterale.php) : rien ne bouge au chargement.
$this->on('after', function () {
    if (is_string($this->response->body ?? null) && str_contains($this->response->body, 'app-container-aside-menu')) {
        $retoucher = include __DIR__.'/barre-laterale.php';
        $entete = include __DIR__.'/entete.php';
        $this->response->body = $entete($this, $retoucher($this, $this->response->body), ($_COOKIE['admincamping-theme'] ?? '') === 'sombre');
    }
});

// Le tableau de bord du client remplace celui de Cockpit (ses blocs par défaut sont retirés).
$this->on('app.dashboard.widgets', function ($widgets) {
    $construire = include __DIR__.'/tableau-de-bord.php';
    $widgets->exchangeArray([[
        'name' => 'camping-tableau',
        'area' => 'primary',
        'prio' => 100,
        'html' => $construire($this),
    ]]);
}, -100);

// L'accueil du contenu suit le tableau de bord ; l'administrateur garde la vue de Cockpit pour gérer les modèles.
$this->on('app.render.view/content:views/index.php', function (&$view) {
    if (!$this->param('cockpit')) {
        $view = 'admincamping:views/contenu.php';
    }
});

// La médiathèque remplace la page Images de Cockpit ; elle s'appuie sur les mêmes routes /assets/*.
$this->on('app.render.view/assets:views/index.php', function (&$view) {
    if (!$this->param('cockpit')) {
        $view = 'admincamping:views/images.php';
    }
});

$this->bind('/admincamping/usages', function () {
    $this->response->mime = 'json';
    if (!$this->helper('auth')->getUser()) {
        $this->response->status = 403;
        return ['erreur' => 'Non connecté'];
    }
    $usages = include __DIR__.'/usages.php';

    return $usages($this);
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:header') {
        $assets[] = 'admincamping:assets/palette.css';
        $assets[] = 'admincamping:assets/mediatheque.css';

        if (($_COOKIE['admincamping-sidebar'] ?? '') === 'reduite') {
            $assets[] = 'admincamping:assets/sidebar-reduite.css';
        }

        if ($theme = $this->retrieve('admincamping.theme')) {
            $assets[] = $theme;
        }
    }

    if ($context === 'app:footer') {
        $assets[] = ['src' => 'admincamping:assets/icones.js', 'type' => 'module', 'position' => 'footer'];
        $assets[] = ['src' => 'admincamping:assets/sidebar.js', 'type' => 'module', 'position' => 'footer'];
        $assets[] = ['src' => 'admincamping:assets/entete.js', 'type' => 'module', 'position' => 'footer'];
    }
});
