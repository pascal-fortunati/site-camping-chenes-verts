/**
 * La médiathèque : les images et fichiers du site en grille ou en liste, avec ce qui aide vraiment le client —
 * où chaque image est utilisée, celles qui ne servent plus, celles trop lourdes, l'envoi par glisser-déposer
 * et un panneau pour décrire une image. Elle s'appuie sur les routes de Cockpit (/assets/*) et sur
 * /admincamping/usages.
 */

const LOURDE = 1024 * 1024;
const PAR_PAGE = 48;

const taille = (octets) => {
    if (!octets) return '';
    if (octets < 1024 * 1024) return Math.max(1, Math.round(octets / 1024)) + ' ko';
    return (octets / 1024 / 1024).toFixed(1).replace('.', ',') + ' Mo';
};

const date = (secondes) => new Date(secondes * 1000).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

const echapper = (texte) => String(texte).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const sansAccents = (texte) => String(texte || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default {

    props: {
        droits: { type: Object, default: () => ({}) }
    },

    data() {
        let vue = 'grille';
        try { vue = localStorage.getItem('admincamping.mediatheque.vue') || 'grille'; } catch (e) {}

        return {
            images: [],
            dossiers: [],
            dossier: null,
            chemin: [],
            usages: null,
            chargement: true,
            recherche: '',
            filtre: 'tout',
            tri: 'recent',
            vue,
            affichees: PAR_PAGE,
            choisies: [],
            ouverte: null,
            fiche: null,
            enregistrement: false,
            envois: [],
            survol: 0
        };
    },

    computed: {

        compteurs() {
            const c = { tout: this.images.length, photos: 0, documents: 0, inutilisees: 0, lourdes: 0, poids: 0 };
            this.images.forEach((a) => {
                c.poids += a.size || 0;
                if (a.type === 'image') c.photos++; else c.documents++;
                if (this.usages && !(this.usages[a._id] || []).length) c.inutilisees++;
                if ((a.size || 0) > LOURDE) c.lourdes++;
            });
            return c;
        },

        filtres() {
            const c = this.compteurs;
            return [
                { cle: 'tout', libelle: 'Tout', nombre: c.tout },
                { cle: 'photos', libelle: 'Images', nombre: c.photos, cache: !c.documents },
                { cle: 'documents', libelle: 'Documents', nombre: c.documents, cache: !c.documents },
                { cle: 'inutilisees', libelle: 'Non utilisées', nombre: c.inutilisees, cache: !this.usages },
                { cle: 'lourdes', libelle: 'Trop lourdes', nombre: c.lourdes, alerte: true, cache: !c.lourdes }
            ].filter((f) => !f.cache);
        },

        liste() {
            const mots = sansAccents(this.recherche).split(/\s+/).filter(Boolean);
            let liste = this.images.filter((a) => {
                if (this.filtre === 'photos' && a.type !== 'image') return false;
                if (this.filtre === 'documents' && a.type === 'image') return false;
                if (this.filtre === 'inutilisees' && (!this.usages || (this.usages[a._id] || []).length)) return false;
                if (this.filtre === 'lourdes' && (a.size || 0) <= LOURDE) return false;
                if (!mots.length) return true;
                const texte = sansAccents([a.title, a.description, (a.tags || []).join(' ')].join(' '));
                return mots.every((m) => texte.includes(m));
            });
            const tris = {
                recent: (a, b) => b._created - a._created,
                ancien: (a, b) => a._created - b._created,
                nom: (a, b) => String(a.title).localeCompare(String(b.title), 'fr'),
                poids: (a, b) => (b.size || 0) - (a.size || 0)
            };
            return liste.sort(tris[this.tri]);
        },

        visibles() {
            return this.liste.slice(0, this.affichees);
        },

        indexOuverte() {
            return this.ouverte ? this.liste.findIndex((a) => a._id === this.ouverte._id) : -1;
        },

        envoisEnCours() {
            return this.envois.filter((e) => e.etat === 'envoi').length;
        }
    },

    watch: {
        vue(v) { try { localStorage.setItem('admincamping.mediatheque.vue', v); } catch (e) {} },
        recherche() { this.affichees = PAR_PAGE; },
        filtre() { this.affichees = PAR_PAGE; }
    },

    mounted() {
        this.charger();
        this.chargerUsages();

        this.surTouche = (e) => {
            if (!this.ouverte) return;
            if (e.key === 'Escape') this.fermer();
            if (e.target.closest('input, textarea, select')) return;
            if (e.key === 'ArrowRight') this.voisine(1);
            if (e.key === 'ArrowLeft') this.voisine(-1);
        };
        document.addEventListener('keydown', this.surTouche);

        if (this.droits.envoyer) {
            this.surEntree = (e) => { if (this.fichiersGlisses(e)) { e.preventDefault(); this.survol++; } };
            this.surSortie = (e) => { if (this.fichiersGlisses(e)) this.survol = Math.max(0, this.survol - 1); };
            this.surPassage = (e) => { if (this.fichiersGlisses(e)) e.preventDefault(); };
            this.surDepot = (e) => {
                if (!this.fichiersGlisses(e)) return;
                e.preventDefault();
                this.survol = 0;
                this.envoyer(e.dataTransfer.files);
            };
            document.addEventListener('dragenter', this.surEntree);
            document.addEventListener('dragleave', this.surSortie);
            document.addEventListener('dragover', this.surPassage);
            document.addEventListener('drop', this.surDepot);
        }
    },

    unmounted() {
        document.removeEventListener('keydown', this.surTouche);
        ['dragenter', 'dragleave', 'dragover', 'drop'].forEach((t, i) => {
            const f = [this.surEntree, this.surSortie, this.surPassage, this.surDepot][i];
            if (f) document.removeEventListener(t, f);
        });
    },

    methods: {

        taille,
        date,

        fichiersGlisses(e) {
            return e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
        },

        charger() {
            this.chargement = true;
            this.choisies = [];
            return this.$request('/assets/assets', { options: { limit: 1000, sort: { _created: -1 } }, folder: this.dossier }).then((r) => {
                this.images = r.assets || [];
                this.dossiers = r.folders || [];
                this.chargement = false;
            }).catch(() => {
                this.chargement = false;
                App.ui.notify('La liste des images n’a pas pu être chargée.', 'error');
            });
        },

        chargerUsages() {
            return this.$request('/admincamping/usages').then((u) => { this.usages = u || {}; }).catch(() => { this.usages = null; });
        },

        vignette(a, largeur = 480) {
            return App.route(`/assets/thumbnail/${a._id}?m=bestFit&mime=auto&w=${largeur}&h=${Math.round(largeur * 0.75)}&q=70&t=${a._modified}`);
        },

        adresse(a) {
            return new URL(this.$baseUrl('#uploads:' + a.path), location.href).href;
        },

        utilisations(a) {
            return this.usages ? (this.usages[a._id] || []) : null;
        },

        icone(a) {
            if (a.type === 'video') return 'movie';
            if (a.type === 'audio') return 'music_note';
            if (/pdf/.test(a.mime || '')) return 'picture_as_pdf';
            return 'description';
        },

        // ── Dossiers ──
        ouvrirDossier(d) {
            if (d) {
                const i = this.chemin.findIndex((c) => c._id === d._id);
                this.chemin = i > -1 ? this.chemin.slice(0, i + 1) : this.chemin.concat([d]);
                this.dossier = d._id;
            } else {
                this.chemin = [];
                this.dossier = null;
            }
            this.recherche = '';
            this.filtre = 'tout';
            this.charger();
        },

        nouveauDossier() {
            VueView.ui.offcanvas('assets:assets/dialogs/asset-folder.js', { parent: this.dossier }, {
                save: (d) => this.dossiers.push(d)
            });
        },

        // ── Choix multiple ──
        basculer(a) {
            const i = this.choisies.indexOf(a._id);
            if (i > -1) this.choisies.splice(i, 1); else this.choisies.push(a._id);
        },

        supprimer(ids) {
            const noms = [];
            ids.forEach((id) => (this.utilisations({ _id: id }) || []).forEach((u) => noms.includes(u.libelle) || noms.push(u.libelle)));
            const message = (ids.length > 1 ? `Supprimer ces ${ids.length} fichiers ?` : 'Supprimer ce fichier ?')
                + (noms.length ? `<br><br><strong>Attention, encore utilisé sur :</strong><br>${noms.map(echapper).join('<br>')}` : '')
                + '<br><br>Cette action est définitive.';
            App.ui.confirm(message, () => {
                this.$request('/assets/remove', { assets: ids }).then(() => {
                    this.images = this.images.filter((a) => !ids.includes(a._id));
                    this.choisies = [];
                    if (this.ouverte && ids.includes(this.ouverte._id)) this.fermer();
                    App.ui.notify(ids.length > 1 ? 'Fichiers supprimés.' : 'Fichier supprimé.');
                }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
            });
        },

        // ── Panneau d'une image ──
        ouvrir(a) {
            this.ouverte = a;
            this.fiche = { title: a.title || '', description: a.description || '' };
            this.$nextTick(() => { const champ = document.querySelector('.mt-panneau input'); if (champ) champ.focus({ preventScroll: true }); });
        },

        fermer() {
            this.ouverte = null;
            this.fiche = null;
        },

        voisine(pas) {
            const i = this.indexOuverte + pas;
            if (i >= 0 && i < this.liste.length) this.ouvrir(this.liste[i]);
        },

        modifiee() {
            if (!this.ouverte || !this.fiche) return false;
            return ['title', 'description'].some((k) => String(this.fiche[k] || '') !== String(this.ouverte[k] || ''));
        },

        enregistrer() {
            if (!this.modifiee()) return;
            this.enregistrement = true;
            const asset = Object.assign({}, this.ouverte, this.fiche);
            this.$request('/assets/update', { asset }).then((maj) => {
                Object.assign(this.ouverte, maj || this.fiche);
                this.enregistrement = false;
                App.ui.notify('Image enregistrée.');
            }).catch((r) => {
                this.enregistrement = false;
                App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error');
            });
        },

        copier(a) {
            App.utils.copyText(this.adresse(a), () => App.ui.notify('Adresse de l’image copiée.'));
        },

        // ── Envoi ──
        choisirFichiers() {
            this.$refs.fichiers.click();
        },

        envoyer(fichiers) {
            Array.from(fichiers || []).forEach((fichier) => {
                const envoi = { nom: fichier.name, taille: fichier.size, progres: 0, etat: 'envoi' };
                this.envois.unshift(envoi);
                const donnees = new FormData();
                donnees.append('files[]', fichier);
                donnees.append('folder', this.dossier || '');
                const xhr = new XMLHttpRequest();
                xhr.open('POST', App.route('/assets/upload'));
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                if (App.csrf) xhr.setRequestHeader('X-CSRF-TOKEN', App.csrf);
                xhr.upload.onprogress = (e) => { if (e.lengthComputable) envoi.progres = Math.round(e.loaded / e.total * 100); };
                xhr.onloadend = () => {
                    let r = null;
                    try { r = JSON.parse(xhr.responseText); } catch (e) {}
                    if (xhr.status === 200 && r && Array.isArray(r.assets) && r.assets.length) {
                        envoi.etat = 'fini';
                        envoi.progres = 100;
                        this.images.unshift(...r.assets);
                        r.assets.forEach((a) => { if (this.usages) this.usages[a._id] = []; });
                    } else {
                        envoi.etat = 'erreur';
                    }
                    if (!this.envoisEnCours) setTimeout(() => { this.envois = this.envois.filter((e) => e.etat === 'envoi'); }, 4000);
                };
                xhr.send(donnees);
            });
            if (this.$refs.fichiers) this.$refs.fichiers.value = '';
        },

        remplacer() {
            this.$refs.remplacement.click();
        },

        envoyerRemplacement(e) {
            const fichier = e.target.files && e.target.files[0];
            if (!fichier || !this.ouverte) return;
            const donnees = new FormData();
            donnees.append('files[]', fichier);
            donnees.append('assetId', this.ouverte._id);
            this.enregistrement = true;
            this.$request('/assets/replace', donnees).then((a) => {
                this.enregistrement = false;
                if (a && a._id) {
                    Object.assign(this.ouverte, a);
                    App.ui.notify('Fichier remplacé. Il est déjà à jour sur le site.');
                }
            }).catch(() => {
                this.enregistrement = false;
                App.ui.notify('Le remplacement a échoué.', 'error');
            });
            e.target.value = '';
        }
    },

    template: /*html*/`
    <div class="mt" :class="{'mt--survol': survol}">

        <section class="tdb-accueil tdb-accueil--page mt-bandeau">
            <h1 class="tdb-accueil__titre">Images et fichiers</h1>
            <p class="tdb-accueil__phrase" v-if="droits.envoyer">Glissez vos photos n’importe où sur cette page pour les envoyer. Cliquez sur une image pour la décrire et voir où elle est utilisée.</p>
            <p class="tdb-accueil__phrase" v-else>Cliquez sur une image pour la décrire et voir où elle est utilisée.</p>
            <div class="mt-bandeau__actions">
                <button type="button" class="mt-bandeau__bouton" @click="choisirFichiers" v-if="droits.envoyer"><icon>upload</icon>Envoyer des images</button>
                <button type="button" class="ctn-admin" @click="nouveauDossier" v-if="droits.dossiers"><icon>create_new_folder</icon>Nouveau dossier</button>
            </div>
            <ul class="mt-bandeau__chiffres" v-if="!chargement">
                <li><b>{{ compteurs.tout }}</b> fichier{{ compteurs.tout > 1 ? 's' : '' }}</li>
                <li><b>{{ taille(compteurs.poids) || '0 ko' }}</b> au total</li>
                <li v-if="usages && compteurs.inutilisees"><b>{{ compteurs.inutilisees }}</b> non utilisée{{ compteurs.inutilisees > 1 ? 's' : '' }}</li>
            </ul>
            <input ref="fichiers" type="file" multiple hidden @change="envoyer($event.target.files)">
        </section>

        <div class="mt-outils">
            <label class="mt-recherche">
                <icon aria-hidden="true">search</icon>
                <input type="search" v-model="recherche" placeholder="Chercher une image par son nom ou sa description" aria-label="Chercher une image">
            </label>
            <div class="mt-filtres" role="group" aria-label="Filtrer">
                <button type="button" v-for="f in filtres" :key="f.cle" class="mt-filtre" :class="{'mt-filtre--actif': filtre === f.cle, 'mt-filtre--alerte': f.alerte && f.nombre}" :aria-pressed="filtre === f.cle ? 'true' : 'false'" @click="filtre = f.cle">
                    {{ f.libelle }}<span class="mt-filtre__nombre">{{ f.nombre }}</span>
                </button>
            </div>
            <div class="mt-outils__droite">
                <label class="mt-tri">
                    <span class="mt-tri__texte">Trier</span>
                    <select v-model="tri" aria-label="Trier">
                        <option value="recent">Les plus récentes</option>
                        <option value="ancien">Les plus anciennes</option>
                        <option value="nom">Par nom</option>
                        <option value="poids">Les plus lourdes</option>
                    </select>
                </label>
                <div class="mt-vues" role="group" aria-label="Affichage">
                    <button type="button" :class="{'mt-vue--active': vue === 'grille'}" :aria-pressed="vue === 'grille' ? 'true' : 'false'" aria-label="En grille" @click="vue = 'grille'"><icon>grid_view</icon></button>
                    <button type="button" :class="{'mt-vue--active': vue === 'liste'}" :aria-pressed="vue === 'liste' ? 'true' : 'false'" aria-label="En liste" @click="vue = 'liste'"><icon>view_list</icon></button>
                </div>
            </div>
        </div>

        <nav class="mt-chemin" v-if="chemin.length" aria-label="Dossiers">
            <a href="#" @click.prevent="ouvrirDossier(null)"><icon>home</icon>Tous les fichiers</a>
            <template v-for="d in chemin" :key="d._id"><icon class="mt-chemin__sep">chevron_right</icon><a href="#" @click.prevent="ouvrirDossier(d)">{{ d.name }}</a></template>
        </nav>

        <div class="mt-dossiers" v-if="!chargement && dossiers.length">
            <button type="button" class="mt-dossier" v-for="d in dossiers" :key="d._id" @click="ouvrirDossier(d)"><icon>folder</icon><span>{{ d.name }}</span></button>
        </div>

        <div class="mt-chargement" v-if="chargement">
            <div class="mt-carte mt-carte--fantome" v-for="n in 12" :key="n"></div>
        </div>

        <div class="mt-vide" v-else-if="!liste.length">
            <icon>{{ images.length ? 'filter_alt_off' : 'add_photo_alternate' }}</icon>
            <p v-if="images.length">Aucun fichier ne correspond.</p>
            <p v-else>Aucun fichier ici pour l’instant.</p>
            <button type="button" class="kiss-button" v-if="images.length" @click="recherche = ''; filtre = 'tout'">Tout afficher</button>
            <button type="button" class="kiss-button kiss-button-primary" v-else-if="droits.envoyer" @click="choisirFichiers"><icon>upload</icon>Envoyer des images</button>
        </div>

        <div class="mt-grille" v-else-if="vue === 'grille'">
            <article class="mt-carte" v-for="a in visibles" :key="a._id" :class="{'mt-carte--choisie': choisies.includes(a._id), 'mt-carte--ouverte': ouverte && ouverte._id === a._id}">
                <button type="button" class="mt-carte__image" @click="ouvrir(a)" :aria-label="'Ouvrir ' + a.title">
                    <img v-if="a.type === 'image'" :src="vignette(a)" alt="" loading="lazy" decoding="async">
                    <span v-else class="mt-carte__fichier"><icon>{{ icone(a) }}</icon><small>{{ (a.mime || '').split('/').pop() }}</small></span>
                </button>
                <label class="mt-carte__case" v-if="droits.supprimer" :title="'Choisir ' + a.title">
                    <input type="checkbox" :checked="choisies.includes(a._id)" @change="basculer(a)" :aria-label="'Choisir ' + a.title">
                </label>
                <div class="mt-carte__badges">
                    <span class="mt-badge mt-badge--alerte" v-if="a.size > 1048576" title="Fichier lourd"><icon>weight</icon></span>
                </div>
                <div class="mt-carte__texte">
                    <b :title="a.title">{{ a.title }}</b>
                    <span>
                        <template v-if="a.width">{{ a.width }} × {{ a.height }} · </template>{{ taille(a.size) }}
                        <template v-if="utilisations(a)"> · <em :class="{'mt-inutilisee': !utilisations(a).length}">{{ utilisations(a).length ? 'utilisée ' + utilisations(a).length + ' fois' : 'non utilisée' }}</em></template>
                    </span>
                </div>
            </article>
        </div>

        <div class="mt-liste" v-else>
            <div class="mt-ligne mt-ligne--tete">
                <span></span><span>Nom</span><span>Utilisation</span><span>Taille</span><span>Envoyée le</span>
            </div>
            <button type="button" class="mt-ligne" v-for="a in visibles" :key="a._id" @click="ouvrir(a)" :class="{'mt-carte--ouverte': ouverte && ouverte._id === a._id}">
                <span class="mt-ligne__image"><img v-if="a.type === 'image'" :src="vignette(a, 160)" alt="" loading="lazy"><icon v-else>{{ icone(a) }}</icon></span>
                <span class="mt-ligne__nom"><b>{{ a.title }}</b><small v-if="a.description">{{ a.description }}</small></span>
                <span><template v-if="utilisations(a)"><em :class="{'mt-inutilisee': !utilisations(a).length}">{{ utilisations(a).length ? utilisations(a).map(u => u.libelle).join(', ') : 'Non utilisée' }}</em></template></span>
                <span :class="{'mt-ligne__alerte': a.size > 1048576}">{{ taille(a.size) }}<template v-if="a.width"><br><small>{{ a.width }} × {{ a.height }}</small></template></span>
                <span>{{ date(a._created) }}</span>
            </button>
        </div>

        <div class="mt-plus" v-if="!chargement && liste.length > affichees">
            <button type="button" class="kiss-button" @click="affichees += 48"><icon>expand_more</icon>Afficher plus ({{ liste.length - affichees }})</button>
        </div>

        <transition name="mt-monte">
            <div class="mt-selection" v-if="choisies.length">
                <b>{{ choisies.length }} choisi{{ choisies.length > 1 ? 's' : '' }}</b>
                <button type="button" class="kiss-button" @click="choisies = []">Annuler</button>
                <button type="button" class="kiss-button kiss-button-danger" @click="supprimer(choisies.slice())"><icon>delete</icon>Supprimer</button>
            </div>
        </transition>

        <div class="mt-depot" v-if="survol" aria-hidden="true">
            <div><icon>cloud_upload</icon><b>Déposez vos fichiers</b><span>Ils seront envoyés{{ chemin.length ? ' dans « ' + chemin[chemin.length - 1].name + ' »' : '' }}.</span></div>
        </div>

        <div class="mt-envois" v-if="envois.length" role="status">
            <div class="mt-envoi" v-for="(e, i) in envois" :key="i" :class="'mt-envoi--' + e.etat">
                <icon>{{ e.etat === 'fini' ? 'check_circle' : (e.etat === 'erreur' ? 'error' : 'upload') }}</icon>
                <span class="mt-envoi__nom">{{ e.nom }}<small>{{ e.etat === 'erreur' ? 'Échec : format ou taille refusés' : taille(e.taille) }}</small></span>
                <span class="mt-envoi__barre"><span :style="{width: e.progres + '%'}"></span></span>
            </div>
        </div>

        <teleport to="body">
            <transition name="mt-fondu">
                <div class="mt-voile" v-if="ouverte" @click="fermer"></div>
            </transition>
            <transition name="mt-glisse">
                <aside class="mt-panneau" v-if="ouverte && fiche" role="dialog" aria-modal="true" :aria-label="ouverte.title">
                    <header class="mt-panneau__tete">
                        <button type="button" class="mt-rond" @click="voisine(-1)" :disabled="indexOuverte <= 0" aria-label="Image précédente"><icon>chevron_left</icon></button>
                        <button type="button" class="mt-rond" @click="voisine(1)" :disabled="indexOuverte >= liste.length - 1" aria-label="Image suivante"><icon>chevron_right</icon></button>
                        <span class="mt-panneau__position">{{ indexOuverte + 1 }} / {{ liste.length }}</span>
                        <button type="button" class="mt-rond" @click="fermer" aria-label="Fermer"><icon>close</icon></button>
                    </header>

                    <div class="mt-panneau__corps">
                        <a class="mt-panneau__apercu" :href="adresse(ouverte)" target="_blank" rel="noopener" title="Ouvrir en grand dans un nouvel onglet">
                            <img v-if="ouverte.type === 'image'" :src="vignette(ouverte, 900)" alt="">
                            <span v-else class="mt-carte__fichier"><icon>{{ icone(ouverte) }}</icon></span>
                        </a>
                        <p class="mt-panneau__infos">
                            <span v-if="ouverte.width">{{ ouverte.width }} × {{ ouverte.height }} px</span>
                            <span :class="{'mt-ligne__alerte': ouverte.size > 1048576}">{{ taille(ouverte.size) }}</span>
                            <span>{{ (ouverte.mime || '').split('/').pop().toUpperCase() }}</span>
                            <span>Envoyée le {{ date(ouverte._created) }}</span>
                        </p>
                        <p class="mt-conseil" v-if="ouverte.size > 1048576"><icon>speed</icon>Cette image pèse plus de 1 Mo : elle ralentit l’affichage sur téléphone. Préférez une version plus légère (moins de 500 ko).</p>

                        <form class="mt-fiche" @submit.prevent="enregistrer">
                            <label class="mt-champ">
                                <span>Nom de l’image</span>
                                <input type="text" v-model="fiche.title" :disabled="!droits.modifier">
                            </label>
                            <label class="mt-champ">
                                <span>Note pour vous <em>facultatif</em></span>
                                <textarea rows="2" v-model="fiche.description" :disabled="!droits.modifier" placeholder="Par exemple : photo de l’été 2026, à refaire avec la nouvelle piscine"></textarea>
                                <small>Pour retrouver l’image avec la recherche. Elle n’apparaît pas sur le site.</small>
                            </label>
                            <p class="mt-conseil mt-conseil--doux" v-if="ouverte.type === 'image'"><icon>info</icon>La description lue aux personnes aveugles se saisit à côté de l’image, dans chaque page (« Description de la photo »).</p>
                        </form>

                        <section class="mt-usages">
                            <h3><icon>link</icon>Utilisée sur</h3>
                            <p v-if="!usages" class="mt-discret">Vérification…</p>
                            <p v-else-if="!utilisations(ouverte).length" class="mt-discret">Pas encore utilisée sur le site.</p>
                            <a v-for="u in utilisations(ouverte) || []" :href="u.lien" class="mt-usage"><icon>description</icon>{{ u.libelle }}<icon class="mt-usage__fleche">arrow_forward</icon></a>
                        </section>

                        <div class="mt-panneau__outils">
                            <button type="button" class="kiss-button" @click="copier(ouverte)"><icon>content_copy</icon>Copier l’adresse</button>
                            <a class="kiss-button" :href="adresse(ouverte)" download><icon>download</icon>Télécharger</a>
                            <button type="button" class="kiss-button" @click="remplacer" v-if="droits.envoyer"><icon>sync</icon>Remplacer le fichier</button>
                            <button type="button" class="kiss-button mt-danger" @click="supprimer([ouverte._id])" v-if="droits.supprimer"><icon>delete</icon>Supprimer</button>
                            <input ref="remplacement" type="file" hidden @change="envoyerRemplacement">
                        </div>
                    </div>

                    <footer class="mt-panneau__pied" v-if="droits.modifier">
                        <button type="button" class="kiss-button" @click="fermer">Fermer</button>
                        <button type="button" class="kiss-button kiss-button-primary" :disabled="!modifiee() || enregistrement" @click="enregistrer"><icon>check</icon>Enregistrer</button>
                    </footer>
                </aside>
            </transition>
        </teleport>
    </div>
    `
};
