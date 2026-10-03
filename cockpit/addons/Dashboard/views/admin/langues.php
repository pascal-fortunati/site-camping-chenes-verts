<?php

/**
 * Langues : les langues dans lesquelles le contenu peut être saisi, en plus de la langue principale.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$langues = array_map(static fn (array $l): array => [
    '_id' => $l['_id'],
    'i18n' => (string) ($l['i18n'] ?? ''),
    'name' => (string) ($l['name'] ?? ''),
    'enabled' => $l['enabled'] ?? true,
], $this->dataStorage->find('system/locales', ['sort' => ['name' => 1]])->toArray());
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Langues</h1>
                    <p class="tdb-accueil__phrase">Avec une seconde langue, chaque champ traduisible propose une version par langue. Le site affiche celle qu’on lui demande.</p>
                    <div class="mt-bandeau__actions">
                        <a class="mt-bandeau__bouton" :href="$routeUrl('/system/locales/create')"><icon>add</icon>Ajouter une langue</a>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ langues.length + 1 }}</b> langue{{ langues.length ? 's' : '' }}</li>
                    </ul>
                </section>

                <div class="ls-liste">
                    <article class="ls-ligne">
                        <span class="ls-ligne__principal">
                            <span class="ls-ligne__icone"><icon>translate</icon></span>
                            <span class="ls-ligne__texte"><b>Langue principale</b><small>Celle du contenu saisi sans précision de langue.</small></span>
                        </span>
                        <span class="ls-etat ls-etat--ligne"><span class="ls-etat__point"></span>Toujours active</span>
                        <div class="ls-actions"><span class="ls-rond" aria-hidden="true"><icon>lock</icon></span></div>
                    </article>
                    <article class="ls-ligne" v-for="l in langues" :key="l._id">
                        <a class="ls-ligne__principal" :href="$routeUrl('/system/locales/locale/' + l._id)">
                            <span class="ls-ligne__icone ls-ligne__icone--code">{{ l.i18n }}</span>
                            <span class="ls-ligne__texte"><b>{{ l.name || l.i18n }}</b><small>Code : {{ l.i18n }}</small></span>
                        </a>
                        <span class="ls-etat" :class="l.enabled ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><span class="ls-etat__point"></span>{{ l.enabled ? 'Active' : 'Désactivée' }}</span>
                        <div class="ls-actions">
                            <a class="ls-rond" :href="$routeUrl('/system/locales/locale/' + l._id)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                            <button type="button" class="ls-rond ls-rond--danger" @click="supprimer(l)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            export default {
                data() {
                    return { langues: <?= json_encode($langues, JSON_UNESCAPED_UNICODE) ?> };
                },

                methods: {
                    async supprimer(l) {
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        const oui = await confirmer({ titre: `Supprimer la langue « ${l.name || l.i18n} » ?`, texte: 'Les textes déjà traduits restent enregistrés mais ne sont plus proposés.', bouton: 'Supprimer', danger: true });
                        if (!oui) return;
                        this.$request('/system/locales/remove', { locale: { _id: l._id, i18n: l.i18n } }).then(() => {
                            this.langues = this.langues.filter((x) => x._id !== l._id);
                            App.ui.notify('Langue supprimée.');
                        }).catch((e) => App.ui.notify((e && e.error) || 'La suppression a échoué.', 'error'));
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
