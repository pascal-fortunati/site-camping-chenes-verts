<?php

/**
 * The customer's admin, in French and without the actions their role forbids.
 *
 * - i18n/fr.php: Cockpit ships no French translation. It is loaded whenever
 *   the language is « fr » — the « i18n » setting of config.php, or the
 *   language chosen in the account.
 * - assets/admin.css: labels keep the case they were written in (« Nom du
 *   site », not « Nom Du Site »).
 * - assets/actions-client.js: for every account that is not an administrator,
 *   hides the actions on the structure — edit, clone or delete a model, raw
 *   JSON. Cockpit shows them to everyone and refuses them only once clicked.
 *   Display only: security still rests on the role's permissions.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

/**
 * The admin wears the customer's site: its name, its logo, its main colour,
 * and its sharing image behind the sign-in form — all read from « Identité du
 * site », so changing them there changes the admin too.
 *
 * Cockpit only accepts a logo that is a file of its own, and the sign-in page
 * has no hook for inline styles: both are written to generated/, under a name
 * that changes with the identity, so a browser never keeps a stale one.
 */
$this->on('before', function () {

    if (str_starts_with((string) ($this->request->route ?? ''), '/api')) {
        return;
    }

    $identity = $this->module('content')->item('settings') ?? [];
    $colour = strtoupper(trim((string) ($identity['couleurPrincipale'] ?? '')));
    $logo = (string) ($identity['logo']['path'] ?? '');
    $image = (string) ($identity['imagePartage']['path'] ?? '');

    if (!empty($identity['nom'])) {
        $this['app.name'] = $identity['nom'];
    }

    // Same rule as the site: a colour unreadable as a button background on
    // white text is ignored, and Cockpit keeps its own.
    $readable = static function (string $hex): bool {
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return false;
        }
        $channel = static function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map(static fn (string $p): int => (int) hexdec($p), str_split(substr($hex, 1), 2));
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return 1.05 / ($luminance + 0.05) >= 4.5;
    };

    $folder = __DIR__.'/generated';
    $uploads = rtrim((string) $this->path('#uploads:'), '/\\');
    $mediaUrl = rtrim((string) $this->fileStorage->getURL('uploads://'), '/');
    $signature = substr(md5(implode('|', [$colour, $logo, $image, $mediaUrl])), 0, 10);
    $css = "{$folder}/theme-{$signature}.css";

    if (!is_file($css)) {
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        foreach (glob("{$folder}/*") ?: [] as $old) {
            unlink($old);
        }

        $rules = [];
        if ($readable($colour)) {
            $rules[] = ":root, html, body { --kiss-color-primary: {$colour} !important; }";
        }
        if ($image !== '' && $mediaUrl !== '') {
            $url = $mediaUrl.$image;
            $rules[] = "html:has(.auth-wrapper) { min-height: 100%; background: linear-gradient(rgb(0 0 0 / 35%), rgb(0 0 0 / 35%)), #1f2a22 url(\"{$url}\") center / cover no-repeat fixed !important; }";
            $rules[] = 'html:has(.auth-wrapper) body { background: transparent !important; }';
            $rules[] = 'html:has(.auth-wrapper) bg-fluxanimation { display: none; }';
            $rules[] = '.auth-wrapper .auth-dialog { box-shadow: 0 24px 60px rgb(0 0 0 / 40%); }';
        }
        $rules[] = '.auth-dialog .app-logo { height: 72px !important; }';
        file_put_contents($css, implode("\n", $rules)."\n");

        if ($logo !== '' && is_file($uploads.$logo)) {
            copy($uploads.$logo, "{$folder}/logo-{$signature}.".pathinfo($logo, PATHINFO_EXTENSION));
        }
    }

    foreach (glob("{$folder}/logo-{$signature}.*") ?: [] as $copy) {
        $this->helper('theme')->logo('adminclient:generated/'.basename($copy));
        $this->helper('theme')->favicon('adminclient:generated/'.basename($copy));
    }

    $this->set('adminclient.theme', 'adminclient:generated/'.basename($css));
});

$this->on('app.admin.i18n.load', function ($locale, $i18n) {
    if ($locale === 'fr') {
        $i18n->load('adminclient:i18n/fr.php', 'fr');
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:header') {
        $assets[] = 'adminclient:assets/admin.css';

        if ($theme = $this->retrieve('adminclient.theme')) {
            $assets[] = $theme;
        }
    }

    if ($context === 'app:footer') {
        $user = $this->helper('auth')->getUser();

        if ($user && ($user['role'] ?? '') !== 'admin') {
            $assets[] = ['src' => 'adminclient:assets/actions-client.js', 'type' => 'module', 'position' => 'footer'];
        }
    }
});
