# Contributeurs

Les personnes dont le travail est entré dans le socle, par ordre d'arrivée.

## Céline Devaux

[@Celinedev1201](https://github.com/Celinedev1201)

**Version 2.0.7** : a identifié et corrigé le blocage de la page « Identité du site »
([#42](https://github.com/jean-ely-pro/site-vitrine-cockpit-php/pull/42)).

Le défaut était invisible aux tests : le serveur répondait normalement, et c'est le navigateur
qui se figeait. Deux scripts de l'administration modifiaient le DOM à chaque passage de leur
propre observateur, qui se réveillait donc lui-même sans fin. Le diagnostic valait autant que la
correction.

## Pascal Fortunati

[@Zeigadis](https://github.com/Zeigadis)

**Version 2.0.8** : administration en français, 530 traductions, et masquage des actions que
Cockpit affichait aux comptes non administrateurs avant de les refuser au clic
([#44](https://github.com/jean-ely-pro/site-vitrine-cockpit-php/pull/44)). A corrigé au passage
le cache des modules, qui empêchait un addon nouvellement installé d'être chargé sur un site en
service.

**Version 2.0.9** : passerelle entre le site public et l'administration, sans écrire l'adresse
de l'administration dans un seul fichier public
([#49](https://github.com/jean-ely-pro/site-vitrine-cockpit-php/pull/49)).

Également à l'origine des propositions
[#52](https://github.com/jean-ely-pro/site-vitrine-cockpit-php/issues/52) et
[#54](https://github.com/jean-ely-pro/site-vitrine-cockpit-php/issues/54).

## Jean-Ely Gendrau

[@jean-ely-pro](https://github.com/jean-ely-pro)

Conception et maintenance du socle.

---

Pour figurer ici, voir [CONTRIBUTING.md](CONTRIBUTING.md).
