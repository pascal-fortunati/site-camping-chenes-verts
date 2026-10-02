/**
 * Le champ date : tout le champ ouvre un calendrier aux couleurs du site (et pas seulement la petite icône du
 * calendrier du navigateur). La date s'écrit en toutes lettres ; semaine du lundi ; flèches du clavier pour
 * les jours, Pg préc / Pg suiv pour les mois, Entrée, Échap. La valeur reste celle de Cockpit : « AAAA-MM-JJ ».
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-date.js') + version);

const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const deux = (n) => String(n).padStart(2, '0');
const versTexte = (d) => `${d.getFullYear()}-${deux(d.getMonth() + 1)}-${deux(d.getDate())}`;
const lire = (t) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(t || '');
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
};

export default {
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
