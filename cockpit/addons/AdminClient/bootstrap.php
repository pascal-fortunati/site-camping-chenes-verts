<?php

/**
 * L'administration du client, en français et sans les actions qui ne lui sont pas permises.
 *
 * - i18n/fr.php : Cockpit ne fournit aucune traduction française ; elle est chargée quand la langue est « fr »
 *   (réglage « i18n » de config.php, ou langue choisie dans le compte).
 * - assets/admin.css : les libellés gardent leurs majuscules d'origine (« Nom du site », pas « Nom Du Site »).
 * - assets/actions-client.js : pour tout compte qui n'est pas administrateur, masque les actions sur la
 *   structure (modifier, dupliquer, supprimer un modèle, objet JSON). Cockpit les refuse déjà côté serveur :
 *   ce n'est qu'une question d'affichage, la sécurité reste celle des droits du rôle.
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
        $user = $this->helper('auth')->getUser();

        if ($user && ($user['role'] ?? '') !== 'admin') {
            $assets[] = ['src' => 'adminclient:assets/actions-client.js', 'type' => 'module', 'position' => 'footer'];
        }
    }
});
