<?php

/**
 * CAMPING LES CHÊNES VERTS — l'administration aux couleurs du site, toujours en clair : nom, logo, couleur
 * principale et image de partage lus dans « Identité du site » ; palette, polices et en-tête dans assets/.
 * Propre à ce site (le module AdminClient, lui, est proposé au socle).
 */

$this->on('before', function () {

    if (str_starts_with((string) ($this->request->route ?? ''), '/api')) {
        return;
    }

    // Toujours le thème clair, celui du site. Le choix enregistré dans le profil n'est pas modifié.
    $this->set('theme/default', 'light');
    $user = $this->helper('auth')->getUser();
    if ($user && ($user['theme'] ?? 'light') !== 'light') {
        $this->helper('auth')->setUser(array_merge($user, ['theme' => 'light']), false);
    }

    $identite = $this->module('content')->item('settings') ?? [];
    $couleur = strtoupper(trim((string) ($identite['couleurPrincipale'] ?? '')));
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
    $signature = substr(md5(implode('|', [$couleur, $logo, $image, $medias])), 0, 10);
    $css = "{$dossier}/theme-{$signature}.css";

    if (!is_file($css)) {
        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }
        foreach (glob("{$dossier}/*") ?: [] as $ancien) {
            unlink($ancien);
        }

        $regles = [];
        if ($lisible($couleur)) {
            $regles[] = "html[data-theme]:root { --kiss-color-primary: {$couleur}; --camping-vert: {$couleur}; }";
        }
        if ($image !== '' && $medias !== '') {
            $regles[] = "html[data-theme]:has(.auth-wrapper) { background: linear-gradient(rgb(24 58 35 / 45%), rgb(24 58 35 / 45%)), #183A23 url(\"{$medias}{$image}\") center / cover no-repeat fixed !important; }";
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

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:header') {
        $assets[] = 'admincamping:assets/palette.css';

        if ($theme = $this->retrieve('admincamping.theme')) {
            $assets[] = $theme;
        }
    }

    if ($context === 'app:footer') {
        $assets[] = ['src' => 'admincamping:assets/icones.js', 'type' => 'module', 'position' => 'footer'];
    }
});
