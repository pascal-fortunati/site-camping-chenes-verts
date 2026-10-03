<?php

/**
 * API et sécurité : les adresses de l'API, l'accès public et les clés d'accès.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$roles = array_column($this->helper('acl')->roles(), 'name', 'appid');
$public = $this->dataStorage->findOne('system/api_keys', ['key' => 'public']);
$cles = array_map(static fn (array $k): array => [
    '_id' => $k['_id'],
    'name' => (string) ($k['name'] ?? ''),
    'key' => (string) ($k['key'] ?? ''),
    'role' => (string) ($k['role'] ?? ''),
], $this->dataStorage->find('system/api_keys', ['filter' => ['key' => ['$ne' => 'public']], 'sort' => ['name' => 1]])->toArray());
$adresses = [
    ['REST', 'api', $this->module('system')->spaceUrl('/api')],
    ['GraphQL', 'hub', rtrim((string) $this->getSiteUrl(true), '/').'/api/gql'],
];
// Sous Windows, Cockpit compose l'adresse avec une barre oblique inverse.
$adresses = array_map(static fn (array $a): array => [$a[0], $a[1], str_replace('\\', '', $a[2])], $adresses);
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">API et sécurité</h1>
                    <p class="tdb-accueil__phrase">Le site public et le formulaire lisent le contenu avec une clé. Chaque clé a un rôle, qui limite ce qu’elle peut faire.</p>
                    <div class="mt-bandeau__actions">
                        <a class="mt-bandeau__bouton" :href="$routeUrl('/system/api/create')"><icon>add</icon>Nouvelle clé</a>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ cles.length }}</b> clé{{ cles.length > 1 ? 's' : '' }}</li>
                    </ul>
                </section>

                <section class="tdb-carte">
                    <h2><icon>link</icon>Adresses de l’API</h2>
                    <div class="api__adresses">
                        <div class="api__adresse" v-for="a in adresses" :key="a[0]">
                            <span class="ls-ligne__icone"><icon>{{ a[1] }}</icon></span>
                            <span class="ls-ligne__texte"><b>{{ a[0] }}</b><code>{{ a[2] }}</code></span>
                            <button type="button" class="ls-rond" @click="copier(a[2], 'Adresse copiée.')" title="Copier" aria-label="Copier l’adresse"><icon>content_copy</icon></button>
                            <button type="button" class="kiss-button kiss-button-small" @click="essayer(a[0])"><icon>science</icon>Essayer</button>
                        </div>
                    </div>
                </section>

                <section class="tdb-carte">
                    <h2><icon>public</icon>Accès sans clé</h2>
                    <div class="api__public">
                        <p>Ce que peut faire une requête sans clé : <b>{{ publicRole ? (roles[publicRole] || publicRole) : 'rien' }}</b>.</p>
                        <a class="kiss-button" :href="$routeUrl('/system/api/public')"><icon>tune</icon>Régler</a>
                    </div>
                </section>

                <div class="mt-vide" v-if="!cles.length">
                    <icon>key_off</icon>
                    <p>Aucune clé pour l’instant.</p>
                </div>

                <div class="ls-liste" v-else>
                    <article class="ls-ligne" v-for="k in cles" :key="k._id">
                        <a class="ls-ligne__principal" :href="$routeUrl('/system/api/key/' + k._id)">
                            <span class="ls-ligne__icone"><icon>key</icon></span>
                            <span class="ls-ligne__texte">
                                <b>{{ k.name || 'Sans nom' }}</b>
                                <small><code>•••••{{ k.key.slice(-6) }}</code></small>
                            </span>
                        </a>
                        <span class="ls-etat" :class="k.role ? 'ls-etat--ligne' : 'ls-etat--nouveau'"><icon>badge</icon>{{ k.role ? (roles[k.role] || k.role) : 'Sans rôle' }}</span>
                        <div class="ls-actions">
                            <button type="button" class="ls-rond" @click="copier(k.key, 'Clé copiée.')" title="Copier la clé" aria-label="Copier la clé"><icon>content_copy</icon></button>
                            <button type="button" class="ls-rond" @click="essayer('REST', k.key)" title="Essayer avec cette clé" aria-label="Essayer avec cette clé"><icon>science</icon></button>
                            <a class="ls-rond" :href="$routeUrl('/system/api/key/' + k._id)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                            <button type="button" class="ls-rond ls-rond--danger" @click="supprimer(k)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            export default {
                data() {
                    return {
                        cles: <?= json_encode($cles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        roles: <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>,
                        adresses: <?= json_encode($adresses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        publicRole: <?= json_encode((string) ($public['role'] ?? '')) ?>
                    };
                },

                methods: {
                    copier(texte, message) {
                        App.utils.copyText(texte, () => App.ui.notify(message));
                    },

                    /**
                     * Ouvre la visionneuse REST ou GraphQL de Cockpit, avec une clé si elle est donnée.
                     */
                    essayer(type, apiKey = null) {
                        if (type === 'REST') {
                            VueView.ui.offcanvas('system:assets/dialogs/api-viewer.js', { openApiUrl: this.$routeUrl('/system/api/openapi?format=json'), apiKey });
                        } else {
                            VueView.ui.offcanvas('system:assets/dialogs/graphql-viewer.js', { apiKey });
                        }
                    },

                    async supprimer(k) {
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        const oui = await confirmer({ titre: `Supprimer la clé « ${k.name} » ?`, texte: 'Ce qui l’utilise (le site, le formulaire) ne pourra plus lire ni écrire. Cette action est définitive.', bouton: 'Supprimer', danger: true });
                        if (!oui) return;
                        this.$request('/system/api/remove', { key: { _id: k._id, key: k.key } }).then(() => {
                            this.cles = this.cles.filter((x) => x._id !== k._id);
                            App.ui.notify('Clé supprimée.');
                        }).catch((e) => App.ui.notify((e && e.error) || 'La suppression a échoué.', 'error'));
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
