<?php

/**
 * Palettes claire et sombre tirées de l'identité du site. Chaque couleur de texte est ajustée jusqu'à un
 * contraste de 4,5:1 sur son fond.
 *
 * @package Dashboard
 * @author  Pascal Fortunati
 * @link    https://github.com/pascal-fortunati
 *
 * @param  string $principale couleur principale (#RRGGBB)
 * @param  string $texte      couleur du texte (#RRGGBB)
 * @return string             les variables --admin-* des deux thèmes
 */
return function (string $principale, string $texte): string {

    $versRvb = static fn (string $hex): array => array_map(static fn (string $p): int => (int) hexdec($p), str_split(ltrim($hex, '#'), 2));
    $versHex = static fn (array $rvb): string => sprintf('#%02X%02X%02X', ...array_map(static fn (float $c): int => (int) round(max(0, min(255, $c))), $rvb));

    $versTsl = static function (string $hex) use ($versRvb): array {
        [$r, $g, $b] = array_map(static fn (int $c): float => $c / 255, $versRvb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s * 100, $l * 100];
    };

    $tsl = static function (float $h, float $s, float $l) use ($versHex): string {
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return $versHex([($r + $m) * 255, ($g + $m) * 255, ($b + $m) * 255]);
    };

    $luminance = static function (string $hex) use ($versRvb): float {
        $c = array_map(static function (int $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $versRvb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $contraste = static function (string $a, string $b) use ($luminance): float {
        [$x, $y] = [$luminance($a), $luminance($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    };

    // Éclaircit (ou fonce) une teinte jusqu'à ce qu'elle se lise sur son fond.
    $lisibleSur = static function (float $h, float $s, float $l, string $fond, float $pas) use ($tsl, $contraste): string {
        $couleur = $tsl($h, $s, $l);
        while ($contraste($couleur, $fond) < 4.5 && $l > 0 && $l < 100) {
            $l += $pas;
            $couleur = $tsl($h, $s, $l);
        }

        return $couleur;
    };

    $rgb = static fn (string $hex, int $alpha): string => 'rgb('.implode(' ', $versRvb($hex)).' / '.$alpha.'%)';

    [$h, $s, $l] = $versTsl($principale);
    $sat = min($s, 70);

    // ── Clair : le papier crème du site, la couleur principale pour les boutons et les icônes ──
    $clairFond = '#FBF8F1';
    $clairTexte = $contraste($texte, $clairFond) >= 4.5 ? $texte : '#1F2A22';
    [$th, $ts] = $versTsl($clairTexte);
    $barre = $tsl($h, min($s, 45), 15);
    $clair = [
        'fond' => $clairFond,
        'surface' => '#FFFFFF',
        'surface-douce' => '#F2ECDF',
        'surface-forte' => '#E7DFCD',
        'trait' => '#DCD3C3',
        'texte' => $clairTexte,
        'texte-doux' => $lisibleSur($th, min($ts, 12), 38, '#F2ECDF', -2),
        'primaire' => $principale,
        'primaire-fort' => $tsl($h, $s, max(8, $l - 9)),
        'sur-primaire' => '#FFFFFF',
        'accent' => $principale,
        'accent-doux' => $rgb($principale, 10),
        'barre' => $barre,
        'barre-haut' => $tsl($h, min($s, 45), 20),
        'barre-texte' => $lisibleSur($h, 30, 90, $barre, 2),
        'barre-survol' => 'rgb(255 255 255 / 10%)',
        'barre-actif' => '#FFFFFF',
        'barre-actif-texte' => $barre,
        'barre-actif-icone' => $principale,
        'alerte-fond' => '#FBE3D6',
        'alerte-texte' => '#8A3A12',
        'alerte-point' => '#D2541E',
        'danger' => '#A12A1F',
        'danger-fond' => '#A12A1F',
        'succes' => '#1E5A32',
        'dossier' => '#C98F2A',
        'coeur' => '#F2777A',
        'terminal' => $tsl($h, min($s, 30), 6),
        'ombre' => '0 1px 2px rgb(31 42 34 / 6%), 0 6px 18px rgb(31 42 34 / 7%)',
        'ombre-haute' => '0 2px 4px rgb(31 42 34 / 8%), 0 14px 32px rgb(31 42 34 / 12%)',
        'halo' => '0 0 0 4px '.$rgb($principale, 18),
        'entete' => 'rgb(251 248 241 / 88%)',
        'voile' => $rgb($barre, 92),
        'voile-milieu' => $rgb($barre, 70),
        'voile-photo' => $rgb($barre, 45),
        'voile-leger' => $rgb($barre, 15),
    ];

    // ── Sombre : des fonds teintés de la couleur principale, jamais le bleu nuit de Cockpit ──
    $sombreSurface = $tsl($h, min($sat, 20), 11);
    $sombreFond = $tsl($h, min($sat, 24), 7);
    $sombreBarre = $tsl($h, min($sat, 30), 5);
    $accent = $lisibleSur($h, max(min($s, 42), 30), 58, $tsl($h, min($sat, 16), 16), 2);
    // Les boutons : la couleur principale un peu éclaircie, tant que le texte blanc s'y lit.
    $boutonSombre = $principale;
    for ($pas = 1; $pas <= 8 && $contraste($tsl($h, $s, $l + $pas), '#FFFFFF') >= 4.5; $pas++) {
        $boutonSombre = $tsl($h, $s, $l + $pas);
    }
    $sombre = [
        'fond' => $sombreFond,
        'surface' => $sombreSurface,
        'surface-douce' => $tsl($h, min($sat, 16), 15),
        'surface-forte' => $tsl($h, min($sat, 14), 20),
        'trait' => $tsl($h, min($sat, 12), 24),
        'texte' => '#EEE8DA',
        'texte-doux' => $lisibleSur($h, 10, 70, $tsl($h, min($sat, 16), 15), 2),
        'primaire' => $boutonSombre,
        'primaire-fort' => $principale,
        'sur-primaire' => '#FFFFFF',
        'accent' => $accent,
        'accent-doux' => $rgb($accent, 14),
        'barre' => $sombreBarre,
        'barre-haut' => $tsl($h, min($sat, 28), 9),
        'barre-texte' => $lisibleSur($h, 22, 86, $sombreBarre, 2),
        'barre-survol' => 'rgb(255 255 255 / 7%)',
        'barre-actif' => $tsl($h, min($sat, 26), 17),
        'barre-actif-texte' => '#FFFFFF',
        'barre-actif-icone' => $accent,
        'alerte-fond' => '#4A2616',
        'alerte-texte' => '#F7B896',
        'alerte-point' => '#F07A43',
        'danger' => '#E5675B',
        'danger-fond' => '#B3372B',
        'succes' => '#7FC79A',
        'dossier' => '#E8B95A',
        'coeur' => '#F2777A',
        'terminal' => $tsl($h, min($sat, 30), 4),
        'ombre' => '0 1px 2px rgb(0 0 0 / 30%), 0 8px 22px rgb(0 0 0 / 28%)',
        'ombre-haute' => '0 2px 4px rgb(0 0 0 / 35%), 0 16px 36px rgb(0 0 0 / 40%)',
        'halo' => '0 0 0 4px '.$rgb($accent, 28),
        'entete' => $rgb($sombreFond, 86),
        'voile' => $rgb($sombreBarre, 94),
        'voile-milieu' => $rgb($sombreBarre, 78),
        'voile-photo' => $rgb($sombreBarre, 60),
        'voile-leger' => $rgb($sombreBarre, 30),
    ];

    $bloc = static fn (string $selecteur, array $jetons): string => $selecteur." {\n"
        .implode('', array_map(static fn (string $nom, string $valeur): string => "    --admin-{$nom}: {$valeur};\n", array_keys($jetons), $jetons))
        ."}\n";

    return $bloc('html[data-theme]:root', $clair).$bloc('html[data-theme="dark"]:root', $sombre).'html[data-theme="dark"] { color-scheme: dark; }'."\n";
};
