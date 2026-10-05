<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The bridge between the public site and the admin. Its one promise to visitors:
 * nothing — no button, and no address of the admin in any public file.
 */
final class PasserelleTest extends TestCase
{
    private const RACINE = __DIR__.'/../..';

    private function fichier(string $chemin): string
    {
        return (string) file_get_contents(self::RACINE.'/'.$chemin);
    }

    #[Test]
    public function chaque_page_charge_la_passerelle(): void
    {
        $this->assertStringContainsString('src="/assets/js/passerelle.js" defer', $this->fichier('templates/base.html.twig'));
    }

    #[Test]
    public function aucun_fichier_public_ne_donne_l_adresse_de_l_administration(): void
    {
        foreach (['public/assets/js/passerelle.js', 'public/assets/css/passerelle.css'] as $chemin) {
            $this->assertDoesNotMatchRegularExpression('#/admin\b#', $this->fichier($chemin), "{$chemin} révélerait l’adresse de l’administration");
        }
    }

    #[Test]
    public function sans_cookie_le_site_n_affiche_rien(): void
    {
        $script = $this->fichier('public/assets/js/passerelle.js');

        $this->assertMatchesRegularExpression('/if \(!brut\) \{\s*return;/', $script, 'sans le cookie de l’administration, le script doit s’arrêter tout de suite');
    }

    #[Test]
    public function le_cookie_est_retire_a_la_deconnexion(): void
    {
        $amorce = $this->fichier('cockpit/addons/Passerelle/bootstrap.php');

        $this->assertStringContainsString("'expires' => time() - 3600", $amorce, 'sans session, le cookie doit être effacé');
        $this->assertStringContainsString("'samesite' => 'Lax'", $amorce);
    }
}
