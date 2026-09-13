<?php

namespace App\Support;

/**
 * Canonicalizes an msisdn to bare digits in `254XXXXXXXXX` form — no `+`,
 * spaces, dashes, or leading `0`. Africa's Talking's `phoneNumber` param
 * arrives as `+254712345678` (E.164); web login input can just as easily
 * be typed as `0712345678` or `712345678`. Every write to `players.msisdn`
 * must go through this first, or the same physical number ends up as two
 * different strings — defeating the column's `unique` constraint and
 * silently creating a second player (and wallet) for one real person.
 */
class MsisdnNormalizer
{
    public static function normalize(string $msisdn): string
    {
        $digits = preg_replace('/\D+/', '', $msisdn) ?? '';

        if (str_starts_with($digits, '2540')) {
            // Common typo: country code plus the local leading zero left in,
            // e.g. "+2540712345678" — collapse the extra 0.
            return '254'.substr($digits, 4);
        }

        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && in_array($digits[0], ['7', '1'], true)) {
            return '254'.$digits;
        }

        return $digits;
    }
}
