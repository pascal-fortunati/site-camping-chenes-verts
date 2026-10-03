<?php

/**
 * Fiche d'un rôle : nom, description et droits, groupe par groupe. Les droits viennent des modules
 * (app.permissions.collect) ; ceux du contenu gardent le composant de Cockpit, modèle par modèle.
 *
 * @var array $role le rôle (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$permissions = new ArrayObject(['Users' => ['app/users/manage' => 'Manage users', 'app/roles/manage' => 'Manage roles']]);
$this->trigger('app.permissions.collect', [$permissions]);
$composants = [];
foreach ($permissions as $meta) {
    if (isset($meta['component'])) {
        $composants[$meta['component']] = $meta['src'];
    }
}
$nouveau = empty($role['_id']);
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche role">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon>{{ role._id ? 'badge' : 'add_moderator' }}</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/system/users/roles') ?>">Rôles et droits</a>
                    </nav>
                    <h1>{{ role.name || <?= json_encode($nouveau ? 'Nouveau rôle' : $role['appid'], JSON_UNESCAPED_UNICODE) ?> }}</h1>
                    <p class="fiche-tete__info">{{ total }} droit{{ total > 1 ? 's' : '' }} accordé{{ total > 1 ? 's' : '' }}</p>
                </div>
            </section>

            <div class="role__grille">
                <section class="fiche-carte role__infos">
                    <h2><icon>info</icon>Le rôle</h2>
                    <label class="mt-champ"><span>Nom</span><input type="text" v-model="role.name" required></label>
                    <label class="mt-champ">
                        <span>Identifiant <em>en minuscules, sans espace</em></span>
                        <input type="text" v-model="role.appid" :disabled="!!role._id" required autocapitalize="off" spellcheck="false" pattern="[a-z0-9_-]+">
                        <small v-if="role._id">Il ne change plus : les comptes et les clés y sont rattachés.</small>
                    </label>
                    <label class="mt-champ"><span>Description</span><textarea v-model="role.info" rows="3"></textarea></label>
                </section>

                <section class="fiche-carte role__droits">
                    <div class="role__droits-tete">
                        <h2><icon>checklist</icon>Droits</h2>
                        <label class="mt-recherche role__filtre">
                            <icon aria-hidden="true">search</icon>
                            <input type="search" v-model="filtre" placeholder="Filtrer les groupes" aria-label="Filtrer les groupes">
                        </label>
                    </div>

                    <section class="el role__groupe" v-for="(meta, groupe) in groupes" :key="groupe" :class="{'el--ouvert': ouverts[groupe]}">
                        <header class="el__tete">
                            <button type="button" class="el__resume" @click="ouverts[groupe] = !ouverts[groupe]" :aria-expanded="ouverts[groupe] ? 'true' : 'false'">
                                <span class="el__numero"><icon>{{ icone(groupe) }}</icon></span>
                                <span class="el__titre">{{ t(groupe) }}</span>
                                <span class="role__compte" v-if="!meta.component && accordes(meta)">{{ accordes(meta) }} / {{ Object.keys(meta).length }}</span>
                                <icon class="el__fleche">expand_more</icon>
                            </button>
                        </header>
                        <div class="el__corps" v-if="ouverts[groupe]">
                            <component :is="meta.component" v-model="role.permissions" v-bind="meta.props || {}" :expressions="role.expressions" v-if="meta.component"></component>
                            <div class="role__liste" v-else>
                                <div class="role__droit" v-for="(libelle, droit) in meta" :key="droit">
                                    <field-boolean v-model="role.permissions[droit]" :label="t(libelle)"></field-boolean>
                                    <button type="button" class="ls-rond" v-if="role.permissions[droit]" :class="{'role__regle--active': role.expressions[droit]?.expr}" @click="regle(droit)" title="Règle sur les éléments" aria-label="Règle sur les éléments"><icon>rule</icon></button>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>
            </div>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat"><icon>checklist</icon>{{ total }} droit{{ total > 1 ? 's' : '' }}</span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $this->routeUrl('/system/users/roles') ?>">{{ role._id ? 'Fermer' : 'Annuler' }}</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="enregistrement || !role.appid || !role.name" @click="enregistrer"><icon>check</icon>{{ role._id ? 'Enregistrer' : 'Créer le rôle' }}</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        const ICONES = { 'Users': 'group', 'Content': 'article', 'Assets': 'perm_media', 'Api & Security': 'key', 'Locales': 'translate', 'System': 'settings', 'Logs': 'receipt_long', 'Spaces': 'workspaces', 'Finder': 'folder_open' };

        export default {
            data() {
                const role = <?= json_encode($role) ?>;
                if (!role.expressions || Array.isArray(role.expressions)) role.expressions = {};
                if (!role.permissions || Array.isArray(role.permissions)) role.permissions = {};
                return { role, permissions: <?= json_encode($permissions) ?>, ouverts: {}, filtre: '', enregistrement: false };
            },

            components: <?= json_encode(new ArrayObject($composants)) ?>,

            computed: {
                groupes() {
                    const f = this.filtre.toLowerCase();
                    const r = {};
                    Object.keys(this.permissions).sort((a, b) => this.t(a).localeCompare(this.t(b), 'fr')).forEach((g) => {
                        if (!f || this.t(g).toLowerCase().includes(f) || g.toLowerCase().includes(f)) r[g] = this.permissions[g];
                    });
                    return r;
                },
                total() {
                    return Object.values(this.role.permissions).filter(Boolean).length;
                }
            },

            methods: {
                icone(groupe) {
                    return ICONES[groupe] || 'extension';
                },

                accordes(meta) {
                    return Object.keys(meta).filter((d) => this.role.permissions[d]).length;
                },

                regle(droit) {
                    this.$dialog('system:assets/dialogs/acl-expression.js', { permission: droit, expression: this.role.expressions[droit] || null }, {
                        save: (entree) => {
                            if (entree) this.role.expressions[droit] = entree; else delete this.role.expressions[droit];
                        }
                    });
                },

                enregistrer() {
                    const nouveau = !this.role._id;
                    this.enregistrement = true;
                    this.$request('/system/users/roles/save', { role: this.role }).then((role) => {
                        if (!role.expressions || Array.isArray(role.expressions)) role.expressions = {};
                        if (nouveau) {
                            location.href = this.$routeUrl('/system/users/roles/role/' + role._id);
                            return;
                        }
                        this.role = role;
                        App.ui.notify('Rôle enregistré.');
                    }).catch((r) => App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error'))
                        .finally(() => { this.enregistrement = false; });
                }
            }
        };
    </script>

</vue-view>
