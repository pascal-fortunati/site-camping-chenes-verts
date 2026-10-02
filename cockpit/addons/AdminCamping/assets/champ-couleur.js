/**
 * Le champ couleur : la pastille et le code (#2B5A16) ; au clic sur la pastille, un panneau aux couleurs du site
 * avec des teintes lisibles sur blanc et un curseur « plus clair / plus foncé », au lieu du sélecteur du système.
 * Le code reste un champ texte : l'extension EditorGuards y affiche toujours le contraste sur blanc.
 * Le sélecteur du système reste possible (« Autre couleur… »).
 */

const version = new URL(import.meta.url).search;
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
const valide = (v) => /^#[0-9a-f]{6}$/i.test(String(v || '').trim());

export default {
    ...origine,

    data() {
        return { ...origine.data.call(this), ouvert: false, saisie: String(this.modelValue || '').toUpperCase() };
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

        // Le sélecteur du système, créé au clic seulement : présent dans la page, EditorGuards y ajouterait un
        // second message de contraste.
        autreCouleur() {
            const boite = document.createElement('div');
            boite.style.cssText = 'position:fixed;left:-9999px;top:0';
            const champ = document.createElement('input');
            champ.type = 'color';
            champ.dataset.contraste = '1';
            champ.value = valide(this.val) ? this.val.toLowerCase() : '#2b5a16';
            champ.addEventListener('input', () => this.choisir(champ.value));
            champ.addEventListener('change', () => setTimeout(() => boite.remove(), 0));
            boite.append(champ);
            document.body.append(boite);
            champ.click();
            setTimeout(() => { if (document.activeElement !== champ) boite.remove(); }, 60000);
        }
    },

    template: /*html*/`
        <div field="color" class="couleur">
            <div class="couleur__ligne">
                <button type="button" class="couleur__pastille" :style="val ? {background: val} : {}" :class="{'couleur__pastille--vide': !val}" @click="ouvert = !ouvert" :aria-expanded="ouvert ? 'true' : 'false'" aria-label="Choisir une couleur"></button>
                <input ref="code" type="text" class="kiss-input couleur__code" v-model="saisie" @input="surSaisie" placeholder="#2B5A16" maxlength="7" spellcheck="false" aria-label="Code de la couleur">
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
                <button type="button" class="couleur__systeme" @click="autreCouleur"><icon>colorize</icon>Autre couleur…</button>
            </div>
        </div>
    `
};
