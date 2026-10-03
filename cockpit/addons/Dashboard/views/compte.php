<?php

/**
 * Fiche d'un compte : le sien (Mon compte), ou celui d'un autre utilisateur pour qui gère les comptes (création
 * comprise) : informations, accès, mot de passe, vérification en deux étapes.
 *
 * @var array $user          le compte affiché (Cockpit)
 * @var bool  $isAccountView vrai pour son propre compte
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

if (!isset($user['twofa'])) {
    $user['twofa'] = ['enabled' => false, 'secret' => $this->helper('twfa')->createSecret(160)];
}
if (!isset($user['_meta']) || (is_array($user['_meta']) && array_is_list($user['_meta']))) {
    $user['_meta'] = new ArrayObject([]);
}
// Les rôles des clés d'API (site public, formulaire) ne sont pas proposés pour un compte de personne.
$api = array_column($this->dataStorage->find('system/api_keys', ['fields' => ['role' => 1]])->toArray(), 'role');
$roles = array_filter(['admin' => 'Administrateur'] + array_column($this->helper('acl')->roles(), 'name', 'appid'),
    static fn (string $cle): bool => $cle !== 'public' && (!in_array($cle, $api, true) || $cle === ($user['role'] ?? '')), ARRAY_FILTER_USE_KEY);
$moi = ($user['_id'] ?? null) === ($this->helper('auth')->getUser()['_id'] ?? '');
$gerer = !$isAccountView && $this->helper('acl')->isAllowed('app/users/manage');
$nouveau = empty($user['_id']);
$photo = str_starts_with((string) ($user['avatar'] ?? ''), '/avatars/') ? rtrim((string) $this->fileStorage->getURL('uploads://'), '/').$user['avatar'] : '';
$avecAvatar = $moi && isset($this['modules']['avatar']);
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche compte">

            <section class="fiche-tete">
                <span class="compte__photo">
                    <?php if ($nouveau) : ?>
                        <span class="fiche-tete__icone"><icon>person_add</icon></span>
                    <?php elseif ($photo && !$moi) : ?>
                        <img src="<?= $this->escape($photo) ?>" alt="" width="64" height="64">
                    <?php else : ?>
                        <app-avatar size="64" :name="user.name || '?'"></app-avatar>
                    <?php endif ?>
                </span>
                <div class="fiche-tete__texte">
                    <?php if ($gerer) : ?>
                        <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                            <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/system/users') ?>">Utilisateurs</a>
                        </nav>
                    <?php endif ?>
                    <h1>{{ user.name || <?= json_encode($nouveau ? 'Nouvel utilisateur' : 'Mon compte', JSON_UNESCAPED_UNICODE) ?> }}</h1>
                    <p class="fiche-tete__info" v-if="user.email">{{ user.email }} · {{ roles[user.role] || user.role }}</p>
                    <p class="fiche-tete__info" v-else>Remplissez au moins l’identifiant, l’adresse e-mail et le mot de passe.</p>
                </div>
                <?php if ($avecAvatar) : ?>
                    <button type="button" class="kiss-button" @click="changerPhoto"><icon>face</icon>Changer ma photo</button>
                <?php endif ?>
            </section>

            <form class="compte__grille" @submit.prevent="enregistrer" autocomplete="off">
                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>badge</icon><?= $isAccountView ? 'Vos informations' : 'Informations' ?></h2>
                        <label class="mt-champ"><span>Nom affiché</span><input type="text" v-model="user.name" required></label>
                        <label class="mt-champ"><span>Identifiant de connexion</span><input type="text" v-model="user.user" required autocapitalize="off" spellcheck="false"></label>
                        <label class="mt-champ"><span>Adresse e-mail</span><input type="email" v-model="user.email" required><small>Pour recevoir un lien en cas d’oubli du mot de passe.</small></label>
                    </section>

                    <?php if ($gerer && !$moi) : ?>
                        <section class="fiche-carte">
                            <h2><icon>admin_panel_settings</icon>Accès</h2>
                            <div class="mt-champ">
                                <span>Rôle</span>
                                <div class="fiche-choix fiche-choix--liste" role="radiogroup" aria-label="Rôle">
                                    <button type="button" role="radio" v-for="(nom, cle) in roles" :key="cle" :aria-checked="user.role === cle ? 'true' : 'false'" :class="{'fiche-choix--actif': user.role === cle}" @click="user.role = cle">
                                        <icon>{{ cle === 'admin' ? 'shield_person' : 'badge' }}</icon>{{ nom }}
                                    </button>
                                </div>
                                <small v-if="user.role === 'admin'">Un administrateur peut tout faire, y compris modifier la structure du site et les comptes.</small>
                            </div>
                            <button type="button" role="switch" class="compte__interrupteur" :aria-checked="user.active ? 'true' : 'false'" @click="user.active = !user.active">
                            <span class="oui-non__piste" :class="{'oui-non__piste--oui': user.active}"><span class="oui-non__bouton-rond"><icon>{{ user.active ? 'check' : 'close' }}</icon></span></span>
                            <span class="compte__interrupteur-texte"><b>Compte actif</b><small>Désactivé, le compte ne peut plus se connecter ; rien n’est supprimé.</small></span>
                        </button>
                        </section>
                    <?php endif ?>

                    <?php if ($isAccountView) : ?>
                        <section class="fiche-carte">
                            <h2><icon>contrast</icon>Affichage</h2>
                            <div class="fiche-choix" role="radiogroup" aria-label="Thème">
                                <button type="button" role="radio" :aria-checked="!sombre ? 'true' : 'false'" :class="{'fiche-choix--actif': !sombre}" @click="choisirTheme(false)"><icon>light_mode</icon>Clair</button>
                                <button type="button" role="radio" :aria-checked="sombre ? 'true' : 'false'" :class="{'fiche-choix--actif': sombre}" @click="choisirTheme(true)"><icon>dark_mode</icon>Sombre</button>
                            </div>
                            <p class="fiche-aide">Les deux suivent les couleurs du site. Le choix est gardé sur cet appareil.</p>
                        </section>
                    <?php endif ?>
                </div>

                <div class="compte__colonne">
                    <section class="fiche-carte">
                        <h2><icon>key</icon>Mot de passe</h2>
                        <label class="mt-champ">
                            <span><?= $nouveau ? 'Mot de passe' : 'Nouveau mot de passe <em>laisser vide pour le garder</em>' ?></span>
                            <span class="compte__mdp">
                                <input :type="voir ? 'text' : 'password'" v-model="mdp" autocomplete="new-password" <?= $nouveau ? 'required' : '' ?>>
                                <button type="button" class="ls-rond" @click="voir = !voir" :aria-label="voir ? 'Masquer' : 'Afficher'"><icon>{{ voir ? 'visibility_off' : 'visibility' }}</icon></button>
                            </span>
                        </label>
                        <div class="compte__force" v-if="mdp"><span :style="{width: force.pourcent + '%'}" :class="'compte__force--' + force.niveau"></span></div>
                        <small class="compte__aide" v-if="mdp">{{ force.texte }}</small>
                        <label class="mt-champ" v-if="mdp">
                            <span>Le même, encore une fois</span>
                            <input :type="voir ? 'text' : 'password'" v-model="mdp2" autocomplete="new-password">
                            <small class="compte__erreur" v-if="mdp2 && mdp2 !== mdp">Les deux mots de passe ne sont pas identiques.</small>
                        </label>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>verified_user</icon>Sécurité</h2>
                        <button type="button" role="switch" class="compte__interrupteur" :aria-checked="user.twofa.enabled ? 'true' : 'false'" @click="user.twofa.enabled = !user.twofa.enabled">
                            <span class="oui-non__piste" :class="{'oui-non__piste--oui': user.twofa.enabled}"><span class="oui-non__bouton-rond"><icon>{{ user.twofa.enabled ? 'check' : 'close' }}</icon></span></span>
                            <span class="compte__interrupteur-texte"><b>Vérification en deux étapes</b><small>En plus du mot de passe, un code à 6 chiffres donné par une application sur le téléphone (Google Authenticator, Microsoft Authenticator…).</small></span>
                        </button>
                        <div class="compte__qr" v-if="user.twofa.enabled && <?= $moi ? 'true' : 'false' ?>">
                            <img :src="$routeUrl('/system/users/getSecretQRCode/' + user.twofa.secret + '/150')" width="150" height="150" alt="Code QR à scanner avec l’application">
                            <p>Scannez ce code avec l’application, puis enregistrez. À la prochaine connexion, elle vous donnera le code à saisir.</p>
                        </div>
                        <?php if ($gerer) : ?>
                            <div class="mt-champ compte__cle">
                                <span>Clé d’API personnelle</span>
                                <code v-if="user.apiKey">{{ user.apiKey }}</code>
                                <small v-else>Aucune. Elle donne à un programme les mêmes droits que ce compte.</small>
                                <div class="compte__cle-actions">
                                    <button type="button" class="kiss-button kiss-button-small" @click="creerCle"><icon>autorenew</icon>{{ user.apiKey ? 'Remplacer' : 'Créer une clé' }}</button>
                                    <button type="button" class="kiss-button kiss-button-small" v-if="user.apiKey" @click="App.utils.copyText(user.apiKey, () => App.ui.notify('Clé copiée.'))"><icon>content_copy</icon>Copier</button>
                                    <button type="button" class="kiss-button kiss-button-small" v-if="user.apiKey" @click="user.apiKey = null"><icon>close</icon>Retirer</button>
                                </div>
                            </div>
                        <?php endif ?>
                    </section>
                </div>
            </form>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat" :class="{'fiche-barre__etat--modifie': modifie}">
                        <icon>{{ modifie ? 'edit_note' : 'check_circle' }}</icon>{{ modifie ? 'Modifications non enregistrées' : (user._id ? 'Tout est enregistré' : 'Pas encore enregistré') }}
                    </span>
                    <div class="kiss-flex-1"></div>
                    <?php if ($gerer) : ?>
                        <a class="kiss-button" href="<?= $this->routeUrl('/system/users') ?>">{{ user._id ? 'Fermer' : 'Annuler' }}</a>
                    <?php endif ?>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="!pret" @click="enregistrer"><icon>check</icon>{{ user._id ? 'Enregistrer' : 'Créer le compte' }}</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        export default {
            data() {
                const user = <?= json_encode($user) ?>;
                return {
                    user,
                    roles: <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>,
                    depart: JSON.stringify(user),
                    mdp: '',
                    mdp2: '',
                    voir: false,
                    sombre: document.documentElement.getAttribute('data-theme') === 'dark',
                    enregistrement: false
                };
            },

            computed: {
                modifie() {
                    return !this.user._id || this.mdp !== '' || JSON.stringify(this.user) !== this.depart;
                },
                pret() {
                    if (this.enregistrement || !this.modifie || (this.mdp && this.mdp !== this.mdp2)) return false;
                    return this.user._id ? true : !!(this.user.user && this.user.email && this.mdp);
                },
                force() {
                    const m = this.mdp;
                    let points = Math.min(m.length, 16) / 16 * 60;
                    if (/[a-z]/.test(m) && /[A-Z]/.test(m)) points += 15;
                    if (/\d/.test(m)) points += 10;
                    if (/[^A-Za-z0-9]/.test(m)) points += 15;
                    if (m.length < 8) return { pourcent: Math.max(10, points), niveau: 'faible', texte: 'Trop court : au moins 8 caractères, idéalement 12 ou plus.' };
                    if (points < 60) return { pourcent: points, niveau: 'moyen', texte: 'Correct. Une phrase de quelques mots est encore plus sûre.' };
                    return { pourcent: Math.min(100, points), niveau: 'fort', texte: 'Solide.' };
                }
            },

            methods: {
                choisirTheme(sombre) {
                    this.sombre = sombre;
                    window.Dashboard.choisirTheme(sombre);
                },

                changerPhoto() {
                    const photo = document.querySelector('.entete__compte img.avatar-photo');
                    VueView.ui.modal('avatar:assets/dialog-avatar.js', { actuel: photo ? photo.src : null });
                },

                creerCle() {
                    this.$request('/utils/generateToken').then((r) => { this.user.apiKey = 'USR-' + r.token; });
                },

                /**
                 * Enregistre le compte ; une modification demande le mot de passe de la personne connectée.
                 */
                enregistrer() {
                    if (!this.pret) return;
                    const envoyer = (actuel) => {
                        this.enregistrement = true;
                        const user = Object.assign({}, this.user);
                        if (this.mdp) user.password = this.mdp; else delete user.password;
                        this.$request('/system/users/save', { user, password: actuel }).then((enregistre) => {
                            App.ui.notify(this.user._id ? 'Compte enregistré.' : 'Compte créé.');
                            const moi = <?= $moi ? 'true' : 'false' ?>;
                            if (moi) {
                                setTimeout(() => location.reload(), 700);   // nouveau jeton de sécurité, comme Cockpit
                            } else if (!this.user._id) {
                                location.href = this.$routeUrl('/system/users/user/' + enregistre._id);
                            } else {
                                this.user = Object.assign(this.user, enregistre);
                                this.depart = JSON.stringify(this.user);
                                this.mdp = this.mdp2 = '';
                            }
                        }).catch((r) => {
                            App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error');
                        }).finally(() => { this.enregistrement = false; });
                    };
                    if (!this.user._id) return envoyer('');
                    App.ui.prompt('Confirmez avec votre mot de passe actuel', '', envoyer, { type: 'password', info: 'Par sécurité, toute modification d’un compte demande votre mot de passe.' });
                }
            }
        };
    </script>

</vue-view>
