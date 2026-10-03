<?php

/**
 * Dashboard : l'administration de Cockpit aux couleurs du site (nom, logo, couleurs et photo de « Identité du
 * site »), en clair ou en sombre, en français, la même pour tous les sites du socle et pour tous les rôles.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

/* ── Traduction française ── */

$this->on('app.admin.i18n.load', function ($locale, $i18n) {
    if ($locale === 'fr') {
        $i18n->load('dashboard:i18n/fr.php', 'fr');
    }
});

/* ── Thème : version des fichiers, clair ou sombre, identité du site ── */

$this->on('before', function () {

    if (str_starts_with((string) ($this->request->route ?? ''), '/api')) {
        return;
    }

    // Les fichiers de l'admin sont gardés un an en cache : la version suit le dernier fichier modifié des extensions.
    $dates = array_map('filemtime', glob(dirname(__DIR__).'/*/assets/{,*/}*.{css,js}', GLOB_BRACE) ?: []);
    if ($dates !== [] && !str_contains((string) $this->retrieve('app.version'), '-')) {
        $this->set('app.version', $this->retrieve('app.version').'-'.max($dates));
    }

    $theme = ($_COOKIE['dashboard-theme'] ?? '') === 'sombre' ? 'dark' : 'light';
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

    /**
     * Couleur sur laquelle un texte blanc se lit (4,5:1), comme sur le site.
     *
     * @param  string $hex #RRGGBB
     * @return bool
     */
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

    // Le thème et le logo sont écrits dans generated/ sous un nom qui change avec l'identité.
    $dossier = __DIR__.'/generated';
    $uploads = rtrim((string) $this->path('#uploads:'), '/\\');
    $medias = rtrim((string) $this->fileStorage->getURL('uploads://'), '/');
    $signature = substr(md5(implode('|', [$couleur, $texte, $logo, $image, $medias, filemtime(__DIR__.'/lib/theme.php'), filemtime(__FILE__)])), 0, 10);
    $css = "{$dossier}/theme-{$signature}.css";

    if (!is_file($css)) {
        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }
        foreach (glob("{$dossier}/*") ?: [] as $ancien) {
            unlink($ancien);
        }

        $palette = include __DIR__.'/lib/theme.php';
        $regles = [$palette($lisible($couleur) ? $couleur : '#2B5A16', preg_match('/^#[0-9A-F]{6}$/', $texte) ? $texte : '#1F2A22')];
        if ($image !== '' && $medias !== '') {
            $regles[] = "html[data-theme]:root { --admin-photo: url(\"{$medias}{$image}\"); }";
            $regles[] = "html[data-theme]:has(.auth-wrapper) { background: linear-gradient(var(--admin-voile-photo), var(--admin-voile-photo)), var(--admin-barre) var(--admin-photo) center / cover no-repeat fixed !important; }";
        }
        file_put_contents($css, implode("\n", $regles)."\n");

        // Logo réduit à 192 px en WebP ; sans GD, ou pour un SVG, copié tel quel.
        if ($logo !== '' && is_file($uploads.$logo)) {
            $source = @imagecreatefromstring((string) file_get_contents($uploads.$logo));
            if ($source !== false && function_exists('imagewebp')) {
                [$l, $h] = [imagesx($source), imagesy($source)];
                $ratio = min(1, 192 / max($l, $h));
                $reduit = imagecreatetruecolor(max(1, (int) round($l * $ratio)), max(1, (int) round($h * $ratio)));
                imagealphablending($reduit, false);
                imagesavealpha($reduit, true);
                imagecopyresampled($reduit, $source, 0, 0, 0, 0, imagesx($reduit), imagesy($reduit), $l, $h);
                imagewebp($reduit, "{$dossier}/logo-{$signature}.webp", 86);
            } else {
                copy($uploads.$logo, "{$dossier}/logo-{$signature}.".pathinfo($logo, PATHINFO_EXTENSION));
            }
        }
    }

    foreach (glob("{$dossier}/logo-{$signature}.*") ?: [] as $copie) {
        $this->helper('theme')->logo('dashboard:generated/'.basename($copie));
        $this->helper('theme')->favicon('dashboard:generated/'.basename($copie));
    }

    $this->set('dashboard.theme', 'dashboard:generated/'.basename($css));
});

/* ── Accès : « Réglages » réduit au compte, page Contenu, verrou de son propre compte ── */

$this->on('before', function () {
    $user = $this->helper('auth')->getUser();
    if (!$user) {
        return;
    }
    $acl = $this->helper('acl');
    $route = rtrim((string) $this->request->route, '/');

    $permis = 0;
    foreach ($this->helper('settings')->groups(true) as $elements) {
        foreach ($elements as $e) {
            if (($e['route'] ?? '') !== '/system/users/user' && (!isset($e['permission']) || $acl->isAllowed($e['permission']))) {
                $permis++;
            }
        }
    }
    if ($permis === 0) {
        $this->set('dashboard.compteSeul', true);
    }

    // Sans la gestion des modèles, la page Contenu n'apporte rien : la barre latérale mène à chaque modèle.
    $cible = match (true) {
        $permis === 0 && $route === '/system' => '/system/users/user',
        $route === '/content' && !$acl->isAllowed('content/:models/manage') => '/',
        default => null,
    };
    if ($cible !== null) {
        try {
            $this->reroute($cible);
        } catch (\Lime\StopException) {
            // Lime envoie la redirection en fin de requête.
        }
    }

    if ($route === '/system/users/user') {
        $verrou = $this->helper('admin')->isResourceLocked($user['_id']);
        if ($verrou && ($verrou['user']['_id'] ?? null) === $user['_id']) {
            $this->helper('admin')->unlockResourceId($user['_id']);
        }
    }
});

/* ── Barre latérale, en-tête et habillage des écrans de Cockpit, écrits dans la page ── */

$this->on('after', function () {
    if (is_string($this->response->body ?? null) && str_contains($this->response->body, 'app-container-aside-menu')) {
        $habillage = include __DIR__.'/lib/habillage.php';
        $interface = include __DIR__.'/lib/interface.php';
        $this->response->body = $interface($this, $habillage($this, $this->response->body), ($_COOKIE['dashboard-theme'] ?? '') === 'sombre');
    }
});

/* ── Tableau de bord, à la place des blocs de Cockpit ── */

$this->on('app.dashboard.widgets', function ($widgets) {
    $accueil = include __DIR__.'/lib/accueil.php';
    $widgets->exchangeArray([[
        'name' => 'dashboard-tableau',
        'area' => 'primary',
        'prio' => 100,
        'html' => $accueil($this),
    ]]);
}, -100);

/* ── Écrans remplacés ; « ?cockpit=1 » rend celui de Cockpit ── */

foreach ([
    'assets:views/index.php' => 'dashboard:views/medias.php',
    'content:views/collection/items.php' => 'dashboard:views/liste.php',
    'content:views/collection/item.php' => 'dashboard:views/fiche.php',
    'content:views/singleton/item.php' => 'dashboard:views/fiche.php',
    'system:views/users/user.php' => 'dashboard:views/compte.php',
    'system:views/settings.php' => 'dashboard:views/admin/reglages.php',
    'system:views/users/index.php' => 'dashboard:views/admin/comptes.php',
    'system:views/users/roles/index.php' => 'dashboard:views/admin/roles.php',
    'system:views/users/roles/role.php' => 'dashboard:views/admin/role.php',
    'system:views/api/index.php' => 'dashboard:views/admin/api.php',
    'system:views/api/key.php' => 'dashboard:views/admin/cle.php',
    'system:views/locales/index.php' => 'dashboard:views/admin/langues.php',
    'system:views/locales/locale.php' => 'dashboard:views/admin/langue.php',
    'system:views/info.php' => 'dashboard:views/admin/infos.php',
    'system:views/logs/index.php' => 'dashboard:views/admin/journaux.php',
    'content:views/index.php' => 'dashboard:views/admin/modeles.php',
    'system:views/spaces/index.php' => 'dashboard:views/admin/espaces.php',
    'system:views/spaces/create.php' => 'dashboard:views/admin/espace.php',
    'system:views/worker/index.php' => 'dashboard:views/admin/taches.php',
    'updater:views/index.php' => 'dashboard:views/admin/maj.php',
] as $origine => $remplacement) {
    $this->on("app.render.view/{$origine}", function (&$view) use ($remplacement) {
        if (!$this->param('cockpit')) {
            $view = $remplacement;
        }
    });
}

foreach ([401, 404, 500] as $code) {
    $this->on("app.render.view/app:views/errors/{$code}.php", function (&$view, &$slots) use ($code) {
        $view = 'dashboard:views/erreur.php';
        $slots['code'] = $code;
    });
}

$this->on('app.render.view/app:views/lockedResouce.php', function (&$view) {
    $view = 'dashboard:views/verrou.php';
});

/* ── Routes du Dashboard (JSON, compte connecté) ── */

/**
 * Déclare une route JSON réservée aux comptes connectés.
 *
 * @param  string   $chemin
 * @param  callable $action reçoit les paramètres de la route
 * @return void
 */
$route = function (string $chemin, callable $action): void {
    $this->bind($chemin, function ($params = []) use ($action) {
        $this->response->mime = 'json';
        if (!$this->helper('auth')->getUser()) {
            $this->response->status = 403;
            return ['erreur' => 'Non connecté'];
        }
        return $action($params ?? []);
    });
};

// Reprendre une fiche verrouillée par soi-même (ou par un autre, avec le droit de déverrouiller).
$route('/dashboard/reprendre/:id', function (array $params) {
    $user = $this->helper('auth')->getUser();
    if (!$this->helper('csrf')->isValid('app.csrf', $this->request->server['HTTP_X_CSRF_TOKEN'] ?? null)) {
        $this->response->status = 403;
        return ['erreur' => 'Action non autorisée'];
    }
    $id = (string) ($params['id'] ?? '');
    $meta = $this->helper('admin')->isResourceLocked($id);
    if ($meta && ($meta['user']['_id'] ?? null) !== $user['_id'] && !$this->helper('acl')->isAllowed('app/resources/unlock')) {
        $this->response->status = 403;
        return ['erreur' => 'Fiche verrouillée par une autre personne'];
    }
    $this->helper('admin')->unlockResourceId($id);

    return ['ok' => true];
});

// Les non lus de chaque collection qui a une case « lu », pour la pastille de la barre latérale.
$route('/dashboard/non-lus', function () {
    $resultat = [];
    foreach ($this->module('content')->models() as $nom => $m) {
        $lu = array_filter($m['fields'] ?? [], static fn (array $c): bool => ($c['name'] ?? '') === 'lu' && ($c['type'] ?? '') === 'boolean');
        if ($m['type'] !== 'collection' || !$lu || !$this->helper('acl')->isAllowed("content/{$nom}/read")) {
            continue;
        }
        $nonLus = array_values(array_filter($this->module('content')->items($nom, ['sort' => ['_created' => -1]]), static fn (array $i): bool => empty($i['lu'])));
        $dernier = $nonLus[0] ?? null;
        $resultat[$nom] = [
            'nombre' => count($nonLus),
            'dernier' => $dernier ? [
                'id' => $dernier['_id'],
                'nom' => (string) ($dernier['nom'] ?? $dernier['name'] ?? $dernier['titre'] ?? ''),
                'lien' => $this->routeUrl("/content/collection/item/{$nom}/{$dernier['_id']}"),
            ] : null,
        ];
    }

    return $resultat;
});

$route('/dashboard/recherche', function () {
    return (include __DIR__.'/lib/recherche.php')($this, (string) $this->param('q', ''));
});

$route('/dashboard/usages', function () {
    return (include __DIR__.'/lib/usages.php')($this);
});

/* ── En-tête de page et fichiers ── */

// Polices et logo annoncés tôt : la page ne s'affiche pas d'abord sans icônes ni logo.
$this->on('app.layout.head', function () {
    $e = fn (string $url): string => htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<link rel="preload" href="'.$e($this->helper('theme')->logo()).'" as="image">'."\n";
    foreach ([
        $this->baseUrl('app:assets/fonts/material-icons/material-outline.woff2').'?v=2024-10-20',
        $this->baseUrl('dashboard:assets/fonts/lexend-400.woff2'),
        $this->baseUrl('dashboard:assets/fonts/lexend-700.woff2'),
        $this->baseUrl('dashboard:assets/fonts/fraunces-600.woff2'),
    ] as $url) {
        echo '<link rel="preload" href="'.$e($url).'" as="font" type="font/woff2" crossorigin>'."\n";
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {
    if ($context === 'app:header') {
        $assets[] = 'dashboard:assets/dashboard.css';
        if ($theme = $this->retrieve('dashboard.theme')) {
            $assets[] = $theme;
        }
    }
    if ($context === 'app:footer') {
        $assets[] = ['src' => 'dashboard:assets/dashboard.js', 'type' => 'module', 'position' => 'footer'];
    }
});
