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
 * - assets/notifications.js: Cockpit's notifications go through the
 *   dictionary too, as its dialogs already do.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

$this->on('app.admin.i18n.load', function ($locale, $i18n) {
    if ($locale === 'fr') {
        $i18n->load('adminclient:i18n/fr.php', 'fr');
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:header') {
        $assets[] = 'adminclient:assets/admin.css';
    }

    if ($context === 'app:footer') {
        $assets[] = ['src' => 'adminclient:assets/notifications.js', 'type' => 'module', 'position' => 'footer'];

        $user = $this->helper('auth')->getUser();

        if ($user && ($user['role'] ?? '') !== 'admin') {
            $assets[] = ['src' => 'adminclient:assets/actions-client.js', 'type' => 'module', 'position' => 'footer'];
        }
    }
});
