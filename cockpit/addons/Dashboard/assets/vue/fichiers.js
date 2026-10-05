/**
 * Explorateur de fichiers du serveur (administrateur) : arborescence des dossiers, barre d'adresse, historique,
 * affichage en icônes ou en détails, tri, sélection (Ctrl, Maj), menu contextuel, renommage sur place, panneau
 * de détails, envoi par glisser-déposer et raccourcis clavier. S'appuie sur l'API /finder/api de Cockpit.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const version = new URL(import.meta.url).search;
const { confirmer } = await import(`./communs.js${version}`);

const TYPES = [
    [/^(png|jpe?g|gif|webp|avif|bmp|ico|svg)$/, 'image', 'Image'],
    [/^(mp4|webm|mov|avi|mkv)$/, 'movie', 'Vidéo'],
    [/^(mp3|wav|ogg|flac|m4a)$/, 'music_note', 'Son'],
    [/^pdf$/, 'picture_as_pdf', 'Document PDF'],
    [/^(zip|gz|tgz|tar|rar|7z)$/, 'folder_zip', 'Archive'],
    [/^(php|js|mjs|ts|css|scss|html?|twig|json|xml|ya?ml|sql|sh)$/, 'code', 'Code'],
    [/^(md|txt|log|csv|env|ini|htaccess|editorconfig|gitignore|npmrc)$/, 'description', 'Texte'],
    [/^(woff2?|ttf|otf|eot)$/, 'font_download', 'Police'],
    [/^(docx?|odt|rtf)$/, 'article', 'Document'],
    [/^(xlsx?|ods)$/, 'table_chart', 'Tableur'],
];
const TEXTE = /^(php|js|mjs|ts|css|scss|html?|twig|json|xml|ya?ml|sql|sh|md|txt|log|csv|env|ini|htaccess|editorconfig|gitignore|npmrc|svg)$/;

/**
 * @param {?number} o taille en octets
 * @returns {string}
 */
const taille = (o) => {
    if (o === null || o === undefined) return '';
    if (o < 1024) return o + ' o';
    if (o < 1048576) return (o / 1024).toFixed(o < 10240 ? 1 : 0).replace('.', ',') + ' ko';
    if (o < 1073741824) return (o / 1048576).toFixed(1).replace('.', ',') + ' Mo';
    return (o / 1073741824).toFixed(1).replace('.', ',') + ' Go';
};
const lire = (cle, defaut) => { try { return localStorage.getItem(cle) || defaut; } catch (e) { return defaut; } };
const garder = (cle, v) => { try { localStorage.setItem(cle, v); } catch (e) { /* stockage indisponible */ } };

/**
 * Un dossier de l'arborescence de gauche, et ses sous-dossiers une fois dépliés (composant récursif).
 */
const noeud = {
    name: 'noeud',
    props: { dossier: Object, chemin: String, profondeur: Number },
    emits: ['ouvrir', 'deplier'],
    template: /*html*/`
        <li>
            <div class="fx-arbre__ligne" :class="{'fx-arbre__ligne--ici': chemin === dossier.path}" :style="{'--profondeur': profondeur}">
                <button type="button" class="fx-arbre__fleche" @click.stop="$emit('deplier', dossier)" :aria-label="dossier.ouvert ? 'Replier' : 'Déplier'" :aria-expanded="dossier.ouvert ? 'true' : 'false'">
                    <icon>{{ dossier.ouvert ? 'expand_more' : 'chevron_right' }}</icon>
                </button>
                <button type="button" class="fx-arbre__nom" @click="$emit('ouvrir', dossier.path)" :title="dossier.name">
                    <icon class="fx-dossier">{{ dossier.ouvert || chemin === dossier.path ? 'folder_open' : 'folder' }}</icon><span>{{ dossier.name }}</span>
                </button>
            </div>
            <ul v-if="dossier.ouvert && dossier.enfants && dossier.enfants.length">
                <noeud v-for="d in dossier.enfants" :key="d.path" :dossier="d" :chemin="chemin" :profondeur="profondeur + 1" @ouvrir="(p) => $emit('ouvrir', p)" @deplier="(x) => $emit('deplier', x)"></noeud>
            </ul>
        </li>`
};
noeud.components = { noeud };

export default {
    components: { noeud },

    props: {
        root: { type: String, default: '#root:' },
        racine: { type: String, default: 'Racine' }
    },

    data() {
        return {
            chemin: '',
            dossiers: [],
            fichiers: [],
            chargement: true,
            arriere: [],
            avant: [],
            selection: [],
            ancre: null,
            vue: lire('dashboard.fichiers.vue', 'details'),
            tri: { cle: 'nom', sens: 1 },
            recherche: '',
            arbre: [],
            menu: null,
            renommage: null,
            nouveauNom: '',
            envoi: false,
            glisse: false,
            panneau: lire('dashboard.fichiers.panneau', 'oui') === 'oui'
        };
    },

    computed: {
        elements() {
            const q = this.recherche.trim().toLowerCase();
            const garde = (e) => !q || e.name.toLowerCase().includes(q);
            const cle = { nom: (e) => e.name.toLowerCase(), taille: (e) => e.filesize || 0, date: (e) => e.modified || 0, type: (e) => this.type(e).libelle }[this.tri.cle];
            const trier = (a, b) => (cle(a) > cle(b) ? 1 : (cle(a) < cle(b) ? -1 : 0)) * this.tri.sens;
            return [...this.dossiers.filter(garde).sort(trier), ...this.fichiers.filter(garde).sort(trier)];
        },
        miettes() {
            const parties = this.chemin.split('/').filter(Boolean);
            return parties.map((nom, i) => ({ nom, chemin: parties.slice(0, i + 1).join('/') }));
        },
        choisis() {
            return this.elements.filter((e) => this.selection.includes(e.path));
        },
        detail() {
            return this.choisis.length === 1 ? this.choisis[0] : null;
        },
        poidsSelection() {
            return this.choisis.reduce((s, e) => s + (e.filesize || 0), 0);
        },
        nomDossier() {
            return this.miettes.length ? this.miettes[this.miettes.length - 1].nom : this.racine;
        },
        modifiable() {
            return !!(this.detail && this.detail.is_file && TEXTE.test(String(this.detail.ext || '').toLowerCase()));
        }
    },

    watch: {
        root() {
            this.arriere = [];
            this.avant = [];
            this.ouvrir('', false);
            this.chargerArbre();
        },
        vue(v) { garder('dashboard.fichiers.vue', v); },
        panneau(v) { garder('dashboard.fichiers.panneau', v ? 'oui' : 'non'); }
    },

    mounted() {
        this.ouvrir('', false);
        this.chargerArbre();
        App.assets.require(['assets:assets/vendor/spotlight/spotlight.bundle.js', 'assets:assets/vendor/spotlight/css/spotlight.min.css']);
        this.clavier = (e) => this.raccourci(e);
        this.fermerMenu = () => { this.menu = null; };
        document.addEventListener('keydown', this.clavier);
        document.addEventListener('click', this.fermerMenu);
        window.addEventListener('blur', this.fermerMenu);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.clavier);
        document.removeEventListener('click', this.fermerMenu);
        window.removeEventListener('blur', this.fermerMenu);
    },

    methods: {
        /**
         * @param {string} cmd commande de l'API Finder
         * @param {object} params
         * @returns {Promise<object>}
         */
        api(cmd, params = {}) {
            return this.$request('/finder/api', { root: this.root, cmd, ...params });
        },

        type(e) {
            if (e.is_dir) return { icone: 'folder', libelle: 'Dossier' };
            const ext = String(e.ext || '').toLowerCase();
            const t = TYPES.find(([motif]) => motif.test(ext));
            return { icone: t ? t[1] : 'draft', libelle: t ? t[2] + (ext ? ' ' + ext.toUpperCase() : '') : (ext ? 'Fichier ' + ext.toUpperCase() : 'Fichier') };
        },

        image(e) {
            return e.is_file && /^(png|jpe?g|gif|webp|avif|svg)$/.test(String(e.ext || '').toLowerCase()) && e.url;
        },

        taille,

        date(s) {
            return s ? new Date(s * 1000).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
        },

        /* ── Navigation ── */

        ouvrir(chemin, historiser = true) {
            if (historiser && chemin !== this.chemin) {
                this.arriere.push(this.chemin);
                this.avant = [];
            }
            this.chargement = true;
            this.menu = null;
            this.renommage = null;
            return this.api('ls', { path: chemin || '/' }).then((r) => {
                this.chemin = chemin;
                this.dossiers = r.folders || [];
                this.fichiers = r.files || [];
                this.selection = [];
                this.recherche = '';
                this.deplierJusqua(chemin);
            }).catch(() => App.ui.notify('Ce dossier n’a pas pu être ouvert.', 'error'))
                .finally(() => { this.chargement = false; });
        },

        precedent() {
            if (!this.arriere.length) return;
            this.avant.push(this.chemin);
            this.ouvrir(this.arriere.pop(), false);
        },

        suivant() {
            if (!this.avant.length) return;
            this.arriere.push(this.chemin);
            this.ouvrir(this.avant.pop(), false);
        },

        parent() {
            if (!this.chemin) return;
            this.ouvrir(this.chemin.split('/').slice(0, -1).join('/'));
        },

        actualiser() {
            this.ouvrir(this.chemin, false);
            this.rafraichirNoeud(this.chemin);
        },

        /* ── Arborescence ── */

        enfants(chemin) {
            return this.api('ls', { path: chemin || '/' }).then((r) => (r.folders || []).map((d) => ({ ...d, ouvert: false, enfants: null })));
        },

        chargerArbre() {
            this.enfants('').then((d) => { this.arbre = d; });
        },

        trouverNoeud(chemin, liste = this.arbre) {
            for (const d of liste) {
                if (d.path === chemin) return d;
                if (d.enfants && chemin.startsWith(d.path + '/')) {
                    const t = this.trouverNoeud(chemin, d.enfants);
                    if (t) return t;
                }
            }
            return null;
        },

        deplier(d) {
            if (d.ouvert) {
                d.ouvert = false;
                return Promise.resolve();
            }
            return (d.enfants ? Promise.resolve(d.enfants) : this.enfants(d.path)).then((e) => { d.enfants = e; d.ouvert = true; });
        },

        async deplierJusqua(chemin) {
            const parties = chemin.split('/').filter(Boolean);
            for (let i = 1; i < parties.length; i++) {
                const n = this.trouverNoeud(parties.slice(0, i).join('/'));
                if (n && !n.ouvert) await this.deplier(n);
            }
        },

        rafraichirNoeud(chemin) {
            if (!chemin) return this.chargerArbre();
            const n = this.trouverNoeud(chemin);
            if (n) this.enfants(chemin).then((e) => { n.enfants = e; });
        },

        /* ── Sélection ── */

        choisir(e, evt) {
            if (this.renommage) return;
            const liste = this.elements.map((x) => x.path);
            if (evt.shiftKey && this.ancre) {
                const [a, b] = [liste.indexOf(this.ancre), liste.indexOf(e.path)].sort((x, y) => x - y);
                this.selection = liste.slice(a, b + 1);
            } else if (evt.ctrlKey || evt.metaKey) {
                this.selection = this.selection.includes(e.path) ? this.selection.filter((p) => p !== e.path) : [...this.selection, e.path];
                this.ancre = e.path;
            } else {
                this.selection = [e.path];
                this.ancre = e.path;
            }
        },

        vider() {
            if (!this.renommage) this.selection = [];
        },

        lancer(e) {
            if (!e) return;
            if (e.is_dir) return this.ouvrir(e.path);
            if (TEXTE.test(String(e.ext || '').toLowerCase())) return this.modifier(e);
            if (/^(image|video|audio)\//.test(e.mime || '') && window.Spotlight) return window.Spotlight.show([{ src: e.url }]);
            this.telecharger(e);
        },

        trier(cle) {
            this.tri = this.tri.cle === cle ? { cle, sens: -this.tri.sens } : { cle, sens: 1 };
        },

        /* ── Menu contextuel ── */

        contexte(evt, e = null) {
            evt.preventDefault();
            if (e && !this.selection.includes(e.path)) this.choisir(e, {});
            if (!e) this.selection = [];
            const zone = this.$el.getBoundingClientRect();
            this.menu = { x: Math.max(8, Math.min(evt.clientX - zone.left, zone.width - 250)), y: Math.max(8, Math.min(evt.clientY - zone.top, zone.height - 320)), cible: !!e };
        },

        /* ── Actions ── */

        modifier(e) {
            VueView.ui.offcanvas('finder:assets/dialogs/file-editor.js', { root: this.root, file: e });
        },

        telecharger(e) {
            const cmd = e.is_dir ? 'downloadfolder' : 'download';
            window.open(this.$routeUrl(`/finder/api?cmd=${cmd}&path=${encodeURIComponent(e.path)}&root=${encodeURIComponent(this.root)}&xcsrftoken=${App.csrf || ''}`));
        },

        copierChemin(e) {
            App.utils.copyText(e.path, () => App.ui.notify('Chemin copié.'));
        },

        creer(type) {
            const dossier = type === 'dossier';
            App.ui.prompt(dossier ? 'Nom du nouveau dossier' : 'Nom du nouveau fichier', dossier ? 'Nouveau dossier' : 'nouveau.txt', (name) => {
                if (!name || !name.trim()) return;
                this.api(dossier ? 'createfolder' : 'createfile', { path: this.chemin || '/', name: name.trim() }).then(() => {
                    this.actualiser();
                    App.ui.notify(dossier ? 'Dossier créé.' : 'Fichier créé.');
                }).catch((r) => App.ui.notify((r && r.error) || 'La création a échoué.', 'error'));
            });
        },

        renommer(e) {
            if (!e) return;
            this.menu = null;
            this.renommage = e.path;
            this.nouveauNom = e.name;
            this.$nextTick(() => {
                const champ = this.$el.querySelector('.fx-renommer');
                if (!champ) return;
                champ.focus();
                const point = e.is_file ? e.name.lastIndexOf('.') : -1;
                champ.setSelectionRange(0, point > 0 ? point : e.name.length);
            });
        },

        validerNom(e) {
            if (this.renommage !== e.path) return;
            const nom = this.nouveauNom.trim();
            this.renommage = null;
            if (!nom || nom === e.name) return;
            this.api('rename', { path: e.path, name: nom }).then(() => this.actualiser())
                .catch((r) => App.ui.notify((r && r.error) || 'Le renommage a échoué.', 'error'));
        },

        extraire(e) {
            this.api('unzip', { path: this.chemin || '/', zip: e.path }).then((r) => {
                const ok = typeof r === 'string' ? JSON.parse(r).success : r && r.success;
                App.ui.notify(ok ? 'Archive extraite.' : 'L’archive n’a pas pu être extraite.', ok ? 'success' : 'error');
                this.actualiser();
            });
        },

        async supprimer(liste = this.choisis) {
            if (!liste.length) return;
            const n = liste.length;
            const dossiers = liste.filter((e) => e.is_dir).length;
            const oui = await confirmer({
                titre: n === 1 ? `Supprimer « ${liste[0].name} » ?` : `Supprimer ${n} éléments ?`,
                texte: (dossiers ? 'Les dossiers sont supprimés avec tout leur contenu. ' : '') + 'Rien ne va dans une corbeille : cette action est définitive.',
                details: n > 1 ? liste.slice(0, 6).map((e) => e.name).concat(n > 6 ? [`et ${n - 6} autre${n > 7 ? 's' : ''}`] : []) : [],
                bouton: 'Supprimer', danger: true
            });
            if (!oui) return;
            this.api('removefiles', { paths: liste.map((e) => e.path) }).then(() => {
                App.ui.notify(n > 1 ? `${n} éléments supprimés.` : 'Supprimé.');
                this.actualiser();
            }).catch(() => App.ui.notify('La suppression a échoué.', 'error'));
        },

        /* ── Envoi ── */

        envoyer(fichiers) {
            if (!fichiers || !fichiers.length) return;
            const donnees = new FormData();
            let poids = 0;
            [...fichiers].forEach((f) => { poids += f.size; donnees.append('files[]', f); });
            if (App._vars && poids >= App._vars.maxUploadSize) {
                App.ui.notify('Ces fichiers dépassent la taille d’envoi permise par le serveur.', 'error');
                return;
            }
            donnees.append('cmd', 'upload');
            donnees.append('path', this.chemin || '/');
            donnees.append('root', this.root);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', App.route('/finder/api'));
            xhr.setRequestHeader('X-CSRF-TOKEN', App.csrf);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            this.envoi = 0;
            xhr.upload.addEventListener('progress', ({ loaded, total }) => { this.envoi = Math.round(loaded / total * 100); });
            xhr.addEventListener('loadend', () => {
                this.envoi = false;
                if (xhr.status === 200) {
                    App.ui.notify(fichiers.length > 1 ? `${fichiers.length} fichiers envoyés.` : 'Fichier envoyé.');
                    this.actualiser();
                } else {
                    App.ui.notify('L’envoi a échoué.', 'error');
                }
            });
            xhr.send(donnees);
        },

        deposer(evt) {
            this.glisse = false;
            if (evt.dataTransfer && evt.dataTransfer.files && evt.dataTransfer.files.length) this.envoyer(evt.dataTransfer.files);
        },

        /* ── Clavier ── */

        raccourci(e) {
            if (!this.$el.isConnected || e.target.closest('input, textarea, select, [contenteditable], kiss-dialog, kiss-offcanvas')) return;
            const touche = e.key;
            if (e.altKey && touche === 'ArrowLeft') { e.preventDefault(); return this.precedent(); }
            if (e.altKey && touche === 'ArrowRight') { e.preventDefault(); return this.suivant(); }
            if ((e.altKey && touche === 'ArrowUp') || touche === 'Backspace') { e.preventDefault(); return this.parent(); }
            if ((e.ctrlKey || e.metaKey) && touche.toLowerCase() === 'a') { e.preventDefault(); this.selection = this.elements.map((x) => x.path); return; }
            if (touche === 'F5') { e.preventDefault(); return this.actualiser(); }
            if (touche === 'Escape') { this.selection = []; this.menu = null; return; }
            if (touche === 'Delete') { e.preventDefault(); return this.supprimer(); }
            if (touche === 'F2' && this.detail) { e.preventDefault(); return this.renommer(this.detail); }
            if (touche === 'Enter' && this.detail) { e.preventDefault(); return this.lancer(this.detail); }
            if (touche === 'ArrowDown' || touche === 'ArrowUp' || ((touche === 'ArrowRight' || touche === 'ArrowLeft') && this.vue === 'icones')) {
                e.preventDefault();
                const liste = this.elements;
                if (!liste.length) return;
                const i = liste.findIndex((x) => x.path === (this.ancre || ''));
                const colonnes = this.vue === 'icones' ? Math.max(1, Math.floor((this.$el.querySelector('.fx-grille')?.clientWidth || 600) / 132)) : 1;
                const pas = { ArrowDown: colonnes, ArrowUp: -colonnes, ArrowRight: 1, ArrowLeft: -1 }[touche];
                const j = i < 0 ? 0 : Math.min(liste.length - 1, Math.max(0, i + pas));
                this.choisir(liste[j], e.shiftKey ? { shiftKey: true } : {});
                this.$nextTick(() => this.$el.querySelector('.fx-element--choisi')?.scrollIntoView({ block: 'nearest' }));
            }
        }
    },

    template: /*html*/`
        <div class="fx" :class="{'fx--glisse': glisse}" @dragover.prevent="glisse = true" @drop.prevent="deposer">

            <div class="fx-outils" role="toolbar" aria-label="Navigation">
                <div class="fx-outils__groupe">
                    <button type="button" class="ls-rond" :disabled="!arriere.length" @click="precedent" title="Précédent (Alt ←)" aria-label="Précédent"><icon>arrow_back</icon></button>
                    <button type="button" class="ls-rond" :disabled="!avant.length" @click="suivant" title="Suivant (Alt →)" aria-label="Suivant"><icon>arrow_forward</icon></button>
                    <button type="button" class="ls-rond" :disabled="!chemin" @click="parent" title="Dossier parent (Alt ↑)" aria-label="Dossier parent"><icon>arrow_upward</icon></button>
                    <button type="button" class="ls-rond" @click="actualiser" title="Actualiser (F5)" aria-label="Actualiser"><icon>refresh</icon></button>
                </div>
                <nav class="fx-adresse" aria-label="Adresse">
                    <icon class="fx-dossier">folder_open</icon>
                    <button type="button" @click="ouvrir('')">{{ racine }}</button>
                    <template v-for="m in miettes" :key="m.chemin"><icon>chevron_right</icon><button type="button" @click="ouvrir(m.chemin)">{{ m.nom }}</button></template>
                </nav>
                <label class="fx-chercher">
                    <icon aria-hidden="true">search</icon>
                    <input type="search" v-model="recherche" :placeholder="'Chercher dans ' + nomDossier" aria-label="Chercher dans ce dossier">
                </label>
            </div>

            <div class="fx-commandes">
                <button type="button" class="kiss-button kiss-button-small" @click="creer('dossier')"><icon>create_new_folder</icon>Nouveau dossier</button>
                <button type="button" class="kiss-button kiss-button-small" @click="creer('fichier')"><icon>note_add</icon>Nouveau fichier</button>
                <label class="kiss-button kiss-button-small fx-envoi" :class="{'kiss-disabled': envoi !== false}">
                    <icon>upload</icon>{{ envoi !== false ? 'Envoi ' + envoi + ' %' : 'Envoyer' }}
                    <input type="file" multiple @change="(e) => { envoyer(e.target.files); e.target.value = ''; }" :disabled="envoi !== false">
                </label>
                <template v-if="choisis.length">
                    <span class="fx-commandes__trait"></span>
                    <button type="button" class="kiss-button kiss-button-small" v-if="detail" @click="renommer(detail)"><icon>drive_file_rename_outline</icon>Renommer</button>
                    <button type="button" class="kiss-button kiss-button-small" v-if="detail" @click="telecharger(detail)"><icon>download</icon>Télécharger</button>
                    <button type="button" class="kiss-button kiss-button-small kiss-button-danger" @click="supprimer()"><icon>delete</icon>Supprimer</button>
                </template>
                <div class="kiss-flex-1"></div>
                <div class="fiche-choix fx-vues" role="radiogroup" aria-label="Affichage">
                    <button type="button" role="radio" :aria-checked="vue === 'details' ? 'true' : 'false'" :class="{'fiche-choix--actif': vue === 'details'}" @click="vue = 'details'" title="Détails" aria-label="Détails"><icon>view_list</icon></button>
                    <button type="button" role="radio" :aria-checked="vue === 'icones' ? 'true' : 'false'" :class="{'fiche-choix--actif': vue === 'icones'}" @click="vue = 'icones'" title="Grandes icônes" aria-label="Grandes icônes"><icon>grid_view</icon></button>
                </div>
                <button type="button" class="ls-rond" :class="{'fx-bascule--actif': panneau}" @click="panneau = !panneau" :aria-pressed="panneau ? 'true' : 'false'" title="Panneau de détails" aria-label="Panneau de détails"><icon>right_panel_open</icon></button>
            </div>

            <div class="fx-corps" :class="{'fx-corps--panneau': panneau}">
                <aside class="fx-arbre" aria-label="Dossiers">
                    <ul>
                        <li>
                            <div class="fx-arbre__ligne" :class="{'fx-arbre__ligne--ici': chemin === ''}" style="--profondeur: 0">
                                <span class="fx-arbre__fleche"><icon>expand_more</icon></span>
                                <button type="button" class="fx-arbre__nom" @click="ouvrir('')"><icon class="fx-dossier">hard_drive</icon><span>{{ racine }}</span></button>
                            </div>
                            <ul>
                                <noeud v-for="d in arbre" :key="d.path" :dossier="d" :chemin="chemin" :profondeur="1" @ouvrir="ouvrir" @deplier="deplier"></noeud>
                            </ul>
                        </li>
                    </ul>
                </aside>

                <section class="fx-contenu" @click.self="vider" @contextmenu="(e) => { if (!e.target.closest('.fx-element')) contexte(e); }" aria-label="Contenu du dossier">
                    <div class="fx-attente" v-if="chargement"><app-loader></app-loader></div>

                    <div class="fx-vide" v-else-if="!elements.length" @click.self="vider">
                        <icon>{{ recherche ? 'search_off' : 'folder_off' }}</icon>
                        <p>{{ recherche ? 'Rien ne correspond à « ' + recherche + ' ».' : 'Ce dossier est vide. Glissez des fichiers ici pour les envoyer.' }}</p>
                    </div>

                    <div class="fx-liste" v-else-if="vue === 'details'" @click.self="vider">
                        <div class="fx-liste__tete" role="row">
                            <button type="button" v-for="c in [['nom', 'Nom'], ['date', 'Modifié le'], ['type', 'Type'], ['taille', 'Taille']]" :key="c[0]" @click="trier(c[0])" :class="'fx-col--' + c[0]">
                                {{ c[1] }}<icon v-if="tri.cle === c[0]">{{ tri.sens > 0 ? 'arrow_drop_up' : 'arrow_drop_down' }}</icon>
                            </button>
                        </div>
                        <div class="fx-element fx-ligne" v-for="e in elements" :key="e.path" role="row" :class="{'fx-element--choisi': selection.includes(e.path)}"
                            @click="choisir(e, $event)" @dblclick="lancer(e)" @contextmenu.stop="contexte($event, e)" :title="e.name">
                            <span class="fx-col--nom">
                                <icon :class="{'fx-dossier': e.is_dir}">{{ type(e).icone }}</icon>
                                <input v-if="renommage === e.path" class="fx-renommer" v-model="nouveauNom" @keydown.enter.prevent="validerNom(e)" @keydown.esc.prevent="renommage = null" @blur="validerNom(e)" @click.stop @dblclick.stop>
                                <span v-else class="fx-nom">{{ e.name }}</span>
                                <icon v-if="!e.is_writable" class="fx-verrou" title="Lecture seule">lock</icon>
                            </span>
                            <span class="fx-col--date">{{ date(e.modified) }}</span>
                            <span class="fx-col--type">{{ type(e).libelle }}</span>
                            <span class="fx-col--taille">{{ e.is_dir ? '' : taille(e.filesize) }}</span>
                        </div>
                    </div>

                    <div class="fx-grille" v-else @click.self="vider">
                        <div class="fx-element fx-tuile" v-for="e in elements" :key="e.path" :class="{'fx-element--choisi': selection.includes(e.path)}"
                            @click="choisir(e, $event)" @dblclick="lancer(e)" @contextmenu.stop="contexte($event, e)" :title="e.name">
                            <span class="fx-tuile__visuel">
                                <img v-if="image(e)" :src="e.url" alt="" loading="lazy">
                                <icon v-else :class="{'fx-dossier': e.is_dir}">{{ type(e).icone }}</icon>
                            </span>
                            <input v-if="renommage === e.path" class="fx-renommer" v-model="nouveauNom" @keydown.enter.prevent="validerNom(e)" @keydown.esc.prevent="renommage = null" @blur="validerNom(e)" @click.stop @dblclick.stop>
                            <span v-else class="fx-nom">{{ e.name }}</span>
                        </div>
                    </div>

                    <div class="fx-depot" v-if="glisse" @dragleave.self="glisse = false"><icon>upload</icon>Déposez pour envoyer dans « {{ nomDossier }} »</div>
                </section>

                <aside class="fx-details" v-if="panneau" aria-label="Détails">
                    <template v-if="detail">
                        <div class="fx-details__visuel">
                            <img v-if="image(detail)" :src="detail.url" alt="">
                            <icon v-else :class="{'fx-dossier': detail.is_dir}">{{ type(detail).icone }}</icon>
                        </div>
                        <h3>{{ detail.name }}</h3>
                        <dl class="fiche-infos fx-details__infos">
                            <div><dt>Type</dt><dd>{{ type(detail).libelle }}</dd></div>
                            <div v-if="detail.is_file"><dt>Taille</dt><dd>{{ taille(detail.filesize) }}</dd></div>
                            <div v-if="detail.modified"><dt>Modifié</dt><dd>{{ date(detail.modified) }}</dd></div>
                            <div v-if="detail.mime"><dt>MIME</dt><dd>{{ detail.mime }}</dd></div>
                            <div><dt>Chemin</dt><dd><code>/{{ detail.path }}</code></dd></div>
                            <div><dt>Accès</dt><dd>{{ detail.is_writable ? 'Lecture et écriture' : 'Lecture seule' }}</dd></div>
                        </dl>
                        <div class="fx-details__actions">
                            <button type="button" class="kiss-button kiss-button-small kiss-button-primary" @click="lancer(detail)"><icon>{{ detail.is_dir ? 'folder_open' : (modifiable ? 'edit' : 'open_in_new') }}</icon>{{ detail.is_dir ? 'Ouvrir' : (modifiable ? 'Modifier' : 'Ouvrir') }}</button>
                            <button type="button" class="kiss-button kiss-button-small" @click="telecharger(detail)"><icon>download</icon>Télécharger</button>
                            <button type="button" class="kiss-button kiss-button-small" @click="copierChemin(detail)"><icon>content_copy</icon>Copier le chemin</button>
                        </div>
                    </template>
                    <template v-else-if="choisis.length > 1">
                        <div class="fx-details__visuel"><icon>library_add_check</icon></div>
                        <h3>{{ choisis.length }} éléments sélectionnés</h3>
                        <p class="fiche-aide" v-if="poidsSelection">{{ taille(poidsSelection) }} au total</p>
                    </template>
                    <template v-else>
                        <div class="fx-details__visuel"><icon class="fx-dossier">folder_open</icon></div>
                        <h3>{{ nomDossier }}</h3>
                        <p class="fiche-aide">{{ dossiers.length }} dossier{{ dossiers.length > 1 ? 's' : '' }}, {{ fichiers.length }} fichier{{ fichiers.length > 1 ? 's' : '' }}</p>
                        <p class="fiche-aide">Choisissez un élément pour voir ses détails. Double-clic pour ouvrir, clic droit pour les actions.</p>
                    </template>
                </aside>
            </div>

            <footer class="fx-etat">
                <span>{{ elements.length }} élément{{ elements.length > 1 ? 's' : '' }}</span>
                <span v-if="choisis.length">{{ choisis.length }} sélectionné{{ choisis.length > 1 ? 's' : '' }}<template v-if="poidsSelection"> · {{ taille(poidsSelection) }}</template></span>
                <div class="kiss-flex-1"></div>
                <span class="fx-etat__aide">Double-clic : ouvrir · F2 : renommer · Suppr : supprimer · Ctrl A : tout sélectionner</span>
            </footer>

            <div class="fx-menu" v-if="menu" :style="{left: menu.x + 'px', top: menu.y + 'px'}" @click.stop role="menu">
                <template v-if="menu.cible">
                    <button type="button" role="menuitem" v-if="detail" @click="menu = null; lancer(detail)"><icon>{{ detail.is_dir ? 'folder_open' : 'open_in_new' }}</icon>Ouvrir</button>
                    <button type="button" role="menuitem" v-if="modifiable" @click="menu = null; modifier(detail)"><icon>edit</icon>Modifier le texte</button>
                    <button type="button" role="menuitem" v-if="detail" @click="menu = null; telecharger(detail)"><icon>download</icon>{{ detail.is_dir ? 'Télécharger en zip' : 'Télécharger' }}</button>
                    <button type="button" role="menuitem" v-if="detail && /^zip$/i.test(detail.ext || '')" @click="menu = null; extraire(detail)"><icon>unarchive</icon>Extraire ici</button>
                    <button type="button" role="menuitem" v-if="detail" @click="renommer(detail)"><icon>drive_file_rename_outline</icon>Renommer</button>
                    <button type="button" role="menuitem" v-if="detail" @click="menu = null; copierChemin(detail)"><icon>content_copy</icon>Copier le chemin</button>
                    <span class="fx-menu__trait"></span>
                    <button type="button" role="menuitem" class="fx-menu__danger" @click="menu = null; supprimer()"><icon>delete</icon>Supprimer{{ choisis.length > 1 ? ' (' + choisis.length + ')' : '' }}</button>
                </template>
                <template v-else>
                    <button type="button" role="menuitem" @click="menu = null; creer('dossier')"><icon>create_new_folder</icon>Nouveau dossier</button>
                    <button type="button" role="menuitem" @click="menu = null; creer('fichier')"><icon>note_add</icon>Nouveau fichier</button>
                    <button type="button" role="menuitem" @click="menu = null; $el.querySelector('.fx-envoi input').click()"><icon>upload</icon>Envoyer des fichiers</button>
                    <span class="fx-menu__trait"></span>
                    <button type="button" role="menuitem" @click="menu = null; vue = vue === 'details' ? 'icones' : 'details'"><icon>{{ vue === 'details' ? 'grid_view' : 'view_list' }}</icon>{{ vue === 'details' ? 'Grandes icônes' : 'Détails' }}</button>
                    <button type="button" role="menuitem" @click="menu = null; actualiser()"><icon>refresh</icon>Actualiser</button>
                </template>
            </div>
        </div>
    `
};
