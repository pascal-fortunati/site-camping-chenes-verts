<?php

/**
 * Espaces : des administrations séparées sur la même installation de Cockpit.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$fichiers = $this->module('finder') && !$this->retrieve('finder.disabled', false) && $this->helper('acl')->isSuperAdmin();
$espaces = array_values(array_map(static fn (array $s): array => [
    'name' => (string) ($s['name'] ?? ''),
    'group' => (string) ($s['group'] ?? ''),
    'url' => (string) ($s['url'] ?? ''),
], $this->helper('spaces')->spaces()));
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Espaces</h1>
                    <p class="tdb-accueil__phrase">Chaque espace est une administration à part, avec ses comptes, ses modèles et son contenu, sur la même installation.</p>
                    <div class="mt-bandeau__actions">
                        <a class="mt-bandeau__bouton" :href="$routeUrl('/system/spaces/create')"><icon>add</icon>Nouvel espace</a>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ espaces.length }}</b> espace{{ espaces.length > 1 ? 's' : '' }}</li>
                        <li v-if="groupes.length"><b>{{ groupes.length }}</b> groupe{{ groupes.length > 1 ? 's' : '' }}</li>
                    </ul>
                </section>

                <div class="mt-outils" v-if="espaces.length">
                    <label class="mt-recherche">
                        <icon aria-hidden="true">search</icon>
                        <input type="search" v-model="recherche" placeholder="Chercher un espace" aria-label="Chercher un espace">
                    </label>
                    <div class="mt-filtres" role="group" aria-label="Filtrer par groupe" v-if="groupes.length">
                        <button type="button" class="mt-filtre" :class="{'mt-filtre--actif': !groupe}" @click="groupe = ''">Tous<span class="mt-filtre__nombre">{{ espaces.length }}</span></button>
                        <button type="button" class="mt-filtre" v-for="g in groupes" :key="g" :class="{'mt-filtre--actif': groupe === g}" @click="groupe = g">{{ g }}<span class="mt-filtre__nombre">{{ espaces.filter((e) => e.group === g).length }}</span></button>
                    </div>
                    <?php if ($fichiers) : ?>
                        <div class="mt-outils__droite">
                            <button type="button" class="kiss-button" @click="fichiers()"><icon>folder_open</icon>Dossier des espaces</button>
                        </div>
                    <?php endif ?>
                </div>

                <div class="mt-vide" v-if="!liste.length">
                    <icon>workspaces</icon>
                    <p v-if="espaces.length">Aucun espace ne correspond.</p>
                    <p v-else>Aucun espace pour l’instant. Un espace sert à gérer un autre site depuis la même installation.</p>
                    <a class="kiss-button kiss-button-primary" v-if="!espaces.length" :href="$routeUrl('/system/spaces/create')"><icon>add</icon>Créer un espace</a>
                </div>

                <div class="ls-liste" v-else>
                    <article class="ls-ligne" v-for="e in liste" :key="e.name">
                        <a class="ls-ligne__principal" :href="e.url" target="_blank" rel="noopener noreferrer">
                            <span class="ls-ligne__icone"><icon>workspaces</icon></span>
                            <span class="ls-ligne__texte"><b>{{ e.name }}</b><small><code>{{ e.url }}</code></small></span>
                        </a>
                        <span class="ls-etat ls-etat--brouillon" v-if="e.group"><icon>folder</icon>{{ e.group }}</span>
                        <div class="ls-actions">
                            <a class="ls-rond" :href="e.url" target="_blank" rel="noopener noreferrer" title="Ouvrir l’espace" aria-label="Ouvrir l’espace"><icon>open_in_new</icon></a>
                            <?php if ($fichiers) : ?>
                                <button type="button" class="ls-rond" @click="fichiers(e.name)" title="Ses fichiers" aria-label="Ses fichiers"><icon>folder_open</icon></button>
                            <?php endif ?>
                            <button type="button" class="ls-rond ls-rond--danger" @click="supprimer(e)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

            export default {
                data() {
                    return { espaces: <?= json_encode($espaces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, recherche: '', groupe: '' };
                },

                computed: {
                    groupes() {
                        return [...new Set(this.espaces.map((e) => e.group).filter(Boolean))].sort();
                    },
                    liste() {
                        const mots = plat(this.recherche).split(/\s+/).filter(Boolean);
                        return this.espaces.filter((e) => (!this.groupe || e.group === this.groupe) && mots.every((m) => plat(e.name + ' ' + e.group).includes(m)));
                    }
                },

                methods: {
                    fichiers(nom = '') {
                        VueView.ui.offcanvas('finder:assets/dialogs/finder.js', { root: '#root:', path: `/.spaces/${nom}` });
                    },

                    supprimer(e) {
                        App.ui.prompt(`Supprimer l’espace « ${e.name} » ?`, '', (password) => {
                            if (!password) return;
                            this.$request('/system/spaces/remove', { space: { name: e.name }, password }).then(() => {
                                this.espaces = this.espaces.filter((x) => x.name !== e.name);
                                App.ui.notify('Espace supprimé.');
                            }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
                        }, { type: 'password', info: 'Ses comptes, ses modèles et tout son contenu seront effacés. Confirmez avec votre mot de passe.' });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
