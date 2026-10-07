# Contribuer

Ce dépôt est un socle recopié chez des clients. Une modification fusionnée ici finit sur des
sites en service : les règles qui suivent existent pour ça, pas pour la forme.

## Avant de coder

**Ouvrez une issue.** Même pour une correction évidente. Elle sert à vérifier que le défaut est
bien là où on le croit, et elle devient la trace que quelqu'un relira dans six mois.

Pour un défaut, l'issue doit permettre de le reproduire depuis une installation neuve. Un
défaut qu'on ne sait pas reproduire ne peut être ni constaté avant correction, ni vérifié
après.

## Une proposition traite un sujet

Une correction, une évolution, une mise à jour de documentation. Pas deux.

C'est la règle la plus souvent enfreinte, et celle qui coûte le plus cher : une proposition qui
mélange deux sujets oblige à tout refuser pour un seul des deux, ou à tout accepter sans avoir
relu le second.

## Le circuit

```bash
git checkout -b fix-intitule-du-probleme
# travailler
composer test
git push -u origin fix-intitule-du-probleme
```

Préfixes utilisés : `fix-` pour une correction, `feat-` pour une capacité nouvelle, `docs-`
pour la documentation, `chore-` pour l'outillage. Ensuite un intitulé en français, en
minuscules, séparé par des tirets.

Les commits sont **en français, à l'impératif** : « Corriger la boucle des observateurs », pas
« correction de la boucle » ni « fix observers ». Le corps du message dit pourquoi, pas
comment : le comment est dans le diff.

## La langue

| Où | Langue |
|---|---|
| `src/`, `cockpit/` : code, noms et commentaires | anglais |
| `tests/` : noms de méthodes et commentaires | français |
| `docs/`, journal, `.github/` | français |
| Commits, propositions, issues | français |

L'administration et le site public sont en français, comme tout ce qui est lu par un client ou
par l'équipe. Le code du produit reste en anglais.

Aucune mention d'outil, aucun co-auteur automatique dans les commits et les propositions.

## Avant de pousser

```bash
composer test
```

Si la modification touche `cockpit/`, elle n'atteint l'administration qu'après recopie :

```bash
php bin/install-cockpit.php --force
```

`public/admin/` n'est pas versionné. Modifier un fichier à cet endroit ne sert à rien, il sera
écrasé à la prochaine installation. Les sources sont dans `cockpit/`.

## Version et journal

**Une proposition qui change le produit porte son numéro.** Elle modifie `VERSION` et ajoute
son entrée dans [CHANGELOG.md](CHANGELOG.md).

| La modification touche | `VERSION` et `CHANGELOG.md` |
|---|---|
| `src/`, `templates/`, `templates-client/`, `cockpit/`, `public/`, `bin/`, `docs/` | **oui** |
| l'outillage du projet : `.github/`, ce fichier, `CONTRIBUTORS.md` | **non**, rien à faire |

Le contrôle décide d'après les dossiers touchés : une proposition d'outillage passe sans que
rien ne lui soit demandé.

Les numéros suivent le versionnage sémantique, avec le sens décrit en tête du journal : majeur
quand un site demande une intervention manuelle, mineur pour une capacité nouvelle, correctif
pour une correction.

### L'étiquette `sans version`

Elle sert à une modification **dans les dossiers du produit** qui ne mérite pas d'être
racontée : une faute dans un commentaire, un lien mort. Jamais à un changement de comportement
ni à une instruction corrigée.

Une modification fusionnée sans version n'est pas perdue pour autant : elle part chez les sites
avec la version suivante, puisqu'une étiquette embarque tout l'historique qui la précède. Ce
qu'elle n'a pas, c'est sa mention dans le journal.

Dans le doute, publier la version. Un numéro ne coûte rien ; une correction qui arrive chez un
client sans être documentée coûte une enquête.

### La forme de l'entrée

```markdown
## 2.0.13 — 2026-10-12

Ce qui ne fonctionnait pas, ou ce qui manquait, en une ou deux phrases.

**Ce qui change.** Le détail, du point de vue de la personne qui exploite un site.

Rien à faire sur un site existant au-delà de la fusion.
```

Trois règles, vérifiées automatiquement :

- le titre reprend exactement le numéro de `VERSION`, suivi de la date au format `AAAA-MM-JJ` ;
- cette entrée est la première du journal ;
- elle dit ce qu'un **site existant** doit faire. Le plus souvent « Rien à faire sur un site
  existant au-delà de la fusion », ou la commande exacte quand `cockpit/` est touché :
  `php bin/install-cockpit.php --force`.

Une entrée issue d'une contribution extérieure se termine par « Proposé par Prénom Nom (#NN) ».

### Après la fusion

Rien à faire. Dès que `VERSION` change sur `main`, l'étiquette est posée, la version publiée et
sa discussion d'annonce ouverte.

## Ce que la relecture vérifie

- La suite de tests passe, et le contrôle automatique est vert.
- Le périmètre est tenu : un sujet.
- Les règles du socle sont respectées. Les principales : aucune ressource chargée depuis un
  autre site, le contenu présent dans le HTML de la première réponse, un contraste d'au moins
  4,5:1, pas de transparence sur du texte, `alt` obligatoire sur les médias.
- Aucun secret dans le dépôt : ni `.env`, ni clé, ni base de données.

## Discuter avant de décider

Une décision qui change le socle se discute dans les Discussions avant d'être codée, et sa
conclusion est recopiée dans l'issue. Une décision qui vit dans un fil de discussion est une
décision que personne ne retrouvera.

## Relecture et fusion

Les propositions sont relues avant fusion. Une relecture qui demande une modification n'est pas
un refus : elle dit ce qui manque pour que la proposition entre.

Merci de contribuer. Les apports sont crédités dans [CONTRIBUTORS.md](CONTRIBUTORS.md) et
nommés dans le journal des versions.
