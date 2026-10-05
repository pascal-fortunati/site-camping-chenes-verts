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

| La modification | `VERSION` et `CHANGELOG.md` |
|---|---|
| change ce qu'un site reçoit ou exige | **oui**, avec une entrée qui dit ce qu'un site existant doit faire |
| ne touche que l'outillage du projet (intégration continue, modèles d'issue, ce fichier) | **non** |

Un test refuse que `VERSION` et la première entrée du journal annoncent deux numéros
différents.

Les numéros suivent le versionnage sémantique, avec le sens décrit en tête de
[CHANGELOG.md](CHANGELOG.md) : majeur quand un site demande une intervention manuelle, mineur
pour une capacité nouvelle, correctif pour une correction.

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
