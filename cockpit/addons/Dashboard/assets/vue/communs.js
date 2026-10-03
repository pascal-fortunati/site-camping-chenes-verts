/**
 * Composants et outils partagés par les écrans Vue du Dashboard.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

// re=0 : Cockpit renvoie l'adresse au lieu d'une redirection qui ajouterait /admin aux médias relatifs.
const adresses = new Map();
/**
 * Vignette d’une image des médias, demandée une seule fois par adresse.
 */
export const vignette = {
    props: { asset: Object, largeur: { type: Number, default: 480 } },
    data: () => ({ src: null }),
    watch: { 'asset._modified'() { this.charger(); } },
    mounted() { this.charger(); },
    methods: {
        charger() {
            const a = this.asset;
            const cle = `${a._id}-${this.largeur}-${a._modified}`;
            if (!adresses.has(cle)) {
                adresses.set(cle, App.request(`/assets/thumbnail/${a._id}?m=bestFit&mime=auto&w=${this.largeur}&h=${Math.round(this.largeur * 0.75)}&q=70&t=${a._modified}&re=0`)
                    .then((r) => (r && r.url) || null).catch(() => null));
            }
            adresses.get(cle).then((src) => { this.src = src; });
        }
    },
    template: '<img v-if="src" :src="src" alt="" decoding="async" class="mt-vignette"><span v-else class="mt-vignette mt-vignette--attente"></span>'
};

const echapper = (texte) => String(texte).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/**
 * Demande une confirmation dans une fenêtre aux couleurs du site.
 *
 * @param {{titre: string, texte?: string, details?: string[], bouton?: string, danger?: boolean, icone?: string}} options
 * @returns {Promise<boolean>} true si la personne confirme
 */
export function confirmer({ titre, texte = '', details = [], bouton = 'Confirmer', danger = false, icone = null }) {
    return new Promise((resoudre) => {
        const liste = details.length ? `<ul class="dlg-details">${details.map((d) => `<li>${echapper(d)}</li>`).join('')}</ul>` : '';
        const fenetre = App.ui.dialog(/*html*/`
            <div class="dlg" role="alertdialog" aria-labelledby="dlg-titre">
                <span class="dlg-icone${danger ? ' dlg-icone--danger' : ''}" aria-hidden="true"><icon>${icone || (danger ? 'delete' : 'help')}</icon></span>
                <h2 class="dlg-titre" id="dlg-titre">${echapper(titre)}</h2>
                ${texte ? `<p class="dlg-texte">${echapper(texte)}</p>` : ''}
                ${liste}
                <div class="dlg-boutons">
                    <button type="button" class="kiss-button dlg-annuler">Annuler</button>
                    <button type="button" class="kiss-button ${danger ? 'kiss-button-danger' : 'kiss-button-primary'} dlg-ok"><icon>${danger ? 'delete' : 'check'}</icon>${echapper(bouton)}</button>
                </div>
            </div>
        `, { escape: true }, 'confirm');

        let repondu = false;
        const repondre = (oui) => {
            if (repondu) return;
            repondu = true;
            resoudre(oui);
            fenetre.close();
        };

        fenetre.querySelector('.dlg-ok').addEventListener('click', () => repondre(true));
        fenetre.querySelector('.dlg-annuler').addEventListener('click', () => repondre(false));
        fenetre.addEventListener('dialogclose', () => repondre(false));
        fenetre.show();
        setTimeout(() => fenetre.querySelector('.dlg-annuler').focus(), 50);
    });
}

/**
 * Menu de tri d’une liste (↑ ↓, Entrée, Échap), à la place de la liste native du navigateur.
 */
export const menuTri = {

    props: {
        modelValue: { type: String, default: '' },
        options: { type: Array, default: () => [] },   // [{ value, label }]
        libelle: { type: String, default: 'Trier' }
    },

    emits: ['update:modelValue'],

    data() {
        return { ouvert: false, choisi: 0 };
    },

    computed: {
        actuel() {
            return this.options.find((o) => o.value === this.modelValue) || this.options[0] || { label: '' };
        }
    },

    mounted() {
        this.ailleurs = (e) => { if (this.ouvert && !this.$el.contains(e.target)) this.ouvert = false; };
        document.addEventListener('mousedown', this.ailleurs);
    },

    unmounted() {
        document.removeEventListener('mousedown', this.ailleurs);
    },

    methods: {
        basculer() {
            this.ouvert = !this.ouvert;
            if (this.ouvert) {
                this.choisi = Math.max(0, this.options.findIndex((o) => o.value === this.modelValue));
                this.$nextTick(() => this.$refs.liste && this.$refs.liste.focus());
            }
        },

        prendre(o) {
            this.$emit('update:modelValue', o.value);
            this.ouvert = false;
            this.$nextTick(() => this.$refs.bouton && this.$refs.bouton.focus());
        },

        touche(e) {
            const n = this.options.length;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                this.choisi = (this.choisi + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.prendre(this.options[this.choisi]);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.ouvert = false;
                this.$refs.bouton && this.$refs.bouton.focus();
            }
        }
    },

    template: /*html*/`
        <div class="tri">
            <button ref="bouton" type="button" class="tri__bouton" @click="basculer" aria-haspopup="listbox" :aria-expanded="ouvert ? 'true' : 'false'">
                <icon>sort</icon><span class="tri__libelle">{{ libelle }}</span><b>{{ actuel.label }}</b><icon class="tri__fleche">expand_more</icon>
            </button>
            <ul ref="liste" class="tri__menu" role="listbox" tabindex="-1" v-if="ouvert" @keydown="touche">
                <li v-for="(o, i) in options" :key="o.value" role="option" :aria-selected="o.value === modelValue ? 'true' : 'false'"
                    class="tri__option" :class="{'tri__option--choisie': i === choisi, 'tri__option--actuelle': o.value === modelValue}"
                    @mousedown.prevent="prendre(o)" @mouseenter="choisi = i">
                    <span>{{ o.label }}</span><icon v-if="o.value === modelValue">check</icon>
                </li>
            </ul>
        </div>
    `
};
