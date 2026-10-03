<?php

/**
 * Modèles de contenu (administrateur) : chaque modèle, son type, son nombre d'éléments et ses actions.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

$presenter = include dirname(__DIR__, 2).'/lib/modeles.php';
$content = $this->module('content');
$fichiers = is_dir(dirname(__DIR__, 6).'/cockpit/models');
$modeles = [];
foreach ($content->models() as $nom => $m) {
    $vue = $presenter($m);
    $modeles[] = [
        'name' => $nom,
        'label' => (string) ($m['label'] ?: $nom),
        'info' => (string) ($m['info'] ?? ''),
        'type' => $m['type'],
        'icone' => $vue['icone'],
        'groupe' => $vue['titreGroupe'],
        'champs' => count($m['fields'] ?? []),
        'elements' => $m['type'] === 'singleton' ? null : $content->count($nom),
    ];
}
usort($modeles, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
?>
<kiss-container class="kiss-margin-small dashboard-page">
    <vue-view>
        <template>
            <div class="mt ls">

                <section class="tdb-accueil tdb-accueil--page">
                    <h1 class="tdb-accueil__titre">Modèles de contenu</h1>
                    <p class="tdb-accueil__phrase">La structure du site : les champs de chaque fiche, liste ou arborescence.</p>
                    <div class="mt-bandeau__actions">
                        <button type="button" class="mt-bandeau__bouton" @click="nouveau = !nouveau" :aria-expanded="nouveau ? 'true' : 'false'"><icon>add</icon>Nouveau modèle</button>
                    </div>
                    <ul class="mt-bandeau__chiffres">
                        <li v-for="t in types" :key="t.cle" v-show="t.nombre"><b>{{ t.nombre }}</b> {{ t.nombre > 1 ? t.pluriel : t.singulier }}</li>
                    </ul>
                </section>

                <?php if ($fichiers) : ?>
                    <p class="modeles__note"><icon>info</icon><span>Sur ce site, les modèles sont aussi écrits dans les fichiers <code>cockpit/models/</code> : une modification faite ici est remplacée à la prochaine installation, sauf si elle est reportée dans le fichier.</span></p>
                <?php endif ?>

                <section class="tdb-carte" v-if="nouveau">
                    <h2><icon>add_circle</icon>Quel type de modèle ?</h2>
                    <div class="tdb-raccourcis">
                        <a class="tdb-raccourci" v-for="t in types" :key="t.cle" :href="$routeUrl('/content/models/create') + '?type=' + t.cle">
                            <span class="tdb-raccourci__icone"><icon>{{ t.icone }}</icon></span>
                            <span><b>{{ t.titre }}</b><span>{{ t.texte }}</span></span>
                            <icon class="tdb-raccourci__fleche">arrow_forward</icon>
                        </a>
                    </div>
                </section>

                <div class="mt-outils">
                    <label class="mt-recherche">
                        <icon aria-hidden="true">search</icon>
                        <input type="search" v-model="recherche" placeholder="Chercher un modèle" aria-label="Chercher un modèle">
                    </label>
                    <div class="mt-filtres" role="group" aria-label="Filtrer par type">
                        <button type="button" class="mt-filtre" :class="{'mt-filtre--actif': filtre === ''}" @click="filtre = ''">Tous<span class="mt-filtre__nombre">{{ modeles.length }}</span></button>
                        <button type="button" v-for="t in types" :key="t.cle" v-show="t.nombre" class="mt-filtre" :class="{'mt-filtre--actif': filtre === t.cle}" @click="filtre = t.cle">{{ t.pluriel }}<span class="mt-filtre__nombre">{{ t.nombre }}</span></button>
                    </div>
                </div>

                <div class="mt-vide" v-if="!liste.length">
                    <icon>schema</icon>
                    <p>Aucun modèle ne correspond.</p>
                </div>

                <div class="ls-liste" v-else>
                    <article class="ls-ligne" v-for="m in liste" :key="m.name">
                        <a class="ls-ligne__principal" :href="lienElements(m)">
                            <span class="ls-ligne__icone"><icon>{{ m.icone }}</icon></span>
                            <span class="ls-ligne__texte">
                                <b>{{ m.label }}</b>
                                <small>{{ m.name }} · {{ m.champs }} champ{{ m.champs > 1 ? 's' : '' }}<template v-if="m.info"> · {{ m.info }}</template></small>
                            </span>
                        </a>
                        <span class="ls-etat ls-etat--brouillon"><icon>{{ type(m.type).icone }}</icon>{{ type(m.type).singulier }}</span>
                        <span class="ls-ligne__date" v-if="m.elements !== null">{{ m.elements }} élément{{ m.elements > 1 ? 's' : '' }}</span>
                        <div class="ls-actions">
                            <a class="ls-rond" :href="lienElements(m)" title="Voir le contenu" aria-label="Voir le contenu"><icon>visibility</icon></a>
                            <a class="ls-rond" :href="$routeUrl('/content/models/edit/' + m.name)" title="Modifier le modèle" aria-label="Modifier le modèle"><icon>edit</icon></a>
                            <button type="button" class="ls-rond ls-plus" @click.stop="menu = menu === m.name ? null : m.name" :aria-expanded="menu === m.name ? 'true' : 'false'" aria-label="Plus d’actions"><icon>more_vert</icon></button>
                            <div class="ls-menu" v-if="menu === m.name">
                                <button type="button" @click="dupliquer(m)"><icon>content_copy</icon>Dupliquer</button>
                                <button type="button" class="ls-menu__danger" @click="supprimer(m)"><icon>delete</icon>Supprimer</button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </template>

        <script type="module">
            const TYPES = [
                { cle: 'collection', icone: 'stacks', singulier: 'liste', pluriel: 'Listes', titre: 'Une liste', texte: 'Plusieurs éléments du même genre : pages, messages, actualités' },
                { cle: 'singleton', icone: 'description', singulier: 'fiche unique', pluriel: 'Fiches uniques', titre: 'Une fiche unique', texte: 'Un seul ensemble de réglages : identité, mentions légales' },
                { cle: 'tree', icone: 'account_tree', singulier: 'arborescence', pluriel: 'Arborescences', titre: 'Une arborescence', texte: 'Des éléments rangés les uns sous les autres' }
            ];
            const plat = (t) => String(t || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

            export default {
                data() {
                    return { modeles: <?= json_encode($modeles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, recherche: '', filtre: '', menu: null, nouveau: false };
                },

                computed: {
                    types() {
                        return TYPES.map((t) => ({ ...t, nombre: this.modeles.filter((m) => m.type === t.cle).length }));
                    },
                    liste() {
                        const mots = plat(this.recherche).split(/\s+/).filter(Boolean);
                        return this.modeles.filter((m) => (!this.filtre || m.type === this.filtre) && mots.every((x) => plat(m.label + ' ' + m.name + ' ' + m.info).includes(x)));
                    }
                },

                mounted() {
                    this.ailleurs = () => { this.menu = null; };
                    document.addEventListener('click', this.ailleurs);
                },

                unmounted() {
                    document.removeEventListener('click', this.ailleurs);
                },

                methods: {
                    type(cle) {
                        return TYPES.find((t) => t.cle === cle) || { icone: 'schema', singulier: cle };
                    },

                    lienElements(m) {
                        return this.$routeUrl(m.type === 'singleton' ? '/content/singleton/item/' + m.name : '/content/' + m.type + '/items/' + m.name);
                    },

                    dupliquer(m) {
                        App.ui.prompt('Nom technique du nouveau modèle', m.name + '_copie', (name) => {
                            if (!name) return;
                            this.$request('/content/models/clone/' + m.name, { name }).then((n) => {
                                location.href = this.$routeUrl('/content/models/edit/' + n.name);
                            }).catch((r) => App.ui.notify((r && r.error) || 'La duplication a échoué.', 'error'));
                        }, { info: 'Lettres et chiffres, sans espace : il sert dans les adresses de l’API.' });
                    },

                    async supprimer(m) {
                        const { confirmer } = await App.utils.import('dashboard:assets/vue/communs.js');
                        const details = m.elements ? [`Ses ${m.elements} élément${m.elements > 1 ? 's' : ''} seront supprimés avec lui.`] : [];
                        if (!await confirmer({ titre: `Supprimer le modèle « ${m.label} » ?`, texte: 'Le site ne pourra plus afficher ce contenu. Cette action est définitive.', details, bouton: 'Supprimer', danger: true })) return;
                        this.$request('/content/models/remove/' + m.name, {}).then(() => {
                            this.modeles = this.modeles.filter((x) => x.name !== m.name);
                            App.ui.notify('Modèle supprimé.');
                        }).catch((r) => App.ui.notify((r && r.error) || 'La suppression a échoué.', 'error'));
                    }
                }
            };
        </script>
    </vue-view>
</kiss-container>
