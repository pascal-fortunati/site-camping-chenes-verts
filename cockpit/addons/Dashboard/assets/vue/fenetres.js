/**
 * Fenêtres et morceaux de Cockpit remplacés (voir l'aiguillage de dashboard.js) : mêmes entrées, même retour.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const version = new URL(import.meta.url).search;
const { vignette } = await import(`./communs.js${version}`);

/**
 * Choix d'une image : vignettes, recherche, dossiers et envoi depuis l'ordinateur.
 * Mêmes réglages (filter, multiple) et même retour (onSelect) que la fenêtre de Cockpit.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function choisirImage() {
    const sansAccents = (texte) => String(texte || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    const taille = (octets) => !octets ? '' : (octets < 1048576 ? Math.max(1, Math.round(octets / 1024)) + ' ko' : (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo');

    return {

        _meta: { size: 'xlarge' },

        components: { mtVignette: vignette },

        props: {
            filter: { default: null },
            multiple: { type: Boolean, default: false }
        },

        data() {
            return {
                images: [],
                dossiers: [],
                dossier: null,
                chemin: [],
                chargement: true,
                recherche: '',
                choisies: [],
                envois: 0,
                survol: false,
                peutEnvoyer: true
            };
        },

        computed: {
            liste() {
                const mots = sansAccents(this.recherche).split(/\s+/).filter(Boolean);
                if (!mots.length) return this.images;
                return this.images.filter((a) => {
                    const texte = sansAccents([a.title, a.description, (a.tags || []).join(' ')].join(' '));
                    return mots.every((m) => texte.includes(m));
                });
            },
            selection() {
                return this.choisies.map((id) => this.images.find((a) => a._id === id)).filter(Boolean);
            },
            imagesSeulement() {
                return JSON.stringify(this.filter || '').includes('image');
            }
        },

        mounted() {
            this.charger();
            this.$nextTick(() => { const champ = this.$el.querySelector('.ci-recherche input'); if (champ) champ.focus(); });
        },

        methods: {

            taille,

            charger() {
                this.chargement = true;
                const options = { limit: 1000, sort: { _created: -1 } };
                if (this.filter) options.filter = [this.filter];
                return this.$request('/assets/assets', { options, folder: this.dossier }).then((r) => {
                    this.images = r.assets || [];
                    this.dossiers = this.recherche ? [] : (r.folders || []);
                }).catch(() => App.ui.notify('Les images n’ont pas pu être chargées.', 'error'))
                  .finally(() => { this.chargement = false; });
            },

            ouvrirDossier(d) {
                if (d) {
                    const i = this.chemin.findIndex((c) => c._id === d._id);
                    this.chemin = i > -1 ? this.chemin.slice(0, i + 1) : this.chemin.concat([d]);
                    this.dossier = d._id;
                } else {
                    this.chemin = [];
                    this.dossier = null;
                }
                this.charger();
            },

            basculer(a) {
                const i = this.choisies.indexOf(a._id);
                if (i > -1) {
                    this.choisies.splice(i, 1);
                } else if (this.multiple) {
                    this.choisies.push(a._id);
                } else {
                    this.choisies = [a._id];
                }
            },

            valider(a) {
                if (a) this.choisies = [a._id];
                if (!this.selection.length) return;
                this.$call('onSelect', this.multiple ? this.selection : this.selection[0]);
                this.$close();
            },

            choisirFichiers() {
                this.$refs.fichiers.click();
            },

            deposer(e) {
                this.survol = false;
                if (e.dataTransfer && e.dataTransfer.files.length) this.envoyer(e.dataTransfer.files);
            },

            envoyer(fichiers) {
                const liste = Array.from(fichiers || []);
                if (!liste.length) return;
                this.envois += liste.length;
                liste.forEach((fichier) => {
                    const donnees = new FormData();
                    donnees.append('files[]', fichier);
                    donnees.append('folder', this.dossier || '');
                    this.$request('/assets/upload', donnees).then((r) => {
                        const nouvelles = (r && r.assets) || [];
                        this.images.unshift(...nouvelles);
                        nouvelles.forEach((a) => {
                            if (this.multiple) this.choisies.push(a._id); else this.choisies = [a._id];
                        });
                        if (r && r.failed && r.failed.length) App.ui.notify('Refusé : ' + r.failed.join(', '), 'error');
                    }).catch((r) => {
                        if (r && /not allowed/i.test(r.error || '')) this.peutEnvoyer = false;
                        App.ui.notify((r && r.error) || `« ${fichier.name} » n’a pas pu être envoyé.`, 'error');
                    }).finally(() => { this.envois--; });
                });
                this.$refs.fichiers.value = '';
            }
        },

        template: /*html*/`
        <div class="ci" :class="{'ci--survol': survol}" @dragover.prevent="survol = true" @dragleave.self="survol = false" @drop.prevent="deposer">

            <header class="ci-tete">
                <span class="ci-tete__icone"><icon>add_photo_alternate</icon></span>
                <div class="ci-tete__texte">
                    <h2>{{ multiple ? 'Choisir des images' : (imagesSeulement ? 'Choisir une image' : 'Choisir un fichier') }}</h2>
                    <p>Cliquez pour choisir, double-cliquez pour valider tout de suite. Vous pouvez aussi glisser une photo ici.</p>
                </div>
                <button type="button" class="mt-rond" kiss-dialog-close aria-label="Fermer"><icon>close</icon></button>
            </header>

            <div class="ci-outils">
                <label class="mt-recherche ci-recherche">
                    <icon aria-hidden="true">search</icon>
                    <input type="search" v-model="recherche" placeholder="Chercher par nom" aria-label="Chercher une image">
                </label>
                <button type="button" class="kiss-button" @click="choisirFichiers" v-if="peutEnvoyer">
                    <icon>{{ envois ? 'hourglass_top' : 'upload' }}</icon>{{ envois ? 'Envoi en cours…' : 'Envoyer depuis l’ordinateur' }}
                </button>
                <input ref="fichiers" type="file" :accept="imagesSeulement ? 'image/*' : null" :multiple="multiple" hidden @change="envoyer($event.target.files)">
            </div>

            <nav class="mt-chemin" v-if="chemin.length" aria-label="Dossiers">
                <a href="#" @click.prevent="ouvrirDossier(null)"><icon>home</icon>Tous les fichiers</a>
                <template v-for="d in chemin" :key="d._id"><icon class="mt-chemin__sep">chevron_right</icon><a href="#" @click.prevent="ouvrirDossier(d)">{{ d.name }}</a></template>
            </nav>

            <div class="ci-corps">
                <div class="mt-dossiers ci-dossiers" v-if="!chargement && dossiers.length && !recherche">
                    <button type="button" class="mt-dossier" v-for="d in dossiers" :key="d._id" @click="ouvrirDossier(d)"><icon>folder</icon><span>{{ d.name }}</span></button>
                </div>

                <div class="ci-grille" v-if="chargement">
                    <div class="mt-carte mt-carte--fantome" v-for="n in 10" :key="n"></div>
                </div>
                <div class="mt-vide ci-vide" v-else-if="!liste.length">
                    <icon>{{ images.length ? 'filter_alt_off' : 'add_photo_alternate' }}</icon>
                    <p>{{ images.length ? 'Aucune image ne correspond.' : 'Aucune image ici pour l’instant.' }}</p>
                    <button type="button" class="kiss-button kiss-button-primary" v-if="!images.length && peutEnvoyer" @click="choisirFichiers"><icon>upload</icon>Envoyer une image</button>
                </div>
                <div class="ci-grille" v-else>
                    <button type="button" class="ci-image" v-for="a in liste" :key="a._id" :class="{'ci-image--choisie': choisies.includes(a._id)}" :aria-pressed="choisies.includes(a._id) ? 'true' : 'false'" @click="basculer(a)" @dblclick="valider(a)" :title="a.title">
                        <span class="ci-image__apercu">
                            <mt-vignette v-if="a.type === 'image'" :asset="a" :largeur="320"></mt-vignette>
                            <span v-else class="mt-carte__fichier"><icon>description</icon><small>{{ (a.mime || '').split('/').pop() }}</small></span>
                        </span>
                        <span class="ci-image__coche" aria-hidden="true"><icon>check</icon></span>
                        <span class="ci-image__texte"><b>{{ a.title }}</b><small><template v-if="a.width">{{ a.width }} × {{ a.height }} · </template>{{ taille(a.size) }}</small></span>
                    </button>
                </div>
            </div>

            <footer class="ci-pied">
                <span class="ci-pied__choix">
                    <template v-if="selection.length === 1"><icon>check_circle</icon>{{ selection[0].title }}</template>
                    <template v-else-if="selection.length"><icon>check_circle</icon>{{ selection.length }} images choisies</template>
                    <template v-else>Aucune image choisie</template>
                </span>
                <button type="button" class="kiss-button" kiss-dialog-close>Annuler</button>
                <button type="button" class="kiss-button kiss-button-primary" :disabled="!selection.length" @click="valider()"><icon>check</icon>{{ selection.length > 1 ? 'Choisir ces ' + selection.length + ' images' : 'Choisir' }}</button>
            </footer>

            <div class="ci-depot" v-if="survol"><icon>cloud_upload</icon><b>Déposez pour envoyer</b></div>
        </div>
        `
    };
}

/**
 * Recherche (Ctrl K, ou une lettre tapée n'importe où) dans le contenu et les médias, avec des raccourcis.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function recherche() {
    const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

    const RACCOURCIS = [
        { titre: 'Tableau de bord', detail: 'Les chiffres et les gestes du quotidien', route: '/', icone: 'space_dashboard', mots: 'accueil tableau bord' },
        { titre: 'Médias', detail: 'Envoyer, décrire, retrouver une photo', route: '/assets', icone: 'perm_media', mots: 'medias images photos fichiers' },
        { titre: 'Mon compte', detail: 'Nom, adresse e-mail, mot de passe', route: '/system/users/user', icone: 'account_circle', mots: 'compte mot de passe profil' },
    ];

    return {

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
            // La fenêtre de Cockpit place le curseur sur le premier lien à l'ouverture : on le remet dans le champ.
            const focaliser = () => {
                const champ = this.$refs.champ;
                if (champ && document.activeElement !== champ) {
                    champ.focus();
                    champ.setSelectionRange(champ.value.length, champ.value.length);
                }
            };
            [0, 60, 200, 400].forEach((ms) => setTimeout(focaliser, ms));
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
                this.$request('/dashboard/recherche', { q }).then((r) => {
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
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.$close();
                    return;
                }
                // Une lettre tapée alors que le curseur est ailleurs : elle va dans le champ.
                if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && e.target !== this.$refs.champ) {
                    this.$refs.champ.focus();
                    return;
                }
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
        <div id="app-search" class="rc" role="search" @keydown="touche">
            <label class="rc-champ">
                <icon aria-hidden="true">{{ chargement ? 'hourglass_top' : 'search' }}</icon>
                <input ref="champ" type="search" v-model="q" placeholder="Chercher une page, une information, une image…" aria-label="Rechercher" autocomplete="off" spellcheck="false">
                <kbd kiss-dialog-close title="Fermer">Échap</kbd>
            </label>

            <div class="rc-resultats">
                <section class="rc-groupe" v-for="g in groupes" :key="g.nom">
                    <h3>{{ g.nom }}</h3>
                    <a v-for="r in g.lignes" :key="r.i" :data-i="r.i" :href="r.lien" tabindex="-1" class="rc-ligne" :class="{'rc-ligne--choisie': r.i === choisi}" @mouseenter="choisi = r.i">
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
}

/**
 * Lien dans un texte riche : une page du site, une adresse web ou un document. Retour : { href, title, target }.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function lienTexte() {
    const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    const DANGEREUX = /^\s*(javascript|data|vbscript):/i;

    return {

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
}

/**
 * Menu « format » du texte riche : Paragraphe, Titre (h2), Sous-titre (h3), comme le permet EditorGuards.
 *
 * @returns {Promise<object>} le composant Vue
 */
export async function formatTexte() {
    let numero = 0;

    const FORMATS = [
        { cle: 'paragraphe', libelle: 'Paragraphe', aide: 'Le texte courant', icone: 'format_paragraph' },
        { cle: 'h2', libelle: 'Titre', aide: 'Un intertitre dans le texte', icone: 'format_h2' },
        { cle: 'h3', libelle: 'Sous-titre', aide: 'Sous un titre', icone: 'format_h3' },
    ];

    return {

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
}
