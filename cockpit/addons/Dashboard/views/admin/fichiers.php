<?php

/**
 * Fichiers du serveur (administrateur), par racine : Cockpit, le site, chaque espace.
 *
 * @var array<string, string> $roots les racines (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$racines = [];
foreach ($roots as $libelle => $chemin) {
    $racines[] = [
        'libelle' => $libelle === 'Cockpit' ? 'Administration' : ($libelle === 'Root' ? 'Site' : str_replace('Space: ', 'Espace ', $libelle)),
        'chemin' => $chemin,
    ];
}
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt">
                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Fichiers</h1>
                    <p class="tdb-accueil__phrase">Les fichiers sur le serveur. Une erreur ici peut casser le site : modifiez ou supprimez avec précaution.</p>
                    <ul class="mt-bandeau__chiffres"><li><b>{{ racines.length }}</b> racine{{ racines.length > 1 ? 's' : '' }}</li></ul>
                </section>

                <div class="mt-filtres fichiers__racines" role="group" aria-label="Racine" v-if="racines.length > 1">
                    <button type="button" class="mt-filtre" v-for="r in racines" :key="r.chemin" :class="{'mt-filtre--actif': racine === r.chemin}" :aria-pressed="racine === r.chemin ? 'true' : 'false'" @click="racine = r.chemin"><icon>folder_special</icon>{{ r.libelle }}</button>
                </div>

                <fichiers :root="racine"></fichiers>
            </div>
        </template>

        <script type="module">
            export default {
                components: {
                    fichiers: 'dashboard:assets/vue/fichiers.js'
                },

                data() {
                    const racines = <?= json_encode($racines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                    return { racines, racine: racines[0] ? racines[0].chemin : '#root:' };
                }
            };
        </script>
    </vue-view>
</kiss-container>
