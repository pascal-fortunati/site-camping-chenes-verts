<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The customer's admin in French. Cockpit ships no translation: a broken file
 * would silently put the whole admin back into English.
 */
final class AdminClientTest extends TestCase
{
    private const ADDON = __DIR__.'/../../cockpit/addons/AdminClient';

    /** @return array<mixed> */
    private function traduction(): array
    {
        return include self::ADDON.'/i18n/fr.php';
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
        $amorce = (string) file_get_contents(self::ADDON.'/bootstrap.php');

        $this->assertStringContainsString("!== 'admin'", $amorce, 'l’administrateur doit garder toutes ses actions');
    }

    #[Test]
    public function l_installation_vide_le_cache_des_modules(): void
    {
        $script = (string) file_get_contents(__DIR__.'/../../bin/install-cockpit.php');

        $this->assertStringContainsString('modules.cache.php', $script, 'sans cela, un addon ajouté à un site en service n’est jamais chargé');
    }
}
