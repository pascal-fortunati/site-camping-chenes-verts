<?php

declare(strict_types=1);

namespace Modules;

/**
 * Les addons présents, leur fiche (addon.json) et leur état : activé ou désactivé.
 *
 * L'état est un fichier JSON ({"desactives": ["Avatar", …]}) que la configuration de Cockpit lit à chaque
 * requête, avant le chargement des addons (clé « modules.disabled », prévue par Cockpit) : un addon désactivé
 * n'est tout simplement pas chargé. Ses fichiers et ses données restent en place ; le réactiver suffit.
 *
 * Aucune dépendance à Cockpit : la classe se teste seule.
 *
 * @package Modules
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */
final class Registre
{
    /** Toujours actifs : l'administration elle-même et l'écran qui gère les modules. */
    public const PROTEGES = ['Dashboard', 'Modules'];

    public function __construct(
        private readonly string $dossierAddons,
        private readonly string $fichierEtat,
    ) {
    }

    /**
     * Les addons présents, triés par catégorie puis par nom.
     *
     * @return list<array{nom: string, titre: string, description: string, version: string, auteur: string, lien: string,
     *     categorie: string, dependances: list<string>, modeles: list<string>, obligatoire: bool, actif: bool, fiche: bool,
     *     manquantes: list<string>, inactives: list<string>, dependants: list<string>}>
     */
    public function modules(): array
    {
        $desactives = $this->desactives();
        $modules = [];

        foreach ($this->dossiers() as $nom) {
            $fiche = $this->fiche($nom);
            $modules[$nom] = [
                'nom' => $nom,
                'titre' => (string) ($fiche['nom'] ?? $nom),
                'description' => (string) ($fiche['description'] ?? ''),
                'version' => (string) ($fiche['version'] ?? ''),
                'auteur' => (string) ($fiche['auteur'] ?? ''),
                'lien' => (string) ($fiche['lien'] ?? ''),
                'categorie' => (string) ($fiche['categorie'] ?? 'Autres'),
                // Un addon ne dépend pas de lui-même (fiche recopiée d'un autre module, par exemple).
                'dependances' => array_values(array_unique(array_diff(array_filter((array) ($fiche['dependances'] ?? []), 'is_string'), [$nom]))),
                'modeles' => array_values(array_filter((array) ($fiche['modeles'] ?? []), 'is_string')),
                'obligatoire' => in_array($nom, self::PROTEGES, true) || !empty($fiche['obligatoire']),
                'actif' => !in_array($nom, $desactives, true),
                'fiche' => $fiche !== null,
                'manquantes' => [],
                'inactives' => [],
                'dependants' => [],
            ];
        }

        foreach ($modules as $nom => &$m) {
            foreach ($m['dependances'] as $dep) {
                if (!isset($modules[$dep])) {
                    $m['manquantes'][] = $dep;
                } elseif (!$modules[$dep]['actif']) {
                    $m['inactives'][] = $dep;
                }
                if (isset($modules[$dep]) && $m['actif']) {
                    $modules[$dep]['dependants'][] = $nom;
                }
            }
        }
        unset($m);

        $ordre = ['Administration' => 0, 'Site' => 1, 'Contenu' => 2, 'Commerce' => 3];
        uasort($modules, static fn (array $a, array $b): int => [$ordre[$a['categorie']] ?? 9, $a['titre']] <=> [$ordre[$b['categorie']] ?? 9, $b['titre']]);

        return array_values($modules);
    }

    /**
     * Active ou désactive un addon.
     *
     * @return string|null le motif du refus, ou null si c'est fait
     */
    public function changer(string $nom, bool $actif, string $par = ''): ?string
    {
        $modules = array_column($this->modules(), null, 'nom');
        $m = $modules[$nom] ?? null;

        if ($m === null) {
            return "Le module « {$nom} » n’existe pas.";
        }
        if ($m['actif'] === $actif) {
            return null;
        }
        if (!$actif && $m['obligatoire']) {
            return "« {$m['titre']} » est indispensable à l’administration : il reste toujours actif.";
        }
        if (!$actif && $m['dependants'] !== []) {
            return "« {$m['titre']} » est utilisé par ".$this->liste(array_map(static fn (string $d): string => $modules[$d]['titre'], $m['dependants']))
                .' : désactivez d’abord '.(count($m['dependants']) > 1 ? 'ces modules' : 'ce module').'.';
        }
        if ($actif && $m['manquantes'] !== []) {
            return "« {$m['titre']} » a besoin de ".$this->liste($m['manquantes']).', qui n’est pas installé.';
        }
        if ($actif && $m['inactives'] !== []) {
            return "« {$m['titre']} » a besoin de ".$this->liste(array_map(static fn (string $d): string => $modules[$d]['titre'], $m['inactives'])).' : activez-le d’abord.';
        }

        $desactives = $this->desactives();
        $desactives = $actif ? array_values(array_diff($desactives, [$nom])) : array_values(array_unique([...$desactives, $nom]));
        sort($desactives);

        return $this->ecrire($desactives, $par) ? null : 'L’état des modules n’a pas pu être enregistré (droits d’écriture du dossier).';
    }

    /**
     * Les modèles de contenu des modules désactivés (clé « modeles » de leur fiche) : ils sont masqués dans
     * l'administration, sans que leurs données soient touchées.
     *
     * @return list<string>
     */
    public function modelesMasques(): array
    {
        $masques = [];
        foreach ($this->desactives() as $nom) {
            $fiche = $this->fiche($nom);
            foreach ((array) ($fiche['modeles'] ?? []) as $modele) {
                if (is_string($modele)) {
                    $masques[] = $modele;
                }
            }
        }

        return array_values(array_unique($masques));
    }

    /** @return list<string> les addons désactivés qui existent encore, jamais un addon protégé */
    public function desactives(): array
    {
        // Cockpit change les avertissements en erreurs, même masqués : le fichier est testé avant d'être lu.
        $etat = is_file($this->fichierEtat) ? json_decode((string) file_get_contents($this->fichierEtat), true) : null;
        $liste = is_array($etat) && is_array($etat['desactives'] ?? null) ? $etat['desactives'] : [];

        return array_values(array_intersect(
            array_diff(array_filter($liste, 'is_string'), self::PROTEGES),
            $this->dossiers(),
        ));
    }

    /** @return list<string> les noms des dossiers d'addons */
    private function dossiers(): array
    {
        $noms = [];
        foreach (glob(rtrim($this->dossierAddons, '/').'/*', GLOB_ONLYDIR) ?: [] as $dossier) {
            if (is_file("{$dossier}/bootstrap.php")) {
                $noms[] = basename($dossier);
            }
        }
        sort($noms);

        return $noms;
    }

    /** @return array<string, mixed>|null le contenu de addon.json, s'il existe et se lit */
    private function fiche(string $nom): ?array
    {
        $fichier = rtrim($this->dossierAddons, '/')."/{$nom}/addon.json";
        if (!is_file($fichier)) {
            return null;
        }
        $fiche = json_decode((string) file_get_contents($fichier), true);

        return is_array($fiche) ? $fiche : null;
    }

    /** @param list<string> $desactives */
    private function ecrire(array $desactives, string $par): bool
    {
        $dossier = dirname($this->fichierEtat);
        if (!is_dir($dossier) && !@mkdir($dossier, 0o755, true) && !is_dir($dossier)) {
            return false;
        }
        $json = json_encode(['desactives' => $desactives, 'modifie' => date('c'), 'par' => $par], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Écrit à côté puis déplacé : une requête ne lit jamais un fichier à moitié écrit.
        $temporaire = $this->fichierEtat.'.'.bin2hex(random_bytes(4));

        return @file_put_contents($temporaire, $json."\n", LOCK_EX) !== false && @rename($temporaire, $this->fichierEtat);
    }

    /** @param list<string> $noms */
    private function liste(array $noms): string
    {
        $noms = array_map(static fn (string $n): string => "« {$n} »", $noms);
        $dernier = array_pop($noms);

        return $noms === [] ? (string) $dernier : implode(', ', $noms).' et '.$dernier;
    }
}
