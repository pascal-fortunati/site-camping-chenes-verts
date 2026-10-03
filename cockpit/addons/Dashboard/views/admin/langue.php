<?php

/**
 * Fiche d'une langue : code, nom affiché et activation.
 *
 * @var array $locale la langue (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$suggestions = ['en' => 'English', 'de' => 'Deutsch', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands', 'pt' => 'Português', 'fr' => 'Français'];
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon>translate</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/system/locales') ?>">Langues</a>
                    </nav>
                    <h1>{{ locale.name || (locale._id ? locale.i18n : 'Nouvelle langue') }}</h1>
                    <p class="fiche-tete__info">Le code est celui que le site demande : « en » pour l’anglais, « de » pour l’allemand.</p>
                </div>
            </section>

            <div class="compte__grille">
                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>label</icon>La langue</h2>
                        <label class="mt-champ">
                            <span>Code</span>
                            <input type="text" v-model="locale.i18n" :disabled="!!locale._id" pattern="[a-zA-Z0-9_]+" required autocapitalize="off" spellcheck="false">
                            <small v-if="locale._id">Il ne change plus : les textes traduits y sont rattachés.</small>
                        </label>
                        <div class="langue__suggestions" v-if="!locale._id">
                            <button type="button" class="kiss-button kiss-button-small" v-for="(nom, code) in suggestions" :key="code" @click="locale.i18n = code; locale.name = nom">{{ nom }}</button>
                        </div>
                        <label class="mt-champ"><span>Nom affiché</span><input type="text" v-model="locale.name" required></label>
                    </section>
                </div>
                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>toggle_on</icon>Activation</h2>
                        <button type="button" role="switch" class="compte__interrupteur" :aria-checked="locale.enabled ? 'true' : 'false'" @click="locale.enabled = !locale.enabled">
                            <span class="oui-non__piste" :class="{'oui-non__piste--oui': locale.enabled}"><span class="oui-non__bouton-rond"><icon>{{ locale.enabled ? 'check' : 'close' }}</icon></span></span>
                            <span class="compte__interrupteur-texte"><b>Langue active</b><small>Désactivée, elle n’est plus proposée à la saisie ; ses textes restent enregistrés.</small></span>
                        </button>
                    </section>
                </div>
            </div>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat" :class="{'fiche-barre__etat--modifie': modifie}">
                        <icon>{{ modifie ? 'edit_note' : 'check_circle' }}</icon>{{ modifie ? 'Modifications non enregistrées' : 'Tout est enregistré' }}
                    </span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $this->routeUrl('/system/locales') ?>">{{ locale._id ? 'Fermer' : 'Annuler' }}</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="enregistrement || !modifie || !locale.i18n || !locale.name" @click="enregistrer"><icon>check</icon>{{ locale._id ? 'Enregistrer' : 'Ajouter la langue' }}</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        export default {
            data() {
                const locale = <?= json_encode($locale) ?>;
                return { locale, depart: JSON.stringify(locale), suggestions: <?= json_encode($suggestions, JSON_UNESCAPED_UNICODE) ?>, enregistrement: false };
            },

            computed: {
                modifie() {
                    return !this.locale._id || JSON.stringify(this.locale) !== this.depart;
                }
            },

            methods: {
                enregistrer() {
                    const nouveau = !this.locale._id;
                    this.enregistrement = true;
                    this.$request('/system/locales/save', { locale: this.locale }).then((locale) => {
                        if (nouveau) {
                            location.href = this.$routeUrl('/system/locales');
                            return;
                        }
                        this.locale = locale;
                        this.depart = JSON.stringify(locale);
                        App.ui.notify('Langue enregistrée.');
                    }).catch((r) => App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error'))
                        .finally(() => { this.enregistrement = false; });
                }
            }
        };
    </script>

</vue-view>
