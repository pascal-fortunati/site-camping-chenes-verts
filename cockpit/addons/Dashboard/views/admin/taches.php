<?php

/**
 * Tâches : la file des travaux lancés en arrière-plan, par état, et les processus qui les exécutent.
 *
 * @var bool $canStopProcess le serveur permet d'arrêter un processus (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$gestionnaires = new ArrayObject([]);
$this->trigger('worker.handlers.collect', [$gestionnaires]);
$gestionnaires = array_keys($gestionnaires->getArrayCopy());
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Tâches</h1>
                    <p class="tdb-accueil__phrase">Les travaux que Cockpit fait en arrière-plan (envois, traitements d’images…) et leur état.</p>
                    <div class="mt-bandeau__actions">
                        <button type="button" class="mt-bandeau__bouton" @click="charger(page)" :disabled="chargement"><icon>refresh</icon>Recharger</button>
                    </div>
                    <ul class="mt-bandeau__chiffres" v-if="stats">
                        <li><b>{{ stats.pending || 0 }}</b> en attente</li>
                        <li v-if="stats.failed"><b>{{ stats.failed }}</b> en échec</li>
                    </ul>
                </section>

                <section class="tdb-carte" v-if="processus !== null">
                    <h2><icon>memory</icon>Processus actifs</h2>
                    <p class="fiche-aide" v-if="!processus.length">Aucun processus ne traite la file en ce moment.</p>
                    <div class="ls-liste taches__processus" v-else>
                        <article class="ls-ligne" v-for="p in processus" :key="p.pid">
                            <span class="ls-ligne__principal">
                                <span class="ls-ligne__icone"><icon>memory</icon></span>
                                <span class="ls-ligne__texte"><b>Processus {{ p.pid }}</b><small>{{ p.mode }} · démarré {{ quand(p.start) }}</small></span>
                            </span>
                            <span class="ls-etat" :class="p.alive ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><span class="ls-etat__point"></span>{{ p.alive === true ? 'Actif' : (p.alive === false ? 'Arrêté' : 'Inconnu') }}</span>
                            <div class="ls-actions">
                                <button type="button" class="ls-rond ls-rond--danger" v-if="arreter && p.alive" @click="stopper(p.pid)" title="Arrêter" aria-label="Arrêter"><icon>stop_circle</icon></button>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="mt-outils">
                    <label class="mt-recherche">
                        <icon aria-hidden="true">search</icon>
                        <input type="search" v-model="recherche" @keydown.enter.prevent="charger(1)" placeholder="Chercher une tâche (Entrée)" aria-label="Chercher une tâche">
                    </label>
                    <div class="mt-filtres" role="group" aria-label="Filtrer par état">
                        <button type="button" class="mt-filtre" v-for="e in etats" :key="e[0]" :class="{'mt-filtre--actif': etat === e[0], 'mt-filtre--alerte': e[0] === 'failed' && stats && stats.failed}" :aria-pressed="etat === e[0] ? 'true' : 'false'" @click="etat = e[0]">
                            {{ e[1] }}<span class="mt-filtre__nombre">{{ stats ? (stats[e[0]] || 0) : '…' }}</span>
                        </button>
                    </div>
                </div>

                <div class="gc__groupes taches__types" v-if="types.length">
                    <button type="button" class="kiss-button kiss-button-small" v-for="t in types" :key="t" @click="recherche = t; charger(1)">{{ t }}</button>
                </div>

                <div class="ls-liste" v-if="chargement && !taches.length">
                    <div class="ls-ligne ls-ligne--fantome" v-for="n in 4" :key="n"></div>
                </div>

                <div class="mt-vide" v-else-if="!taches.length">
                    <icon>task_alt</icon>
                    <p>Aucune tâche {{ libelleEtat.toLowerCase() }}.</p>
                </div>

                <div class="ls-liste" v-else :class="{'journal--chargement': chargement}">
                    <article class="ls-ligne" v-for="t in taches" :key="t._id">
                        <span class="ls-ligne__principal">
                            <span class="ls-ligne__icone" :class="{'journal__icone--error': etat === 'failed'}"><icon>{{ icone }}</icon></span>
                            <span class="ls-ligne__texte"><b>{{ t.data && t.data.job }}</b><small>{{ libelleEtat }}</small></span>
                        </span>
                        <span class="ls-ligne__date">{{ quand(t.created_at) }}</span>
                        <div class="ls-actions">
                            <button type="button" class="ls-rond" @click="detail(t)" title="Détails" aria-label="Détails"><icon>data_object</icon></button>
                        </div>
                    </article>
                </div>

                <div class="journal__pages" v-if="page > 1 || taches.length === limite">
                    <button type="button" class="kiss-button" :disabled="page <= 1" @click="charger(page - 1)"><icon>arrow_back</icon>Précédentes</button>
                    <span>Page {{ page }}</span>
                    <button type="button" class="kiss-button" :disabled="taches.length < limite" @click="charger(page + 1)">Suivantes<icon>arrow_forward</icon></button>
                </div>
            </div>
        </template>

        <script type="module">
            const ETATS = [['pending', 'En attente', 'schedule'], ['reserved', 'En cours', 'lock_clock'], ['completed', 'Terminées', 'check_circle'], ['failed', 'En échec', 'error']];

            export default {
                data() {
                    return {
                        etats: ETATS, etat: 'pending', types: <?= json_encode($gestionnaires) ?>, arreter: <?= $canStopProcess ? 'true' : 'false' ?>,
                        stats: null, processus: null, taches: [], recherche: '', page: 1, limite: 25, chargement: false
                    };
                },

                computed: {
                    libelleEtat() {
                        return (ETATS.find((e) => e[0] === this.etat) || ETATS[0])[1];
                    },
                    icone() {
                        return (ETATS.find((e) => e[0] === this.etat) || ETATS[0])[2];
                    }
                },

                watch: {
                    etat() { this.charger(1); }
                },

                mounted() {
                    this.charger(1);
                },

                methods: {
                    charger(page) {
                        this.chargement = true;
                        this.page = page;
                        this.$request('/system/worker/load', { filter: this.recherche.trim(), status: this.etat, limit: this.limite, skip: (page - 1) * this.limite }).then((r) => {
                            this.stats = r.stats;
                            this.processus = r.workers ?? null;
                            this.taches = r.jobs || [];
                        }).catch(() => App.ui.notify('Les tâches n’ont pas pu être chargées.', 'error'))
                            .finally(() => { this.chargement = false; });
                    },

                    async stopper(pid) {
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        if (!await confirmer({ titre: `Arrêter le processus ${pid} ?`, texte: 'Les tâches en attente seront reprises au prochain démarrage.', bouton: 'Arrêter', danger: true, icone: 'stop_circle' })) return;
                        this.$request('/system/worker/stopProcess', { pid }).then(() => {
                            this.processus = this.processus.filter((p) => p.pid !== pid);
                            App.ui.notify('Processus arrêté.');
                        }).catch((r) => App.ui.notify((r && r.error) || 'L’arrêt a échoué.', 'error'));
                    },

                    quand(date) {
                        const t = typeof date === 'number' ? date * (date < 1e12 ? 1000 : 1) : Date.parse(date);
                        if (!t) return '';
                        const il = (Date.now() - t) / 1000;
                        if (il < 3600) return 'il y a ' + Math.max(1, Math.floor(il / 60)) + ' min';
                        if (il < 86400) return 'il y a ' + Math.floor(il / 3600) + ' h';
                        return 'le ' + new Date(t).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
                    },

                    detail(t) {
                        VueView.ui.offcanvas('system:assets/dialogs/json-viewer.js', { caption: (t.data && t.data.job) || 'Tâche', data: t });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
