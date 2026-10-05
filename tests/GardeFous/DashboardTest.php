<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Module Dashboard : l'administration en français, la même pour tous les sites du socle.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 */
final class DashboardTest extends TestCase
{
    private const MODULE = __DIR__.'/../../cockpit/addons/Dashboard';

    /**
     * @return array<mixed>
     */
    private function traduction(): array
    {
        return include self::MODULE.'/i18n/fr.php';
    }

    /**
     * @return list<string> les fichiers PHP, JS et CSS du module
     */
    private function fichiers(): array
    {
        $liste = [];
        $parcours = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MODULE, \FilesystemIterator::SKIP_DOTS));
        foreach ($parcours as $f) {
            if (in_array($f->getExtension(), ['php', 'js', 'css'], true) && !str_contains($f->getPathname(), 'generated')) {
                $liste[] = $f->getPathname();
            }
        }
        sort($liste);

        return $liste;
    }

    #[Test]
    public function la_traduction_est_un_tableau_de_textes(): void
    {
        $traduction = $this->traduction();

        $this->assertGreaterThan(400, count($traduction), 'la traduction semble tronquée');

        foreach ($traduction as $anglais => $francais) {
            $this->assertIsString($anglais);
            $this->assertIsString($francais, "« {$anglais} » n’a pas de traduction");
            $this->assertNotSame('', trim($francais), "« {$anglais} » est traduit par un texte vide");
        }
    }

    #[Test]
    public function les_boutons_du_quotidien_sont_traduits(): void
    {
        $traduction = $this->traduction();

        foreach (['Save' => 'Enregistrer', 'Update item' => 'Enregistrer', 'Close' => 'Fermer', 'All fields' => 'Tous les champs'] as $anglais => $francais) {
            $this->assertSame($francais, $traduction[$anglais] ?? null, "« {$anglais} »");
        }
    }

    #[Test]
    public function le_masquage_ne_vise_que_les_comptes_non_administrateurs(): void
    {
        $interface = (string) file_get_contents(self::MODULE.'/lib/interface.php');

        $this->assertStringContainsString("!== 'admin') ? 'dashboard-client'", $interface, 'l’administrateur doit garder toutes ses actions');
    }

    #[Test]
    public function l_installation_vide_le_cache_des_modules(): void
    {
        $script = (string) file_get_contents(__DIR__.'/../../bin/install-cockpit.php');

        $this->assertStringContainsString('modules.cache.php', $script, 'sans cela, un module ajouté à un site en service n’est jamais chargé');
    }

    #[Test]
    public function chaque_fichier_porte_l_auteur(): void
    {
        foreach ($this->fichiers() as $f) {
            if (str_contains($f, 'i18n')) {
                continue;
            }
            $texte = (string) file_get_contents($f);
            $this->assertStringContainsString('@package Dashboard', $texte, basename($f));
            $this->assertStringContainsString('@author  Pascal Fortunati', $texte, basename($f));
        }
    }

    #[Test]
    public function le_module_ne_contient_rien_de_propre_a_un_site(): void
    {
        foreach ($this->fichiers() as $f) {
            if (str_contains($f, 'i18n')) {
                continue;
            }
            $texte = (string) file_get_contents($f);
            foreach (['camping', 'snack', 'Chênes', 'saison'] as $mot) {
                $this->assertDoesNotMatchRegularExpression('/'.$mot.'/iu', preg_replace("/'libelle' => '[^']*'/", '', $texte), basename($f)." : « {$mot} » doit aller dans cockpit/dashboard.php ou dans le modèle");
            }
        }
    }

    #[Test]
    public function les_addons_enrichissent_le_tableau_de_bord(): void
    {
        $accueil = (string) file_get_contents(self::MODULE.'/lib/accueil.php');

        $this->assertStringContainsString("trigger('dashboard.accueil', [&\$accueil", $accueil, 'événement dashboard.accueil, par référence');
        $this->assertStringContainsString("\$reglage['cartes']", $accueil, 'cartes du réglage du site');
    }

    #[Test]
    public function les_widgets_des_autres_addons_sont_gardes(): void
    {
        $amorce = (string) file_get_contents(self::MODULE.'/bootstrap.php');

        $this->assertStringContainsString("'dashboard-content-widget'", $amorce, 'seuls les widgets de Cockpit sont retirés');
        $this->assertStringContainsString('$autres', $amorce, 'les autres widgets restent');
    }

    #[Test]
    public function l_aiguillage_vise_des_exports_qui_existent(): void
    {
        $script = (string) file_get_contents(self::MODULE.'/assets/dashboard.js');
        preg_match_all('#dashboard:assets/vue/(\w+)\.js\#(\w+)#', $script, $cibles, PREG_SET_ORDER);
        preg_match_all("#'[\w-]+': '(\w+)'#", (string) strstr((string) strstr($script, 'const CHAMPS'), '};', true), $champs);

        $this->assertNotEmpty($cibles);
        foreach ($cibles as [, $fichier, $nom]) {
            $this->assertStringContainsString("export async function {$nom}()", (string) file_get_contents(self::MODULE."/assets/vue/{$fichier}.js"), "{$fichier}.js#{$nom}");
        }
        $this->assertCount(9, $champs[1]);
        foreach ($champs[1] as $nom) {
            $this->assertStringContainsString("export async function {$nom}()", (string) file_get_contents(self::MODULE.'/assets/vue/champs.js'), "champs.js#{$nom}");
        }
    }
}
