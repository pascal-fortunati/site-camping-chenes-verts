<?php
$acl = $this->helper('acl');
$droits = [
    'envoyer' => $acl->isAllowed('assets/upload'),
    'modifier' => $acl->isAllowed('assets/edit'),
    'supprimer' => $acl->isAllowed('assets/delete'),
    'dossiers' => $acl->isAllowed('assets/folders/create') || $acl->isSuperAdmin(),
];
?>
<kiss-container class="kiss-margin-small camping-page">

    <vue-view>
        <template>
            <mediatheque :droits='<?= $this->escape(json_encode($droits)) ?>'></mediatheque>
        </template>

        <script type="module">
            export default {
                components: {
                    mediatheque: 'admincamping:assets/mediatheque.js'
                }
            }
        </script>
    </vue-view>

</kiss-container>
