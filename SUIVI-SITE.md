# Suivi du site — Camping Les Chênes Verts

Site créé depuis le socle `jean-ely-pro/site-vitrine-cockpit-php` (version 2.0.6), dépôt distant `socle`.
Ce fichier liste ce qui s'écarte du socle, pour relire chaque fusion d'une mise à jour.

## Fichiers du socle modifiés

À relire à chaque fusion : un conflit y est possible. Les ajouts sont regroupés entre deux commentaires
`CAMPING LES CHÊNES VERTS` … `fin CAMPING LES CHÊNES VERTS`.

| Fichier | Modification | Pourquoi |
|---|---|---|
| `cockpit/models/pages.model.php` | 5 types de section (`annonce`, `cartes`, `hebergements`, `tarifs`, `reservation`) et leurs champs | Support 06 : seul fichier partagé prévu par le socle |
| `cockpit/models/settings.model.php` | 7 champs de l'identité : `classement`, `saisonOuverture`, `saisonFermeture`, `placesDisponibles`, `heureArrivee`, `heureDepart`, `horairesSaisons` | Décisions A1, A2, A6 : saisis une seule fois, affichés sur plusieurs pages |
| `cockpit/models/messages.model.php` | 9 champs du séjour demandé, en lecture seule | Option A : la demande de réservation arrive complète dans « Messages reçus » |
| `cockpit/addons/Contact/bootstrap.php` | Le courriel de notification annonce le séjour en tête ; objet « Demande de réservation » | Option A |
| `src/Contact/Submission.php` | Champ caché `formulaire=reservation` : vérifie et enregistre le séjour ; message facultatif. Un message de contact est traité exactement comme avant | Option A |

Après chaque fusion : `php bin/install-cockpit.php --force`, `php bin/purge-cache.php`, `composer test`.

## Gabarits du socle recopiés (`templates-client/`)

Une correction du socle sur ces fichiers ne s'applique plus : relire `git log socle/main -- <fichier>` après une mise à jour.

| Copie | Original | Pourquoi |
|---|---|---|
| `templates-client/base.html.twig` | `templates/base.html.twig` | Logo et nom, bouton « Réserver », bouton « Menu » |
| `templates-client/partials/pied.html.twig` | `templates/partials/pied.html.twig` | Classement, horaires par saison, lien vers les CGV |

## Fichiers propres au site

- `templates-client/blocs/` : `annonce`, `cartes`, `hebergements`, `tarifs`, `reservation`.
- `public/assets/css/client.css` ; `public/assets/fonts/` (Lexend, Fraunces, licences OFL) ; `public/assets/js/menu.js`.
- `tests/Site/ReservationTest.php` : 17 tests de la demande de réservation (et du contact inchangé).

## À prévoir chez l'hébergeur

- PHP 8.3 ou plus, avec `pdo_sqlite`, `gd`, `curl`, `fileinfo`, `zip`, `openssl`, `mbstring`.
- `date.timezone = Europe/Paris` : sinon l'heure d'arrivée des messages est en temps universel.
- Ce que demande la politique de contenu du socle est respecté : polices, script du menu et images servis par le site lui-même.
