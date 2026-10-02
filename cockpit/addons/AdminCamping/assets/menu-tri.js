/**
 * Le choix du tri d'une liste (pages, messages, médias) : un petit menu aux couleurs du site, comme les autres
 * listes déroulantes, au lieu de la liste native du navigateur. Clavier : ↑ ↓, Entrée, Échap.
 */

export default {

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
