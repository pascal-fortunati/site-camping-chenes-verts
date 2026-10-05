/**
 * Arborescence d'un modèle : les éléments rangés les uns sous les autres, dépliés à la demande, avec ordre
 * (monter, descendre), ajout d'un sous-élément, recherche et suppression. Routes /content/tree/* de Cockpit.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const version = new URL(import.meta.url).search;
const { confirmer } = await import(`./communs.js${version}`);

/**
 * Une branche : un niveau de l'arborescence, et ses enfants dépliés (composant récursif).
 */
const branche = {
    name: 'branche',
    props: { elements: Array, modele: Object, droits: Object, titre: Function, profondeur: { type: Number, default: 0 } },
    emits: ['deplacer', 'supprimer', 'deplier'],
    template: /*html*/`
        <div class="arbre__niveau" :class="{'arbre__niveau--enfant': profondeur}">
            <template v-for="(e, i) in elements" :key="e._id">
                <article class="ls-ligne arbre__ligne" :style="{'--profondeur': profondeur}">
                    <button type="button" class="ls-rond arbre__deplier" @click="$emit('deplier', e)" :disabled="!e._children" :aria-expanded="e._ouvert ? 'true' : 'false'" :aria-label="e._ouvert ? 'Replier' : 'Déplier'">
                        <icon>{{ e._children ? (e._ouvert ? 'expand_more' : 'chevron_right') : 'remove' }}</icon>
                    </button>
                    <a class="ls-ligne__principal" :href="$routeUrl('/content/tree/item/' + modele.name + '/' + e._id)">
                        <span class="ls-ligne__icone"><icon>{{ e._children ? 'folder' : 'description' }}</icon></span>
                        <span class="ls-ligne__texte"><b>{{ titre(e) }}</b><small>{{ e._children ? e._children + ' sous-élément' + (e._children > 1 ? 's' : '') : 'Aucun sous-élément' }}</small></span>
                    </a>
                    <span class="ls-etat" v-if="modele.publication !== false" :class="e._state === 1 ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><span class="ls-etat__point"></span>{{ e._state === 1 ? 'En ligne' : 'Hors ligne' }}</span>
                    <div class="ls-actions">
                        <template v-if="droits.ordre">
                            <button type="button" class="ls-rond" :disabled="i === 0" @click="$emit('deplacer', elements, i, -1)" title="Monter" aria-label="Monter"><icon>arrow_upward</icon></button>
                            <button type="button" class="ls-rond" :disabled="i === elements.length - 1" @click="$emit('deplacer', elements, i, 1)" title="Descendre" aria-label="Descendre"><icon>arrow_downward</icon></button>
                        </template>
                        <a class="ls-rond" v-if="droits.creer" :href="$routeUrl('/content/tree/item/' + modele.name + '?pid=' + e._id)" title="Ajouter un sous-élément" aria-label="Ajouter un sous-élément"><icon>add</icon></a>
                        <a class="ls-rond" :href="$routeUrl('/content/tree/item/' + modele.name + '/' + e._id)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                        <button type="button" class="ls-rond ls-rond--danger" v-if="droits.supprimer" @click="$emit('supprimer', elements, e)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                    </div>
                </article>
                <branche v-if="e._ouvert && e.children && e.children.length" :elements="e.children" :modele="modele" :droits="droits" :titre="titre" :profondeur="profondeur + 1"
                    @deplacer="(...a) => $emit('deplacer', ...a)" @supprimer="(...a) => $emit('supprimer', ...a)" @deplier="(x) => $emit('deplier', x)"></branche>
            </template>
        </div>`
};
branche.components = { branche };

export default {
    components: { branche },

    props: { model: Object, droits: Object, vue: Object },

    data() {
        return { elements: [], resultats: null, recherche: '', chargement: true, minuterie: null };
    },

    computed: {
        champTitre() {
            return ['titre', 'title', 'nom', 'name', 'label'].find((n) => (this.model.fields || []).some((f) => f.name === n)) || null;
        },
        mots() {
            return (this.vue && this.vue.mots) || ['élément', 'éléments', 'Ajouter'];
        }
    },

    watch: {
        recherche(v) {
            clearTimeout(this.minuterie);
            if (!v.trim()) {
                this.resultats = null;
                return;
            }
            this.minuterie = setTimeout(() => this.chercher(v), 350);
        }
    },

    mounted() {
        this.charger(null).then((e) => { this.elements = e; this.chargement = false; });
    },

    methods: {
        titre(e) {
            const t = this.champTitre ? e[this.champTitre] : '';
            return (typeof t === 'string' && t.trim()) ? t : 'Sans titre';
        },

        /**
         * @param {?string} pid l'élément parent, null pour la racine
         * @returns {Promise<object[]>}
         */
        charger(pid) {
            return this.$request(`/content/tree/load/${this.model.name}`, { _pid: pid || {} })
                .then((r) => (r || []).map((e) => ({ ...e, _ouvert: false, children: [] })))
                .catch(() => { App.ui.notify('L’arborescence n’a pas pu être chargée.', 'error'); return []; });
        },

        deplier(e) {
            if (e._ouvert) {
                e._ouvert = false;
                return;
            }
            this.charger(e._id).then((enfants) => { e.children = enfants; e._ouvert = true; });
        },

        deplacer(liste, i, sens) {
            const j = i + sens;
            if (j < 0 || j >= liste.length) return;
            liste.splice(j, 0, liste.splice(i, 1)[0]);
            this.$request(`/content/tree/updateOrder/${this.model.name}`, { items: liste.map((e, o) => ({ _id: e._id, _o: o })) })
                .catch(() => App.ui.notify('Le nouvel ordre n’a pas été enregistré.', 'error'));
        },

        async supprimer(liste, e) {
            const oui = await confirmer({ titre: `Supprimer « ${this.titre(e)} » ?`, texte: e._children ? 'Ses sous-éléments sont supprimés avec lui. Cette action est définitive.' : 'Cette action est définitive.', bouton: 'Supprimer', danger: true });
            if (!oui) return;
            this.$request(`/content/tree/remove/${this.model.name}`, { item: e }).then(() => {
                liste.splice(liste.indexOf(e), 1);
                App.ui.notify('Supprimé.');
            }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
        },

        chercher(q) {
            this.$request(`/content/collection/find/${this.model.name}`, { options: { filter: q, limit: 30 } })
                .then((r) => { this.resultats = (r && r.items) || []; })
                .catch(() => { this.resultats = []; });
        }
    },

    template: /*html*/`
        <div class="mt ls arbre">
            <section class="tdb-accueil tdb-accueil--page">
                <h1 class="tdb-accueil__titre">{{ model.label || model.name }}</h1>
                <p class="tdb-accueil__phrase" v-if="model.info">{{ model.info }}</p>
                <div class="mt-bandeau__actions" v-if="droits.creer">
                    <a class="mt-bandeau__bouton" :href="$routeUrl('/content/tree/item/' + model.name)"><icon>add</icon>{{ mots[2] }}</a>
                </div>
                <ul class="mt-bandeau__chiffres" v-if="!chargement">
                    <li><b>{{ elements.length }}</b> {{ elements.length > 1 ? mots[1] : mots[0] }} au premier niveau</li>
                </ul>
            </section>

            <div class="mt-outils">
                <label class="mt-recherche">
                    <icon aria-hidden="true">search</icon>
                    <input type="search" v-model="recherche" :placeholder="'Chercher dans les ' + mots[1]" :aria-label="'Chercher dans les ' + mots[1]">
                </label>
            </div>

            <div class="ls-liste" v-if="chargement">
                <div class="ls-ligne ls-ligne--fantome" v-for="n in 5" :key="n"></div>
            </div>

            <template v-else-if="resultats !== null">
                <div class="mt-vide" v-if="!resultats.length"><icon>search_off</icon><p>Rien ne correspond.</p></div>
                <div class="ls-liste" v-else>
                    <article class="ls-ligne" v-for="e in resultats" :key="e._id">
                        <a class="ls-ligne__principal" :href="$routeUrl('/content/tree/item/' + model.name + '/' + e._id)">
                            <span class="ls-ligne__icone"><icon>description</icon></span>
                            <span class="ls-ligne__texte"><b>{{ titre(e) }}</b></span>
                        </a>
                        <div class="ls-actions"><a class="ls-rond" :href="$routeUrl('/content/tree/item/' + model.name + '/' + e._id)" aria-label="Modifier"><icon>edit</icon></a></div>
                    </article>
                </div>
            </template>

            <div class="mt-vide" v-else-if="!elements.length">
                <icon>account_tree</icon>
                <p>Aucun {{ mots[0] }} pour l’instant.</p>
            </div>

            <div class="ls-liste" v-else>
                <branche :elements="elements" :modele="model" :droits="droits" :titre="titre" @deplacer="deplacer" @supprimer="supprimer" @deplier="deplier"></branche>
            </div>
        </div>
    `
};
