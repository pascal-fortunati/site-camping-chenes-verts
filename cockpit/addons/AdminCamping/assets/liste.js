/**
 * La liste d'une collection (pages, messages…) : chaque élément en ligne lisible — titre, adresse ou résumé, état,
 * dernière modification — avec recherche, filtres, tri et les gestes courants (modifier, voir sur le site, mettre
 * en ligne, dupliquer, supprimer). S'appuie sur les routes de Cockpit /content/collection/*.
 */

// Les fichiers voisins sont chargés à la même version que celui-ci (?v=…) : le serveur les garde un an en cache.
const version = new URL(import.meta.url).search;
const { default: confirmer } = await import(`./confirmer.js${version}`);
const { default: menuTri } = await import(`./menu-tri.js${version}`);

const sansAccents = (texte) => String(texte || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

const quand = (secondes) => {
    const il = Date.now() / 1000 - secondes;
    if (il < 60) return 'à l’instant';
    if (il < 3600) return `il y a ${Math.floor(il / 60)} min`;
    if (il < 86400) return `il y a ${Math.floor(il / 3600)} h`;
    if (il < 2 * 86400) return 'hier';
    const d = new Date(secondes * 1000);
    return `le ${d.getDate()}${d.getDate() === 1 ? 'er' : ''} ${mois[d.getMonth()]}${d.getFullYear() !== new Date().getFullYear() ? ' ' + d.getFullYear() : ''}`;
};


export default {

    components: { menuTri },

    props: {
        model: { type: Object, required: true },
        vue: { type: Object, default: () => ({ icone: 'folder', mots: ['élément', 'éléments', 'Ajouter'] }) },
        droits: { type: Object, default: () => ({}) },
        site: { type: String, default: '' },
        accueil: { type: String, default: 'accueil' }
    },

    data() {
        let tri = this.model.name === 'messages' ? 'cree' : 'modifie';
        try { tri = localStorage.getItem(`admincamping.liste.${this.model.name}.tri`) || tri; } catch (e) {}

        return {
            elements: [],
            chargement: true,
            recherche: '',
            filtre: 'tout',
            tri,
            menu: null
        };
    },

    computed: {

        noms() {
            return this.vue.mots;
        },

        icone() {
            return this.vue.icone;
        },

        champs() {
            return (this.model.fields || []).map((f) => f.name);
        },

        champTitre() {
            return ['titre', 'title', 'nom', 'name'].find((n) => this.champs.includes(n))
                || ((this.model.fields || []).find((f) => f.type === 'text') || {}).name || '_id';
        },

        tris() {
            return this.avecLu
                ? [{ value: 'cree', label: 'Les plus récents' }, { value: 'nom', label: 'Par nom' }]
                : [{ value: 'modifie', label: 'Modifiés récemment' }, { value: 'cree', label: 'Ajoutés récemment' }, { value: 'nom', label: 'Par nom' }];
        },

        avecEtat() {
            return this.model.name !== 'messages';
        },

        avecLu() {
            return (this.model.fields || []).some((f) => f.name === 'lu' && f.type === 'boolean');
        },

        compteurs() {
            const c = { tout: this.elements.length, ligne: 0, brouillon: 0, nonlus: 0 };
            this.elements.forEach((e) => {
                if (e._state === 1) c.ligne++; else c.brouillon++;
                if (this.avecLu && !e.lu) c.nonlus++;
            });
            return c;
        },

        filtres() {
            const c = this.compteurs;
            if (this.avecLu) {
                return [
                    { cle: 'tout', libelle: 'Tous', nombre: c.tout },
                    { cle: 'nonlus', libelle: 'Non lus', nombre: c.nonlus, alerte: true }
                ];
            }
            return [
                { cle: 'tout', libelle: 'Tout', nombre: c.tout },
                { cle: 'ligne', libelle: 'En ligne', nombre: c.ligne },
                { cle: 'brouillon', libelle: 'Hors ligne', nombre: c.brouillon, cache: !c.brouillon }
            ].filter((f) => !f.cache);
        },

        liste() {
            const mots = sansAccents(this.recherche).split(/\s+/).filter(Boolean);
            const liste = this.elements.filter((e) => {
                if (this.filtre === 'ligne' && e._state !== 1) return false;
                if (this.filtre === 'brouillon' && e._state === 1) return false;
                if (this.filtre === 'nonlus' && e.lu) return false;
                if (!mots.length) return true;
                const texte = sansAccents([this.titre(e), this.detail(e), e.slug, e.email, e.message].join(' '));
                return mots.every((m) => texte.includes(m));
            });
            const tris = {
                modifie: (a, b) => (b._modified || 0) - (a._modified || 0),
                cree: (a, b) => (b._created || 0) - (a._created || 0),
                nom: (a, b) => this.titre(a).localeCompare(this.titre(b), 'fr')
            };
            return liste.sort(tris[this.tri] || tris.modifie);
        }
    },

    watch: {
        tri(v) { try { localStorage.setItem(`admincamping.liste.${this.model.name}.tri`, v); } catch (e) {} }
    },

    mounted() {
        this.charger();
        this.fermerMenu = (e) => { if (this.menu && !e.target.closest('.ls-menu, .ls-plus')) this.menu = null; };
        document.addEventListener('click', this.fermerMenu);
        this.surTouche = (e) => { if (e.key === 'Escape') this.menu = null; };
        document.addEventListener('keydown', this.surTouche);
    },

    unmounted() {
        document.removeEventListener('click', this.fermerMenu);
        document.removeEventListener('keydown', this.surTouche);
    },

    methods: {

        quand,

        charger() {
            this.chargement = true;
            return this.$request(`/content/collection/find/${this.model.name}`, { options: { limit: 1000, sort: { _modified: -1 } } }).then((r) => {
                this.elements = r.items || [];
            }).catch((r) => {
                App.ui.notify((r && r.error) || 'La liste n’a pas pu être chargée.', 'error');
            }).finally(() => { this.chargement = false; });
        },

        titre(e) {
            const t = e[this.champTitre];
            return (typeof t === 'string' && t.trim()) ? t : 'Sans titre';
        },

        detail(e) {
            if (this.model.name === 'pages') return e.slug === this.accueil ? '/ (page d’accueil)' : '/' + (e.slug || '');
            if (this.model.name === 'messages') {
                const sejour = [e.arrivee, e.depart].filter(Boolean).join(' → ');
                return [e.email, sejour, e.hebergement, e.personnes ? e.personnes + ' pers.' : ''].filter(Boolean).join(' · ') || (e.message || '').slice(0, 90);
            }
            if (e.slug) return '/' + e.slug;
            const texte = (this.model.fields || []).find((f) => ['text', 'wysiwyg'].includes(f.type) && f.name !== this.champTitre && typeof e[f.name] === 'string' && e[f.name].trim());
            return texte ? e[texte.name].replace(/<[^>]+>/g, ' ').slice(0, 110) : '';
        },

        lien(e) {
            return this.$routeUrl(`/content/collection/item/${this.model.name}/${e._id}`);
        },

        surLeSite(e) {
            if (!this.site || this.model.name !== 'pages' || !e.slug || e._state !== 1) return '';
            return this.site + (e.slug === this.accueil ? '/' : '/' + e.slug);
        },

        basculerMenu(e) {
            this.menu = this.menu === e._id ? null : e._id;
        },

        changerEtat(e) {
            if (!this.droits.publier) return;
            const etat = e._state === 1 ? 0 : 1;
            this.$request(`/content/collection/updateState/${this.model.name}`, { ids: [e._id], state: etat }).then(() => {
                e._state = etat;
                App.ui.notify(etat === 1 ? 'Mis en ligne.' : 'Retiré du site.');
            }).catch((r) => App.ui.notify((r && r.error) || 'Le changement a échoué.', 'error'));
        },

        basculerLu(e) {
            const item = { _id: e._id, lu: !e.lu };
            this.$request(`/content/models/saveItem/${this.model.name}`, { item }).then(() => {
                e.lu = !e.lu;
            }).catch((r) => App.ui.notify((r && r.error) || 'Le changement a échoué.', 'error'));
        },

        async supprimer(e) {
            this.menu = null;
            const oui = await confirmer({
                titre: `Supprimer « ${this.titre(e)} » ?`,
                texte: this.model.name === 'pages' ? 'La page disparaît du site et du menu. Cette action est définitive.' : 'Cette action est définitive.',
                bouton: 'Supprimer',
                danger: true
            });
            if (!oui) return;
            this.$request(`/content/collection/remove/${this.model.name}`, { ids: [e._id] }).then(() => {
                this.elements = this.elements.filter((x) => x._id !== e._id);
                App.ui.notify('Supprimé.');
            }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
        }
    },

    template: /*html*/`
    <div class="mt ls">

        <section class="tdb-accueil tdb-accueil--page">
            <h1 class="tdb-accueil__titre">{{ model.label || model.name }}</h1>
            <p class="tdb-accueil__phrase" v-if="model.info">{{ model.info }}</p>
            <div class="mt-bandeau__actions" v-if="droits.creer">
                <a class="mt-bandeau__bouton" :href="$routeUrl('/content/collection/item/' + model.name)"><icon>add</icon>{{ noms[2] }}</a>
            </div>
            <ul class="mt-bandeau__chiffres" v-if="!chargement">
                <li><b>{{ compteurs.tout }}</b> {{ compteurs.tout > 1 ? noms[1] : noms[0] }}</li>
                <li v-if="avecEtat"><b>{{ compteurs.ligne }}</b> en ligne</li>
                <li v-if="avecEtat && compteurs.brouillon"><b>{{ compteurs.brouillon }}</b> hors ligne</li>
                <li v-if="avecLu"><b>{{ compteurs.nonlus }}</b> non lu{{ compteurs.nonlus > 1 ? 's' : '' }}</li>
            </ul>
        </section>

        <div class="mt-outils">
            <label class="mt-recherche">
                <icon aria-hidden="true">search</icon>
                <input type="search" v-model="recherche" :placeholder="'Chercher dans les ' + noms[1]" :aria-label="'Chercher dans les ' + noms[1]">
            </label>
            <div class="mt-filtres" role="group" aria-label="Filtrer">
                <button type="button" v-for="f in filtres" :key="f.cle" class="mt-filtre" :class="{'mt-filtre--actif': filtre === f.cle, 'mt-filtre--alerte': f.alerte && f.nombre}" :aria-pressed="filtre === f.cle ? 'true' : 'false'" @click="filtre = f.cle">
                    {{ f.libelle }}<span class="mt-filtre__nombre">{{ f.nombre }}</span>
                </button>
            </div>
            <div class="mt-outils__droite">
                <menu-tri v-model="tri" :options="tris"></menu-tri>
            </div>
        </div>

        <div class="ls-liste" v-if="chargement">
            <div class="ls-ligne ls-ligne--fantome" v-for="n in 6" :key="n"></div>
        </div>

        <div class="mt-vide" v-else-if="!liste.length">
            <icon>{{ elements.length ? 'filter_alt_off' : icone }}</icon>
            <p v-if="elements.length">Rien ne correspond.</p>
            <p v-else>Aucun {{ noms[0] }} pour l’instant.</p>
            <button type="button" class="kiss-button" v-if="elements.length" @click="recherche = ''; filtre = 'tout'">Tout afficher</button>
        </div>

        <div class="ls-liste" v-else>
            <article class="ls-ligne" v-for="e in liste" :key="e._id" :class="{'ls-ligne--nouveau': avecLu && !e.lu}">
                <a class="ls-ligne__principal" :href="lien(e)">
                    <span class="ls-ligne__icone"><icon>{{ avecLu ? (e.lu ? 'drafts' : 'mark_email_unread') : icone }}</icon></span>
                    <span class="ls-ligne__texte">
                        <b>{{ titre(e) }}</b>
                        <small>{{ detail(e) }}</small>
                    </span>
                </a>
                <button type="button" class="ls-etat" v-if="avecEtat" :class="e._state === 1 ? 'ls-etat--ligne' : 'ls-etat--brouillon'" :disabled="!droits.publier" @click="changerEtat(e)" :title="droits.publier ? (e._state === 1 ? 'Retirer du site' : 'Mettre en ligne') : ''">
                    <span class="ls-etat__point"></span>{{ e._state === 1 ? 'En ligne' : 'Hors ligne' }}
                </button>
                <button type="button" class="ls-etat" v-if="avecLu" :class="e.lu ? 'ls-etat--brouillon' : 'ls-etat--nouveau'" :disabled="!droits.modifier" @click="basculerLu(e)" :title="e.lu ? 'Marquer comme non lu' : 'Marquer comme lu'">
                    <span class="ls-etat__point"></span>{{ e.lu ? 'Lu' : 'Nouveau' }}
                </button>
                <span class="ls-ligne__date" :title="new Date((e._modified || 0) * 1000).toLocaleString('fr-FR')">{{ quand(avecLu ? e._created : e._modified) }}</span>
                <div class="ls-actions">
                    <a class="ls-rond" :href="surLeSite(e)" target="_blank" rel="noopener" v-if="surLeSite(e)" title="Voir sur le site" aria-label="Voir sur le site"><icon>open_in_new</icon></a>
                    <a class="ls-rond" :href="lien(e)" title="Modifier" aria-label="Modifier"><icon>edit</icon></a>
                    <button type="button" class="ls-rond ls-plus" v-if="droits.creer || droits.supprimer" @click.stop="basculerMenu(e)" :aria-expanded="menu === e._id ? 'true' : 'false'" aria-label="Plus d’actions"><icon>more_vert</icon></button>
                    <div class="ls-menu" v-if="menu === e._id">
                        <a v-if="droits.creer" :href="$routeUrl('/content/collection/clone/' + model.name + '/' + e._id)"><icon>content_copy</icon>Dupliquer</a>
                        <button type="button" v-if="droits.supprimer" class="ls-menu__danger" @click="supprimer(e)"><icon>delete</icon>Supprimer</button>
                    </div>
                </div>
            </article>
        </div>
    </div>
    `
};
