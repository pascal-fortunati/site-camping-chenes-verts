<?php
$acl = $this->helper('acl');
$nom = $model['name'];
$env = (string) @file_get_contents(dirname(__DIR__, 5).'/.env');
$site = preg_match('/^SITE_URL=(.*)$/m', $env, $t) ? rtrim(trim($t[1], " \t\r\"'"), '/') : '';
$accueil = preg_match('/^HOME_PAGE_SLUG=(.*)$/m', $env, $t) ? trim($t[1], " \t\r\"'") : 'accueil';
$vue = (include dirname(__DIR__).'/modeles.php')($model);
$proprietes = [
    'vue' => ['icone' => $vue['icone'], 'mots' => [$vue['element'], $vue['elements'], $vue['nouveau']]],
    'model' => ['name' => $nom, 'label' => $model['label'] ?? '', 'info' => $model['info'] ?? '', 'fields' => array_map(static fn (array $f): array => ['name' => $f['name'], 'type' => $f['type']], $model['fields'] ?? [])],
    'droits' => [
        'creer' => $acl->isAllowed("content/{$nom}/create"),
        'modifier' => $acl->isAllowed("content/{$nom}/update"),
        'publier' => $acl->isAllowed("content/{$nom}/publish"),
        'supprimer' => $acl->isAllowed("content/{$nom}/delete"),
    ],
    'site' => $site,
    'accueil' => $accueil ?: 'accueil',
];
?>
<kiss-container class="kiss-margin-small camping-page">

    <vue-view>
        <template>
            <liste v-bind='<?= $this->escape(json_encode($proprietes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></liste>
        </template>

        <script type="module">
            export default {
                components: {
                    liste: 'admincamping:assets/liste.js'
                }
            }
        </script>
    </vue-view>

</kiss-container>
