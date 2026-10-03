<?php

/**
 * Médiathèque (composant vue/medias.js), sur les routes /assets/* de Cockpit.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$acl = $this->helper('acl');
$droits = [
    'envoyer' => $acl->isAllowed('assets/upload'),
    'modifier' => $acl->isAllowed('assets/edit'),
    'supprimer' => $acl->isAllowed('assets/delete'),
    'dossiers' => $acl->isAllowed('assets/folders/create') || $acl->isSuperAdmin(),
];
?>
<kiss-container class="kiss-margin-small dashboard-page">

    <vue-view>
        <template>
            <mediatheque :droits='<?= $this->escape(json_encode($droits)) ?>'></mediatheque>
        </template>

        <script type="module">
            export default {
                components: {
                    mediatheque: 'dashboard:assets/vue/medias.js'
                }
            }
        </script>
    </vue-view>

</kiss-container>
