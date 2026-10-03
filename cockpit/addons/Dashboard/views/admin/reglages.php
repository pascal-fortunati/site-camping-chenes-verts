<?php

/**
 * Réglages : les écrans d'administration permis au compte, en trois groupes.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$connus = [
    '/system/users/user' => ['acces', 'account_circle', 'Mon compte', 'Nom, adresse e-mail, mot de passe, sécurité'],
    '/system/users' => ['acces', 'group', 'Utilisateurs', 'Les comptes qui ouvrent l’administration'],
    '/system/users/roles' => ['acces', 'admin_panel_settings', 'Rôles et droits', 'Ce que chaque type de compte peut faire'],
    '/system/api' => ['acces', 'key', 'API et sécurité', 'Les clés du site public et du formulaire'],
    '/system/locales' => ['contenu', 'translate', 'Langues', 'Les langues dans lesquelles le contenu est saisi'],
    '/finder' => ['contenu', 'folder_open', 'Fichiers', 'Les fichiers de l’administration sur le serveur'],
    '/system/info' => ['systeme', 'info', 'Informations système', 'Version, cache, extensions, PHP'],
    '/system/logs' => ['systeme', 'receipt_long', 'Journaux', 'Connexions, erreurs et événements'],
    '/system/worker' => ['systeme', 'pending_actions', 'Tâches', 'Les tâches lancées en arrière-plan'],
    '/system/spaces' => ['systeme', 'workspaces', 'Espaces', 'Des administrations séparées sur la même installation'],
    '/system/tower' => ['systeme', 'terminal', 'Console', 'Les commandes de Cockpit'],
    '/updater' => ['systeme', 'system_update', 'Mise à jour', 'La version de Cockpit'],
];
$groupes = [
    'acces' => ['manage_accounts', 'Comptes et accès', []],
    'contenu' => ['dataset', 'Contenu', []],
    'systeme' => ['settings', 'Système', []],
];

if ($this->helper('acl')->isAllowed('content/:models/manage')) {
    $groupes['contenu'][2][] = ['schema', 'Modèles de contenu', 'Les champs de chaque page, liste ou fiche', $this->routeUrl('/content')];
}
foreach ($this->helper('settings')->groups(true) as $elements) {
    foreach ($elements as $e) {
        // Un addon peut décrire sa tuile : « description », « icone » (Material Symbols), « groupe » (acces, contenu, systeme).
        [$groupe, $icone, $titre, $texte] = $connus[$e['route']] ?? [
            isset($groupes[$e['groupe'] ?? '']) ? $e['groupe'] : 'systeme',
            (string) ($e['icone'] ?? 'extension'),
            t($e['label'] ?? ''),
            (string) ($e['description'] ?? ''),
        ];
        $groupes[$groupe][2][] = [$icone, $titre, $texte, $this->routeUrl($e['route'])];
    }
}
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <div class="dashboard-tableau">

        <section class="tdb-accueil tdb-accueil--page">
            <h1 class="tdb-accueil__titre">Réglages</h1>
            <p class="tdb-accueil__phrase">Les comptes, les accès et le fonctionnement de l’administration.</p>
        </section>

        <?php foreach ($groupes as [$icone, $titre, $tuiles]) : ?>
            <?php if ($tuiles) : ?>
                <section class="tdb-carte">
                    <h2><icon><?= $icone ?></icon><?= $this->escape($titre) ?></h2>
                    <div class="tdb-raccourcis">
                        <?php foreach ($tuiles as [$i, $t, $texte, $lien]) : ?>
                            <a class="tdb-raccourci" href="<?= $this->escape($lien) ?>">
                                <span class="tdb-raccourci__icone"><icon><?= $this->escape($i) ?></icon></span>
                                <span><b><?= $this->escape($t) ?></b><span><?= $this->escape($texte) ?></span></span>
                                <icon class="tdb-raccourci__fleche">arrow_forward</icon>
                            </a>
                        <?php endforeach ?>
                    </div>
                </section>
            <?php endif ?>
        <?php endforeach ?>

    </div>
</kiss-container>
