<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * L'accord entre le fichier VERSION et le journal des versions.
 *
 * Un site lit VERSION pour savoir où il en est, et c'est la première étape de
 * la mise à jour décrite dans docs/mise-a-jour-socle.md. Le fichier est resté
 * en arrière sur trois versions sans que rien ne le signale : ce contrôle
 * ferme cette porte.
 */
final class VersionTest extends TestCase
{
    private function version(): string
    {
        return trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));
    }

    /** Le numéro de la première entrée du journal, qui est la version publiée. */
    private function derniereEntreeDuJournal(): string
    {
        $journal = (string) file_get_contents(dirname(__DIR__, 2).'/CHANGELOG.md');

        preg_match('/^## (\d+\.\d+\.\d+)/m', $journal, $trouve);

        return $trouve[1] ?? '';
    }

    #[Test]
    public function le_fichier_version_porte_un_numero_semantique(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $this->version(),
            'VERSION doit contenir un numéro de la forme X.Y.Z, et rien d’autre.',
        );
    }

    #[Test]
    public function le_fichier_version_correspond_a_la_derniere_entree_du_journal(): void
    {
        $this->assertSame(
            $this->derniereEntreeDuJournal(),
            $this->version(),
            'VERSION et la première entrée de CHANGELOG.md annoncent deux versions différentes : '
                .'un site qui fusionne afficherait un numéro faux.',
        );
    }
}
