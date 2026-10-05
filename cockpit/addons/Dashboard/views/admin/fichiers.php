<?php

/**
 * Explorateur de fichiers du serveur (administrateur), par racine : l'administration, le site, chaque espace.
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
<kiss-container class="kiss-margin-small dashboard-page fichiers-page">
    <vue-view>
        <template>
            <section class="fiche-tete fichiers__tete">
                <span class="fiche-tete__icone"><icon>folder_open</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane"><a href="<?= $this->routeUrl('/system') ?>">Réglages</a></nav>
                    <h1>Fichiers</h1>
                    <p class="fiche-tete__info">Les fichiers sur le serveur. Une erreur ici peut casser le site : rien ne va dans une corbeille.</p>
                </div>
                <div class="fiche-choix fichiers__racines" role="radiogroup" aria-label="Racine" v-if="racines.length > 1">
                    <button type="button" role="radio" v-for="r in racines" :key="r.chemin" :aria-checked="racine === r.chemin ? 'true' : 'false'" :class="{'fiche-choix--actif': racine === r.chemin}" @click="racine = r.chemin"><icon>hard_drive</icon>{{ r.libelle }}</button>
                </div>
            </section>

            <fichiers :root="racine" :racine="libelle"></fichiers>
        </template>

        <script type="module">
            export default {
                components: {
                    fichiers: 'dashboard:assets/vue/fichiers.js'
                },

                data() {
                    const racines = <?= json_encode($racines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                    return { racines, racine: racines[0] ? racines[0].chemin : '#root:' };
                },

                computed: {
                    libelle() {
                        return (this.racines.find((r) => r.chemin === this.racine) || { libelle: 'Racine' }).libelle;
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
