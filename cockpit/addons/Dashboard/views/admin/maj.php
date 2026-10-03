<?php

/**
 * Mise à jour de Cockpit : la version installée, la dernière publiée, et la mise à jour après accord explicite.
 *
 * @var array $meta la dernière version publiée (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$script = is_file(dirname(__DIR__, 6).'/bin/install-cockpit.php');
$messages = ['Your PHP version is too low' => 'La version de PHP du serveur est trop ancienne pour cette version.'];
$meta['notices'] = array_map(static fn (string $n): string => $messages[$n] ?? $n, $meta['notices'] ?? []);
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon>system_update</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane"><a href="<?= $this->routeUrl('/system') ?>">Réglages</a></nav>
                    <h1>Mise à jour de Cockpit</h1>
                    <p class="fiche-tete__info">{{ meta.isNewVersionAvailable ? 'Une nouvelle version est disponible.' : 'Cockpit est à jour.' }}</p>
                </div>
                <span class="fiche-pastille" :class="meta.isNewVersionAvailable ? 'fiche-pastille--attention' : 'fiche-pastille--ligne'"><span class="ls-etat__point"></span>{{ meta.isNewVersionAvailable ? 'Mise à jour disponible' : 'À jour' }}</span>
            </section>

            <?php if ($script) : ?>
                <p class="modeles__note maj__note"><icon>info</icon><span>Ce site installe Cockpit avec <code>bin/install-cockpit.php</code>, qui fixe la version et réinstalle les extensions : mettez plutôt à jour par ce script. Une mise à jour faite ici serait remplacée à la prochaine installation.</span></p>
            <?php endif ?>

            <div class="compte__grille">
                <div class="compte__colonne">
                    <section class="fiche-carte maj__version">
                        <h2><icon>deployed_code</icon>Version installée</h2>
                        <p class="maj__numero"><?= $this->escape(APP_VERSION) ?></p>
                    </section>
                    <section class="fiche-carte maj__version">
                        <h2><icon>new_releases</icon>Dernière version publiée</h2>
                        <p class="maj__numero">{{ meta.version }}</p>
                        <p class="fiche-aide" v-if="meta.date">Publiée le {{ new Date(meta.date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                    </section>
                </div>

                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>upgrade</icon>Mettre à jour</h2>
                        <div class="fiche-choix fiche-choix--liste" role="radiogroup" aria-label="Version visée">
                            <button type="button" role="radio" v-if="meta.isNewVersionAvailable" :aria-checked="cible === 'master' ? 'true' : 'false'" :class="{'fiche-choix--actif': cible === 'master'}" @click="cible = 'master'"><icon>verified</icon>Version {{ meta.version }}</button>
                            <button type="button" role="radio" :aria-checked="cible === 'develop' ? 'true' : 'false'" :class="{'fiche-choix--actif': cible === 'develop'}" @click="cible = 'develop'"><icon>science</icon>Version de développement</button>
                        </div>
                        <template v-if="cible">
                            <ul class="dlg-details maj__avis" v-if="meta.notices.length"><li v-for="n in meta.notices" :key="n">{{ n }}</li></ul>
                            <p class="fiche-aide">Une mise à jour peut rendre le site instable ou abîmer des données. Faites d’abord une sauvegarde des fichiers et de la base.</p>
                            <button type="button" role="switch" class="compte__interrupteur" :aria-checked="accord ? 'true' : 'false'" @click="accord = !accord">
                                <span class="oui-non__piste" :class="{'oui-non__piste--oui': accord}"><span class="oui-non__bouton-rond"><icon>{{ accord ? 'check' : 'close' }}</icon></span></span>
                                <span class="compte__interrupteur-texte"><b>J’ai compris les risques</b><small>Et j’ai une sauvegarde récente.</small></span>
                            </button>
                        </template>
                        <p class="fiche-aide" v-else>Choisissez la version visée.</p>
                    </section>
                </div>
            </div>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat"><icon>info</icon>Installée : <?= $this->escape(APP_VERSION) ?></span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $this->routeUrl('/system') ?>">Fermer</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="!cible || !accord" @click="mettreAJour"><icon>system_update</icon>Mettre à jour</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        export default {
            data() {
                return { meta: <?= json_encode($meta, JSON_UNESCAPED_UNICODE) ?>, cible: null, accord: false };
            },

            methods: {
                mettreAJour() {
                    if (!this.cible || !this.accord) return;
                    App.ui.block('Mise à jour en cours…');
                    this.$request('/updater/update', { version: this.cible }).then(() => location.reload()).catch((r) => {
                        App.ui.unblock();
                        App.ui.notify((r && r.error) || 'La mise à jour a échoué.', 'error');
                    });
                }
            }
        };
    </script>

</vue-view>
