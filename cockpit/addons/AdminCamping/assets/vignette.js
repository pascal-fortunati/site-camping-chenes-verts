/**
 * La vignette d'une image, partagée par la médiathèque et le choix d'une image.
 */

// Cockpit en donne l'adresse (re=0) plutôt que d'y rediriger, car sa redirection ajoute /admin
// devant une adresse de médias relative (/medias, en production). Chaque adresse n'est demandée qu'une fois.
const adresses = new Map();
export default {
    props: { asset: Object, largeur: { type: Number, default: 480 } },
    data: () => ({ src: null }),
    watch: { 'asset._modified'() { this.charger(); } },
    mounted() { this.charger(); },
    methods: {
        charger() {
            const a = this.asset;
            const cle = `${a._id}-${this.largeur}-${a._modified}`;
            if (!adresses.has(cle)) {
                adresses.set(cle, App.request(`/assets/thumbnail/${a._id}?m=bestFit&mime=auto&w=${this.largeur}&h=${Math.round(this.largeur * 0.75)}&q=70&t=${a._modified}&re=0`)
                    .then((r) => (r && r.url) || null).catch(() => null));
            }
            adresses.get(cle).then((src) => { this.src = src; });
        }
    },
    template: '<img v-if="src" :src="src" alt="" decoding="async" class="mt-vignette"><span v-else class="mt-vignette mt-vignette--attente"></span>'
};
