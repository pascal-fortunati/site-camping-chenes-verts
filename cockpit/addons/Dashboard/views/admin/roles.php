<?php

/**
 * Rôles et droits : chaque rôle, le nombre de comptes qui l'ont et les clés d'API qui l'utilisent.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$comptes = array_count_values(array_map('strval', array_column($this->dataStorage->find('system/users', ['fields' => ['role' => 1]])->toArray(), 'role')));
$cles = [];
foreach ($this->dataStorage->find('system/api_keys', ['fields' => ['role' => 1, 'name' => 1]])->toArray() as $k) {
    $cles[(string) ($k['role'] ?? '')][] = (string) ($k['name'] ?? '');
}
$roles = array_map(static fn (array $r): array => [
    '_id' => $r['_id'],
    'appid' => $r['appid'],
    'name' => (string) ($r['name'] ?? $r['appid']),
    'info' => (string) ($r['info'] ?? ''),
    'droits' => count(array_filter((array) ($r['permissions'] ?? []))),
    'comptes' => $comptes[$r['appid']] ?? 0,
    'cles' => $cles[$r['appid']] ?? [],
], $this->dataStorage->find('system/roles', ['sort' => ['name' => 1]])->toArray());
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Rôles et droits</h1>
                    <p class="tdb-accueil__phrase">Chaque compte et chaque clé d’API a un rôle : il décide de ce qui peut être lu, modifié ou publié.</p>
                    <div class="mt-bandeau__actions">
                        <a class="mt-bandeau__bouton" :href="$routeUrl('/system/users/roles/create')"><icon>add</icon>Nouveau rôle</a>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ roles.length + 1 }}</b> rôles</li>
                        <li><b><?= $comptes['admin'] ?? 0 ?></b> administrateur<?= ($comptes['admin'] ?? 0) > 1 ? 's' : '' ?></li>
                    </ul>
                </section>

                <div class="ls-liste">
                    <article class="ls-ligne">
                        <span class="ls-ligne__principal">
                            <span class="ls-ligne__icone"><icon>shield_person</icon></span>
                            <span class="ls-ligne__texte">
                                <b>Administrateur</b>
                                <small>Tous les droits, y compris la structure du site et les comptes. Ce rôle ne se modifie pas.</small>
                            </span>
                        </span>
                        <span class="ls-etat ls-etat--ligne"><icon>person</icon><?= $comptes['admin'] ?? 0 ?> compte<?= ($comptes['admin'] ?? 0) > 1 ? 's' : '' ?></span>
                        <div class="ls-actions"><span class="ls-rond" aria-hidden="true"><icon>lock</icon></span></div>
                    </article>

                    <article class="ls-ligne" v-for="r in roles" :key="r._id">
                        <a class="ls-ligne__principal" :href="$routeUrl('/system/users/roles/role/' + r._id)">
                            <span class="ls-ligne__icone"><icon>{{ r.cles.length ? 'key' : 'badge' }}</icon></span>
                            <span class="ls-ligne__texte">
                                <b>{{ r.name }}</b>
                                <small>{{ r.info || (r.droits + ' droit' + (r.droits > 1 ? 's' : '')) }}</small>
                            </span>
                        </a>
                        <span class="ls-etat ls-etat--brouillon" v-if="r.cles.length" :title="r.cles.join(', ')"><icon>key</icon>Clé d’API</span>
                        <span class="ls-etat" :class="r.comptes ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><icon>person</icon>{{ r.comptes }} compte{{ r.comptes > 1 ? 's' : '' }}</span>
                        <div class="ls-actions">
                            <a class="ls-rond" :href="$routeUrl('/system/users/roles/role/' + r._id)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                            <button type="button" class="ls-rond ls-rond--danger" @click="supprimer(r)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            export default {
                data() {
                    return { roles: <?= json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> };
                },

                methods: {
                    async supprimer(r) {
                        const details = [];
                        if (r.comptes) details.push(`${r.comptes} compte${r.comptes > 1 ? 's' : ''} perdront leurs droits.`);
                        if (r.cles.length) details.push(`Clé${r.cles.length > 1 ? 's' : ''} sans rôle : ${r.cles.join(', ')}.`);
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        const oui = await confirmer({ titre: `Supprimer le rôle « ${r.name} » ?`, texte: 'Cette action est définitive.', details, bouton: 'Supprimer', danger: true });
                        if (!oui) return;
                        this.$request('/system/users/roles/remove', { role: { _id: r._id, appid: r.appid } }).then(() => {
                            this.roles = this.roles.filter((x) => x._id !== r._id);
                            App.ui.notify('Rôle supprimé.');
                        }).catch((e) => App.ui.notify((e && e.error) || 'La suppression a échoué.', 'error'));
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
