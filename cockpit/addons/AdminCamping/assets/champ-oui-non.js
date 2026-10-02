/**
 * Le champ oui / non (afficher les heures, le lien de réservation…) : un interrupteur aux couleurs du site, avec
 * « Oui » ou « Non » écrit à côté, au lieu du petit interrupteur de Cockpit. Clavier : Espace ou Entrée.
 * La valeur reste celle de Cockpit (vrai / faux, ou 1 / 0 en mode nombre).
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-boolean.js') + version);

export default {
    ...origine,

    methods: {
        ...origine.methods,

        basculer() {
            const actif = !!this.val;
            this.val = this.mode === 'number' ? (actif ? 0 : 1) : !actif;
            this.update();
        }
    },

    template: /*html*/`
        <div field="boolean" class="oui-non">
            <button type="button" role="switch" class="oui-non__bouton" :class="{'oui-non__bouton--oui': !!val}" :aria-checked="val ? 'true' : 'false'" @click="basculer" :id="uuid">
                <span class="oui-non__piste"><span class="oui-non__bouton-rond"><icon>{{ val ? 'check' : 'close' }}</icon></span></span>
                <span class="oui-non__texte">{{ val ? 'Oui' : 'Non' }}</span>
            </button>
            <label :for="uuid" class="oui-non__libelle" v-if="label">{{ label }}</label>
        </div>
    `
};
