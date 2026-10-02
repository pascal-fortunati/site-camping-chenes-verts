/**
 * Le choix d'une image depuis un champ, à la place de la fenêtre de Cockpit (assets-picker) : les mêmes réglages
 * (filter, multiple) et le même retour (onSelect), mais une grille de vignettes, la recherche, les dossiers et
 * l'envoi depuis l'ordinateur sans quitter la fenêtre — l'image envoyée est aussitôt choisie.
 */

// Les fichiers voisins sont chargés à la même version que celui-ci (?v=…) : le serveur les garde un an en cache.
const version = new URL(import.meta.url).search;
const { default: vignette } = await import(`./vignette.js${version}`);

const sansAccents = (texte) => String(texte || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const taille = (octets) => !octets ? '' : (octets < 1048576 ? Math.max(1, Math.round(octets / 1024)) + ' ko' : (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo');

export default {

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
