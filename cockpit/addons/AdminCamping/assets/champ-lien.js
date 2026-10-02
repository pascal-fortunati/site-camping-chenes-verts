/**
 * Le champ « lien vers un contenu » (la page d'une entrée du menu…) : une liste déroulante avec recherche, dans
 * le champ même, au lieu d'une fenêtre qui liste les éléments. On tape quelques lettres, on choisit au clic ou au
 * clavier (↑ ↓ Entrée, Échap). La valeur enregistrée reste celle de Cockpit : { _model, _id }.
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('content:assets/vue-components/field-content-item-link.js') + version);

const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const listes = new Map();   // un seul chargement par modèle, partagé par tous les champs de la page

export default {
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
