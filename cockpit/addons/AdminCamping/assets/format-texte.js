/**
 * Le menu « format » du texte riche : Paragraphe, Titre, Sous-titre, en français, dans un petit menu qui s'ouvre
 * sous son bouton (Cockpit l'écrivait en anglais, au milieu de l'écran, avec « Code » et six niveaux de titre).
 * Les niveaux sont ceux qu'autorise l'extension EditorGuards : 2 et 3, le niveau 1 étant le titre de la page.
 */

let numero = 0;

const FORMATS = [
    { cle: 'paragraphe', libelle: 'Paragraphe', aide: 'Le texte courant', icone: 'format_paragraph' },
    { cle: 'h2', libelle: 'Titre', aide: 'Un intertitre dans le texte', icone: 'format_h2' },
    { cle: 'h3', libelle: 'Sous-titre', aide: 'Sous un titre', icone: 'format_h3' },
];

export default {

    props: {
        editor: { type: Object, default: null }
    },

    data() {
        return { uid: `format-texte-${++numero}`, ouvert: false, position: {} };
    },

    computed: {
        actuel() {
            if (this.editor.isActive('heading', { level: 2 })) return FORMATS[1];
            if (this.editor.isActive('heading', { level: 3 })) return FORMATS[2];
            return FORMATS[0];
        },
        formats() {
            return FORMATS;
        }
    },

    mounted() {
        const bouton = this.$el.parentNode;
        bouton.setAttribute('aria-haspopup', 'true');
        bouton.setAttribute('title', 'Format du texte');
        this.surClic = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.ouvert ? this.fermer() : this.ouvrir();
        };
        bouton.addEventListener('click', this.surClic);
        this.ailleurs = (e) => { if (this.ouvert && !e.target.closest(`#${this.uid}`) && !bouton.contains(e.target)) this.fermer(); };
        this.surTouche = (e) => { if (this.ouvert && e.key === 'Escape') this.fermer(); };
        document.addEventListener('mousedown', this.ailleurs);
        document.addEventListener('keydown', this.surTouche);
    },

    unmounted() {
        this.$el.parentNode && this.$el.parentNode.removeEventListener('click', this.surClic);
        document.removeEventListener('mousedown', this.ailleurs);
        document.removeEventListener('keydown', this.surTouche);
    },

    methods: {
        ouvrir() {
            const r = this.$el.parentNode.getBoundingClientRect();
            this.position = { top: `${r.bottom + 6}px`, left: `${Math.min(r.left, window.innerWidth - 250)}px` };
            this.ouvert = true;
        },

        fermer() {
            this.ouvert = false;
        },

        choisir(f) {
            const chaine = this.editor.chain().focus();
            if (f.cle === 'paragraphe') chaine.setParagraph().run();
            else chaine.setHeading({ level: Number(f.cle.slice(1)) }).run();
            this.fermer();
        }
    },

    template: /*html*/`
        <span class="ft-bouton"><icon>{{ actuel.icone }}</icon></span>
        <teleport to="body">
            <div :id="uid" class="ft-menu" role="menu" v-if="ouvert" :style="position">
                <button type="button" role="menuitem" class="ft-option" :class="{'ft-option--actif': f.cle === actuel.cle}" v-for="f in formats" :key="f.cle" @mousedown.prevent @click="choisir(f)">
                    <icon>{{ f.icone }}</icon>
                    <span><b :class="'ft-apercu--' + f.cle">{{ f.libelle }}</b><small>{{ f.aide }}</small></span>
                    <icon class="ft-coche" v-if="f.cle === actuel.cle">check</icon>
                </button>
            </div>
        </teleport>
    `
};
