<?php

/**
 * Modules : activer ou désactiver les addons de l'administration, site par site.
 *
 * - Réglages › Modules : la liste des addons présents (fiche addon.json : nom, description, version,
 *   catégorie, dépendances), avec un interrupteur pour chacun. Réservé au droit « modules/manage ».
 * - Dashboard et Modules restent toujours actifs ; un module dont un autre dépend ne peut pas être désactivé.
 * - L'état est écrit dans un fichier JSON que la configuration de Cockpit lit avant de charger les addons
 *   (clé « modules.disabled », prévue par Cockpit) : voir README, « Installation ».
 * - Route d'API GET /modules/actifs : la liste des addons actifs, pour que le site public sache ce qui l'est.
 *
 * @package Modules
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

require_once __DIR__.'/Registre.php';

/** Le registre des modules de cette installation. */
$this->service('modules.registre', function () {
    $fichier = (string) ($this->retrieve('modules.fichier') ?: $this->path('#storage:').'/modules.json');

    return new Modules\Registre((string) $this->path('#addons:'), $fichier);
});

// Les modèles de contenu d'un module désactivé (Produits, Commandes…) disparaissent de l'administration :
// son code n'est plus chargé, ses écrans n'ont plus de sens. Les données restent ; le réactiver suffit.
$this->module('content')->extend([
    'models' => function (bool $extended = false): array {
        $modeles = $this->app->helper('content.model')->models();
        foreach ($this->app['modules.registre']->modelesMasques() as $masque) {
            unset($modeles[$masque]);
        }

        return $modeles;
    },
]);

$this->on('app.permissions.collect', function ($permissions) {
    $permissions['Modules'] = [
        'modules/manage' => 'Activer ou désactiver les modules',
    ];
});

$this->on('app.admin.init', function () {

    $this->bindClass('Modules\\Controller\\Modules', '/modules');

    $this->on('app.settings.collect', function ($settings) {
        $settings['System'][] = [
            'icon' => 'modules:icon.svg',
            'route' => '/modules',
            'label' => 'Modules',
            'permission' => 'modules/manage',
            // Lus par Dashboard (Réglages) ; Cockpit les ignore.
            'description' => 'Les fonctionnalités activées pour ce site',
            'icone' => 'toggle_on',
        ];
    });
});

$this->on('restApi.config', function ($restApi) {

    $restApi->addEndPoint('/modules/actifs', [
        'GET' => function ($params, $app) {
            $actifs = array_values(array_filter($app['modules.registre']->modules(), static fn (array $m): bool => $m['actif']));

            return array_map(static fn (array $m): array => ['nom' => $m['nom'], 'version' => $m['version']], $actifs);
        },
    ]);
});
