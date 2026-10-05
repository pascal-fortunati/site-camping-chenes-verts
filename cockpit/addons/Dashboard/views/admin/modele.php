<?php

/**
 * Éditeur d'un modèle (administrateur) : type, nom, champs, présentation dans le Dashboard (clé « admin »),
 * adresses d'aperçu, révisions et méta.
 *
 * @var array $model    le modèle (Cockpit)
 * @var bool  $isUpdate modification d'un modèle existant
 * @var array $groups   les groupes déjà utilisés
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

if (!isset($model['admin']) || !is_array($model['admin'])) {
    $model['admin'] = new ArrayObject([]);
}
$fichier = $isUpdate && is_file(dirname(__DIR__, 6).'/cockpit/models/'.$model['name'].'.model.php');
?>
<vue-view class="kiss-margin-small dashboard-page dashboard-fiche">

    <template>
        <kiss-container class="fiche modele">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon>{{ model.admin.icone || type(model.type).icone }}</icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <a href="<?= $this->routeUrl('/system') ?>">Réglages</a><icon>chevron_right</icon><a href="<?= $this->routeUrl('/content') ?>">Modèles de contenu</a>
                    </nav>
                    <h1>{{ model.label || model.name || 'Nouveau modèle' }}</h1>
                    <p class="fiche-tete__info">{{ type(model.type).titre }} · {{ model.fields.length }} champ{{ model.fields.length > 1 ? 's' : '' }}</p>
                </div>
                <a class="kiss-button" v-if="isUpdate" :href="lienElements"><icon>visibility</icon>{{ model.type === 'singleton' ? 'Ouvrir la fiche' : 'Voir les éléments' }}</a>
            </section>

            <?php if ($fichier) : ?>
                <p class="modeles__note modele__note"><icon>info</icon><span>Ce modèle est aussi écrit dans <code>cockpit/models/<?= $this->escape($model['name']) ?>.model.php</code> : reportez-y vos changements, sinon la prochaine installation les remplacera.</span></p>
            <?php endif ?>

            <div class="modele__grille" :class="{'fiche-grille--enregistrement': enregistrement}">
                <div class="modele__principal">
                    <section class="fiche-carte">
                        <h2><icon>schema</icon>Le modèle</h2>
                        <div class="mt-champ" v-if="!isUpdate">
                            <span>Type</span>
                            <div class="fiche-choix fiche-choix--liste" role="radiogroup" aria-label="Type">
                                <button type="button" role="radio" v-for="t in types" :key="t.cle" :aria-checked="model.type === t.cle ? 'true' : 'false'" :class="{'fiche-choix--actif': model.type === t.cle}" @click="model.type = t.cle"><icon>{{ t.icone }}</icon>{{ t.titre }}</button>
                            </div>
                            <small>{{ type(model.type).texte }}</small>
                        </div>
                        <div class="gc__deux">
                            <label class="mt-champ">
                                <span>Nom technique</span>
                                <input type="text" v-model="model.name" :disabled="isUpdate" pattern="[a-zA-Z0-9]+" required autocapitalize="off" spellcheck="false" placeholder="pages">
                                <small>{{ isUpdate ? 'Il ne change plus : l’API et les gabarits s’en servent.' : 'Lettres et chiffres seulement ; il sert dans l’API et les gabarits.' }}</small>
                            </label>
                            <label class="mt-champ"><span>Nom affiché</span><input type="text" v-model="model.label" placeholder="Pages"></label>
                        </div>
                        <label class="mt-champ"><span>Description <em>affichée en tête de la liste ou de la fiche</em></span><textarea v-model="model.info" rows="2"></textarea></label>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>view_list</icon>Champs</h2>
                        <fields-manager v-model="model.fields"></fields-manager>
                    </section>
                </div>

                <aside class="modele__cote">
                    <section class="fiche-carte">
                        <h2><icon>space_dashboard</icon>Dans l’administration</h2>
                        <p class="fiche-aide">Comment le modèle apparaît dans la barre latérale et ses écrans.</p>
                        <label class="mt-champ"><span>Libellé court</span><input type="text" v-model="model.admin.libelle" :placeholder="model.label || model.name"></label>
                        <div class="mt-champ">
                            <span>Icône <em>nom Material</em></span>
                            <span class="modele__icone">
                                <span class="ls-ligne__icone"><icon>{{ model.admin.icone || type(model.type).defaut }}</icon></span>
                                <input type="text" v-model="model.admin.icone" :placeholder="type(model.type).defaut" autocapitalize="off" spellcheck="false">
                            </span>
                            <div class="modele__icones">
                                <button type="button" class="ls-rond" v-for="i in icones" :key="i" :class="{'modele__icones--actif': model.admin.icone === i}" @click="model.admin.icone = i" :title="i" :aria-label="i"><icon>{{ i }}</icon></button>
                            </div>
                        </div>
                        <div class="mt-champ">
                            <span>Groupe de la barre</span>
                            <div class="fiche-choix" role="radiogroup" aria-label="Groupe de la barre">
                                <button type="button" role="radio" :aria-checked="groupe === 'quotidien' ? 'true' : 'false'" :class="{'fiche-choix--actif': groupe === 'quotidien'}" @click="model.admin.groupe = 'quotidien'"><icon>today</icon>Au quotidien</button>
                                <button type="button" role="radio" :aria-checked="groupe === 'site' ? 'true' : 'false'" :class="{'fiche-choix--actif': groupe === 'site'}" @click="model.admin.groupe = 'site'"><icon>language</icon>Le site</button>
                            </div>
                        </div>
                        <label class="mt-champ"><span>Ordre <em>plus petit = plus haut</em></span><input type="number" v-model.number="model.admin.ordre" placeholder="50" min="0" step="10"></label>
                        <template v-if="model.type !== 'singleton'">
                            <div class="gc__deux">
                                <label class="mt-champ"><span>Un élément</span><input type="text" v-model="model.admin.element" placeholder="élément"></label>
                                <label class="mt-champ"><span>Des éléments</span><input type="text" v-model="model.admin.elements" placeholder="éléments"></label>
                            </div>
                            <label class="mt-champ"><span>Bouton de création</span><input type="text" v-model="model.admin.nouveau" placeholder="Ajouter"></label>
                            <button type="button" role="switch" class="compte__interrupteur" :aria-checked="publication ? 'true' : 'false'" @click="model.admin.publication = !publication">
                                <span class="oui-non__piste" :class="{'oui-non__piste--oui': publication}"><span class="oui-non__bouton-rond"><icon>{{ publication ? 'check' : 'close' }}</icon></span></span>
                                <span class="compte__interrupteur-texte"><b>Se publie (en ligne / brouillon)</b><small>{{ publication ? 'Chaque élément est en ligne ou hors ligne, avec sa carte Publication.' : 'Pas d’état en ligne : pour des messages reçus, des commandes… La liste se trie par date de réception.' }}</small></span>
                            </button>
                        </template>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>preview</icon>Aperçu sur le site</h2>
                        <p class="fiche-aide" v-if="!model.preview.length">Aucune adresse d’aperçu.</p>
                        <div class="modele__apercus">
                            <div class="modele__apercu" v-for="(a, i) in model.preview" :key="i">
                                <input type="text" v-model="a.name" placeholder="Nom" aria-label="Nom de l’aperçu">
                                <input type="url" v-model="a.uri" placeholder="https://…" aria-label="Adresse de l’aperçu">
                                <button type="button" class="ls-rond ls-rond--danger" @click="model.preview.splice(i, 1)" aria-label="Retirer" title="Retirer"><icon>delete</icon></button>
                            </div>
                        </div>
                        <button type="button" class="kiss-button kiss-button-small" @click="model.preview.push({ name: '', uri: '' })"><icon>add</icon>Ajouter une adresse</button>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>settings</icon>Options</h2>
                        <button type="button" role="switch" class="compte__interrupteur" :aria-checked="model.revisions ? 'true' : 'false'" @click="model.revisions = !model.revisions">
                            <span class="oui-non__piste" :class="{'oui-non__piste--oui': model.revisions}"><span class="oui-non__bouton-rond"><icon>{{ model.revisions ? 'check' : 'close' }}</icon></span></span>
                            <span class="compte__interrupteur-texte"><b>Garder l’historique</b><small>Chaque enregistrement est gardé comme version, qu’on peut restaurer.</small></span>
                        </button>
                        <div class="mt-champ"><span>Couleur <em>dans les écrans de Cockpit</em></span><field-color v-model="model.color"></field-color></div>
                        <label class="mt-champ"><span>Groupe Cockpit <em>facultatif</em></span><input type="text" v-model="model.group"></label>
                        <div class="gc__groupes" v-if="groups.length">
                            <button type="button" class="kiss-button kiss-button-small" v-for="g in groups" :key="g" @click="model.group = g">{{ g }}</button>
                        </div>
                    </section>

                    <section class="fiche-carte">
                        <h2><icon>data_object</icon>Méta</h2>
                        <field-object v-model="model.meta"></field-object>
                    </section>
                </aside>
            </div>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat" :class="{'fiche-barre__etat--modifie': modifie}">
                        <icon>{{ modifie ? 'edit_note' : 'check_circle' }}</icon>{{ modifie ? 'Modifications non enregistrées' : 'Tout est enregistré' }}
                    </span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $this->routeUrl('/content') ?>">{{ isUpdate ? 'Fermer' : 'Annuler' }}</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="enregistrement || !model.name || !modifie" @click="enregistrer"><icon>check</icon>{{ isUpdate ? 'Enregistrer' : 'Créer le modèle' }}</button>
                </div>
            </kiss-container>
        </app-actionbar>
    </template>

    <script type="module">
        const TYPES = [
            { cle: 'collection', icone: 'stacks', defaut: 'folder', titre: 'Une liste', texte: 'Plusieurs éléments du même genre : pages, messages, actualités.' },
            { cle: 'singleton', icone: 'description', defaut: 'tune', titre: 'Une fiche unique', texte: 'Un seul ensemble de réglages : identité, mentions légales.' },
            { cle: 'tree', icone: 'account_tree', defaut: 'account_tree', titre: 'Une arborescence', texte: 'Des éléments rangés les uns sous les autres.' }
        ];
        const ICONES = ['description', 'web', 'mail', 'event_seat', 'newspaper', 'badge', 'menu', 'gavel', 'photo_library', 'restaurant_menu', 'euro', 'groups', 'event', 'place', 'star', 'help'];

        export default {
            data() {
                const model = <?= json_encode($model) ?>;
                if (!model.admin || Array.isArray(model.admin)) model.admin = {};
                if (!Array.isArray(model.preview)) model.preview = [];
                return { model, depart: JSON.stringify(model), groups: <?= json_encode($groups) ?>, isUpdate: <?= json_encode($isUpdate) ?>, types: TYPES, icones: ICONES, enregistrement: false };
            },

            computed: {
                modifie() {
                    return !this.isUpdate || JSON.stringify(this.model) !== this.depart;
                },
                groupe() {
                    return this.model.admin.groupe || (this.model.type === 'singleton' ? 'site' : 'quotidien');
                },
                // Même règle par défaut que lib/modeles.php : vrai, sauf pour « messages ».
                publication() {
                    return this.model.admin.publication ?? (this.model.name !== 'messages');
                },
                lienElements() {
                    const m = this.model;
                    return this.$routeUrl(m.type === 'singleton' ? '/content/singleton/item/' + m.name : '/content/' + m.type + '/items/' + m.name);
                }
            },

            methods: {
                type(cle) {
                    return TYPES.find((t) => t.cle === cle) || TYPES[0];
                },

                /**
                 * Enregistre le modèle ; les réglages vides de la clé « admin » sont retirés.
                 */
                enregistrer() {
                    const model = JSON.parse(JSON.stringify(this.model));
                    model.preview = model.preview.filter((p) => p.name && p.uri);
                    Object.keys(model.admin).forEach((k) => { if (model.admin[k] === '' || model.admin[k] === null) delete model.admin[k]; });
                    this.enregistrement = true;
                    this.$request('/content/models/save', { model, isUpdate: this.isUpdate }).then((enregistre) => {
                        if (!this.isUpdate) {
                            location.href = this.$routeUrl('/content/models/edit/' + enregistre.name);
                            return;
                        }
                        if (!enregistre.admin || Array.isArray(enregistre.admin)) enregistre.admin = {};
                        if (!Array.isArray(enregistre.preview)) enregistre.preview = [];
                        this.model = enregistre;
                        this.depart = JSON.stringify(enregistre);
                        App.ui.notify('Modèle enregistré.');
                    }).catch((r) => App.ui.notify((r && r.error) || 'L’enregistrement a échoué.', 'error'))
                        .finally(() => { this.enregistrement = false; });
                }
            }
        };
    </script>

</vue-view>
