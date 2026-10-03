<?php

/**
 * Journaux : connexions, erreurs et événements, par type et par canal, 50 par page.
 *
 * @var array $channels les canaux (modules) de Cockpit
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$types = [];
foreach ($this->dataStorage->find('system/log', ['fields' => ['type' => 1]])->toArray() as $l) {
    $types[$l['type']] = ($types[$l['type']] ?? 0) + 1;
}
$canaux = array_values(array_map(fn (array $c): array => ['value' => $c['name'], 'label' => t($c['label'])], $channels));
usort($canaux, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Journaux</h1>
                    <p class="tdb-accueil__phrase">Ce que Cockpit a noté : les connexions, les erreurs et les événements des extensions.</p>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ total }}</b> entrées</li>
                        <li v-if="types.error"><b>{{ types.error }}</b> erreur{{ types.error > 1 ? 's' : '' }}</li>
                    </ul>
                </section>

                <div class="mt-outils">
                    <label class="mt-recherche">
                        <icon aria-hidden="true">search</icon>
                        <input type="search" v-model="recherche" placeholder="Chercher dans les messages" aria-label="Chercher dans les messages">
                    </label>
                    <div class="mt-filtres" role="group" aria-label="Filtrer par type">
                        <button type="button" v-for="f in filtres" :key="f.cle" class="mt-filtre" :class="{'mt-filtre--actif': type === f.cle, 'mt-filtre--alerte': f.alerte && f.nombre}" :aria-pressed="type === f.cle ? 'true' : 'false'" @click="type = f.cle">
                            {{ f.libelle }}<span class="mt-filtre__nombre">{{ f.nombre }}</span>
                        </button>
                    </div>
                    <div class="mt-outils__droite">
                        <menu-tri v-model="canal" :options="canaux" libelle="Canal"></menu-tri>
                    </div>
                </div>

                <div class="ls-liste" v-if="chargement && !entrees.length">
                    <div class="ls-ligne ls-ligne--fantome" v-for="n in 6" :key="n"></div>
                </div>

                <div class="mt-vide" v-else-if="!entrees.length">
                    <icon>receipt_long</icon>
                    <p>Aucune entrée ne correspond.</p>
                </div>

                <div class="ls-liste" v-else :class="{'journal--chargement': chargement}">
                    <article class="ls-ligne" v-for="e in entrees" :key="e._id">
                        <span class="ls-ligne__principal">
                            <span class="ls-ligne__icone" :class="'journal__icone--' + e.type"><icon>{{ icone(e.type) }}</icon></span>
                            <span class="ls-ligne__texte">
                                <b>{{ message(e.message) }}</b>
                                <small>{{ libelleType(e.type) }} · {{ nomCanal(e.channel) }}</small>
                            </span>
                        </span>
                        <span class="ls-ligne__date" :title="new Date(e.timestamp * 1000).toLocaleString('fr-FR')">{{ quand(e.timestamp) }}</span>
                        <div class="ls-actions">
                            <button type="button" class="ls-rond" v-if="e.context && Object.keys(e.context).length" @click="detail(e)" title="Détails" aria-label="Détails"><icon>data_object</icon></button>
                        </div>
                    </article>
                </div>

                <div class="journal__pages" v-if="pages > 1">
                    <button type="button" class="kiss-button" :disabled="page <= 1" @click="charger(page - 1)"><icon>arrow_back</icon>Plus récentes</button>
                    <span>Page {{ page }} sur {{ pages }}</span>
                    <button type="button" class="kiss-button" :disabled="page >= pages" @click="charger(page + 1)">Plus anciennes<icon>arrow_forward</icon></button>
                </div>
            </div>
        </template>

        <script type="module">
            const LIBELLES = { info: 'Info', error: 'Erreur', warning: 'Attention', notice: 'Remarque', alert: 'Alerte', debug: 'Débogage', critical: 'Critique', emergency: 'Urgence' };
            const ICONES = { info: 'info', error: 'error', warning: 'warning', notice: 'campaign', alert: 'notification_important', debug: 'bug_report', critical: 'report', emergency: 'emergency' };
            const MESSAGES = [[/^User Login: (.*)$/, 'Connexion de $1'], [/^User Logout: (.*)$/, 'Déconnexion de $1']];

            export default {
                components: {
                    menuTri: Vue.defineAsyncComponent(() => App.utils.import('dashboard:assets/vue/communs.js').then((m) => m.menuTri))
                },

                data() {
                    return {
                        types: <?= json_encode((object) $types) ?>,
                        canaux: [{ value: '', label: 'Tous' }].concat(<?= json_encode($canaux, JSON_UNESCAPED_UNICODE) ?>),
                        entrees: [], recherche: '', type: '', canal: '', page: 1, pages: 1, chargement: false, minuterie: null
                    };
                },

                computed: {
                    total() {
                        return Object.values(this.types).reduce((s, n) => s + n, 0);
                    },
                    filtres() {
                        return [{ cle: '', libelle: 'Tout', nombre: this.total }]
                            .concat(Object.keys(this.types).map((t) => ({ cle: t, libelle: LIBELLES[t] || t, nombre: this.types[t], alerte: ['error', 'critical', 'alert', 'emergency'].includes(t) })));
                    }
                },

                watch: {
                    type() { this.charger(1); },
                    canal() { this.charger(1); },
                    recherche() {
                        clearTimeout(this.minuterie);
                        this.minuterie = setTimeout(() => this.charger(1), 400);
                    }
                },

                mounted() {
                    this.charger(1);
                },

                methods: {
                    charger(page) {
                        const options = { limit: 50, skip: (page - 1) * 50, sort: { timestamp: -1 } };
                        const filtre = {};
                        if (this.recherche) filtre.message = { $regex: this.recherche, $options: 'i' };
                        if (this.type) filtre.type = this.type;
                        if (this.canal) filtre.channel = this.canal;
                        if (Object.keys(filtre).length) options.filter = filtre;
                        this.chargement = true;
                        this.$request('/system/logs/load', { options }).then((r) => {
                            this.entrees = (r.items || []).filter(Boolean);
                            this.page = r.page;
                            this.pages = r.pages;
                        }).catch(() => App.ui.notify('Les journaux n’ont pas pu être chargés.', 'error'))
                            .finally(() => { this.chargement = false; });
                    },

                    message(m) {
                        for (const [motif, texte] of MESSAGES) if (motif.test(m)) return m.replace(motif, texte);
                        return m;
                    },

                    libelleType(t) {
                        return LIBELLES[t] || t;
                    },

                    icone(t) {
                        return ICONES[t] || 'circle';
                    },

                    nomCanal(c) {
                        return (this.canaux.find((x) => x.value === c) || { label: c }).label;
                    },

                    quand(s) {
                        const il = Date.now() / 1000 - s;
                        if (il < 3600) return 'il y a ' + Math.max(1, Math.floor(il / 60)) + ' min';
                        if (il < 86400) return 'il y a ' + Math.floor(il / 3600) + ' h';
                        return new Date(s * 1000).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
                    },

                    detail(e) {
                        VueView.ui.offcanvas('system:assets/dialogs/json-viewer.js', { data: e.context, caption: this.message(e.message) });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
