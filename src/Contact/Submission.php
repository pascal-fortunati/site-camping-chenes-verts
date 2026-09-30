<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * What someone typed in the contact form, checked.
 *
 * Errors are named per field so the form can be shown again with what was
 * already written: making someone retype everything because of one mistake is
 * how a contact form loses a customer.
 *
 * ÉCART AU SOCLE — Camping Les Chênes Verts (étape 06, option A) : une demande
 * de réservation passe par le même formulaire, repérée par le champ caché
 * « formulaire=reservation ». Elle ajoute le séjour demandé (dates, hébergement,
 * personnes, dont majeures, animaux, téléphone facultatif) et rend le message
 * facultatif. Un message de contact ordinaire est traité exactement comme avant.
 * Voir SUIVI-SITE.md.
 */
final class Submission
{
    private const MAX_NAME = 120;
    private const MAX_MESSAGE = 5000;
    private const MAX_PERSONNES = 20;
    private const MAX_ANIMAUX = 10;

    /**
     * @param array<string, string> $errors
     * @param array<string, string|int> $sejour Vide pour un message de contact.
     * @param array<string, string> $saisie Valeurs du séjour telles que saisies, pour réafficher le formulaire.
     */
    private function __construct(
        public readonly string $nom,
        public readonly string $email,
        public readonly string $message,
        public readonly bool $consentement,
        public readonly string $origine,
        public readonly array $errors,
        public readonly array $sejour = [],
        public readonly array $saisie = [],
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $reservation = ($input['formulaire'] ?? '') === 'reservation';

        $nom = self::text($input['nom'] ?? '');
        $email = self::text($input['email'] ?? '');
        $message = self::text($input['message'] ?? '', self::MAX_MESSAGE);
        $consentement = !empty($input['consentement']);
        $origine = self::text($input['retour'] ?? '/');

        $errors = [];

        if ($nom === '') {
            $errors['nom'] = 'Indiquer un nom.';
        } elseif (mb_strlen($nom) > self::MAX_NAME) {
            $errors['nom'] = 'Ce nom est trop long.';
        }

        if ($email === '') {
            $errors['email'] = 'Indiquer une adresse e-mail.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Cette adresse e-mail ne semble pas valide.';
        }

        // Une demande de réservation dit déjà l'essentiel : le message y est facultatif.
        if (!$reservation) {
            if ($message === '') {
                $errors['message'] = 'Écrire un message.';
            } elseif (mb_strlen($message) < 10) {
                $errors['message'] = 'Ce message est trop court pour être compris.';
            }
        }

        if (!$consentement) {
            $errors['consentement'] = 'Cocher la case pour autoriser la réponse.';
        }

        // Only ever a path on this site, never an address elsewhere.
        if (preg_match('#^/[a-z0-9/-]*$#', $origine) !== 1) {
            $origine = '/';
        }

        [$sejour, $saisie] = $reservation ? self::sejour($input, $errors) : [[], []];

        return new self($nom, $email, $message, $consentement, $origine, $errors, $sejour, $saisie);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function isReservation(): bool
    {
        return $this->sejour !== [];
    }

    /**
     * The message as it is stored.
     *
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        return [
            'nom' => $this->nom,
            'email' => $this->email,
            'message' => $this->message,
            'consentement' => $this->consentement,
            'envoyeLe' => date('d/m/Y à H:i'),
            'origine' => $this->origine,
            'lu' => false,
        ] + $this->sejour;
    }

    /** @return array<string, string> */
    public function values(): array
    {
        return [
            'nom' => $this->nom,
            'email' => $this->email,
            'message' => $this->message,
        ] + $this->saisie;
    }

    /**
     * Le séjour demandé, vérifié. Les erreurs s'ajoutent à celles du formulaire.
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     * @return array{0: array<string, string|int>, 1: array<string, string>}
     */
    private static function sejour(array $input, array &$errors): array
    {
        $saisie = [
            'arrivee' => self::text($input['arrivee'] ?? '', 10),
            'depart' => self::text($input['depart'] ?? '', 10),
            'hebergement' => self::text($input['hebergement'] ?? '', 120),
            'personnes' => self::text($input['personnes'] ?? '', 3),
            'majeurs' => self::text($input['majeurs'] ?? '', 3),
            'animaux' => self::text($input['animaux'] ?? '', 3),
            'telephone' => self::text($input['telephone'] ?? '', 30),
        ];

        $arrivee = self::date($saisie['arrivee']);
        $depart = self::date($saisie['depart']);

        if ($arrivee === null) {
            $errors['arrivee'] = 'Indiquer la date d’arrivée.';
        } elseif ($arrivee < new \DateTimeImmutable('today')) {
            $errors['arrivee'] = 'Choisir une date d’arrivée à venir.';
        }

        if ($depart === null) {
            $errors['depart'] = 'Indiquer la date de départ.';
        } elseif ($arrivee !== null && $depart <= $arrivee) {
            $errors['depart'] = 'La date de départ doit être après la date d’arrivée.';
        }

        if ($saisie['hebergement'] === '') {
            $errors['hebergement'] = 'Choisir un hébergement.';
        }

        $personnes = self::entier($saisie['personnes']);
        $majeurs = self::entier($saisie['majeurs']);
        $animaux = $saisie['animaux'] === '' ? 0 : self::entier($saisie['animaux']);

        if ($personnes === null || $personnes < 1) {
            $errors['personnes'] = 'Indiquer le nombre de personnes.';
        } elseif ($personnes > self::MAX_PERSONNES) {
            $errors['personnes'] = 'Pour un groupe de plus de '.self::MAX_PERSONNES.' personnes, écrivez-nous ou appelez-nous.';
        }

        if ($majeurs === null || $majeurs < 1) {
            $errors['majeurs'] = 'Indiquer combien de personnes ont 18 ans ou plus.';
        } elseif ($personnes !== null && $majeurs > $personnes) {
            $errors['majeurs'] = 'Il ne peut pas y avoir plus de personnes majeures que de personnes.';
        }

        if ($animaux === null || $animaux < 0 || $animaux > self::MAX_ANIMAUX) {
            $errors['animaux'] = 'Indiquer un nombre d’animaux (0 si aucun).';
        }

        if ($saisie['telephone'] !== '' && preg_match('/^[0-9 +().-]{6,30}$/', $saisie['telephone']) !== 1) {
            $errors['telephone'] = 'Ce numéro de téléphone ne semble pas valide.';
        }

        $sejour = [
            'formulaire' => 'reservation',
            'arrivee' => $arrivee?->format('d/m/Y') ?? '',
            'depart' => $depart?->format('d/m/Y') ?? '',
            'nuits' => ($arrivee !== null && $depart !== null && $depart > $arrivee) ? (int) $arrivee->diff($depart)->days : 0,
            'hebergement' => $saisie['hebergement'],
            'personnes' => $personnes ?? 0,
            'majeurs' => $majeurs ?? 0,
            'animaux' => $animaux ?? 0,
            'telephone' => $saisie['telephone'],
        ];

        return [$sejour, $saisie];
    }

    /** Une date du champ « date » du navigateur (AAAA-MM-JJ), ou null. */
    private static function date(string $valeur): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return ($date !== false && $date->format('Y-m-d') === $valeur) ? $date : null;
    }

    private static function entier(string $valeur): ?int
    {
        $n = filter_var($valeur, FILTER_VALIDATE_INT);

        return $n === false ? null : $n;
    }

    private static function text(mixed $value, int $max = 500): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Control characters would only ever come from something automated.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($clean), 0, $max);
    }
}
