/**
 * La recherche (Ctrl K, ou une lettre tapée n'importe où) : dans les pages, les fiches de l'identité, les messages
 * et les images, avec des raccourcis « Aller à ». Remplace la fenêtre de Cockpit, qui ne cherchait que le nom des
 * modèles. Flèches pour choisir, Entrée pour ouvrir, Échap pour fermer.
 */

const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

const RACCOURCIS = [
    { titre: 'Tableau de bord', detail: 'Les chiffres et les gestes du quotidien', route: '/', icone: 'space_dashboard', mots: 'accueil tableau bord' },
    { titre: 'Le contenu du site', detail: 'Pages, informations, messages', route: '/content', icone: 'article', mots: 'contenu pages' },
    { titre: 'Images et fichiers', detail: 'Envoyer, décrire, retrouver une photo', route: '/assets', icone: 'photo_library', mots: 'images photos fichiers medias' },
    { titre: 'Mon compte', detail: 'Nom, adresse e-mail, mot de passe', route: '/system/users/user', icone: 'account_circle', mots: 'compte mot de passe profil' },
];

export default {

    _meta: { size: 'large' },

    props: {
        value: { type: String, default: '' }
    },

    data() {
        return {
            q: this.value || '',
            resultats: [],
            chargement: false,
            choisi: 0,
            minuteur: null,
            demande: 0
        };
    },

    computed: {
        raccourcis() {
            const mots = plat(this.q).split(/\s+/).filter(Boolean);
            return RACCOURCIS.filter((r) => !mots.length || mots.every((m) => plat(r.titre + ' ' + r.mots).includes(m)))
                .map((r) => ({ ...r, groupe: 'Aller à', lien: App.route(r.route) }));
        },
        tous() {
            return this.raccourcis.concat(this.resultats);
        },
        groupes() {
            const g = [];
            this.tous.forEach((r, i) => {
                let groupe = g.find((x) => x.nom === r.groupe);
                if (!groupe) g.push(groupe = { nom: r.groupe, lignes: [] });
                groupe.lignes.push({ ...r, i });
            });
            return g;
        }
    },

    watch: {
        q() {
            this.choisi = 0;
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.chercher(), 180);
        }
    },

    mounted() {
        this.$nextTick(() => this.$refs.champ && this.$refs.champ.focus());
        if (this.q) this.chercher();
    },

    methods: {

        chercher() {
            const q = this.q.trim();
            if (q.length < 2) {
                this.resultats = [];
                this.chargement = false;
                return;
            }
            const numero = ++this.demande;
            this.chargement = true;
            this.$request('/admincamping/recherche', { q }).then((r) => {
                if (numero !== this.demande) return;
                this.resultats = Array.isArray(r) ? r : [];
            }).catch(() => {
                if (numero === this.demande) this.resultats = [];
            }).finally(() => {
                if (numero === this.demande) this.chargement = false;
            });
        },

        surligner(texte) {
            const mots = plat(this.q).split(/\s+/).filter((m) => m.length > 1);
            const sur = String(texte || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            if (!mots.length) return sur;
            const source = plat(sur);
            const marques = new Array(sur.length).fill(false);
            mots.forEach((m) => {
                let i = source.indexOf(m);
                while (i > -1) { for (let k = i; k < i + m.length; k++) marques[k] = true; i = source.indexOf(m, i + m.length); }
            });
            let html = '';
            let ouvert = false;
            for (let k = 0; k < sur.length; k++) {
                if (marques[k] && !ouvert) { html += '<mark>'; ouvert = true; }
                if (!marques[k] && ouvert) { html += '</mark>'; ouvert = false; }
                html += sur[k];
            }
            return html + (ouvert ? '</mark>' : '');
        },

        touche(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const n = this.tous.length;
                if (!n) return;
                this.choisi = (this.choisi + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                this.$nextTick(() => {
                    const ligne = this.$el.querySelector(`[data-i="${this.choisi}"]`);
                    if (ligne) ligne.scrollIntoView({ block: 'nearest' });
                });
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const r = this.tous[this.choisi];
                if (r) this.ouvrir(r);
            }
        },

        ouvrir(r) {
            location.href = r.lien;
        }
    },

    template: /*html*/`
    <div id="app-search" class="rc" role="search">
        <label class="rc-champ">
            <icon aria-hidden="true">{{ chargement ? 'hourglass_top' : 'search' }}</icon>
            <input ref="champ" type="search" v-model="q" @keydown="touche" placeholder="Chercher une page, une information, une image…" aria-label="Rechercher" autocomplete="off" spellcheck="false">
            <kbd kiss-dialog-close title="Fermer">Échap</kbd>
        </label>

        <div class="rc-resultats">
            <section class="rc-groupe" v-for="g in groupes" :key="g.nom">
                <h3>{{ g.nom }}</h3>
                <a v-for="r in g.lignes" :key="r.i" :data-i="r.i" :href="r.lien" class="rc-ligne" :class="{'rc-ligne--choisie': r.i === choisi}" @mouseenter="choisi = r.i">
                    <span class="rc-ligne__icone"><icon>{{ r.icone }}</icon></span>
                    <span class="rc-ligne__texte"><b v-html="surligner(r.titre)"></b><small v-if="r.detail" v-html="surligner(r.detail)"></small></span>
                    <icon class="rc-ligne__entree" aria-hidden="true">keyboard_return</icon>
                </a>
            </section>
            <p class="rc-vide" v-if="q.trim().length >= 2 && !chargement && !resultats.length">
                <icon>search_off</icon>Rien trouvé pour « {{ q.trim() }} ». Essayez un autre mot.
            </p>
        </div>

        <footer class="rc-pied">
            <span><kbd>↑</kbd><kbd>↓</kbd> choisir</span>
            <span><kbd>Entrée</kbd> ouvrir</span>
            <span><kbd>Échap</kbd> fermer</span>
        </footer>
    </div>
    `
};
