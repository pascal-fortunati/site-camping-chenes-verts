<?php

/**
 * Création d'un espace : nom, groupe, compte administrateur et stockage des données.
 *
 * @var list<string> $groups les groupes déjà utilisés (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$mongodb = extension_loaded('mongodb');
$serveur = ($this->dataStorage->type === 'mongodb' && $mongodb) ? (string) $this['database/server'] : '';
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon>{{ cree ? 'check' : 'add_circle' }}</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/system/spaces') ?>">Espaces</a>
                    </nav>
                    <h1>{{ cree ? 'Espace créé' : (space.name || 'Nouvel espace') }}</h1>
                    <p class="fiche-tete__info">{{ cree ? 'Il s’ouvre dans un nouvel onglet ; connectez-vous avec le compte indiqué.' : 'Une administration à part, avec son propre compte administrateur.' }}</p>
                </div>
            </section>

            <section class="tdb-carte espace__cree" v-if="cree">
                <p>L’espace <b>{{ space.name }}</b> est prêt : <code>{{ space.url }}</code></p>
                <div class="infos__boutons">
                    <a class="kiss-button kiss-button-primary" :href="space.url" target="_blank" rel="noopener noreferrer"><icon>open_in_new</icon>Ouvrir l’espace</a>
                    <a class="kiss-button" href="<?= $this->routeUrl('/system/spaces') ?>"><icon>list</icon>Tous les espaces</a>
                </div>
            </section>

            <div class="compte__grille" v-else :class="{'fiche-grille--enregistrement': enregistrement}">
                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>workspaces</icon>L’espace</h2>
                        <label class="mt-champ"><span>Nom <em>il fait partie de l’adresse</em></span><input type="text" v-model="space.name" required pattern="[A-Za-z0-9_-]+" autocapitalize="off" spellcheck="false" placeholder="autre-site"></label>
                        <label class="mt-champ"><span>Groupe <em>facultatif</em></span><input type="text" v-model="space.options.group"></label>
                        <div class="gc__groupes" v-if="groupes.length">
                            <button type="button" class="kiss-button kiss-button-small" v-for="g in groupes" :key="g" @click="space.options.group = g">{{ g }}</button>
                        </div>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>shield_person</icon>Compte administrateur</h2>
                        <label class="mt-champ"><span>Identifiant</span><input type="text" v-model="space.options.user" required autocapitalize="off" spellcheck="false" autocomplete="off"></label>
                        <label class="mt-champ">
                            <span>Mot de passe</span>
                            <span class="compte__mdp">
                                <input :type="voir ? 'text' : 'password'" v-model="space.options.password" required autocomplete="new-password">
                                <button type="button" class="ls-rond" @click="voir = !voir" :aria-label="voir ? 'Masquer' : 'Afficher'"><icon>{{ voir ? 'visibility_off' : 'visibility' }}</icon></button>
                            </span>
                        </label>
                    </section>
                </div>

                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>database</icon>Stockage des données</h2>
                        <div class="fiche-choix" role="radiogroup" aria-label="Stockage">
                            <button type="button" role="radio" :aria-checked="stockage === 'mongolite' ? 'true' : 'false'" :class="{'fiche-choix--actif': stockage === 'mongolite'}" @click="space.options.datastorage.type = 'mongolite'"><icon>description</icon>Fichier (Mongolite)</button>
                            <?php if ($mongodb) : ?>
                                <button type="button" role="radio" :aria-checked="stockage === 'mongodb' ? 'true' : 'false'" :class="{'fiche-choix--actif': stockage === 'mongodb'}" @click="space.options.datastorage.type = 'mongodb'"><icon>dns</icon>MongoDB</button>
                            <?php endif ?>
                        </div>
                        <p class="fiche-aide" v-if="stockage === 'mongolite'">Les données sont gardées dans des fichiers sur le serveur, comme pour ce site. Rien d’autre à régler.</p>
                        <template v-if="stockage === 'mongodb'">
                            <label class="mt-champ"><span>Serveur</span><input type="text" v-model="space.options.datastorage.server" placeholder="mongodb://…" required></label>
                            <label class="mt-champ"><span>Base de données</span><input type="text" v-model="space.options.datastorage.database" required></label>
                            <button type="button" class="kiss-button" :disabled="test || !space.options.datastorage.server || !space.options.datastorage.database" @click="tester"><icon>{{ test ? 'hourglass_top' : 'lan' }}</icon>Tester la connexion</button>
                        </template>
                    </section>
                </div>
            </div>
        </kiss-container>

        <app-actionbar v-if="!cree">
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat fiche-barre__etat--modifie"><icon>edit_note</icon>Pas encore créé</span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $this->routeUrl('/system/spaces') ?>">Annuler</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="!pret" @click="creer"><icon>add</icon>Créer l’espace</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        export default {
            data() {
                return {
                    space: { name: '', options: { group: null, user: '', password: '', datastorage: { type: 'mongolite', server: <?= json_encode($serveur) ?>, database: '' } } },
                    groupes: <?= json_encode(array_values($groups), JSON_UNESCAPED_UNICODE) ?>,
                    voir: false, enregistrement: false, test: false, cree: false
                };
            },

            computed: {
                stockage() {
                    return this.space.options.datastorage.type;
                },
                pret() {
                    const o = this.space.options;
                    if (this.enregistrement || !/^[A-Za-z0-9_-]+$/.test(this.space.name) || !o.user || !o.password) return false;
                    return o.datastorage.type !== 'mongodb' || !!(o.datastorage.server && o.datastorage.database);
                }
            },

            methods: {
                creer() {
                    this.enregistrement = true;
                    this.$request('/system/spaces/create', { space: this.space }).then((r) => {
                        this.space = r.space;
                        this.cree = true;
                    }).catch((r) => App.ui.notify((r && r.error) || 'La création a échoué.', 'error'))
                        .finally(() => { this.enregistrement = false; });
                },

                tester() {
                    this.test = true;
                    this.$request('/system/spaces/checkDatabaseConnection', { options: this.space.options.datastorage })
                        .then(() => App.ui.notify('Connexion réussie.'))
                        .catch((r) => App.ui.notify((r && r.error) || 'La connexion a échoué.', 'error'))
                        .finally(() => { this.test = false; });
                }
            }
        };
    </script>

</vue-view>
