<?php

/**
 * Informations système : Cockpit, PHP, envoi d'e-mails, cache, extensions et variables d'environnement.
 *
 * @var list<string> $addons              les extensions chargées (Cockpit)
 * @var list<string> $supportedImageTypes les formats d'image que GD sait écrire
 * @var array        $license             la licence de Cockpit
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$oui = static fn (bool $v, string $si = 'Activé', string $non = 'Désactivé'): string => '<span class="ls-etat '.($v ? 'ls-etat--ligne' : 'ls-etat--brouillon').'"><span class="ls-etat__point"></span>'.($v ? $si : $non).'</span>';
$ligne = fn (string $libelle, string $valeur, bool $code = true): string => '<div><dt>'.$this->escape($libelle).'</dt><dd>'.($code ? '<code>'.$this->escape($valeur).'</code>' : $valeur).'</dd></div>';
$cockpit = [
    $ligne('Version', APP_VERSION),
    $ligne('Mode débogage', $oui((bool) $this->retrieve('debug')), false),
    $ligne('Stockage des données', (string) $this->dataStorage->type),
    $ligne('Adresse des médias', (string) $this->fileStorage->getURL('uploads://')),
    $ligne('Licence', (string) ($license['model'] ?? 'community')),
];
$php = [
    $ligne('Version', PHP_VERSION),
    $ligne('Interface', PHP_SAPI),
    $ligne('Mémoire', (string) ini_get('memory_limit')),
    $ligne('Taille d’envoi maximale', (string) ini_get('upload_max_filesize')),
    $ligne('Durée d’exécution maximale', ini_get('max_execution_time').' s'),
    $ligne('Opcache', $oui((bool) ini_get('opcache.enable')), false),
    $ligne('Formats d’image', implode(', ', $supportedImageTypes)),
    $ligne('Dossier temporaire', sys_get_temp_dir()),
];
$extensions = get_loaded_extensions();
sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="dashboard-tableau">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Informations système</h1>
                    <p class="tdb-accueil__phrase">Cockpit <?= $this->escape(APP_VERSION) ?> sur PHP <?= $this->escape(PHP_VERSION) ?>. Utile à la personne qui s’occupe du serveur.</p>
                    <div class="mt-bandeau__actions">
                        <button type="button" class="mt-bandeau__bouton" @click="viderCache"><icon>cleaning_services</icon>Vider le cache</button>
                    </div>
                </section>

                <div class="infos__grille">
                    <section class="tdb-carte">
                        <h2><icon>deployed_code</icon>Cockpit</h2>
                        <dl class="fiche-infos infos__liste"><?= implode('', $cockpit) ?></dl>
                    </section>

                    <section class="tdb-carte">
                        <h2><icon>code</icon>PHP</h2>
                        <dl class="fiche-infos infos__liste"><?= implode('', $php) ?></dl>
                    </section>

                    <section class="tdb-carte">
                        <h2><icon>mail</icon>Envoi d’e-mails</h2>
                        <p class="fiche-aide">Envoie un message d’essai pour vérifier les réglages du serveur d’envoi.</p>
                        <div class="infos__boutons">
                            <button type="button" class="kiss-button" v-for="compte in comptes" :key="compte" @click="essaiMail(compte)"><icon>send</icon>{{ comptes.length > 1 ? 'Essai : ' + compte : 'Envoyer un e-mail d’essai' }}</button>
                        </div>
                    </section>

                    <section class="tdb-carte">
                        <h2><icon>memory</icon>Cache</h2>
                        <p class="fiche-aide">Après une mise à jour de fichiers sur le serveur, vider le cache fait relire les modèles, les droits et le code.</p>
                        <div class="infos__boutons">
                            <button type="button" class="kiss-button" @click="viderCache"><icon>cleaning_services</icon>Vider le cache</button>
                            <button type="button" class="kiss-button" @click="viderOpcache" v-if="<?= ini_get('opcache.enable') ? 'true' : 'false' ?>"><icon>bolt</icon>Vider l’opcache</button>
                        </div>
                    </section>

                    <section class="tdb-carte infos__large">
                        <h2><icon>extension</icon>Extensions de Cockpit</h2>
                        <div class="infos__puces">
                            <?php foreach ($addons as $nom) : ?>
                                <span class="infos__puce"><icon>extension</icon><?= $this->escape(t(ucfirst($nom))) ?></span>
                            <?php endforeach ?>
                        </div>
                    </section>

                    <section class="tdb-carte infos__large">
                        <h2><icon>terminal</icon>Extensions de PHP</h2>
                        <p class="infos__texte"><code><?= $this->escape(implode(', ', $extensions)) ?></code></p>
                    </section>

                    <section class="tdb-carte infos__large">
                        <h2><icon>key</icon>Variables d’environnement</h2>
                        <template v-if="env === null">
                            <p class="fiche-aide">Elles peuvent contenir des mots de passe : votre mot de passe est demandé pour les afficher.</p>
                            <button type="button" class="kiss-button" @click="chargerEnv" :disabled="chargement"><icon>visibility</icon>Afficher</button>
                        </template>
                        <template v-else>
                            <label class="mt-recherche infos__filtre">
                                <icon aria-hidden="true">search</icon>
                                <input type="search" v-model="filtre" placeholder="Filtrer les variables" aria-label="Filtrer les variables">
                            </label>
                            <dl class="fiche-infos infos__liste">
                                <div v-for="(valeur, nom) in variables" :key="nom"><dt>{{ nom }}</dt><dd><code>{{ valeur }}</code></dd></div>
                            </dl>
                        </template>
                    </section>
                </div>
            </div>
        </template>

        <script type="module">
            export default {
                data() {
                    return { comptes: <?= json_encode($this->mailer->getAccounts()) ?>, env: null, filtre: '', chargement: false };
                },

                computed: {
                    variables() {
                        const f = this.filtre.toLowerCase();
                        return Object.fromEntries(Object.entries(this.env || {}).filter(([n, v]) => !f || (n + ' ' + v).toLowerCase().includes(f)));
                    }
                },

                methods: {
                    async viderCache() {
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        if (!await confirmer({ titre: 'Vider le cache ?', texte: 'Cockpit relira ses modèles, ses droits et ses réglages. Rien n’est supprimé.', bouton: 'Vider', icone: 'cleaning_services' })) return;
                        App.ui.block();
                        this.$request('/system/utils/flushCache', {}).then(() => App.ui.notify('Cache vidé.'))
                            .catch(() => App.ui.notify('Le cache n’a pas pu être vidé.', 'error')).finally(() => App.ui.unblock());
                    },

                    viderOpcache() {
                        App.ui.block();
                        this.$request('/system/utils/resetOpcache', {}).then(() => App.ui.notify('Opcache vidé.'))
                            .catch(() => App.ui.notify('L’opcache n’a pas pu être vidé.', 'error')).finally(() => App.ui.unblock());
                    },

                    chargerEnv() {
                        App.ui.prompt('Confirmez avec votre mot de passe', '', (password) => {
                            if (!password) return;
                            this.chargement = true;
                            this.$request('/system/utils/env', { password }).then((r) => { this.env = r.env || {}; })
                                .catch((r) => App.ui.notify((r && r.error) || 'Le chargement a échoué.', 'error'))
                                .finally(() => { this.chargement = false; });
                        }, { type: 'password', info: 'Par sécurité, l’affichage des variables demande votre mot de passe.' });
                    },

                    essaiMail(compte) {
                        App.ui.prompt('Adresse qui recevra l’essai', '', (email) => {
                            if (!email) return;
                            App.ui.block('Envoi…');
                            this.$request('/system/utils/testMailer', { email, account: compte }).then(() => App.ui.notify('E-mail d’essai envoyé.'))
                                .catch((r) => App.ui.notify((r && r.error) || 'L’envoi a échoué.', 'error')).finally(() => App.ui.unblock());
                        });
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
