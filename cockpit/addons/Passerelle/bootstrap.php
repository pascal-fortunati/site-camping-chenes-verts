<?php

/**
 * A bridge between the admin and the public site, both ways.
 *
 * Admin → site: a « Voir le site » button in the header, and « Voir cette
 * page » while editing a page (assets/passerelle-admin.js).
 *
 * Site → admin: while someone is signed in, the site shows their avatar with
 * a green dot, linking back to the admin (public/assets/js/passerelle.js).
 * No public file names the admin: its address travels in a cookie set here,
 * in the signed-in person's browser only — visitors get neither cookie nor
 * avatar. Pages stay cacheable: the avatar is added in the browser.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

const PASSERELLE_COOKIE = 'passerelle';

/** The public site's address, from the project .env (SITE_URL); the admin's own host otherwise. */
$passerelleSite = function (): string {
    $env = (string) @file_get_contents(dirname(__DIR__, 4).'/.env');
    if (preg_match('/^SITE_URL=(.*)$/m', $env, $m) && trim($m[1]) !== '') {
        return rtrim(trim($m[1], " \t\"'"), '/');
    }

    return rtrim((string) $this->getSiteUrl(false), '/');
};

$this->on('before', function () {

    $route = (string) ($this->request->route ?? '');
    if (str_starts_with($route, '/api') || $route === '/check-session' || $route === '/app-event-stream') {
        return;
    }

    $user = $this->helper('auth')->getUser();
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $options = ['path' => '/', 'secure' => $https, 'httponly' => false, 'samesite' => 'Lax'];

    if (!$user) {
        if (isset($_COOKIE[PASSERELLE_COOKIE])) {
            setcookie(PASSERELLE_COOKIE, '', ['expires' => time() - 3600] + $options);
        }
        return;
    }

    $nom = trim((string) ($user['name'] ?? '')) ?: (string) ($user['user'] ?? '');
    $valeur = rtrim(strtr(base64_encode((string) json_encode([
        'nom' => $nom,
        // On Windows, dirname() in getSiteUrl() may end the address with a backslash.
        'admin' => rtrim(str_replace('\\', '/', (string) $this->getSiteUrl(true)), '/').'/',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

    // A session cookie: it goes when the browser closes, and on the next admin page after signing out.
    if (($_COOKIE[PASSERELLE_COOKIE] ?? '') !== $valeur) {
        setcookie(PASSERELLE_COOKIE, $valeur, $options);
    }
});

// The site's address and the page being edited, for the admin-side buttons.
$this->bind('/passerelle/infos', function () use ($passerelleSite) {

    $this->response->mime = 'json';

    $infos = ['site' => $passerelleSite(), 'page' => null];
    $id = (string) $this->param('page', '');

    if ($id !== '' && $this->helper('acl')->isAllowed('content/pages/read')) {
        $page = $this->module('content')->item('pages', ['_id' => $id]);
        if ($page && !empty($page['slug'])) {
            $accueil = (string) (getenv('HOME_PAGE_SLUG') ?: 'accueil');
            $infos['page'] = $page['slug'] === $accueil ? '/' : '/'.$page['slug'];
        }
    }

    return $infos;
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer' && $this->helper('auth')->getUser()) {
        $assets[] = ['src' => 'passerelle:assets/passerelle-admin.js', 'type' => 'module', 'position' => 'footer'];
    }
});
