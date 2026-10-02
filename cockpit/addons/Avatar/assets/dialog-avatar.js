/**
 * « Mon avatar »: send a photo or pick one among the assets, then save; or go back to the initials.
 * The photo is reduced in the browser before sending (at most 1024 px), and rebuilt by the server.
 */

export default {

    _meta: { size: 'small' },

    props: {
        actuel: { default: null },
    },

    data() {
        return {
            apercu: this.actuel,
            image: null,
            asset: null,
            envoi: false,
            erreur: null,
        };
    },

    computed: {
        initiale() {
            return ((App.user && App.user.name) || '?').trim().charAt(0).toUpperCase();
        },
        aChange() {
            return !!(this.image || this.asset);
        },
    },

    template: /*html*/`
        <div>
            <div class="kiss-size-4 kiss-text-bold kiss-margin kiss-flex kiss-flex-middle">
                <icon class="kiss-margin-small-end kiss-size-3" size="larger">face</icon>
                <div class="kiss-flex-1">Mon avatar</div>
            </div>

            <div class="avatar-dialogue__apercu">
                <img :src="apercu" alt="" v-if="apercu">
                <span v-else>{{ initiale }}</span>
            </div>

            <p class="kiss-color-muted kiss-size-small kiss-align-center">
                La photo est recadrée en carré, au centre. Visible par les autres comptes de l’administration,
                et par vous seul sur le site.
            </p>

            <div class="avatar-dialogue__choix">
                <label class="kiss-button kiss-width-1-1">
                    <icon class="kiss-margin-xsmall-end">upload</icon> Envoyer une photo
                    <input type="file" accept="image/png,image/jpeg,image/webp" hidden @change="choisirFichier">
                </label>
                <button type="button" class="kiss-button kiss-width-1-1" @click="choisirImage">
                    <icon class="kiss-margin-xsmall-end">photo_library</icon> Choisir dans les images
                </button>
            </div>

            <p class="kiss-color-danger kiss-size-small kiss-align-center" v-if="erreur">{{ erreur }}</p>

            <div class="avatar-dialogue__actions">
                <button type="button" class="kiss-button" @click="$close()">{{ t('Close') }}</button>
                <button type="button" class="kiss-button kiss-button-primary" @click="enregistrer" :disabled="!aChange || envoi">
                    {{ envoi ? t('Saving...') : t('Save') }}
                </button>
            </div>

            <p class="kiss-align-center kiss-margin-small" v-if="actuel">
                <a class="kiss-link-muted kiss-size-small" href="#" @click.prevent="retirer">Revenir aux initiales</a>
            </p>
        </div>
    `,

    methods: {

        choisirFichier(evt) {
            const fichier = evt.target.files && evt.target.files[0];
            if (!fichier) return;
            this.erreur = null;

            const lecteur = new FileReader();
            lecteur.onload = () => {
                const img = new Image();
                img.onload = () => {
                    const k = Math.min(1, 1024 / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * k);
                    canvas.height = Math.round(img.height * k);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    this.image = canvas.toDataURL('image/jpeg', 0.9);
                    this.asset = null;
                    this.apercu = this.image;
                };
                img.onerror = () => { this.erreur = 'Cette image ne peut pas être lue.'; };
                img.src = lecteur.result;
            };
            lecteur.readAsDataURL(fichier);
        },

        choisirImage() {
            App.utils.selectAsset((asset) => {
                const choisi = Array.isArray(asset) ? asset[0] : asset;
                if (!choisi || choisi.type !== 'image') return;
                this.asset = choisi._id;
                this.image = null;
                this.apercu = App.route(`/assets/thumbnail/${choisi._id}?m=bestFit&w=320&h=320&q=80&mime=auto`);
            }, { type: 'image' });
        },

        enregistrer() {
            this.envoi = true;
            this.erreur = null;
            App.request('/avatar/enregistrer', this.image ? { image: this.image } : { asset: this.asset })
                .then(() => {
                    App.ui.notify('Avatar enregistré !');
                    location.reload();
                })
                .catch((res) => {
                    this.erreur = (res && res.erreur) || 'L’enregistrement n’a pas abouti.';
                    this.envoi = false;
                });
        },

        retirer() {
            this.envoi = true;
            App.request('/avatar/retirer', {})
                .then(() => location.reload())
                .catch(() => { this.envoi = false; this.erreur = 'L’action n’a pas abouti.'; });
        },
    },
};
