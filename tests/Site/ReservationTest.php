<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Contact\Submission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Camping Les Chênes Verts — demande de réservation (étape 06, option A).
 *
 * Le formulaire de réservation passe par le même traitement que le contact.
 * Ce qu'il accepte doit tenir, et un message de contact ordinaire doit rester
 * traité exactement comme avant (voir aussi ContactTest, inchangé).
 */
final class ReservationTest extends TestCase
{
    /** @param array<string, mixed> $extra */
    private function demande(array $extra = []): array
    {
        $arrivee = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $depart = (new \DateTimeImmutable('+37 days'))->format('Y-m-d');

        return array_merge([
            'formulaire' => 'reservation',
            'arrivee' => $arrivee,
            'depart' => $depart,
            'hebergement' => 'Chalet',
            'personnes' => '4',
            'majeurs' => '2',
            'animaux' => '1',
            'telephone' => '',
            'nom' => 'Famille Janssens',
            'email' => 'janssens@exemple.be',
            'message' => '',
            'consentement' => '1',
            'retour' => '/reserver',
        ], $extra);
    }

    #[Test]
    public function une_demande_complete_est_acceptee_sans_message(): void
    {
        $this->assertTrue(Submission::fromInput($this->demande())->isValid());
    }

    #[Test]
    public function le_sejour_est_enregistre_avec_le_message(): void
    {
        $item = Submission::fromInput($this->demande(['telephone' => '04 00 00 00 00']))->toItem();

        $this->assertSame('reservation', $item['formulaire']);
        $this->assertSame('Chalet', $item['hebergement']);
        $this->assertSame(7, $item['nuits']);
        $this->assertSame(4, $item['personnes']);
        $this->assertSame(2, $item['majeurs']);
        $this->assertSame(1, $item['animaux']);
        $this->assertSame('04 00 00 00 00', $item['telephone']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4}$#', $item['arrivee']);
        $this->assertSame('Famille Janssens', $item['nom']);
    }

    /** @param array<string, mixed> $remplacement */
    #[Test]
    #[DataProvider('erreurs')]
    public function une_erreur_est_signalee_sous_son_champ(array $remplacement, string $champAttendu): void
    {
        $submission = Submission::fromInput($this->demande($remplacement));

        $this->assertFalse($submission->isValid());
        $this->assertArrayHasKey($champAttendu, $submission->errors);
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function erreurs(): iterable
    {
        $demain = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
        $hier = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');

        yield 'sans arrivée' => [['arrivee' => ''], 'arrivee'];
        yield 'date inventée' => [['arrivee' => '2027-02-30'], 'arrivee'];
        yield 'arrivée passée' => [['arrivee' => $hier], 'arrivee'];
        yield 'départ avant l’arrivée' => [['arrivee' => $demain, 'depart' => $demain], 'depart'];
        yield 'sans hébergement' => [['hebergement' => ''], 'hebergement'];
        yield 'aucune personne' => [['personnes' => '0'], 'personnes'];
        yield 'groupe trop grand' => [['personnes' => '40'], 'personnes'];
        yield 'plus de majeurs que de personnes' => [['majeurs' => '5'], 'majeurs'];
        yield 'aucun majeur' => [['majeurs' => '0'], 'majeurs'];
        yield 'animaux négatifs' => [['animaux' => '-1'], 'animaux'];
        yield 'téléphone illisible' => [['telephone' => 'appelez-moi'], 'telephone'];
    }

    #[Test]
    public function les_valeurs_saisies_reviennent_telles_quelles_pour_la_correction(): void
    {
        $valeurs = Submission::fromInput($this->demande(['majeurs' => '9']))->values();

        $this->assertSame('9', $valeurs['majeurs']);
        $this->assertSame('Chalet', $valeurs['hebergement']);
        $this->assertSame($this->demande()['arrivee'], $valeurs['arrivee']);
    }

    #[Test]
    public function sans_animal_le_nombre_vaut_zero(): void
    {
        $item = Submission::fromInput($this->demande(['animaux' => '']))->toItem();

        $this->assertSame(0, $item['animaux']);
    }

    #[Test]
    public function un_message_de_contact_reste_traite_comme_avant(): void
    {
        $contact = Submission::fromInput([
            'nom' => 'Camille Durand',
            'email' => 'camille@exemple.fr',
            'message' => 'Bonjour, avez-vous de la place en juin ?',
            'consentement' => '1',
            'retour' => '/nous-ecrire',
        ]);

        $this->assertTrue($contact->isValid());
        $this->assertFalse($contact->isReservation());
        $this->assertArrayNotHasKey('formulaire', $contact->toItem());
        $this->assertSame(['nom', 'email', 'message'], array_keys($contact->values()));
    }

    #[Test]
    public function un_message_de_contact_vide_reste_refuse(): void
    {
        $contact = Submission::fromInput(['nom' => 'A', 'email' => 'a@exemple.fr', 'message' => '', 'consentement' => '1']);

        $this->assertArrayHasKey('message', $contact->errors);
    }
}
