<?php

/**
 * Utilisateurs : les comptes de l'administration, leur rôle et leur état.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$roles = ['admin' => 'Administrateur'] + array_column($this->helper('acl')->roles(), 'name', 'appid');
unset($roles['public']);
$medias = rtrim((string) $this->fileStorage->getURL('uploads://'), '/');
$moi = $this->helper('auth')->getUser()['_id'] ?? '';
$comptes = array_map(static fn (array $u): array => [
    '_id' => $u['_id'],
    'name' => (string) ($u['name'] ?? ''),
    'user' => (string) ($u['user'] ?? ''),
    'email' => (string) ($u['email'] ?? ''),
    'role' => (string) ($u['role'] ?? ''),
    'active' => !empty($u['active']),
    'photo' => str_starts_with((string) ($u['avatar'] ?? ''), '/avatars/') ? $medias.$u['avatar'] : '',
    'moi' => $u['_id'] === $moi,
    '_modified' => (int) ($u['_modified'] ?? 0),
], $this->dataStorage->find('system/users', ['sort' => ['name' => 1]])->toArray());
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Utilisateurs</h1>
                    <p class="tdb-accueil__phrase">Les personnes qui ouvrent l’administration. Le rôle décide de ce que chacune peut faire.</p>
                    <div class="mt-bandeau__actions">
                        <a class="mt-bandeau__bouton" :href="$routeUrl('/system/users/create')"><icon>person_add</icon>Nouvel utilisateur</a>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ comptes.length }}</b> compte{{ comptes.length > 1 ? 's' : '' }}</li>
                        <li v-if="inactifs"><b>{{ inactifs }}</b> désactivé{{ inactifs > 1 ? 's' : '' }}</li>
                    </ul>
                </section>

                <div class="mt-outils">
                    <label class="mt-recherche">
                        <icon aria-hidden="true">search</icon>
                        <input type="search" v-model="recherche" placeholder="Chercher un nom, un identifiant, une adresse" aria-label="Chercher un utilisateur">
                    </label>
                    <div class="mt-filtres" role="group" aria-label="Filtrer par rôle">
                        <button type="button" v-for="f in filtres" :key="f.cle" class="mt-filtre" :class="{'mt-filtre--actif': filtre === f.cle}" :aria-pressed="filtre === f.cle ? 'true' : 'false'" @click="filtre = f.cle">
                            {{ f.libelle }}<span class="mt-filtre__nombre">{{ f.nombre }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-vide" v-if="!liste.length">
                    <icon>person_search</icon>
                    <p>Aucun compte ne correspond.</p>
                    <button type="button" class="kiss-button" @click="recherche = ''; filtre = 'tout'">Tout afficher</button>
                </div>

                <div class="ls-liste" v-else>
                    <article class="ls-ligne" v-for="c in liste" :key="c._id">
                        <a class="ls-ligne__principal" :href="$routeUrl('/system/users/user/' + c._id)">
                            <span class="ls-ligne__icone ls-ligne__icone--photo">
                                <img v-if="c.photo" :src="c.photo" alt="" width="40" height="40">
                                <template v-else>{{ initiales(c) }}</template>
                            </span>
                            <span class="ls-ligne__texte">
                                <b>{{ c.name || c.user }}<span class="ls-ligne__moi" v-if="c.moi">vous</span></b>
                                <small>{{ c.user }} · {{ c.email }}</small>
                            </span>
                        </a>
                        <span class="ls-etat" :class="c.role === 'admin' ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><icon>{{ c.role === 'admin' ? 'shield_person' : 'badge' }}</icon>{{ roles[c.role] || c.role }}</span>
                        <span class="ls-etat ls-etat--nouveau" v-if="!c.active"><span class="ls-etat__point"></span>Désactivé</span>
                        <div class="ls-actions">
                            <a class="ls-rond" :href="$routeUrl('/system/users/user/' + c._id)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                            <button type="button" class="ls-rond ls-rond--danger" v-if="!c.moi" @click="supprimer(c)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

            export default {
                data() {
                    return {
                        comptes: <?= json_encode($comptes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        roles: <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>,
                        recherche: '',
                        filtre: 'tout'
                    };
                },

                computed: {
                    inactifs() {
                        return this.comptes.filter((c) => !c.active).length;
                    },
                    filtres() {
                        const utilises = [...new Set(this.comptes.map((c) => c.role))];
                        return [{ cle: 'tout', libelle: 'Tous', nombre: this.comptes.length }]
                            .concat(utilises.map((r) => ({ cle: r, libelle: this.roles[r] || r, nombre: this.comptes.filter((c) => c.role === r).length })));
                    },
                    liste() {
                        const mots = plat(this.recherche).split(/\s+/).filter(Boolean);
                        return this.comptes.filter((c) => (this.filtre === 'tout' || c.role === this.filtre)
                            && mots.every((m) => plat([c.name, c.user, c.email].join(' ')).includes(m)));
                    }
                },

                methods: {
                    initiales(c) {
                        return String(c.name || c.user || '?').split(/\s+/).map((m) => m[0]).join('').slice(0, 2).toUpperCase();
                    },

                    supprimer(c) {
                        App.ui.prompt(`Supprimer le compte « ${c.name || c.user} » ?`, '', (mdp) => {
                            this.$request('/system/users/remove', { user: { _id: c._id }, password: mdp }).then(() => {
                                this.comptes = this.comptes.filter((x) => x._id !== c._id);
                                App.ui.notify('Compte supprimé.');
                            }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
                        }, { type: 'password', info: 'Cette action est définitive. Confirmez avec votre mot de passe.' });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
