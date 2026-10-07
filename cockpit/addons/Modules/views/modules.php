<?php

/**
 * Réglages › Modules : un interrupteur par addon, groupés par catégorie, aux couleurs du Dashboard.
 *
 * @var list<array<string, mixed>> $modules
 * @var bool                       $branche la configuration lit l'état des modules
 * @var string                     $fichier où l'état est écrit
 *
 * @package Modules
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */
?>
<?= $this->assets(['modules:assets/modules.css'], $this->retrieve('app.version')) ?>

<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls mod">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Modules</h1>
                    <p class="tdb-accueil__phrase">Ce que cette administration propose, site par site. Un module désactivé n’est plus chargé ; ses fichiers et ses données restent en place, le réactiver suffit.</p>
                    <ul class="mt-bandeau__chiffres">
                        <li><b>{{ actifs }}</b> actif{{ actifs > 1 ? 's' : '' }}</li>
                        <li><b>{{ modules.length - actifs }}</b> désactivé{{ modules.length - actifs > 1 ? 's' : '' }}</li>
                    </ul>
                </section>

                <section class="mod-alerte" v-if="!branche" role="alert">
                    <icon>warning</icon>
                    <div>
                        <b>Les interrupteurs n’ont pas encore d’effet</b>
                        <p>La configuration de Cockpit ne lit pas l’état des modules. Ajoutez à <code>config/config.php</code> :</p>
                        <pre>'modules.fichier'  => <?= $this->escape(var_export($fichier, true)) ?>,
'modules.disabled' => array_values(array_diff(
    (array) (is_file(<?= $this->escape(var_export($fichier, true)) ?>) ? json_decode((string) file_get_contents(<?= $this->escape(var_export($fichier, true)) ?>), true)['desactives'] ?? [] : []),
    ['Dashboard', 'Modules'],
)),</pre>
                    </div>
                </section>

                <template v-for="(liste, categorie) in parCategorie" :key="categorie">
                    <h2 class="mod-categorie">{{ categorie }}</h2>
                    <div class="ls-liste">
                        <article class="ls-ligne mod-ligne" v-for="m in liste" :key="m.nom" :class="{'mod-ligne--off': !m.actif}">
                            <span class="ls-ligne__principal">
                                <span class="ls-ligne__icone"><icon>{{ icone(m) }}</icon></span>
                                <span class="ls-ligne__texte">
                                    <b>{{ m.titre }} <small class="mod-version" v-if="m.version">{{ m.version }}</small></b>
                                    <small>{{ m.description || 'Pas de description : ajoutez un fichier addon.json au module.' }}</small>
                                    <small class="mod-details">
                                        <span v-if="m.auteur">Par <a v-if="m.lien" :href="m.lien" target="_blank" rel="noopener">{{ m.auteur }}</a><template v-else>{{ m.auteur }}</template></span>
                                        <span v-if="m.dependances.length">Nécessite {{ titres(m.dependances) }}</span>
                                        <span v-if="m.dependants.length">Utilisé par {{ titres(m.dependants) }}</span>
                                        <span class="mod-manque" v-if="m.manquantes.length">Manquant : {{ m.manquantes.join(', ') }}</span>
                                        <span class="mod-manque" v-if="m.actif && m.inactives.length">Désactivé mais nécessaire : {{ titres(m.inactives) }}</span>
                                    </small>
                                </span>
                            </span>
                            <span class="ls-etat" :class="m.actif ? 'ls-etat--ligne' : 'ls-etat--brouillon'"><span class="ls-etat__point"></span>{{ m.obligatoire ? 'Toujours actif' : (m.actif ? 'Actif' : 'Désactivé') }}</span>
                            <div class="ls-actions">
                                <span class="ls-rond" v-if="m.obligatoire" aria-hidden="true" title="Indispensable : ne peut pas être désactivé"><icon>lock</icon></span>
                                <button v-else type="button" role="switch" class="compte__interrupteur mod-interrupteur" :aria-checked="m.actif ? 'true' : 'false'"
                                        :aria-label="(m.actif ? 'Désactiver ' : 'Activer ') + m.titre" :disabled="enCours === m.nom" @click="basculer(m)">
                                    <span class="oui-non__piste" :class="{'oui-non__piste--oui': m.actif}"><span class="oui-non__bouton-rond"><icon>{{ m.actif ? 'check' : 'close' }}</icon></span></span>
                                </button>
                            </div>
                        </article>
                    </div>
                </template>

                <p class="mod-note"><icon>info</icon>Un changement prend effet à la page suivante. Les modules propres à Cockpit (contenu, médias, système) ne sont pas listés : ils ne se désactivent pas.</p>
            </div>
        </template>

        <script type="module">
            const ICONES = { Administration: 'admin_panel_settings', Site: 'language', Contenu: 'article', Commerce: 'storefront' };

            export default {
                data() {
                    return {
                        modules: <?= json_encode($modules, JSON_UNESCAPED_UNICODE) ?>,
                        branche: <?= json_encode($branche) ?>,
                        enCours: null
                    };
                },

                computed: {
                    actifs() {
                        return this.modules.filter((m) => m.actif).length;
                    },
                    parCategorie() {
                        const groupes = {};
                        this.modules.forEach((m) => { (groupes[m.categorie] = groupes[m.categorie] || []).push(m); });
                        return groupes;
                    }
                },

                methods: {
                    icone(m) {
                        return ICONES[m.categorie] || 'extension';
                    },

                    titres(noms) {
                        return noms.map((n) => (this.modules.find((m) => m.nom === n) || { titre: n }).titre).join(', ');
                    },

                    async basculer(m) {
                        const actif = !m.actif;
                        if (!actif) {
                            const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                            const oui = await confirmer({
                                titre: `Désactiver « ${m.titre} » ?`,
                                texte: 'Le module ne sera plus chargé : ses écrans et ses effets disparaissent. Ses fichiers et ses données restent en place.',
                                bouton: 'Désactiver', danger: true, icone: 'toggle_off'
                            });
                            if (!oui) return;
                        }
                        this.enCours = m.nom;
                        this.$request('/modules/changer', { nom: m.nom, actif }).then((r) => {
                            this.modules = r.modules;
                            App.ui.notify(`« ${m.titre} » ${actif ? 'activé' : 'désactivé'}. Rechargement…`);
                            // La barre latérale et les écrans dépendent des modules chargés : la page est rechargée.
                            setTimeout(() => location.reload(), 900);
                        }).catch((e) => {
                            App.ui.notify((e && e.error) || 'Le changement a échoué.', 'error');
                        }).finally(() => { this.enCours = null; });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
