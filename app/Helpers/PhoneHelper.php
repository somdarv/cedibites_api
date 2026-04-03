<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalise a Ghana phone number to +233XXXXXXXXX format.
     *
     * Accepts: 0XXXXXXXXX, 233XXXXXXXXX, +233XXXXXXXXX (with optional whitespace).
     * Returns: +233XXXXXXXXX or the original string when it cannot be normalised.
     */
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '+233') && strlen($phone) === 13) {
            return $phone;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return '+233' . substr($phone, 1);
        }

        if (str_starts_with($phone, '233') && strlen($phone) === 12) {
            return '+' . $phone;
        }

        return $phone;
    }

    /**
     * Convert +233XXXXXXXXX to local 0XXXXXXXXX format.
     */
    public static function toLocal(string $phone): string
    {
        $phone = self::normalize($phone);

        if (str_starts_with($phone, '+233')) {
            return '0' . substr($phone, 4);
        }

        return $phone;
    }

    /**
     * Convert to international 233XXXXXXXXX format (no + prefix).
     * Used by Hubtel APIs that expect this format.
     */
    public static function toInternational(string $phone): string
    {
        $normalised = self::normalize($phone);

        if (str_starts_with($normalised, '+')) {
            return substr($normalised, 1);
        }

        return $normalised;
    }
}
