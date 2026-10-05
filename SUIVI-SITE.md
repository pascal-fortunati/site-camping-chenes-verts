# Suivi du site — Camping Les Chênes Verts

Site créé depuis le socle `jean-ely-pro/site-vitrine-cockpit-php` (version 2.0.6), dépôt distant `socle`.
Ce fichier liste ce qui s'écarte du socle, pour relire chaque fusion d'une mise à jour.

## Version du socle

Fusion de `v2.0.12` le 05/10/2026 (branche `maj-socle-2.0.12`, sans conflit) : le correctif de Passerelle proposé depuis ce site (PR #53). La pastille du site suit l'état de l'addon : le site demande `/passerelle/etat` (et non plus `/check-session`), qui n'existe plus quand Passerelle est désactivée (addon Modules) ; la pastille et son cookie disparaissent alors. Fonctionne aussi en local, site et administration sur deux adresses (:8080, :8090).

Fusion de `v2.0.11` le 05/10/2026 (branche `maj-socle-2.0.11`, sans conflit), qui reprend aussi `v2.0.9` et `v2.0.10`. Apports : la Passerelle proposée depuis ce site (PR #49), déjà présente ici dans sa version plus récente (fichiers inchangés) ; le fichier `VERSION` rétabli et vérifié par `tests/GardeFous/VersionTest.php` (il indique enfin la bonne version) ; le workflow `.github/workflows/tests.yml` (la suite de tests sur chaque proposition et chaque envoi sur `main`) ; les modèles d'issues, `CONTRIBUTING.md` et `CONTRIBUTORS.md` ; les extensions `gd` et `pdo_sqlite` déclarées dans `composer.json` (aucune bibliothèque modifiée, pas de `composer install` à refaire sur le serveur). Le script de Passerelle ajouté à `templates/base.html.twig` est déjà dans la copie `templates-client/base.html.twig`.

Fusion de `v2.0.8` le 02/10/2026 (branche `maj-socle-2.0.8`, sans conflit) : elle apporte le module `AdminClient` proposé depuis ce site (PR #44, remplacé depuis par Dashboard), le vidage du cache des modules dans `bin/install-cockpit.php` et la documentation de `--allow-unrelated-histories`.

Fusion de `v2.0.7` le 02/10/2026 (branche `maj-socle`, `--allow-unrelated-histories` : première fusion d'un dépôt
créé depuis le template). Apports : correctifs des boucles sans fin de `contraste-couleurs.js` (fiche « Identité du
site » figée, que nous avions corrigée de notre côté : la version du socle remplace la nôtre) et de `poids-images.js`
(fenêtre de détail d'une image), `process-timeout: 0` dans `composer.json`. Le fichier `VERSION` du socle indique
encore `2.0.6` pour cette étiquette.

## Fichiers du socle modifiés

À relire à chaque fusion : un conflit y est possible. Les ajouts sont regroupés entre deux commentaires
`CAMPING LES CHÊNES VERTS` … `fin CAMPING LES CHÊNES VERTS`.

| Fichier | Modification | Pourquoi |
|---|---|---|
| `cockpit/models/pages.model.php` | 7 types de section (`annonce`, `cartes`, `hebergements`, `tarifs`, `reservation`, `carte`, `ardoise`) et leurs champs ; `$hasImage` (ligne du socle) inclut `carte`, pour que sa description d'image soit exigée ; libellé des sections dans la liste : titre puis type en clair (« hero » devenait « Bandeau d’ouverture (grande photo) », support 07) | Support 06 : seul fichier partagé prévu par le socle |
| `cockpit/models/settings.model.php` | 7 champs de l'identité : `classement`, `saisonOuverture`, `saisonFermeture`, `placesDisponibles`, `latitude`, `longitude` ; les champs de la saison sont passés dans `saison.model.php` (02/10/2026) | Décisions A1, A2, A6 : saisis une seule fois, affichés sur plusieurs pages |
| `src/Content/Repository.php` | `settings()` lit aussi la fiche « La saison » : les gabarits gardent `site.placesDisponibles`… | Demande du client : l'identité du site ne doit pas contenir la saison |
| `tests/Site/RepositoryTest.php` | « une seule requête par page » devient « une par fiche » : identité et saison | Conséquence de la fiche « La saison » |
| `bin/cockpit-init.php` | Rôles « Site public » et « Client » : droits sur `saison` | Même raison ; pour un site déjà installé : `_outils/migrer-saison.php` |
| `bin/cockpit-init.php` | Pas d'actualités : le rôle « Client » n'a plus de droits sur `articles`, et l'actualité de démonstration du socle n'est plus créée | Le site n'en publie pas (le menu n'affiche « Actualités » que s'il y en a) ; pour un site déjà installé : `_outils/retirer-actualites.php` |
| `bin/cockpit-init.php` | Le rôle « Client » peut supprimer les images (`assets/delete`) | Il ne pouvait pas retirer un fichier envoyé par erreur ; la médiathèque prévient si l'image sert encore. Site déjà installé : `_outils/autoriser-suppression-images.php` |
| `cockpit/models/*.model.php` | Clé `admin` : nom court, icône et groupe de chaque modèle dans la barre latérale de l'administration | Lue par le module Dashboard (`lib/modeles.php`) ; sans elle, une liste va dans « Au quotidien », une fiche unique dans « Le site ». Dans `messages.model.php`, `resume` donne la ligne de détail d'un message (séjour) dans la liste et le tableau de bord |
| `cockpit/models/messages.model.php` | 9 champs du séjour demandé, en lecture seule | Option A : la demande de réservation arrive complète dans « Messages reçus » |
| `cockpit/addons/Contact/bootstrap.php` | Le courriel de notification annonce le séjour en tête ; objet « Demande de réservation » | Option A |
| `cockpit/addons/AdminClient/` (retiré) et `tests/GardeFous/AdminClientTest.php` (remplacé par `DashboardTest.php`) | Le module du socle est fondu dans `cockpit/addons/Dashboard/` : traduction française, notifications traduites, actions masquées aux comptes non administrateurs | Un seul module pour l'administration ; à une fusion du socle, ne pas laisser revenir `AdminClient` (il doublerait la traduction) |
| `src/Contact/Submission.php` | Champ caché `formulaire=reservation` : vérifie et enregistre le séjour ; message facultatif. Un message de contact est traité exactement comme avant | Option A |

Après chaque fusion : `php bin/install-cockpit.php --force`, `php bin/purge-cache.php`, `composer test`.

Nouveau modèle propre au site : `cockpit/models/saison.model.php` (« La saison » : dates, places, heures, horaires de l'accueil).

## Gabarits du socle recopiés (`templates-client/`)

Une correction du socle sur ces fichiers ne s'applique plus : relire `git log socle/main -- <fichier>` après une mise à jour.

| Copie | Original | Pourquoi |
|---|---|---|
| `templates-client/base.html.twig` | `templates/base.html.twig` | Logo et nom, bouton « Réserver », bouton « Menu » |
| `templates-client/partials/pied.html.twig` | `templates/partials/pied.html.twig` | Classement, horaires par saison, lien vers les CGV, crédit « Codé avec ♥ par Pascal Fortunati » |
| `templates-client/confidentialite.html.twig` | `templates/confidentialite.html.twig` | « Ce qui est collecté » : le formulaire de réservation recueille aussi le séjour et le téléphone (option A) ; rubriques en cartes (retour client) |
| `templates-client/mentions-legales.html.twig` | `templates/mentions-legales.html.twig` | Rubriques en cartes, éditeur en liste libellé / valeur (retour client : « trop brouillon ») |
| `templates-client/blocs/hero.html.twig` | `templates/blocs/hero.html.twig` | Logo du site en grand, dans un médaillon, sur le bandeau de la page d'accueil (retour client) |
| `templates-client/page.html.twig` | `templates/page.html.twig` | Mode document : à partir de 5 sections de texte titrées sans image (CGV), feuille centrée et sommaire |
| `templates-client/blocs/texte-image.html.twig` | `templates/blocs/texte-image.html.twig` | Ancre `id` sur la section, pour le sommaire du mode document |

## Fichiers propres au site

- `cockpit/addons/Avatar/` : une photo par compte (« Mon avatar » dans le menu du compte : envoyer une photo ou choisir une image). Le serveur la refait en carré 256 px WebP dans `public/medias/avatars/` (exclu de Git) ; chemin enregistré sur le compte (`avatar`). Remplace les initiales de Cockpit partout, et Passerelle la montre sur le site. Générique : candidat à une PR du socle, séparée de celle de Passerelle.
- `cockpit/addons/Passerelle/`, `public/assets/js/passerelle.js`, `public/assets/css/passerelle.css` : passerelle site ↔ administration. Dans l'administration : « Voir le site » et « Voir cette page ». Sur le site : avatar avec pastille verte pour la personne connectée, qui ouvre l'administration dans un nouvel onglet. L'adresse de l'administration n'est écrite dans aucun fichier public : elle voyage dans un cookie déposé par l'administration dans le seul navigateur connecté (`passerelle`, cookie de session, retiré à la déconnexion). Le site vérifie `/check-session` quand l'administration est sur la même adresse. Générique : candidat à une PR du socle (il faudrait alors ajouter le script à `templates/base.html.twig`).
- `cockpit/addons/Dashboard/` (auteur : Pascal Fortunati) : l'administration aux couleurs du site, en clair ou en sombre, en français, la même pour tous les rôles et générique pour tous les sites du socle. Nom, logo, couleurs et photo lus dans « Identité du site » (fichiers générés dans `public/admin/addons/Dashboard/generated/`). Barre latérale, en-tête et menu du téléphone écrits par le serveur (`lib/interface.php`) ; tableau de bord (`lib/accueil.php`) ; écrans des listes, fiches, médias, compte, verrou et erreurs (`views/`) ; un script commun (`assets/dashboard.js`) et une feuille (`assets/dashboard.css`), les composants Vue dans `assets/vue/`. Remplace les addons `AdminCamping` (propre au site) et `AdminClient` (du socle). Candidat à une PR du socle.
- `cockpit/dashboard.php` : le propre au camping dans le tableau de bord (phrase de la saison, places restantes, raccourcis tarifs, snack, photo d'accueil). Lu par le module Dashboard s'il existe.
  **Sur un serveur déjà installé**, après l'ajout d'un module : supprimer `public/admin/storage/cache/modules.cache.php`, sinon Cockpit ne le charge pas (liste des modules en cache). L'installation ne retire pas un module supprimé : effacer `public/admin/addons/AdminCamping` et `public/admin/addons/AdminClient` à la main.
- `templates-client/blocs/` : `annonce`, `cartes`, `hebergements`, `tarifs`, `reservation`, `carte`, `ardoise`.
- `public/assets/css/client.css` ; `public/assets/fonts/` (Lexend, Fraunces, licences OFL) ; `public/assets/js/menu.js`.
- `tests/Site/ReservationTest.php` : 17 tests de la demande de réservation (et du contact inchangé).

## Section « Carte d'accès »

Une image de la carte, l'adresse en texte et deux boutons vers openstreetmap.org (« Itinéraire », « Voir sur la
carte »). Rien n'est chargé depuis un autre site : la politique de sécurité (`.htaccess`) reste inchangée, aucune
donnée du visiteur ne part chez un tiers tant qu'il ne clique pas, et le vérificateur d'accessibilité reste vert.
Une carte interactive (Leaflet et tuiles OpenStreetMap) demanderait d'ouvrir `img-src` à `tile.openstreetmap.org`
et de compléter la politique de confidentialité ; écartée pour l'instant.

**Données nécessaires** (« Identité du site », groupe Coordonnées) : `latitude` et `longitude` (nombres décimaux,
ex. 44.5442312 et 4.4197889) ; l'adresse postale déjà saisie. Pour les trouver : sur openstreetmap.org, clic droit
sur le lieu, « Afficher l'adresse » : les deux nombres affichés.

**La section** (Pages › la page › Contenu de la page › Add item › « Carte d'accès (OpenStreetMap) ») :

| Champ | Effet |
|---|---|
| Titre, Texte d'introduction | Facultatifs |
| Image + Description de l'image | L'image de la carte (voir ci-dessous) ; la description est obligatoire |
| Afficher la carte | Décoché : la section disparaît, ses réglages restent |
| Afficher le bouton « Itinéraire » | Ouvre `openstreetmap.org/directions` avec le lieu en destination |
| Zoom du lien « Voir sur la carte » | De 5 à 19 (15 par défaut) |

**Cas dégradés** : sans latitude ou longitude valides, pas de boutons (l'image et l'adresse restent) ; sans image ni
coordonnées, la section n'affiche rien. Sans adresse, pas de bloc adresse. Aucun cas ne casse la page.

**L'image de la carte** : sur openstreetmap.org, « Partager », « Image », cocher « Inclure un marqueur », exporter en
PNG ; ou, comme ici, capturer `https://www.openstreetmap.org/export/embed.html?bbox=…&layer=mapnik&marker=LAT,LON`.
Garder la mention « © OpenStreetMap contributors » (licence ODbL) : elle est aussi écrite sous l'image.

**Pour un autre client** : copier `templates-client/blocs/carte.html.twig`, le type `carte` de `pages.model.php`
et les champs `latitude`, `longitude` de `settings.model.php`, puis saisir ses coordonnées et son image : le gabarit
ne contient ni nom, ni adresse, ni coordonnée. À proposer au socle pour qu'il en dispose d'office.

## À prévoir chez l'hébergeur

- PHP 8.3 ou plus, avec `pdo_sqlite`, `gd`, `curl`, `fileinfo`, `zip`, `openssl`, `mbstring`.
- `date.timezone = Europe/Paris` : sinon l'heure d'arrivée des messages est en temps universel.
- Ce que demande la politique de contenu du socle est respecté : polices, script du menu et images servis par le site lui-même.
