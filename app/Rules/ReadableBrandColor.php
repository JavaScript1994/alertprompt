<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Color #RRGGBB sobre el que el texto blanco de los botones se lee bien:
 * contraste WCAG AA (4.5:1) contra blanco.
 */
class ReadableBrandColor implements ValidationRule
{
    public const MIN_CONTRAST = 4.5;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            $fail('Usa un color en formato #RRGGBB.');

            return;
        }

        if (self::contrastWithWhite($value) < self::MIN_CONTRAST) {
            $fail('Ese color es muy claro: el texto blanco de los botones no se leería. Elige uno más oscuro.');
        }
    }

    public static function contrastWithWhite(string $hex): float
    {
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel(substr($hex, 1, 2))
            + 0.7152 * $channel(substr($hex, 3, 2))
            + 0.0722 * $channel(substr($hex, 5, 2));

        return 1.05 / ($luminance + 0.05);
    }
}
