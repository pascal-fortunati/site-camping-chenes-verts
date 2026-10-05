/**
 * Champs des fiches aux couleurs du site. Chaque export est une fabrique : le composant d'origine de Cockpit n'est
 * chargé qu'au premier affichage du champ (voir l'aiguillage de dashboard.js).
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const version = new URL(import.meta.url).search;
const { vignette, confirmer } = await import(`./communs.js${version}`);

/**
 * Éditeur de champs : les listes d'éléments se modifient sur place et les groupes deviennent des onglets.
 * Si le balisage de Cockpit change, son éditeur d'origine est gardé.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function editeur() {
    const origine = await import(App.base('system:assets/vue-components/fields/renderer.js') + version);

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

    const FieldRenderer = remplacable ? {
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
                    window.dispatchEvent(new CustomEvent('dashboard-deplie'));
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

    return {
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
}

/**
 * Champ image : une carte compacte (aperçu, nom, taille) et le dépôt d'une photo depuis l'ordinateur.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function image() {
    const { default: origine } = await import(App.base('assets:assets/vue-components/field-asset.js') + version);

    const taille = (octets) => !octets ? '' : (octets < 1048576 ? Math.max(1, Math.round(octets / 1024)) + ' ko' : (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo');

    return {
        ...origine,

        components: { ...(origine.components || {}), mtVignette: vignette },

        data() {
            return { ...origine.data.call(this), survol: false, envoi: false };
        },

        computed: {
            ...(origine.computed || {}),
            details() {
                if (!this.val) return '';
                return [this.val.width ? `${this.val.width} × ${this.val.height}` : (this.val.mime || ''), taille(this.val.size)].filter(Boolean).join(' · ');
            },
            adresse() {
                return this.val ? this.$baseUrl('#uploads:' + this.val.path) : '';
            }
        },

        methods: {
            ...origine.methods,

            retirer() {
                this.val = null;
            },

            deposer(e) {
                this.survol = false;
                const fichier = e.dataTransfer && e.dataTransfer.files[0];
                if (!fichier) return;
                const donnees = new FormData();
                donnees.append('files[]', fichier);
                this.envoi = true;
                this.$request('/assets/upload', donnees).then((r) => {
                    if (r && r.assets && r.assets[0]) {
                        this.val = r.assets[0];
                        App.ui.notify('Image envoyée et choisie.');
                    } else {
                        App.ui.notify('Ce fichier a été refusé.', 'error');
                    }
                }).catch((r) => App.ui.notify((r && r.error) || 'L’envoi a échoué.', 'error'))
                  .finally(() => { this.envoi = false; });
            }
        },

        template: /*html*/`
            <div field="asset" class="champ-image">

                <div class="champ-image__vide" :class="{'champ-image__vide--survol': survol}" v-if="!val" role="button" tabindex="0"
                    @click="pickAsset()" @keydown.enter.prevent="pickAsset()" @keydown.space.prevent="pickAsset()"
                    @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposer">
                    <icon>{{ envoi ? 'hourglass_top' : 'add_photo_alternate' }}</icon>
                    <b>{{ envoi ? 'Envoi en cours…' : 'Choisir une image' }}</b>
                    <small>ou glissez une photo ici depuis votre ordinateur</small>
                </div>

                <div class="champ-image__plein" v-else @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposer" :class="{'champ-image__plein--survol': survol}">
                    <a class="champ-image__apercu" :href="adresse" target="_blank" rel="noopener" title="Voir en grand">
                        <mt-vignette v-if="val.type === 'image'" :asset="val" :largeur="320"></mt-vignette>
                        <icon v-else>description</icon>
                    </a>
                    <div class="champ-image__texte">
                        <b :title="val.title">{{ val.title || 'Sans nom' }}</b>
                        <small>{{ details }}</small>
                        <div class="champ-image__boutons">
                            <button type="button" class="kiss-button kiss-button-small" @click="pickAsset()"><icon>swap_horiz</icon>Changer</button>
                            <a class="kiss-button kiss-button-small" :href="$routeUrl('/assets') + '?image=' + val._id" target="_blank" rel="noopener"><icon>perm_media</icon>Voir dans les médias</a>
                            <button type="button" class="kiss-button kiss-button-small" v-if="meta" @click="editMeta()"><icon>tune</icon>Détails</button>
                            <button type="button" class="kiss-button kiss-button-small mt-danger" @click="retirer()"><icon>close</icon>Retirer</button>
                        </div>
                    </div>
                </div>
            </div>
        `
    };
}

/**
 * Champ « lien vers un contenu » : une liste déroulante avec recherche. Valeur : { _model, _id }.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function lien() {
    const { default: origine } = await import(App.base('content:assets/vue-components/field-content-item-link.js') + version);

    const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    const listes = new Map();   // un seul chargement par modèle, partagé par tous les champs de la page

    return {
        ...origine,

        data() {
            return { ...origine.data.call(this), elements: [], ouvert: false, q: '', choisi: 0, charge: false };
        },

        computed: {
            ...(origine.computed || {}),
            actuel() {
                return this.val && this.val._id ? this.elements.find((e) => e._id === this.val._id) || null : null;
            },
            resultats() {
                const mots = plat(this.q).split(/\s+/).filter(Boolean);
                return this.elements.filter((e) => !mots.length || mots.every((m) => plat(this.titre(e) + ' ' + (e.slug || '')).includes(m))).slice(0, 50);
            }
        },

        mounted() {
            origine.mounted && origine.mounted.call(this);
            this.charger();
            this.ailleurs = (e) => { if (this.ouvert && !this.$el.contains(e.target)) this.fermer(); };
            document.addEventListener('mousedown', this.ailleurs);
        },

        unmounted() {
            document.removeEventListener('mousedown', this.ailleurs);
        },

        methods: {
            ...origine.methods,

            charger() {
                if (!this.link) return;
                if (!listes.has(this.link)) {
                    listes.set(this.link, this.$request(`/content/collection/find/${this.link}`, { options: { limit: 1000, sort: { titre: 1 }, filter: this.filter || undefined } })
                        .then((r) => (r && r.items) || []).catch(() => []));
                }
                listes.get(this.link).then((items) => { this.elements = items; this.charge = true; });
            },

            titre(e) {
                const t = e.titre || e.title || e.nom || e.name;
                return (typeof t === 'string' && t.trim()) ? t : 'Sans titre';
            },

            ouvrir() {
                this.ouvert = true;
                this.q = '';
                this.choisi = Math.max(0, this.resultats.findIndex((e) => this.val && e._id === this.val._id));
                this.$nextTick(() => this.$refs.champ && this.$refs.champ.focus());
            },

            fermer() {
                this.ouvert = false;
            },

            choisir(e) {
                this.val = { _model: this.link, _id: e._id };
                this.item = e;
                this.update();
                this.fermer();
            },

            touche(e) {
                const n = this.resultats.length;
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (n) this.choisi = (this.choisi + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                    this.$nextTick(() => { const l = this.$el.querySelector('.lien__option--choisie'); if (l) l.scrollIntoView({ block: 'nearest' }); });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (this.resultats[this.choisi]) this.choisir(this.resultats[this.choisi]);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.fermer();
                }
            }
        },

        watch: {
            ...(origine.watch || {}),
            q() { this.choisi = 0; }
        },

        template: /*html*/`
            <div field="content-item-link" class="lien">
                <div class="lien__vide" v-if="!link">Aucun modèle relié à ce champ.</div>

                <button type="button" class="lien__bouton" v-else-if="!ouvert" @click="ouvrir" :aria-expanded="ouvert ? 'true' : 'false'">
                    <icon class="lien__icone">description</icon>
                    <span class="lien__texte" v-if="actuel"><b>{{ titre(actuel) }}</b><small v-if="actuel.slug">/{{ actuel.slug }}</small></span>
                    <span class="lien__texte lien__texte--vide" v-else-if="val && val._id && !charge">Chargement…</span>
                    <span class="lien__texte lien__texte--vide" v-else-if="val && val._id">Élément introuvable (supprimé ?)</span>
                    <span class="lien__texte lien__texte--vide" v-else>Choisir…</span>
                    <icon class="lien__fleche">unfold_more</icon>
                </button>

                <div class="lien__ouvert" v-else>
                    <label class="lien__recherche">
                        <icon>search</icon>
                        <input ref="champ" type="text" v-model="q" @keydown="touche" placeholder="Chercher par titre ou adresse" autocomplete="off">
                    </label>
                    <ul class="lien__options" role="listbox">
                        <li v-for="(e, i) in resultats" :key="e._id" role="option" :aria-selected="val && e._id === val._id ? 'true' : 'false'"
                            class="lien__option" :class="{'lien__option--choisie': i === choisi, 'lien__option--actuelle': val && e._id === val._id}"
                            @mousedown.prevent="choisir(e)" @mouseenter="choisi = i">
                            <span><b>{{ titre(e) }}</b><small v-if="e.slug">/{{ e.slug }}<template v-if="e._state !== 1"> · hors ligne</template></small></span>
                            <icon v-if="val && e._id === val._id">check</icon>
                        </li>
                        <li class="lien__aucun" v-if="!resultats.length">Rien ne correspond.</li>
                    </ul>
                </div>
            </div>
        `
    };
}

/**
 * Champ « liste de choix » : la même liste déroulante, recherche à partir de 8 options, cases à cocher
 * si plusieurs choix sont permis.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function choix() {
    const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-select.js') + version);

    const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

    return {
        ...origine,

        data() {
            return { ...origine.data.call(this), ouvert: false, q: '', choisi: 0 };
        },

        computed: {
            ...(origine.computed || {}),
            toutes() {
                return Object.entries(this.list || {}).flatMap(([groupe, lst]) => lst.map((o) => ({ ...o, groupe })));
            },
            avecRecherche() {
                return this.toutes.length > 8;
            },
            resultats() {
                const mots = plat(this.q).split(/\s+/).filter(Boolean);
                return this.toutes.filter((o) => !mots.length || mots.every((m) => plat(o.label).includes(m)));
            },
            libelleActuel() {
                if (this.multiple) {
                    const choisis = this.toutes.filter((o) => this.selected(o.value));
                    return choisis.length ? choisis.map((o) => o.label).join(', ') : '';
                }
                const o = this.toutes.find((x) => String(x.value) === String(this.val ?? ''));
                return o ? o.label : (this.val ? String(this.val) : '');
            }
        },

        mounted() {
            origine.mounted && origine.mounted.call(this);
            this.ailleurs = (e) => { if (this.ouvert && !this.$el.contains(e.target)) this.ouvert = false; };
            document.addEventListener('mousedown', this.ailleurs);
        },

        unmounted() {
            document.removeEventListener('mousedown', this.ailleurs);
        },

        methods: {
            ...origine.methods,

            ouvrir() {
                this.ouvert = true;
                this.q = '';
                this.choisi = Math.max(0, this.resultats.findIndex((o) => this.estChoisi(o)));
                this.$nextTick(() => {
                    const cible = this.$el.querySelector(this.avecRecherche ? '.lien__recherche input' : '.lien__options');
                    if (cible) cible.focus();
                });
            },

            estChoisi(o) {
                return this.multiple ? this.selected(o.value) : String(o.value) === String(this.val ?? '');
            },

            prendre(o) {
                if (this.multiple) {
                    this.select(o.value);
                    return;
                }
                this.val = o ? o.value : null;
                this.update();
                this.ouvert = false;
                this.$nextTick(() => { const b = this.$el.querySelector('.lien__bouton'); if (b) b.focus(); });
            },

            touche(e) {
                const n = this.resultats.length;
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (n) this.choisi = (this.choisi + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                    this.$nextTick(() => { const l = this.$el.querySelector('.lien__option--choisie'); if (l) l.scrollIntoView({ block: 'nearest' }); });
                } else if (e.key === 'Enter' || (e.key === ' ' && !this.avecRecherche)) {
                    e.preventDefault();
                    if (this.resultats[this.choisi]) this.prendre(this.resultats[this.choisi]);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.ouvert = false;
                }
            }
        },

        watch: {
            ...(origine.watch || {}),
            q() { this.choisi = 0; }
        },

        template: /*html*/`
            <div field="select" class="lien choix" :class="{'kiss-disabled': loading}">

                <button type="button" class="lien__bouton" v-if="!ouvert" @click="ouvrir" @keydown.down.prevent="ouvrir" aria-haspopup="listbox" aria-expanded="false">
                    <span class="lien__texte" v-if="libelleActuel"><b>{{ libelleActuel }}</b></span>
                    <span class="lien__texte lien__texte--vide" v-else>{{ placeholder || (multiple ? 'Choisir…' : 'Choisir…') }}</span>
                    <icon class="lien__fleche">unfold_more</icon>
                </button>

                <div class="lien__ouvert" v-else @keydown="touche">
                    <label class="lien__recherche" v-if="avecRecherche">
                        <icon>search</icon>
                        <input type="text" v-model="q" placeholder="Chercher" autocomplete="off">
                    </label>
                    <ul class="lien__options" role="listbox" tabindex="0" :aria-multiselectable="multiple ? 'true' : 'false'">
                        <li class="lien__option choix__aucun" v-if="!multiple && !q" @mousedown.prevent="prendre(null)" :class="{'lien__option--actuelle': !val}">
                            <span><b>Aucun</b></span><icon v-if="!val">check</icon>
                        </li>
                        <template v-for="(o, i) in resultats" :key="o.groupe + o.value">
                            <li class="choix__groupe" v-if="o.groupe && (i === 0 || resultats[i - 1].groupe !== o.groupe)">{{ o.groupe }}</li>
                            <li class="lien__option" role="option" :aria-selected="estChoisi(o) ? 'true' : 'false'"
                                :class="{'lien__option--choisie': i === choisi, 'lien__option--actuelle': estChoisi(o)}"
                                @mousedown.prevent="prendre(o)" @mouseenter="choisi = i">
                                <span><b>{{ o.label }}</b></span>
                                <icon v-if="multiple">{{ estChoisi(o) ? 'check_box' : 'check_box_outline_blank' }}</icon>
                                <icon v-else-if="estChoisi(o)">check</icon>
                            </li>
                        </template>
                        <li class="lien__aucun" v-if="!resultats.length">Rien ne correspond.</li>
                    </ul>
                    <div class="choix__pied" v-if="multiple"><button type="button" class="kiss-button kiss-button-small" @mousedown.prevent="ouvert = false"><icon>check</icon>Terminé</button></div>
                </div>
            </div>
        `
    };
}

/**
 * Champ date : un calendrier ouvert par tout le champ, semaine du lundi. Valeur : « AAAA-MM-JJ ».
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function date() {
    const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-date.js') + version);

    const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const deux = (n) => String(n).padStart(2, '0');
    const versTexte = (d) => `${d.getFullYear()}-${deux(d.getMonth() + 1)}-${deux(d.getDate())}`;
    const lire = (t) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(t || '');
        return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
    };

    return {
        ...origine,

        data() {
            const d = lire(this.modelValue) || new Date();
            return { ...origine.data.call(this), ouvert: false, mois: d.getMonth(), annee: d.getFullYear(), focus: versTexte(d) };
        },

        computed: {
            ...(origine.computed || {}),
            texte() {
                const d = lire(this.val);
                return d ? `${JOURS[d.getDay()]} ${d.getDate()}${d.getDate() === 1 ? 'er' : ''} ${MOIS[d.getMonth()]} ${d.getFullYear()}` : '';
            },
            titreMois() {
                return `${MOIS[this.mois]} ${this.annee}`;
            },
            semaines() {
                const premier = new Date(this.annee, this.mois, 1);
                const decalage = (premier.getDay() + 6) % 7;   // lundi = 0
                const debut = new Date(this.annee, this.mois, 1 - decalage);
                const aujourdhui = versTexte(new Date());
                const jours = [];
                for (let i = 0; i < 42; i++) {
                    const d = new Date(debut.getFullYear(), debut.getMonth(), debut.getDate() + i);
                    const t = versTexte(d);
                    jours.push({ t, n: d.getDate(), dehors: d.getMonth() !== this.mois, auj: t === aujourdhui, choisi: t === this.val, interdit: this.interdit(t) });
                }
                return [0, 1, 2, 3, 4, 5].map((s) => jours.slice(s * 7, s * 7 + 7));
            }
        },

        mounted() {
            origine.mounted && origine.mounted.call(this);
            this.ailleurs = (e) => { if (this.ouvert && !this.$el.contains(e.target)) this.ouvert = false; };
            document.addEventListener('mousedown', this.ailleurs);
        },

        unmounted() {
            document.removeEventListener('mousedown', this.ailleurs);
        },

        methods: {
            ...origine.methods,

            interdit(t) {
                return (this.min && t < this.min) || (this.max && t > this.max);
            },

            ouvrir() {
                if (this.readonly) return;
                const d = lire(this.val) || new Date();
                this.mois = d.getMonth();
                this.annee = d.getFullYear();
                this.focus = versTexte(d);
                this.ouvert = true;
                this.$nextTick(() => { const g = this.$el.querySelector('.cal__grille'); if (g) g.focus(); });
            },

            decaler(mois) {
                const d = new Date(this.annee, this.mois + mois, 1);
                this.mois = d.getMonth();
                this.annee = d.getFullYear();
            },

            choisir(t) {
                if (!t || this.interdit(t)) return;
                this.val = t;
                this.update();
                this.ouvert = false;
                this.$nextTick(() => { const b = this.$el.querySelector('.cal__champ'); if (b) b.focus(); });
            },

            aujourdhui() {
                return versTexte(new Date());
            },

            effacer() {
                this.val = '';
                this.update();
                this.ouvert = false;
            },

            touche(e) {
                const pas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
                const d = lire(this.focus) || new Date();
                if (pas) {
                    e.preventDefault();
                    d.setDate(d.getDate() + pas);
                } else if (e.key === 'PageUp' || e.key === 'PageDown') {
                    e.preventDefault();
                    d.setMonth(d.getMonth() + (e.key === 'PageUp' ? -1 : 1));
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.choisir(this.focus);
                    return;
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.ouvert = false;
                    return;
                } else {
                    return;
                }
                this.focus = versTexte(d);
                this.mois = d.getMonth();
                this.annee = d.getFullYear();
            }
        },

        template: /*html*/`
            <div field="date" class="cal">
                <button type="button" class="cal__champ lien__bouton" @click="ouvert ? (ouvert = false) : ouvrir()" :disabled="readonly" aria-haspopup="dialog" :aria-expanded="ouvert ? 'true' : 'false'">
                    <icon class="lien__icone">calendar_month</icon>
                    <span class="lien__texte" v-if="texte"><b>{{ texte }}</b></span>
                    <span class="lien__texte lien__texte--vide" v-else>Choisir une date</span>
                    <icon class="lien__fleche">{{ ouvert ? 'expand_less' : 'expand_more' }}</icon>
                </button>

                <div class="cal__panneau" v-if="ouvert" role="dialog" aria-label="Choisir une date">
                    <header class="cal__tete">
                        <button type="button" class="el__rond" @click="decaler(-12)" aria-label="Année précédente" title="Année précédente"><icon>keyboard_double_arrow_left</icon></button>
                        <button type="button" class="el__rond" @click="decaler(-1)" aria-label="Mois précédent" title="Mois précédent"><icon>chevron_left</icon></button>
                        <b class="cal__titre">{{ titreMois }}</b>
                        <button type="button" class="el__rond" @click="decaler(1)" aria-label="Mois suivant" title="Mois suivant"><icon>chevron_right</icon></button>
                        <button type="button" class="el__rond" @click="decaler(12)" aria-label="Année suivante" title="Année suivante"><icon>keyboard_double_arrow_right</icon></button>
                    </header>
                    <div class="cal__grille" tabindex="0" @keydown="touche" role="grid">
                        <div class="cal__ligne cal__ligne--jours" role="row"><span v-for="j in ['L', 'M', 'M', 'J', 'V', 'S', 'D']" role="columnheader">{{ j }}</span></div>
                        <div class="cal__ligne" role="row" v-for="(s, i) in semaines" :key="i">
                            <button type="button" role="gridcell" tabindex="-1" v-for="j in s" :key="j.t"
                                class="cal__jour" :class="{'cal__jour--dehors': j.dehors, 'cal__jour--auj': j.auj, 'cal__jour--choisi': j.choisi, 'cal__jour--focus': j.t === focus}"
                                :disabled="j.interdit" :aria-selected="j.choisi ? 'true' : 'false'" @click="choisir(j.t)">{{ j.n }}</button>
                        </div>
                    </div>
                    <footer class="cal__pied">
                        <button type="button" class="kiss-button kiss-button-small" @click="effacer"><icon>backspace</icon>Effacer</button>
                        <button type="button" class="kiss-button kiss-button-small" @click="choisir(aujourdhui())"><icon>today</icon>Aujourd’hui</button>
                    </footer>
                </div>
            </div>
        `
    };
}

/**
 * Champ nombre : boutons − et + qui respectent min, max et pas.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function nombre() {
    const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-number.js') + version);

    const nombre = (v) => v !== null && v !== '' && !isNaN(Number(v));

    return {
        ...origine,

        computed: {
            ...(origine.computed || {}),
            pas() {
                return nombre(this.step) ? Number(this.step) : 1;
            },
            auMin() {
                return nombre(this.min) && nombre(this.val) && Number(this.val) <= Number(this.min);
            },
            auMax() {
                return nombre(this.max) && nombre(this.val) && Number(this.val) >= Number(this.max);
            }
        },

        methods: {
            ...origine.methods,

            ajouter(sens) {
                if (this.readonly) return;
                let v = (nombre(this.val) ? Number(this.val) : (nombre(this.min) ? Number(this.min) : 0)) + sens * this.pas;
                if (nombre(this.min)) v = Math.max(v, Number(this.min));
                if (nombre(this.max)) v = Math.min(v, Number(this.max));
                this.val = Math.round(v * 1000) / 1000;
                this.update();
            }
        },

        template: /*html*/`
            <div field="number" class="nombre">
                <button type="button" class="nombre__bouton" @click="ajouter(-1)" :disabled="readonly || auMin" aria-label="Moins un"><icon>remove</icon></button>
                <input type="number" class="kiss-input nombre__champ" v-model.number="val" @input="update" @change="check" :placeholder="placeholder" :max="max" :min="min" :step="step" :readonly="readonly" inputmode="decimal">
                <button type="button" class="nombre__bouton" @click="ajouter(1)" :disabled="readonly || auMax" aria-label="Plus un"><icon>add</icon></button>
            </div>
        `
    };
}

/**
 * Champ couleur : teintes lisibles sur blanc, curseur de clarté et choix libre dans le panneau.
 * Le code reste un champ texte, contrôlé par EditorGuards.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function couleur() {
    const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-color.js') + version);

    const PALETTE = ['#2B5A16', '#1E5A32', '#245C4F', '#16525A', '#1F4E79', '#2E3A87', '#5B2A86', '#7A1F5C', '#8B1F3A', '#A12A1F', '#8A4B12', '#6E4220', '#5A4A1E', '#3D4A3F', '#1F2A22', '#333333'];

    const hexVersTsl = (hex) => {
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
        const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
        if (max === min) return [0, 0, l * 100];
        const d = max - min, s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        const h = max === r ? ((g - b) / d + (g < b ? 6 : 0)) : (max === g ? (b - r) / d + 2 : (r - g) / d + 4);
        return [h * 60, s * 100, l * 100];
    };
    const tslVersHex = (h, s, l) => {
        s /= 100; l /= 100;
        const k = (n) => (n + h / 30) % 12, a = s * Math.min(l, 1 - l);
        const f = (n) => Math.round(255 * (l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1))))).toString(16).padStart(2, '0');
        return ('#' + f(0) + f(8) + f(4)).toUpperCase();
    };
    const luminance = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
        .reduce((t, c, i) => t + c * [0.2126, 0.7152, 0.0722][i], 0);
    const contraste = (hex) => 1.05 / (luminance(hex) + 0.05);
    // Teinte, saturation, valeur (le carré du sélecteur libre).
    const hexVersTsv = (hex) => {
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
        const max = Math.max(r, g, b), d = max - Math.min(r, g, b);
        const h = d === 0 ? 0 : (max === r ? ((g - b) / d + 6) % 6 : (max === g ? (b - r) / d + 2 : (r - g) / d + 4)) * 60;
        return [h, max === 0 ? 0 : d / max * 100, max * 100];
    };
    const tsvVersHex = (h, s, v) => {
        s /= 100; v /= 100;
        const f = (n) => { const k = (n + h / 60) % 6; return Math.round(255 * (v - v * s * Math.max(0, Math.min(k, 4 - k, 1)))).toString(16).padStart(2, '0'); };
        return ('#' + f(5) + f(3) + f(1)).toUpperCase();
    };
    const borne = (x, a, b) => Math.min(b, Math.max(a, x));
    const valide = (v) => /^#[0-9a-f]{6}$/i.test(String(v || '').trim());

    return {
        ...origine,

        data() {
            return { ...origine.data.call(this), ouvert: false, saisie: String(this.modelValue || '').toUpperCase(), libre: false, tsv: [100, 70, 40] };
        },

        computed: {
            ...(origine.computed || {}),
            teintes() {
                return this.hasColorSet ? this.colors : PALETTE;
            },
            clarte: {
                get() { return valide(this.val) ? Math.round(hexVersTsl(this.val)[2]) : 30; },
                set(l) {
                    if (!valide(this.val)) return;
                    const [h, s] = hexVersTsl(this.val);
                    this.choisir(tslVersHex(h, s, Number(l)));
                }
            },
            fondCurseur() {
                if (!valide(this.val)) return {};
                const [h, s] = hexVersTsl(this.val);
                return { background: `linear-gradient(90deg, ${tslVersHex(h, s, 8)}, ${tslVersHex(h, s, 50)}, ${tslVersHex(h, s, 92)})` };
            }
        },

        watch: {
            ...(origine.watch || {}),
            val(v) {
                this.update();
                if (valide(v) && v.toUpperCase() !== String(this.saisie).toUpperCase()) this.saisie = v.toUpperCase();
            }
        },

        mounted() {
            origine.mounted && origine.mounted.call(this);
            this.ailleurs = (e) => { if (this.ouvert && !this.$el.contains(e.target)) this.ouvert = false; };
            document.addEventListener('mousedown', this.ailleurs);
        },

        unmounted() {
            document.removeEventListener('mousedown', this.ailleurs);
        },

        methods: {
            ...origine.methods,

            lisible(hex) {
                return contraste(hex) >= 4.5;
            },

            choisir(hex) {
                this.val = hex.toUpperCase();
                this.saisie = this.val;
                // EditorGuards recalcule le contraste quand le champ texte change.
                this.$nextTick(() => { const t = this.$refs.code; if (t) t.dispatchEvent(new Event('input', { bubbles: true })); });
            },

            surSaisie() {
                let v = String(this.saisie || '').trim();
                if (/^[0-9a-f]{6}$/i.test(v)) v = '#' + v;
                if (valide(v)) this.val = v.toUpperCase();
            },

            // Le choix libre, dans le panneau même (le sélecteur du système s'ouvrait n'importe où sur l'écran).
            basculerLibre() {
                this.libre = !this.libre;
                if (this.libre && valide(this.val)) this.tsv = hexVersTsv(this.val);
            },

            appliquerTsv() {
                this.choisir(tsvVersHex(...this.tsv));
            },

            glisser(e, quoi) {
                const zone = e.currentTarget;
                const lire = (ev) => {
                    const r = zone.getBoundingClientRect();
                    const x = borne((ev.clientX - r.left) / r.width, 0, 1), y = borne((ev.clientY - r.top) / r.height, 0, 1);
                    if (quoi === 'carre') this.tsv = [this.tsv[0], x * 100, (1 - y) * 100];
                    else this.tsv = [x * 359.9, this.tsv[1], this.tsv[2]];
                    this.appliquerTsv();
                };
                if (zone.setPointerCapture && e.pointerId !== undefined) {
                    try { zone.setPointerCapture(e.pointerId); } catch (erreur) { /* clic simulé */ }
                }
                lire(e);
                const bouger = (ev) => lire(ev);
                const finir = () => {
                    zone.removeEventListener('pointermove', bouger);
                    zone.removeEventListener('pointerup', finir);
                    zone.removeEventListener('pointercancel', finir);
                };
                zone.addEventListener('pointermove', bouger);
                zone.addEventListener('pointerup', finir);
                zone.addEventListener('pointercancel', finir);
            },

            clavier(e, quoi) {
                const pas = e.shiftKey ? 10 : 2;
                const d = { ArrowLeft: [-pas, 0], ArrowRight: [pas, 0], ArrowUp: [0, pas], ArrowDown: [0, -pas] }[e.key];
                if (!d) return;
                e.preventDefault();
                const [h, sat, v] = this.tsv;
                this.tsv = quoi === 'carre' ? [h, borne(sat + d[0], 0, 100), borne(v + d[1], 0, 100)] : [(h + d[0] * 2 + 360) % 360, sat, v];
                this.appliquerTsv();
            }
        },

        template: /*html*/`
            <div field="color" class="couleur">
                <div class="couleur__ligne">
                    <button type="button" class="couleur__pastille" :style="val ? {background: val} : {}" :class="{'couleur__pastille--vide': !val}" @click="ouvert = !ouvert" :aria-expanded="ouvert ? 'true' : 'false'" aria-label="Choisir une couleur"></button>
                    <input ref="code" type="text" class="kiss-input couleur__code" v-model="saisie" @input="surSaisie" placeholder="#1E5FA8" maxlength="7" spellcheck="false" aria-label="Code de la couleur">
                    <button type="button" class="kiss-button kiss-button-small" @click="ouvert = !ouvert"><icon>palette</icon>{{ ouvert ? 'Fermer' : 'Choisir' }}</button>
                </div>

                <div class="couleur__panneau" v-if="ouvert">
                    <p class="couleur__aide">Des teintes assez foncées pour se lire sur fond blanc :</p>
                    <div class="couleur__palette">
                        <button type="button" v-for="c in teintes" :key="c" class="couleur__teinte" :class="{'couleur__teinte--choisie': val && c.toUpperCase() === val.toUpperCase()}" :style="{background: c}" :title="c + (lisible(c) ? '' : ' (peu lisible sur blanc)')" @click="choisir(c)">
                            <icon v-if="val && c.toUpperCase() === val.toUpperCase()">check</icon>
                        </button>
                    </div>
                    <label class="couleur__clarte" v-if="val">
                        <span>Plus foncé</span>
                        <input type="range" min="8" max="92" step="1" v-model.number="clarte" :style="fondCurseur" aria-label="Clarté de la couleur">
                        <span>Plus clair</span>
                    </label>
                    <button type="button" class="couleur__systeme" @click="basculerLibre" :aria-expanded="libre ? 'true' : 'false'"><icon>{{ libre ? 'expand_less' : 'colorize' }}</icon>{{ libre ? 'Masquer le choix libre' : 'Autre couleur…' }}</button>
                    <div class="couleur__libre" v-if="libre">
                        <div class="couleur__carre" :style="{background: 'linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, hsl(' + tsv[0] + ', 100%, 50%))'}"
                            tabindex="0" role="slider" aria-label="Saturation et luminosité" :aria-valuetext="val"
                            @pointerdown.prevent="glisser($event, 'carre')" @keydown="clavier($event, 'carre')">
                            <span class="couleur__curseur" :style="{left: tsv[1] + '%', top: (100 - tsv[2]) + '%', background: val}"></span>
                        </div>
                        <div class="couleur__teintes" tabindex="0" role="slider" aria-label="Teinte" aria-valuemin="0" aria-valuemax="360" :aria-valuenow="Math.round(tsv[0])"
                            @pointerdown.prevent="glisser($event, 'teinte')" @keydown="clavier($event, 'teinte')">
                            <span class="couleur__curseur couleur__curseur--teinte" :style="{left: (tsv[0] / 360 * 100) + '%', background: 'hsl(' + tsv[0] + ', 100%, 50%)'}"></span>
                        </div>
                    </div>
                </div>
            </div>
        `
    };
}

/**
 * Champ oui / non : un interrupteur avec « Oui » ou « Non » écrit à côté.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function ouiNon() {
    const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-boolean.js') + version);

    return {
        ...origine,

        methods: {
            ...origine.methods,

            basculer() {
                const actif = !!this.val;
                this.val = this.mode === 'number' ? (actif ? 0 : 1) : !actif;
                this.update();
            }
        },

        template: /*html*/`
            <div field="boolean" class="oui-non">
                <button type="button" role="switch" class="oui-non__bouton" :class="{'oui-non__bouton--oui': !!val}" :aria-checked="val ? 'true' : 'false'" @click="basculer" :id="uuid">
                    <span class="oui-non__piste"><span class="oui-non__bouton-rond"><icon>{{ val ? 'check' : 'close' }}</icon></span></span>
                    <span class="oui-non__texte">{{ val ? 'Oui' : 'Non' }}</span>
                </button>
                <label :for="uuid" class="oui-non__libelle" v-if="label">{{ label }}</label>
            </div>
        `
    };
}

/**
 * Gestionnaire des champs d'un modèle (administrateur) : chaque champ est une carte qui se déplie pour ses
 * réglages, et le type se choisit dans la carte, au lieu de la fenêtre et du menu de Cockpit.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function gestionnaireChamps() {
    const { default: origine } = await import(App.base('system:assets/vue-components/fields/manager.js') + version);

    const panneau = /*html*/`
        <div class="gc__panneau">
            <div class="gc__deux">
                <label class="mt-champ"><span>Nom technique</span><input type="text" v-model="field.name" required pattern="[A-Za-z0-9_]+" autocapitalize="off" spellcheck="false" placeholder="titre"><small>Lettres, chiffres et _ ; il sert dans les gabarits et l’API.</small></label>
                <label class="mt-champ"><span>Libellé affiché</span><input type="text" v-model="field.label" placeholder="Titre de la page"></label>
            </div>

            <div class="mt-champ">
                <span>Type</span>
                <button type="button" class="gc__type-actuel" @click="choixType = !choixType" :aria-expanded="choixType ? 'true' : 'false'">
                    <span class="gc__type" :style="{background: couleur(field.type)}"><img :src="$baseUrl(icone(field.type))" alt="" width="20" height="20"></span>
                    <span class="gc__type-texte"><b>{{ libelleType(field.type) }}</b><small>{{ t(fieldTypes?.[field.type]?.info || '') }}</small></span>
                    <span class="kiss-button kiss-button-small"><icon>{{ choixType ? 'expand_less' : 'swap_horiz' }}</icon>{{ choixType ? 'Fermer' : 'Changer' }}</span>
                </button>
                <div class="gc__types" v-if="choixType">
                    <label class="mt-recherche"><icon aria-hidden="true">search</icon><input type="search" v-model="fieldType.filter" placeholder="Chercher un type" aria-label="Chercher un type"></label>
                    <div class="gc__grille-types">
                        <button type="button" v-for="(f, nom) in filteredFieldTypes" :key="nom" class="gc__choix-type" :class="{'gc__choix-type--actif': field.type === nom}" @click="setFieldType(nom); choixType = false">
                            <span class="gc__type" :style="{background: f.color || ''}"><img :src="$baseUrl(f.icon || 'system:assets/icons/edit.svg')" alt="" width="18" height="18"></span>
                            <span class="gc__type-texte"><b>{{ t(f.label || nom) }}</b><small>{{ t(f.info || '') }}</small></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="fiche-choix gc__onglets" role="tablist" aria-label="Réglages du champ">
                <button type="button" role="tab" v-for="o in onglets" :key="o[0]" :aria-selected="onglet === o[0] ? 'true' : 'false'" :class="{'fiche-choix--actif': onglet === o[0]}" @click="onglet = o[0]"><icon>{{ o[1] }}</icon>{{ o[2] }}</button>
            </div>

            <template v-if="onglet === 'general'">
                <label class="mt-champ"><span>Aide <em>affichée sous le libellé</em></span><input type="text" v-model="field.info"></label>
                <label class="mt-champ"><span>Groupe <em>un onglet de la fiche</em></span><input type="text" v-model="field.group"></label>
                <div class="gc__groupes" v-if="fieldGroups.length">
                    <button type="button" class="kiss-button kiss-button-small" v-for="g in fieldGroups" :key="g" @click="field.group = g">{{ g }}</button>
                </div>
                <div class="gc__options">
                    <field-boolean v-model="field.required" label="Obligatoire"></field-boolean>
                    <field-boolean v-model="field.i18n" label="Traduisible" v-if="i18n"></field-boolean>
                    <field-boolean v-model="field.multiple" label="Plusieurs valeurs"></field-boolean>
                </div>
            </template>

            <template v-if="onglet === 'options'">
                <div class="fiche-choix gc__vue-options" v-if="fieldTypes[field.type] && fieldTypes[field.type].settings">
                    <button type="button" :class="{'fiche-choix--actif': state.optionsView !== 'json'}" @click="state.optionsView = 'form'"><icon>tune</icon>Réglages</button>
                    <button type="button" :class="{'fiche-choix--actif': state.optionsView === 'json'}" @click="state.optionsView = 'json'"><icon>data_object</icon>JSON</button>
                </div>
                <fields-renderer v-model="field.opts" :fields="fieldTypes[field.type].settings" v-if="fieldTypes[field.type] && fieldTypes[field.type].settings && state.optionsView !== 'json'"></fields-renderer>
                <field-object v-model="field.opts" v-else></field-object>
            </template>

            <template v-if="onglet === 'avance'">
                <div class="mt-champ"><span>Méta <em>données libres, en JSON</em></span><field-object v-model="field.meta" :height="150"></field-object></div>
                <div class="mt-champ"><span>Condition d’affichage <em>JavaScript</em></span><field-code v-model="field.condition" mode="js" :height="100"></field-code><small>Le champ n’apparaît que si la condition est vraie.</small></div>
            </template>
        </div>`;

    return {
        ...origine,

        data() {
            return { ...origine.data.call(this), choixType: false, onglet: 'general', onglets: [['general', 'tune', 'Général'], ['options', 'settings', 'Options'], ['avance', 'code', 'Avancé']] };
        },

        watch: {
            ...origine.watch,
            field(v) {
                this.fieldType = {};
                this.choixType = false;
                this.onglet = 'general';
                if (v) this.$nextTick(() => this.$el.querySelector('.gc__panneau input')?.focus({ preventScroll: true }));
            }
        },

        methods: {
            ...origine.methods,

            basculer(element) {
                if (this.field === element) {
                    this.field = null;
                } else {
                    this.edit(element);
                }
            },

            /**
             * Ferme le panneau, ou ajoute le nouveau champ, après vérification du nom.
             */
            valider() {
                if (!String(this.field.name || '').trim()) {
                    App.ui.notify('Donnez un nom technique au champ.', 'error');
                    return;
                }
                if (this.fields.some((f) => f !== this.field && f.name === this.field.name)) {
                    App.ui.notify('Un autre champ porte déjà ce nom.', 'error');
                    return;
                }
                this.addOrEditField();
            },

            async retirer(element) {
                const oui = await confirmer({ titre: `Supprimer le champ « ${element.label || element.name} » ?`, texte: 'Il disparaît du modèle à l’enregistrement ; les valeurs déjà saisies restent dans les éléments.', bouton: 'Supprimer', danger: true });
                if (!oui) return;
                if (this.field === element) this.field = null;
                this.remove(element);
            },

            deplacer(index, sens) {
                const cible = index + sens;
                if (cible < 0 || cible >= this.fields.length) return;
                this.fields.splice(cible, 0, this.fields.splice(index, 1)[0]);
            },

            libelleType(type) {
                return this.t(this.fieldTypes?.[type]?.label || type);
            },

            icone(type) {
                return this.fieldTypes?.[type]?.icon || 'system:assets/icons/edit.svg';
            },

            couleur(type) {
                return this.fieldTypes?.[type]?.color || '';
            }
        },

        template: /*html*/`
            <div class="gc">
                <p class="el-vide" v-if="!fields.length && !field"><icon>playlist_add</icon>Aucun champ pour l’instant.</p>
                <app-loader v-if="!fieldTypes"></app-loader>

                <vue-draggable v-model="fields" :animation="150" handle=".el-poignee" class="el-liste" v-if="fieldTypes && fields.length" @start="field = null">
                    <section class="el" v-for="(element, index) in fields" :class="{'el--ouvert': field === element}">
                        <header class="el__tete">
                            <button type="button" class="el__poignee el-poignee" aria-label="Déplacer" title="Glisser pour déplacer"><icon>drag_indicator</icon></button>
                            <button type="button" class="el__resume" @click="basculer(element)" :aria-expanded="field === element ? 'true' : 'false'">
                                <span class="gc__type" :style="{background: couleur(element.type)}"><img :src="$baseUrl(icone(element.type))" alt="" width="18" height="18"></span>
                                <span class="gc__resume">
                                    <span class="el__titre">{{ element.label || element.name || 'Sans nom' }}</span>
                                    <small>{{ element.name }} · {{ libelleType(element.type) }}<template v-if="element.group"> · {{ element.group }}</template></small>
                                </span>
                                <span class="gc__marques">
                                    <icon v-if="element.required" title="Obligatoire">emergency</icon>
                                    <icon v-if="element.i18n" title="Traduisible">translate</icon>
                                    <icon v-if="element.multiple" title="Plusieurs valeurs">format_list_numbered</icon>
                                    <icon v-if="element.condition" title="Affichage conditionnel">conversion_path</icon>
                                </span>
                                <icon class="el__fleche">expand_more</icon>
                            </button>
                            <div class="el__actions">
                                <button type="button" class="el__rond" @click="deplacer(index, -1)" :disabled="index === 0" aria-label="Monter" title="Monter"><icon>arrow_upward</icon></button>
                                <button type="button" class="el__rond" @click="deplacer(index, 1)" :disabled="index === fields.length - 1" aria-label="Descendre" title="Descendre"><icon>arrow_downward</icon></button>
                                <button type="button" class="el__rond" @click="add(element)" aria-label="Insérer un champ après" title="Insérer un champ après"><icon>add</icon></button>
                                <button type="button" class="el__rond el__rond--danger" @click="retirer(element)" aria-label="Supprimer" title="Supprimer"><icon>delete</icon></button>
                            </div>
                        </header>
                        <div class="el__corps" v-if="field === element">
                            ${panneau}
                            <div class="gc__boutons"><button type="button" class="kiss-button" @click="valider"><icon>expand_less</icon>Replier</button></div>
                        </div>
                    </section>
                </vue-draggable>

                <section class="el el--ouvert gc__nouveau" v-if="field && !state.editField">
                    <header class="el__tete"><span class="el__resume"><span class="el__numero"><icon>add</icon></span><span class="el__titre">Nouveau champ</span></span></header>
                    <div class="el__corps">
                        ${panneau}
                        <div class="gc__boutons">
                            <button type="button" class="kiss-button" @click="field = null"><icon>undo</icon>Annuler</button>
                            <button type="button" class="kiss-button kiss-button-primary" @click="valider"><icon>add</icon>Ajouter le champ</button>
                        </div>
                    </div>
                </section>

                <button type="button" class="kiss-button el-ajouter" @click="add()" v-if="fieldTypes && !(field && !state.editField)"><icon>add</icon>Ajouter un champ</button>
            </div>`
    };
}
