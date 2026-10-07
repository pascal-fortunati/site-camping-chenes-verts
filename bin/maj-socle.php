<?php

declare(strict_types=1);

/**
 * Updates a site from the socle.
 *
 * Without an argument: the version installed, the versions available, and what
 * separates them. With --vers: merges that version on a branch of its own and
 * runs the four commands that follow a merge.
 *
 * The script refuses a major version: those ask for a manual operation
 * described under the version in the journal.
 *
 * Usage:
 *   php bin/maj-socle.php
 *   php bin/maj-socle.php --vers=2.0.13
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

const MAJ_SOCLE_DISTANT = 'socle';

function majSocleRacine(): string
{
    return dirname(__DIR__);
}

/** Runs a command in the project and returns its output, or null on failure. */
function majSocleLire(string $commande): ?string
{
    $sortie = [];
    $code = 0;
    exec('cd '.escapeshellarg(majSocleRacine()).' && '.$commande.' 2>&1', $sortie, $code);

    return $code === 0 ? implode("\n", $sortie) : null;
}

/** Runs a command and shows its output as it comes. */
function majSocleExecuter(string $commande): bool
{
    $code = 0;
    passthru('cd '.escapeshellarg(majSocleRacine()).' && '.$commande, $code);

    return $code === 0;
}

function majSocleVersionValide(string $version): bool
{
    return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
}

/** A major version changes the installation itself: it is never automatic. */
function majSocleEstMajeure(string $courante, string $visee): bool
{
    return (int) explode('.', $visee)[0] > (int) explode('.', $courante)[0];
}

/** @return list<string> the socle versions, oldest first */
function majSocleVersions(): array
{
    $brut = majSocleLire('git tag -l "v*" --sort=v:refname') ?? '';
    $versions = [];

    foreach (explode("\n", $brut) as $ligne) {
        $version = ltrim(trim($ligne), 'v');

        if (majSocleVersionValide($version)) {
            $versions[] = $version;
        }
    }

    return $versions;
}

/** @return list<string> the journal titles between two versions, newest first */
function majSocleEntreesJournal(string $journal, string $depuis, string $jusqua): array
{
    preg_match_all('/^## (\d+\.\d+\.\d+) — (\S+)/m', $journal, $trouves, PREG_SET_ORDER);
    $entrees = [];

    foreach ($trouves as [, $version, $date]) {
        if (version_compare($version, $depuis, '>') && version_compare($version, $jusqua, '<=')) {
            $entrees[] = "{$version}  ({$date})";
        }
    }

    return $entrees;
}

function majSocleArret(string $message): never
{
    fwrite(STDERR, "\n  {$message}\n\n");
    exit(1);
}

// --- L'argument, avant tout le reste ----------------------------------------

$visee = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--vers=')) {
        $visee = substr($argument, 7);
    }
}

if ($visee !== null && !majSocleVersionValide($visee)) {
    majSocleArret("« {$visee} » n'est pas un numéro de version. Forme attendue : X.Y.Z");
}

// --- Contrôles communs ------------------------------------------------------

$racine = majSocleRacine();

if (majSocleLire('git rev-parse --is-inside-work-tree') === null) {
    majSocleArret("Ce dossier n'est pas un dépôt git.");
}

if (majSocleLire('git remote get-url '.MAJ_SOCLE_DISTANT) === null) {
    majSocleArret(
        "Le dépôt distant « ".MAJ_SOCLE_DISTANT." » n'est pas déclaré. À faire une fois :\n\n"
        ."    git remote add ".MAJ_SOCLE_DISTANT." <adresse-du-socle>\n"
        ."    git fetch ".MAJ_SOCLE_DISTANT." --tags"
    );
}

$courante = trim((string) @file_get_contents("{$racine}/VERSION"));

if (!majSocleVersionValide($courante)) {
    majSocleArret("Le fichier VERSION ne contient pas un numéro de la forme X.Y.Z.");
}

echo "\nMise à jour depuis le socle\n\n";
echo "  Version installée : {$courante}\n";

echo "  Récupération des versions du socle...\n";
majSocleLire('git fetch '.MAJ_SOCLE_DISTANT.' --tags --quiet');

$versions = majSocleVersions();
$dernieres = array_values(array_filter($versions, fn (string $v) => version_compare($v, $courante, '>')));

// --- Sans argument : l'état des lieux ---------------------------------------

if ($visee === null) {
    if ($dernieres === []) {
        echo "\n  Le site est à jour.\n\n";
        exit(0);
    }

    echo "\n  Versions plus récentes : ".implode(', ', $dernieres)."\n";

    // The site's own journal stops at its version: the entries that follow are
    // read from the socle, at the latest version.
    $derniere = end($dernieres);
    $journal = majSocleLire('git show '.escapeshellarg("v{$derniere}:CHANGELOG.md")) ?? '';
    $entrees = majSocleEntreesJournal($journal, $courante, $derniere);

    if ($entrees !== []) {
        echo "\n  Ce qui sépare le site de la dernière version :\n";

        foreach ($entrees as $entree) {
            echo "    {$entree}\n";
        }
    }

    echo "\n  Lire le détail dans CHANGELOG.md, puis :\n";
    echo "    php bin/maj-socle.php --vers=".end($dernieres)."\n\n";
    exit(0);
}

// --- Avec --vers : la mise à jour -------------------------------------------

if (!in_array($visee, $versions, true)) {
    majSocleArret(
        "La version {$visee} n'existe pas.\n\n"
        ."  Versions disponibles : ".($versions === [] ? 'aucune' : implode(', ', $versions))
    );
}

if (version_compare($visee, $courante, '<=')) {
    majSocleArret("Le site est déjà en {$courante} : la version {$visee} ne lui apporterait rien.");
}

if (majSocleEstMajeure($courante, $visee)) {
    majSocleArret(
        "La version {$visee} est majeure : elle demande une intervention manuelle.\n\n"
        ."  La marche à suivre est décrite sous cette version dans CHANGELOG.md,\n"
        ."  avec son retour arrière. Cette commande ne la remplace pas."
    );
}

if (majSocleLire('git status --porcelain') !== '') {
    majSocleArret(
        "L'arbre de travail n'est pas propre. Committer ou remiser avant de fusionner :\n\n"
        .(majSocleLire('git status --short') ?? '')
    );
}

$branche = "maj-socle-{$visee}";

if (majSocleLire('git rev-parse --verify '.escapeshellarg($branche)) !== null) {
    majSocleArret("La branche {$branche} existe déjà. La terminer ou la supprimer d'abord.");
}

echo "\n  Branche {$branche}\n";

if (!majSocleExecuter('git checkout -b '.escapeshellarg($branche))) {
    majSocleArret("La branche n'a pas pu être créée.");
}

// A repository created from the template starts on a history of its own: the
// first merge has no common ancestor with the socle.
$option = majSocleLire('git merge-base HEAD '.escapeshellarg("v{$visee}")) === null
    ? ' --allow-unrelated-histories'
    : '';

echo "  Fusion de v{$visee}\n\n";

if (!majSocleExecuter('git merge '.escapeshellarg("v{$visee}").$option)) {
    $conflits = majSocleLire('git diff --name-only --diff-filter=U') ?? '';

    majSocleArret(
        "La fusion s'est arrêtée sur un conflit. Fichiers à reprendre :\n\n"
        .$conflits."\n\n"
        ."  Les garder tous les deux, puis : git add <fichier> && git commit"
    );
}

// --- Après la fusion --------------------------------------------------------

$commandes = [
    'composer install' => 'Dépendances',
    'php bin/install-cockpit.php --force' => 'Administration',
    'php bin/purge-cache.php' => 'Cache',
];

// Installed with --no-dev, a site has no test runner: the step is skipped
// rather than failing on a tool that was deliberately left out.
if (is_file("{$racine}/vendor/bin/phpunit") || is_file("{$racine}/vendor/bin/phpunit.bat")) {
    $commandes['composer test'] = 'Tests';
}

foreach ($commandes as $commande => $etape) {
    echo "\n  {$etape} : {$commande}\n\n";

    if (!majSocleExecuter($commande)) {
        majSocleArret(
            "« {$commande} » a échoué.\n\n"
            ."  La fusion est faite et la branche {$branche} est en place : corriger la cause,\n"
            ."  relancer cette commande seule, puis continuer."
        );
    }
}

if (!isset($commandes['composer test'])) {
    echo "\n  Tests ignorés : PHPUnit n'est pas installé sur ce site.\n";
}

echo "\n  Site en {$visee}, sur la branche {$branche}.\n";
echo "  Ouvrir une page du site et /admin, puis pousser pour relecture :\n\n";
echo "    git push -u origin {$branche}\n\n";
