/**
 * Le champ nombre (les places restantes…) : des boutons − et + de part et d'autre, au lieu des petites flèches du
 * navigateur. Les limites (min, max, pas) de Cockpit sont respectées ; on peut toujours taper le nombre.
 */

const version = new URL(import.meta.url).search;
const { default: origine } = await import(App.base('app:assets/vue-components/fields/field-number.js') + version);

const nombre = (v) => v !== null && v !== '' && !isNaN(Number(v));

export default {
    ...origine,

    computed: {
        ...(origine.computed || {}),
        pas() {
            return nombre(this.step) ? Number(this.step) : 1;
        },
        auMin() {
            return nombre(this.min) && nombre(this.val) && Number(this.val) <= Number(this.min);
        },
        auMax() {
            return nombre(this.max) && nombre(this.val) && Number(this.val) >= Number(this.max);
        }
    },

    methods: {
        ...origine.methods,

        ajouter(sens) {
            if (this.readonly) return;
            let v = (nombre(this.val) ? Number(this.val) : (nombre(this.min) ? Number(this.min) : 0)) + sens * this.pas;
            if (nombre(this.min)) v = Math.max(v, Number(this.min));
            if (nombre(this.max)) v = Math.min(v, Number(this.max));
            this.val = Math.round(v * 1000) / 1000;
            this.update();
        }
    },

    template: /*html*/`
        <div field="number" class="nombre">
            <button type="button" class="nombre__bouton" @click="ajouter(-1)" :disabled="readonly || auMin" aria-label="Moins un"><icon>remove</icon></button>
            <input type="number" class="kiss-input nombre__champ" v-model.number="val" @input="update" @change="check" :placeholder="placeholder" :max="max" :min="min" :step="step" :readonly="readonly" inputmode="decimal">
            <button type="button" class="nombre__bouton" @click="ajouter(1)" :disabled="readonly || auMax" aria-label="Plus un"><icon>add</icon></button>
        </div>
    `
};
