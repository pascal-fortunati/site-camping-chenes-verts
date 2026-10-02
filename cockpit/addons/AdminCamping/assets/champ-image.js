/**
 * Le champ image d'une fiche : une carte compacte (aperçu, nom, taille, Changer / Voir dans les médias / Retirer)
 * au lieu de l'image perdue dans un damier ; vide, une zone « Choisir une image » où l'on peut aussi déposer une
 * photo depuis l'ordinateur. Le fonctionnement de Cockpit (field-asset.js) est repris : seul l'affichage change.
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('assets:assets/vue-components/field-asset.js') + version);
const { default: vignette } = await import(`./vignette.js${version}`);

const taille = (octets) => !octets ? '' : (octets < 1048576 ? Math.max(1, Math.round(octets / 1024)) + ' ko' : (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo');

export default {
    ...origine,

    components: { ...(origine.components || {}), mtVignette: vignette },

    data() {
        return { ...origine.data.call(this), survol: false, envoi: false };
    },

    computed: {
        ...(origine.computed || {}),
        details() {
            if (!this.val) return '';
            return [this.val.width ? `${this.val.width} × ${this.val.height}` : (this.val.mime || ''), taille(this.val.size)].filter(Boolean).join(' · ');
        },
        adresse() {
            return this.val ? this.$baseUrl('#uploads:' + this.val.path) : '';
        }
    },

    methods: {
        ...origine.methods,

        retirer() {
            this.val = null;
        },

        deposer(e) {
            this.survol = false;
            const fichier = e.dataTransfer && e.dataTransfer.files[0];
            if (!fichier) return;
            const donnees = new FormData();
            donnees.append('files[]', fichier);
            this.envoi = true;
            this.$request('/assets/upload', donnees).then((r) => {
                if (r && r.assets && r.assets[0]) {
                    this.val = r.assets[0];
                    App.ui.notify('Image envoyée et choisie.');
                } else {
                    App.ui.notify('Ce fichier a été refusé.', 'error');
                }
            }).catch((r) => App.ui.notify((r && r.error) || 'L’envoi a échoué.', 'error'))
              .finally(() => { this.envoi = false; });
        }
    },

    template: /*html*/`
        <div field="asset" class="champ-image">

            <div class="champ-image__vide" :class="{'champ-image__vide--survol': survol}" v-if="!val" role="button" tabindex="0"
                @click="pickAsset()" @keydown.enter.prevent="pickAsset()" @keydown.space.prevent="pickAsset()"
                @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposer">
                <icon>{{ envoi ? 'hourglass_top' : 'add_photo_alternate' }}</icon>
                <b>{{ envoi ? 'Envoi en cours…' : 'Choisir une image' }}</b>
                <small>ou glissez une photo ici depuis votre ordinateur</small>
            </div>

            <div class="champ-image__plein" v-else @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposer" :class="{'champ-image__plein--survol': survol}">
                <a class="champ-image__apercu" :href="adresse" target="_blank" rel="noopener" title="Voir en grand">
                    <mt-vignette v-if="val.type === 'image'" :asset="val" :largeur="320"></mt-vignette>
                    <icon v-else>description</icon>
                </a>
                <div class="champ-image__texte">
                    <b :title="val.title">{{ val.title || 'Sans nom' }}</b>
                    <small>{{ details }}</small>
                    <div class="champ-image__boutons">
                        <button type="button" class="kiss-button kiss-button-small" @click="pickAsset()"><icon>swap_horiz</icon>Changer</button>
                        <a class="kiss-button kiss-button-small" :href="$routeUrl('/assets') + '?image=' + val._id" target="_blank" rel="noopener"><icon>perm_media</icon>Voir dans les médias</a>
                        <button type="button" class="kiss-button kiss-button-small" v-if="meta" @click="editMeta()"><icon>tune</icon>Détails</button>
                        <button type="button" class="kiss-button kiss-button-small mt-danger" @click="retirer()"><icon>close</icon>Retirer</button>
                    </div>
                </div>
            </div>
        </div>
    `
};
