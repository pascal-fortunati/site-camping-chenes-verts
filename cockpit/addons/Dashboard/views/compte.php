<?php

/**
 * Mon compte : informations, affichage, mot de passe et vérification en deux étapes.
 *
 * @var array $user le compte connecté (Cockpit)
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
$roles = array_column($this->helper('acl')->roles(), 'name', 'appid');
$role = $user['role'] === 'admin' ? 'Administrateur' : ($roles[$user['role']] ?? $user['role']);
$avecAvatar = isset($this['modules']['avatar']);
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche compte">

            <section class="fiche-tete">
                <span class="compte__photo"><app-avatar size="64" :name="user.name"></app-avatar></span>
                <div class="fiche-tete__texte">
                    <h1>{{ user.name || 'Mon compte' }}</h1>
                    <p class="fiche-tete__info">{{ user.email }} · <?= $this->escape($role) ?></p>
                </div>
                <?php if ($avecAvatar) : ?>
                    <button type="button" class="kiss-button" @click="changerPhoto"><icon>face</icon>Changer ma photo</button>
                <?php endif ?>
            </section>

            <form class="compte__grille" @submit.prevent="enregistrer" autocomplete="off">
                <div class="compte__colonne">
                <section class="fiche-carte">
                    <h2><icon>badge</icon>Vos informations</h2>
                    <label class="mt-champ"><span>Nom affiché</span><input type="text" v-model="user.name" required></label>
                    <label class="mt-champ"><span>Identifiant de connexion</span><input type="text" v-model="user.user" required autocapitalize="off" spellcheck="false"></label>
                    <label class="mt-champ"><span>Adresse e-mail</span><input type="email" v-model="user.email" required><small>Pour recevoir un lien si vous oubliez votre mot de passe.</small></label>
                </section>
                <section class="fiche-carte">
                    <h2><icon>contrast</icon>Affichage</h2>
                    <div class="fiche-choix" role="radiogroup" aria-label="Thème">
                        <button type="button" role="radio" :aria-checked="!sombre ? 'true' : 'false'" :class="{'fiche-choix--actif': !sombre}" @click="choisirTheme(false)"><icon>light_mode</icon>Clair</button>
                        <button type="button" role="radio" :aria-checked="sombre ? 'true' : 'false'" :class="{'fiche-choix--actif': sombre}" @click="choisirTheme(true)"><icon>dark_mode</icon>Sombre</button>
                    </div>
                    <p class="fiche-aide">Les deux suivent les couleurs du site. Le choix est gardé sur cet appareil.</p>
                </section>
                </div>
                <div class="compte__colonne">
                <section class="fiche-carte">
                    <h2><icon>key</icon>Mot de passe</h2>
                    <label class="mt-champ">
                        <span>Nouveau mot de passe <em>laisser vide pour le garder</em></span>
                        <span class="compte__mdp">
                            <input :type="voir ? 'text' : 'password'" v-model="mdp" autocomplete="new-password">
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
                    <label class="compte__interrupteur">
                        <input type="checkbox" v-model="user.twofa.enabled">
                        <span><b>Vérification en deux étapes</b><small>En plus du mot de passe, un code à 6 chiffres donné par une application sur votre téléphone (Google Authenticator, Microsoft Authenticator…).</small></span>
                    </label>
                    <div class="compte__qr" v-if="user.twofa.enabled">
                        <img :src="$routeUrl('/system/users/getSecretQRCode/' + user.twofa.secret + '/150')" width="150" height="150" alt="Code QR à scanner avec l’application">
                        <p>Scannez ce code avec l’application, puis enregistrez. À la prochaine connexion, elle vous donnera le code à saisir.</p>
                    </div>
                </section>
                </div>
            </form>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat" :class="{'fiche-barre__etat--modifie': modifie}">
                        <icon>{{ modifie ? 'edit_note' : 'check_circle' }}</icon>{{ modifie ? 'Modifications non enregistrées' : 'Tout est enregistré' }}
                    </span>
                    <div class="kiss-flex-1"></div>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="!modifie || enregistrement || (mdp !== '' && mdp !== mdp2)" @click="enregistrer"><icon>check</icon>Enregistrer</button>
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
                    depart: JSON.stringify({ n: user.name, u: user.user, e: user.email, t: !!(user.twofa && user.twofa.enabled) }),
                    mdp: '',
                    mdp2: '',
                    voir: false,
                    sombre: document.documentElement.getAttribute('data-theme') === 'dark',
                    enregistrement: false
                };
            },

            computed: {
                modifie() {
                    return this.mdp !== '' || JSON.stringify({ n: this.user.name, u: this.user.user, e: this.user.email, t: !!this.user.twofa.enabled }) !== this.depart;
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

                enregistrer() {
                    if (!this.modifie || (this.mdp && this.mdp !== this.mdp2)) return;
                    App.ui.prompt('Confirmez avec votre mot de passe actuel', '', (actuel) => {
                        this.enregistrement = true;
                        const user = Object.assign({}, this.user);
                        if (this.mdp) user.password = this.mdp; else delete user.password;
                        this.$request('/system/users/save', { user, password: actuel }).then(() => {
                            App.ui.notify('Compte enregistré.');
                            setTimeout(() => location.reload(), 700);   // nouveau jeton de sécurité, comme Cockpit
                        }).catch((r) => {
                            App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error');
                        }).finally(() => { this.enregistrement = false; });
                    }, { type: 'password', info: 'Par sécurité, toute modification du compte demande votre mot de passe actuel.' });
                }
            }
        };
    </script>

</vue-view>
