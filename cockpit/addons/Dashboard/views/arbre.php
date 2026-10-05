<?php

/**
 * Arborescence d'un modèle (composant vue/arbre.js).
 *
 * @var array $model le modèle
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$acl = $this->helper('acl');
$nom = $model['name'];
$vue = (include dirname(__DIR__).'/lib/modeles.php')($model);
$proprietes = [
    'vue' => ['icone' => $vue['icone'], 'mots' => [$vue['element'], $vue['elements'], $vue['nouveau']]],
    'model' => ['name' => $nom, 'label' => $model['label'] ?? '', 'info' => $model['info'] ?? '', 'fields' => array_map(static fn (array $f): array => ['name' => $f['name'], 'type' => $f['type']], $model['fields'] ?? [])],
    'droits' => [
        'creer' => $acl->isAllowed("content/{$nom}/create"),
        'supprimer' => $acl->isAllowed("content/{$nom}/delete"),
        'ordre' => $acl->isAllowed("content/{$nom}/updateorder"),
    ],
];
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <arbre v-bind='<?= $this->escape(json_encode($proprietes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></arbre>
        </template>

        <script type="module">
            export default {
                components: {
                    arbre: 'dashboard:assets/vue/arbre.js'
                }
            }
        </script>
    </vue-view>
</kiss-container>
