/**
 * Le champ « liste de choix » (le réseau d'un lien social, le type d'une section…) : la même liste déroulante que
 * le choix d'une page (champ-lien.js), au lieu de la liste native du navigateur. Recherche à partir de 8 options,
 * clavier (↑ ↓ Entrée, Échap). Plusieurs choix possibles : des cases à cocher. Le fonctionnement de Cockpit
 * (field-select.js : options, src, valeur) est repris ; seul l'affichage change.
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-select.js') + version);

const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

export default {
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
