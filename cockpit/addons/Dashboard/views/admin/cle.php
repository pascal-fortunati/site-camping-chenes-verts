<?php

/**
 * Fiche d'une clé d'API (ou de l'accès sans clé) : nom, rôle et valeur de la clé.
 *
 * @var array $key la clé (Cockpit)
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$roles = array_column($this->helper('acl')->roles(), 'name', 'appid');
unset($roles['admin']);
$public = ($key['key'] ?? '') === 'public';
$nouveau = empty($key['_id']) && !$public;
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon><?= $public ? 'public' : 'key' ?></icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/system/api') ?>">API et sécurité</a>
                    </nav>
                    <h1><?= $public ? 'Accès sans clé' : '{{ key.name || '.json_encode($nouveau ? 'Nouvelle clé' : 'Clé d’API', JSON_UNESCAPED_UNICODE).' }}' ?></h1>
                    <p class="fiche-tete__info"><?= $public ? 'Ce que peut faire une requête qui ne donne aucune clé.' : 'Donnez-la au programme qui doit lire ou écrire le contenu, jamais à une personne.' ?></p>
                </div>
            </section>

            <div class="compte__grille">
                <div class="compte__colonne">
                    <?php if (!$public) : ?>
                        <section class="fiche-carte">
                            <h2><icon>label</icon>La clé</h2>
                            <label class="mt-champ"><span>Nom</span><input type="text" v-model="key.name" required placeholder="Par exemple : Application mobile"></label>
                            <div class="mt-champ compte__cle">
                                <span>Valeur</span>
                                <code v-if="key.key">{{ key.key }}</code>
                                <small v-else>Pas encore créée.</small>
                                <div class="compte__cle-actions">
                                    <button type="button" class="kiss-button kiss-button-small" @click="creer"><icon>autorenew</icon>{{ key.key ? 'Remplacer' : 'Créer la clé' }}</button>
                                    <button type="button" class="kiss-button kiss-button-small" v-if="key.key" @click="App.utils.copyText(key.key, () => App.ui.notify('Clé copiée.'))"><icon>content_copy</icon>Copier</button>
                                </div>
                                <small v-if="key._id && key.key !== depart.key" class="compte__erreur">Après l’enregistrement, l’ancienne valeur ne fonctionnera plus.</small>
                            </div>
                        </section>
                    <?php endif ?>
                </div>
                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>badge</icon>Rôle</h2>
                        <div class="fiche-choix fiche-choix--liste" role="radiogroup" aria-label="Rôle">
                            <button type="button" role="radio" :aria-checked="!key.role ? 'true' : 'false'" :class="{'fiche-choix--actif': !key.role}" @click="key.role = null"><icon>block</icon>Aucun</button>
                            <button type="button" role="radio" v-for="(nom, cle) in roles" :key="cle" :aria-checked="key.role === cle ? 'true' : 'false'" :class="{'fiche-choix--actif': key.role === cle}" @click="key.role = cle"><icon>badge</icon>{{ nom }}</button>
                        </div>
                        <p class="fiche-aide">Sans rôle, la clé ne permet rien. Les droits se règlent dans <a :href="$routeUrl('/system/users/roles')">Rôles et droits</a>.</p>
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
                    <a class="kiss-button" href="<?= $this->routeUrl('/system/api') ?>">{{ key._id ? 'Fermer' : 'Annuler' }}</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="enregistrement || !modifie || !key.key" @click="enregistrer"><icon>check</icon>{{ key._id || <?= $public ? 'true' : 'false' ?> ? 'Enregistrer' : 'Créer la clé' }}</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        export default {
            data() {
                const key = <?= json_encode($key) ?>;
                return { key, depart: JSON.parse(JSON.stringify(key)), roles: <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>, enregistrement: false };
            },

            computed: {
                modifie() {
                    return !this.key._id || JSON.stringify(this.key) !== JSON.stringify(this.depart);
                }
            },

            methods: {
                creer() {
                    this.$request('/utils/generateToken').then((r) => { this.key.key = 'API-' + r.token; });
                },

                enregistrer() {
                    const nouveau = !this.key._id;
                    this.enregistrement = true;
                    this.$request('/system/api/save', { key: this.key }).then((key) => {
                        if (nouveau && key.key !== 'public') {
                            location.href = this.$routeUrl('/system/api/key/' + key._id);
                            return;
                        }
                        this.key = key;
                        this.depart = JSON.parse(JSON.stringify(key));
                        App.ui.notify('Clé enregistrée.');
                    }).catch((r) => App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error'))
                        .finally(() => { this.enregistrement = false; });
                }
            }
        };
    </script>

</vue-view>
