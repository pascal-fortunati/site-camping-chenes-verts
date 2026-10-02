<?php
$acl = $this->helper('acl');
$nom = $model['name'];
$collection = $model['type'] === 'collection';
$champs = $model['fields'] ?? [];
$langues = $this->helper('locales')->locales();
if (count($langues) === 1) {
    $langues = [];
} else {
    $langues[0]['visible'] = true;
}
$icones = ['settings' => 'badge', 'saison' => 'event_seat', 'legal' => 'gavel', 'menu' => 'menu_open', 'pages' => 'description', 'messages' => 'mail', 'articles' => 'newspaper'];
$noms = ['pages' => 'page', 'messages' => 'message'];
$retour = $collection ? $this->routeUrl("/content/collection/items/{$nom}") : $this->routeUrl('/');
$groupe = in_array($nom, ['pages', 'messages', 'saison', 'articles'], true) ? 'Au quotidien' : 'Le site';   // comme la barre latérale
$droits = [
    'publier' => $collection && $acl->isAllowed("content/{$nom}/publish"),
    'creer' => $collection && $acl->isAllowed("content/{$nom}/create"),
    'modifier' => $acl->isAllowed("content/{$nom}/update") || ($collection && empty($item['_id']) && $acl->isAllowed("content/{$nom}/create")),
];
?>
<vue-view class="kiss-margin-small camping-page camping-fiche">

    <template>

        <kiss-container class="fiche">

            <section class="fiche-tete">
                <span class="fiche-tete__icone"><icon><?= $icones[$nom] ?? ($collection ? 'folder' : 'tune') ?></icon></span>
                <div class="fiche-tete__texte">
                    <nav class="fiche-tete__chemin" aria-label="Fil d’Ariane">
                        <span><?= $groupe ?></span>
                        <?php if ($collection) : ?><icon>chevron_right</icon><a href="<?= $retour ?>"><?= $this->escape($model['label'] ?: $nom) ?></a><?php endif ?>
                    </nav>
                    <h1>{{ titre }}</h1>
                    <p class="fiche-tete__info" v-if="!item._id">Nouvel élément : il n’existe pas encore, pensez à l’enregistrer.</p>
                    <p class="fiche-tete__info" v-else-if="model.info && !estCollection">{{ model.info }}</p>
                </div>
                <div class="fiche-tete__etats">
                    <span class="fiche-pastille fiche-pastille--attention" v-if="isModified"><icon>edit_note</icon>Modifications non enregistrées</span>
                    <span class="fiche-pastille" :class="item._state === 1 ? 'fiche-pastille--ligne' : ''" v-if="estCollection && model.name !== 'messages'"><span class="ls-etat__point"></span>{{ item._state === 1 ? 'En ligne' : 'Hors ligne' }}</span>
                </div>
            </section>

            <div class="fiche-vide" v-if="!fields.length"><icon>info</icon>Aucun champ à remplir.</div>

            <div class="fiche-grille" :class="{'fiche-grille--enregistrement': saving}" v-if="fields.length">
                <aside class="fiche-sommaire">
                    <div class="fiche-sommaire__titre">Sur cette fiche</div>
                    <kiss-sticky id="content-fields-outline" data-offset="90"></kiss-sticky>
                </aside>

                <div class="fiche-champs">
                    <fields-renderer v-model="item" :fields="fields" :locales="locales" outline="#content-fields-outline"></fields-renderer>
                </div>

                <aside class="fiche-cote">

                    <section class="fiche-carte" v-if="droits.publier && model.name !== 'messages'">
                        <h2><icon>public</icon>Publication</h2>
                        <div class="fiche-choix" role="radiogroup" aria-label="Publication">
                            <button type="button" role="radio" :aria-checked="item._state === 1 ? 'true' : 'false'" :class="{'fiche-choix--actif': item._state === 1}" @click="item._state = 1"><icon>visibility</icon>En ligne</button>
                            <button type="button" role="radio" :aria-checked="item._state !== 1 ? 'true' : 'false'" :class="{'fiche-choix--actif fiche-choix--brouillon': item._state !== 1}" @click="item._state = 0"><icon>visibility_off</icon>Hors ligne</button>
                        </div>
                        <p class="fiche-aide">{{ item._state === 1 ? 'Visible sur le site dès l’enregistrement.' : 'Invisible pour les visiteurs. Vous pouvez la préparer tranquillement.' }}</p>
                    </section>

                    <section class="fiche-carte" v-if="item._id">
                        <h2><icon>history</icon>Historique</h2>
                        <dl class="fiche-infos">
                            <div><dt>Créé</dt><dd>{{ date(item._created) }}</dd></div>
                            <div v-if="item._modified && item._modified !== item._created"><dt>Modifié</dt><dd>{{ date(item._modified) }}</dd></div>
                        </dl>
                        <revisions-widget class="fiche-revisions" :oid="item._id" :current="item" v-if="model.revisions"></revisions-widget>
                    </section>

                    <section class="fiche-carte" v-if="droits.creer && item._id">
                        <h2><icon>bolt</icon>Actions</h2>
                        <a class="fiche-action" :href="$routeUrl('/content/collection/clone/' + model.name + '/' + item._id)"><icon>content_copy</icon>Dupliquer</a>
                        <a class="fiche-action" :href="$routeUrl('/content/collection/item/' + model.name)"><icon>add</icon>{{ nouveau }}</a>
                    </section>

                    <section class="fiche-carte fiche-carte--astuce">
                        <p><kbd>Ctrl</kbd> + <kbd>S</kbd> enregistre sans quitter la page.</p>
                    </section>

                </aside>
            </div>
        </kiss-container>

        <app-actionbar>
            <kiss-container>
                <div class="fiche-barre">
                    <span class="fiche-barre__etat" :class="{'fiche-barre__etat--modifie': isModified}">
                        <icon>{{ isModified ? 'edit_note' : 'check_circle' }}</icon>{{ isModified ? 'Modifications non enregistrées' : (item._id ? 'Tout est enregistré' : 'Pas encore enregistré') }}
                    </span>
                    <div class="kiss-flex-1"></div>
                    <a class="kiss-button" href="<?= $retour ?>">{{ item._id ? 'Fermer' : 'Annuler' }}</a>
                    <button type="button" class="kiss-button kiss-button-primary" :disabled="!droits.modifier || saving || (item._id && !isModified)" @click="save()">
                        <icon>{{ saving ? 'hourglass_top' : 'check' }}</icon>{{ item._id ? 'Enregistrer' : 'Créer' }}
                    </button>
                </div>
            </kiss-container>
        </app-actionbar>

    </template>

    <script type="module">

        import {useDirtyCheck} from "module-app/assets/vue-components/dirty-check.js";

        const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        export default {

            mixins: [useDirtyCheck('item')],

            data() {
                return {
                    model: <?= json_encode($model) ?>,
                    item: <?= json_encode($item) ?>,
                    fields: <?= json_encode($champs) ?>,
                    locales: <?= json_encode($langues) ?>,
                    droits: <?= json_encode($droits) ?>,
                    nouveau: <?= json_encode('Nouvelle '.($noms[$nom] ?? 'fiche'), JSON_UNESCAPED_UNICODE) ?>,
                    saving: false
                };
            },

            computed: {
                estCollection() {
                    return this.model.type === 'collection';
                },
                titre() {
                    if (!this.estCollection) return this.model.label || this.model.name;
                    const champ = ['titre', 'title', 'nom', 'name'].find((n) => this.fields.some((f) => f.name === n));
                    const t = champ ? this.item[champ] : '';
                    return (typeof t === 'string' && t.trim()) ? t : (this.item._id ? 'Sans titre' : this.nouveau);
                }
            },

            mounted() {
                this.raccourci = (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                        e.preventDefault();
                        if (this.droits.modifier && !this.saving && (!this.item._id || this.isModified)) this.save();
                    }
                };
                document.addEventListener('keydown', this.raccourci);
            },

            unmounted() {
                document.removeEventListener('keydown', this.raccourci);
            },

            methods: {

                date(secondes) {
                    if (!secondes) return '';
                    const d = new Date(secondes * 1000);
                    return `${d.getDate()}${d.getDate() === 1 ? 'er' : ''} ${mois[d.getMonth()]} ${d.getFullYear()} à ${String(d.getHours()).padStart(2, '0')} h ${String(d.getMinutes()).padStart(2, '0')}`;
                },

                save() {
                    const validate = { root: this.$el.parentNode };
                    App.trigger('fields-renderer-validate', validate);
                    if (validate.errors) {
                        App.ui.notify('Certains champs sont à compléter.', 'error');
                        return;
                    }

                    const nouveau = !this.item._id;
                    this.saving = true;

                    this.$request(`/content/models/saveItem/${this.model.name}`, { item: this.item }).then((item) => {
                        this.item = Object.assign(this.item, item);
                        this.resetDirtyState();
                        this.saving = false;
                        App.ui.notify(nouveau ? 'Créé.' : 'Enregistré.');
                        if (nouveau && this.estCollection && item._id) {
                            history.replaceState(null, '', this.$routeUrl(`/content/collection/item/${this.model.name}/${item._id}`));
                        }
                    }).catch((rsp) => {
                        this.saving = false;
                        App.ui.notify((rsp && rsp.error) || 'L’enregistrement a échoué.', 'error');
                    });
                }
            }
        };
    </script>

</vue-view>
