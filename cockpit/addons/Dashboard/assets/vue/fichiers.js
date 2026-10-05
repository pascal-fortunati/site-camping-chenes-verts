/**
 * Fichiers du serveur (administrateur) : dossiers et fichiers en liste, chemin, recherche, envoi par glisser-
 * déposer, renommage et suppression. Reprend la logique du composant Finder de Cockpit ; seul l'affichage change.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */

const version = new URL(import.meta.url).search;
const { confirmer } = await import(`./communs.js${version}`);
const { default: origine } = await import(App.base('finder:assets/vue-components/finder.js') + version);

const ICONES = [[/^image\//, 'image'], [/^video\//, 'movie'], [/^audio\//, 'music_note'], [/pdf/, 'picture_as_pdf'], [/zip|compressed|tar/, 'folder_zip'], [/json|javascript|php|html|css|xml/, 'code'], [/^text\//, 'description']];

/**
 * Taille d'un fichier : Cockpit l'envoie déjà écrite (« 504 Bytes », « 6.33 KB ») ; les unités passent en français.
 *
 * @param {number|string} valeur
 * @returns {string}
 */
const taille = (valeur) => {
    if (typeof valeur === 'number') return valeur < 1024 ? valeur + ' o' : (valeur < 1048576 ? Math.round(valeur / 1024) + ' ko' : (valeur / 1048576).toFixed(1).replace('.', ',') + ' Mo');
    return String(valeur || '').replace(/(\d)\.(\d)/, '$1,$2').replace(/\bBytes?\b/, 'o').replace(/\bKB\b/, 'ko').replace(/\bMB\b/, 'Mo').replace(/\bGB\b/, 'Go');
};

export default {
    ...origine,

    methods: {
        ...origine.methods,

        icone(f) {
            const mime = String(f.mime || '');
            return (ICONES.find(([motif]) => motif.test(mime)) || [null, 'draft'])[1];
        },

        taille,

        date(s) {
            if (typeof s !== 'number') return String(s || '');
            return new Date(s * 1000).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        modifiable(f) {
            return /text|x-empty/.test(f.mime || '') || ['json', 'svg', 'php', 'js', 'css', 'md', 'yml', 'yaml', 'env', 'htaccess'].includes(String(f.ext || '').toLowerCase());
        },

        toutSelectionner() {
            this.selected = this.selected.length === this.files.length ? [] : this.files.map((f) => f.path);
        },

        createFolder() {
            App.ui.prompt('Nom du nouveau dossier', '', (name) => {
                if (!name || !name.trim()) return;
                this.$request('/finder/api', { root: this.root, cmd: 'createfolder', path: this.currentpath, name }).then(() => this.loadpath())
                    .catch((r) => App.ui.notify((r && r.error) || 'Le dossier n’a pas pu être créé.', 'error'));
            });
        },

        createFile() {
            App.ui.prompt('Nom du nouveau fichier', '', (name) => {
                if (!name || !name.trim()) return;
                this.$request('/finder/api', { root: this.root, cmd: 'createfile', path: this.currentpath, name }).then(() => this.loadpath())
                    .catch((r) => App.ui.notify((r && r.error) || 'Le fichier n’a pas pu être créé.', 'error'));
            });
        },

        rename(item) {
            App.ui.prompt('Nouveau nom', item.name, (name) => {
                if (!name || name === item.name || !name.trim()) return;
                this.$request('/finder/api', { root: this.root, cmd: 'rename', path: item.path, name }).then(() => {
                    const i = item.path.lastIndexOf(item.name);
                    item.path = item.path.substring(0, i) + name + item.path.substring(i + item.name.length);
                    item.name = name;
                }).catch((r) => App.ui.notify((r && r.error) || 'Le renommage a échoué.', 'error'));
            });
        },

        async remove(item) {
            const oui = await confirmer({ titre: `Supprimer « ${item.name} » ?`, texte: item.is_file ? 'Le fichier est effacé du serveur. Cette action est définitive.' : 'Le dossier et tout ce qu’il contient sont effacés du serveur. Cette action est définitive.', bouton: 'Supprimer', danger: true });
            if (!oui) return;
            this.$request('/finder/api', { root: this.root, cmd: 'removefiles', paths: [item.path] }).then(() => {
                const liste = this[item.is_file ? 'files' : 'folders'];
                liste.splice(liste.indexOf(item), 1);
                this.selected = [];
                App.ui.notify('Supprimé.');
            });
        },

        async removeSelected() {
            const n = this.selected.length;
            const oui = await confirmer({ titre: `Supprimer ${n} fichier${n > 1 ? 's' : ''} ?`, texte: 'Ils sont effacés du serveur. Cette action est définitive.', bouton: 'Supprimer', danger: true });
            if (!oui) return;
            this.$request('/finder/api', { root: this.root, cmd: 'removefiles', paths: this.selected }).then(() => {
                this.loadpath();
                App.ui.notify('Supprimé.');
            });
        }
    },

    template: /*html*/`
        <div class="mt ls fichiers">

            <div class="mt-outils">
                <label class="mt-recherche">
                    <icon aria-hidden="true">search</icon>
                    <input type="search" v-model="filter" placeholder="Chercher dans ce dossier" aria-label="Chercher dans ce dossier">
                </label>
                <div class="mt-outils__droite fichiers__actions">
                    <button type="button" class="kiss-button" @click="createFolder"><icon>create_new_folder</icon>Dossier</button>
                    <button type="button" class="kiss-button" @click="createFile"><icon>note_add</icon>Fichier</button>
                    <label class="kiss-button kiss-button-primary fichiers__envoi" :class="{'kiss-disabled': uploading !== false}">
                        <icon>upload</icon>{{ uploading !== false ? uploading + ' %' : 'Envoyer' }}
                        <input type="file" multiple @change="(e) => uploadFiles(e.target.files)" :disabled="uploading !== false">
                    </label>
                </div>
            </div>

            <nav class="fiche-tete__chemin fichiers__chemin" aria-label="Chemin">
                <a href="#" @click.prevent="loadpath(rootPath)"><icon>home</icon>Racine</a>
                <template v-for="f in breadcrumbs" :key="f.path"><icon>chevron_right</icon><a href="#" @click.prevent="loadpath(f.path)">{{ f.name }}</a></template>
            </nav>

            <div class="ls-liste" v-if="loading">
                <div class="ls-ligne ls-ligne--fantome" v-for="n in 5" :key="n"></div>
            </div>

            <div class="mt-vide" v-else-if="!folders.length && !files.length">
                <icon>folder_off</icon>
                <p>Ce dossier est vide. Glissez des fichiers ici pour les envoyer.</p>
            </div>

            <template v-else>
                <div class="ls-liste" v-if="filteredFolders.length">
                    <article class="ls-ligne" v-for="d in filteredFolders" :key="d.path">
                        <a class="ls-ligne__principal" href="#" @click.prevent="loadpath(d.path)">
                            <span class="ls-ligne__icone"><icon>folder</icon></span>
                            <span class="ls-ligne__texte"><b>{{ d.name }}</b><small>Dossier</small></span>
                        </a>
                        <div class="ls-actions">
                            <button type="button" class="ls-rond" @click="downloadfolder(d)" title="Télécharger (zip)" aria-label="Télécharger"><icon>download</icon></button>
                            <button type="button" class="ls-rond" @click="rename(d)" title="Renommer" aria-label="Renommer"><icon>drive_file_rename_outline</icon></button>
                            <button type="button" class="ls-rond ls-rond--danger" @click="remove(d)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>

                <div class="fichiers__selection" v-if="files.length">
                    <label class="fichiers__tout"><input type="checkbox" class="kiss-checkbox" :checked="selected.length && selected.length === files.length" @change="toutSelectionner"> Tout sélectionner</label>
                    <template v-if="selected.length">
                        <span>{{ selected.length }} sélectionné{{ selected.length > 1 ? 's' : '' }}</span>
                        <button type="button" class="kiss-button kiss-button-small kiss-button-danger" @click="removeSelected"><icon>delete</icon>Supprimer</button>
                    </template>
                </div>

                <div class="ls-liste" v-if="filteredFiles.length">
                    <article class="ls-ligne" v-for="f in filteredFiles" :key="f.path" :class="{'fichiers--choisi': selected.includes(f.path)}">
                        <input type="checkbox" class="kiss-checkbox fichiers__case" v-model="selected" :value="f.path" :aria-label="'Sélectionner ' + f.name">
                        <a class="ls-ligne__principal" href="#" @click.prevent="open(f)">
                            <span class="ls-ligne__icone"><icon>{{ icone(f) }}</icon></span>
                            <span class="ls-ligne__texte"><b>{{ f.name }}</b><small>{{ taille(f.size) }}<template v-if="!f.is_writable"> · lecture seule</template></small></span>
                        </a>
                        <span class="ls-ligne__date">{{ date(f.lastmodified) }}</span>
                        <div class="ls-actions">
                            <button type="button" class="ls-rond" v-if="modifiable(f)" @click="edit(f)" title="Modifier" aria-label="Modifier"><icon>edit</icon></button>
                            <button type="button" class="ls-rond" @click="download(f)" title="Télécharger" aria-label="Télécharger"><icon>download</icon></button>
                            <button type="button" class="ls-rond" @click="rename(f)" title="Renommer" aria-label="Renommer"><icon>drive_file_rename_outline</icon></button>
                            <button type="button" class="ls-rond ls-rond--danger" @click="remove(f)" title="Supprimer" aria-label="Supprimer"><icon>delete</icon></button>
                        </div>
                    </article>
                </div>

                <div class="mt-vide" v-if="!filteredFolders.length && !filteredFiles.length">
                    <icon>search_off</icon>
                    <p>Rien ne correspond.</p>
                </div>
            </template>
        </div>
    `
};
