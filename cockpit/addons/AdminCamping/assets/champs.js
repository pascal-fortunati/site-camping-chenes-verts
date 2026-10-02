/**
 * Les listes d'éléments d'une fiche (les sections d'une page, les lignes d'une ardoise…) se modifient sur place :
 * chaque élément est une carte qui se déplie, au lieu d'une fenêtre à ouvrir, remplir puis valider. Un élément
 * ajouté s'ouvre aussitôt. Le reste de l'éditeur de champs de Cockpit est repris tel quel : seuls la liste et le
 * bouton « Ajouter » sont remplacés dans son modèle d'affichage — si ce balisage change, l'original reste.
 */

const version = new URL(import.meta.url).search;
const origine = await import(App.base('system:assets/vue-components/fields/renderer.js') + version);
const { default: confirmer } = await import(`./confirmer.js${version}`);

const Base = origine.FieldRenderer;

const LISTE = /<vue-draggable v-model="val" animation="100" handle="\.fm-handle" v-if="multipleListMode=='list' && Array\.isArray\(val\)">[\s\S]*?<\/vue-draggable>/;
const AJOUTER = /<button type="button" class="kiss-button kiss-button-small" @click="addFieldItem\(field\)">[\s\S]*?<\/button>/;
const VIDE = /<kiss-card class="kiss-padding-larger kiss-align-center kiss-size-small kiss-color-muted" theme="contrast" v-show="[^"]*">[\s\S]*?<\/kiss-card>/;

const liste = /*html*/`
                <vue-draggable v-model="val" animation="150" handle=".el-poignee" @start="ouverts = []" v-if="multipleListMode=='list' && Array.isArray(val)" class="el-liste">
                    <section class="el" v-for="(element, index) in val" :class="{'el--ouvert': ouverts.includes(index)}">
                        <header class="el__tete">
                            <button type="button" class="el__poignee el-poignee" :aria-label="'Déplacer'" title="Glisser pour déplacer"><icon>drag_indicator</icon></button>
                            <button type="button" class="el__resume" @click="basculer(index)" :aria-expanded="ouverts.includes(index) ? 'true' : 'false'">
                                <span class="el__numero">{{ index + 1 }}</span>
                                <span class="el__titre" v-if="val[index] == null">Vide</span>
                                <span class="el__titre" v-else-if="fieldTypes[field.type]?.render" v-html="fieldTypes[field.type].render(val[index], field)"></span>
                                <span class="el__titre" v-else-if="typeof(val[index]) !== 'object'">{{ val[index] }}</span>
                                <span class="el__titre" v-else>Élément {{ index + 1 }}</span>
                                <icon class="el__fleche">expand_more</icon>
                            </button>
                            <div class="el__actions">
                                <button type="button" class="el__rond" @click="monter(index)" :disabled="index === 0" aria-label="Monter" title="Monter"><icon>arrow_upward</icon></button>
                                <button type="button" class="el__rond" @click="descendre(index)" :disabled="index === val.length - 1" aria-label="Descendre" title="Descendre"><icon>arrow_downward</icon></button>
                                <button type="button" class="el__rond" @click="dupliquer(index)" aria-label="Dupliquer" title="Dupliquer"><icon>content_copy</icon></button>
                                <button type="button" class="el__rond el__rond--danger" @click="retirer(index)" aria-label="Supprimer" title="Supprimer"><icon>delete</icon></button>
                            </div>
                        </header>
                        <div class="el__corps" v-if="ouverts.includes(index)">
                            <component :is="getFieldType()" v-model="val[index]" v-bind="field.opts" :locale="locale"></component>
                        </div>
                    </section>
                </vue-draggable>`;

const ajouter = /*html*/`<button type="button" class="kiss-button el-ajouter" @click="ajouter(field)"><icon>add</icon>{{ libelleAjouter }}</button>`;

const vide = /*html*/`<p class="el-vide" v-show="!val || !Array.isArray(val) || !val.length"><icon>playlist_add</icon>Rien pour l’instant.</p>`;

let modele = Base.template;
const remplacable = LISTE.test(modele) && AJOUTER.test(modele);
if (remplacable) {
    modele = modele.replace(LISTE, liste).replace(AJOUTER, ajouter).replace(VIDE, vide);
}

export const FieldRenderer = remplacable ? {
    ...Base,

    data() {
        const d = Base.data.call(this);
        d.ouverts = [];
        return d;
    },

    computed: {
        ...Base.computed,
        libelleAjouter() {
            if (this.field.opts && this.field.opts.ajouter) return this.field.opts.ajouter;   // réglé dans le modèle
            const l = (this.field.label || '').toLowerCase();
            if (/contenu|section/.test(l)) return 'Ajouter une section';
            if (/réseau|reseau/.test(l)) return 'Ajouter un réseau';
            if (/entrée|entree|menu/.test(l)) return 'Ajouter une entrée';
            if (/ligne|tarif|prix/.test(l)) return 'Ajouter une ligne';
            return 'Ajouter';
        }
    },

    methods: {
        ...Base.methods,

        basculer(index) {
            const i = this.ouverts.indexOf(index);
            if (i > -1) {
                this.ouverts.splice(i, 1);
            } else {
                this.ouverts.push(index);
                // Les champs complètent leurs valeurs vides en s'affichant : la fiche ne doit pas y voir une modification.
                window.dispatchEvent(new CustomEvent('admincamping-deplie'));
            }
        },

        ajouter(field) {
            // Un type de champ qui a sa propre façon d'ajouter (un lien vers un contenu…) la garde.
            if (this.fieldTypes[field.type]?.addFieldItem) {
                this.addFieldItem(field);
                return;
            }
            if (!Array.isArray(this.val)) this.val = [];
            const defaut = field.default !== undefined && field.default !== null
                ? JSON.parse(JSON.stringify(field.default))
                : (field.type === 'set' ? {} : null);
            this.val.push(defaut);
            this.ouverts = [this.val.length - 1];
            this.$nextTick(() => {
                const nouveau = this.$el.querySelectorAll('.el')[this.val.length - 1];
                if (!nouveau) return;
                nouveau.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                const champ = nouveau.querySelector('.el__corps input, .el__corps textarea, .el__corps select, .el__corps [contenteditable]');
                if (champ) setTimeout(() => champ.focus({ preventScroll: true }), 150);
            });
        },

        monter(index) {
            if (index === 0) return;
            const [el] = this.val.splice(index, 1);
            this.val.splice(index - 1, 0, el);
            this.ouverts = this.ouverts.map((i) => (i === index ? index - 1 : (i === index - 1 ? index : i)));
        },

        descendre(index) {
            if (index >= this.val.length - 1) return;
            const [el] = this.val.splice(index, 1);
            this.val.splice(index + 1, 0, el);
            this.ouverts = this.ouverts.map((i) => (i === index ? index + 1 : (i === index + 1 ? index : i)));
        },

        dupliquer(index) {
            this.val.splice(index + 1, 0, JSON.parse(JSON.stringify(this.val[index])));
            this.ouverts = [index + 1];
        },

        async retirer(index) {
            const oui = await confirmer({
                titre: 'Supprimer cet élément ?',
                texte: 'Il disparaîtra quand vous enregistrerez la fiche.',
                bouton: 'Supprimer',
                danger: true
            });
            if (!oui) return;
            this.val.splice(index, 1);
            this.ouverts = this.ouverts.filter((i) => i !== index).map((i) => (i > index ? i - 1 : i));
        }
    },

    template: modele
} : Base;

// Le choix du groupe de champs (« GROUPE Tous les champs », une liste déroulante) devient une rangée d'onglets.
const GROUPES = /<kiss-card class="kiss-padding-small kiss-overlay-input kiss-flex kiss-flex-middle kiss-width-1-3@m kiss-margin" theme="bordered contrast" v-if="groups\.length">[\s\S]*?<\/kiss-card>/;
const onglets = /*html*/`<nav class="champs-onglets" role="tablist" aria-label="Groupes de champs" v-if="groups.length > 1">
                <button type="button" role="tab" :aria-selected="!group ? 'true' : 'false'" :class="{'champs-onglets--actif': !group}" @click="group = null"><icon>select_all</icon>Tout</button>
                <button type="button" role="tab" v-for="name in groups" :aria-selected="group == name ? 'true' : 'false'" :class="{'champs-onglets--actif': group == name}" @click="group = name">{{ name }}</button>
            </nav>`;

export default {
    ...origine.default,
    template: GROUPES.test(origine.default.template) ? origine.default.template.replace(GROUPES, onglets) : origine.default.template,
    computed: {
        ...origine.default.computed,
        // Les groupes dans l'ordre des champs de la fiche (Cockpit les trie par ordre alphabétique).
        groups() {
            const groupes = [];
            (this.fields || []).forEach((field) => {
                if (!field.group || groupes.includes(field.group) || !this.checkFieldCondition(field)) return;
                groupes.push(field.group);
            });
            return groupes;
        }
    },
    components: { ...(origine.default.components || {}), fieldRenderer: FieldRenderer }
};
