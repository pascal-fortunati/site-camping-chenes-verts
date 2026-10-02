/**
 * Un lien dans un texte : vers une page du site (choisie par son titre), une adresse web ou un document des
 * médias, au lieu des champs « Url / Title / Target » de Cockpit. Même entrée (meta) et même sortie (save) que
 * sa fenêtre : { href, title, target }.
 */

const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const DANGEREUX = /^\s*(javascript|data|vbscript):/i;

export default {

    _meta: { size: 'medium' },

    props: {
        meta: { type: Object, default: () => ({}) }
    },

    data() {
        const href = this.meta.href || '';
        return {
            mode: href && !href.startsWith('/') ? 'web' : 'page',
            pages: null,
            q: '',
            choisi: 0,
            adresse: href.startsWith('/') ? '' : href,
            nouvelOnglet: this.meta.target === '_blank',
            actuel: href
        };
    },

    computed: {
        resultats() {
            if (!this.pages) return [];
            const mots = plat(this.q).split(/\s+/).filter(Boolean);
            return this.pages.filter((p) => !mots.length || mots.every((m) => plat((p.titre || '') + ' ' + (p.slug || '')).includes(m))).slice(0, 40);
        }
    },

    mounted() {
        this.$request('/content/collection/find/pages', { options: { limit: 1000, sort: { titre: 1 }, fields: { titre: 1, slug: 1, _state: 1 } } })
            .then((r) => { this.pages = (r && r.items) || []; })
            .catch(() => { this.pages = []; if (this.mode === 'page') this.mode = 'web'; });
        this.$nextTick(() => { const c = this.$el.querySelector('input'); if (c) c.focus(); });
    },

    methods: {
        lienPage(p) {
            return p.slug === 'accueil' ? '/' : '/' + p.slug;
        },

        enregistrer(href, target = '') {
            if (DANGEREUX.test(href)) {
                App.ui.notify('Cette adresse n’est pas acceptée.', 'error');
                return;
            }
            this.$call('save', { href, title: '', target });
            this.$close();
        },

        choisirPage(p) {
            this.enregistrer(this.lienPage(p));
        },

        validerAdresse() {
            let href = this.adresse.trim();
            if (!href) return;
            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(href)) href = 'mailto:' + href;
            else if (/^\+?[\d\s.]{8,}$/.test(href)) href = 'tel:' + href.replace(/[\s.]/g, '');
            else if (!/^(https?:|mailto:|tel:|\/)/i.test(href)) href = 'https://' + href;
            this.enregistrer(href, this.nouvelOnglet ? '_blank' : '');
        },

        choisirDocument() {
            App.utils.selectAsset((a) => {
                if (a && a.path) this.enregistrer(App.base('#uploads:' + a.path), '_blank');
            });
        },

        retirer() {
            this.$call('save', { href: '', title: '', target: '' });
            this.$close();
        },

        touche(e) {
            const n = this.resultats.length;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (n) this.choisi = (this.choisi + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.resultats[this.choisi]) this.choisirPage(this.resultats[this.choisi]);
            }
        }
    },

    watch: {
        q() { this.choisi = 0; }
    },

    template: /*html*/`
    <div class="lt">
        <header class="lt-tete">
            <span class="lt-tete__icone"><icon>link</icon></span>
            <h2>{{ actuel ? 'Modifier le lien' : 'Ajouter un lien' }}</h2>
            <button type="button" class="mt-rond" kiss-dialog-close aria-label="Fermer"><icon>close</icon></button>
        </header>

        <div class="fiche-choix lt-modes" role="tablist">
            <button type="button" role="tab" :aria-selected="mode === 'page' ? 'true' : 'false'" :class="{'fiche-choix--actif': mode === 'page'}" @click="mode = 'page'" v-if="pages === null || pages.length"><icon>description</icon>Une page du site</button>
            <button type="button" role="tab" :aria-selected="mode === 'web' ? 'true' : 'false'" :class="{'fiche-choix--actif': mode === 'web'}" @click="mode = 'web'"><icon>public</icon>Une adresse</button>
            <button type="button" role="tab" @click="choisirDocument"><icon>picture_as_pdf</icon>Un document</button>
        </div>

        <div v-if="mode === 'page'">
            <label class="lien__recherche lt-recherche">
                <icon>search</icon>
                <input type="text" v-model="q" @keydown="touche" placeholder="Chercher une page par son titre" autocomplete="off">
            </label>
            <ul class="lien__options lt-options" role="listbox">
                <li v-if="pages === null" class="lien__aucun">Chargement…</li>
                <li v-for="(p, i) in resultats" :key="p._id" class="lien__option" :class="{'lien__option--choisie': i === choisi, 'lien__option--actuelle': lienPage(p) === actuel}" @click="choisirPage(p)" @mouseenter="choisi = i" role="option">
                    <span><b>{{ p.titre || 'Sans titre' }}</b><small>{{ lienPage(p) }}<template v-if="p._state !== 1"> · hors ligne</template></small></span>
                    <icon v-if="lienPage(p) === actuel">check</icon>
                </li>
                <li class="lien__aucun" v-if="pages && !resultats.length">Aucune page ne correspond.</li>
            </ul>
        </div>

        <form v-else class="lt-web" @submit.prevent="validerAdresse">
            <label class="mt-champ">
                <span>Adresse web, e-mail ou téléphone</span>
                <input type="text" v-model="adresse" placeholder="https://www.exemple.fr, contact@exemple.fr ou 04 75 00 00 00" autocomplete="off">
            </label>
            <label class="compte__interrupteur"><input type="checkbox" v-model="nouvelOnglet"><span><b>Ouvrir dans un nouvel onglet</b><small>Conseillé pour un autre site.</small></span></label>
            <div class="dlg-boutons">
                <button type="submit" class="kiss-button kiss-button-primary" :disabled="!adresse.trim()"><icon>check</icon>Mettre le lien</button>
            </div>
        </form>

        <footer class="lt-pied" v-if="actuel">
            <span>Lien actuel : <code>{{ actuel }}</code></span>
            <button type="button" class="kiss-button kiss-button-small mt-danger" @click="retirer"><icon>link_off</icon>Retirer le lien</button>
        </footer>
    </div>
    `
};
