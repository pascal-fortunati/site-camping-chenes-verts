<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Les refus de la commande de mise à jour.
 *
 * Elle fusionne une version du socle dans un site en service : ce qu'elle
 * refuse de faire compte autant que ce qu'elle fait.
 */
final class MajSocleTest extends TestCase
{
    /** @return array{0: string, 1: int} la sortie et le code de retour */
    private function lancer(string $arguments = ''): array
    {
        $script = dirname(__DIR__, 2).'/bin/maj-socle.php';
        $sortie = [];
        $code = 0;

        exec('php '.escapeshellarg($script).' '.$arguments.' 2>&1', $sortie, $code);

        return [implode("\n", $sortie), $code];
    }

    #[Test]
    public function un_numero_de_version_mal_forme_est_refuse(): void
    {
        [$sortie, $code] = $this->lancer('--vers=deux');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('X.Y.Z', $sortie);
    }

    #[Test]
    public function le_script_est_syntaxiquement_valide(): void
    {
        $script = dirname(__DIR__, 2).'/bin/maj-socle.php';
        $sortie = [];
        $code = 0;

        exec('php -l '.escapeshellarg($script).' 2>&1', $sortie, $code);

        $this->assertSame(0, $code, implode("\n", $sortie));
    }

    #[Test]
    public function une_version_majeure_est_refusee(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/bin/maj-socle.php');

        $this->assertStringContainsString('majSocleEstMajeure', $source);
        $this->assertStringContainsString(
            'est majeure : elle demande une intervention manuelle',
            $source,
            'une version majeure demande une intervention décrite dans le journal',
        );
    }

    #[Test]
    public function un_arbre_de_travail_sale_arrete_la_commande(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/bin/maj-socle.php');

        $this->assertStringContainsString("git status --porcelain", $source);
        $this->assertStringContainsString("n'est pas propre", $source);
    }

    #[Test]
    public function la_commande_ne_pousse_ni_ne_fusionne_dans_main(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/bin/maj-socle.php');

        $this->assertStringNotContainsString('git push', str_replace(
            'git push -u origin',
            '',
            $source,
        ), 'la commande affiche la poussée à faire, elle ne la fait pas');

        $this->assertStringNotContainsString('git checkout main', $source);
    }
}
