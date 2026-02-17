<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Clean and format phone number for WhatsApp (Moroccan format)
     */
    public static function cleanPhoneNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove any spaces, dashes or special characters
        $number = preg_replace('/[^0-9]/', '', $phone);

        // Handle different Moroccan number formats
        if (strlen($number) === 9 && in_array(substr($number, 0, 1), ['6', '7'])) {
            // If number starts with 6 or 7 and is 9 digits
            return '212'.$number;
        } elseif (strlen($number) === 10 && in_array(substr($number, 0, 2), ['06', '07'])) {
            // If number starts with 06 or 07 and is 10 digits
            return '212'.substr($number, 1);
        } elseif (strlen($number) === 12 && substr($number, 0, 3) === '212') {
            // If number already has 212 country code
            return $number;
        }

        // Return null for invalid numbers
        return null;
    }

    /**
     * Validate if phone number is valid Moroccan format
     */
    public static function isValidMoroccanPhone(?string $phone): bool
    {
        return self::cleanPhoneNumber($phone) !== null;
    }

    /**
     * Normalize any phone to its last 9 digits for fast matching.
     */
    public static function suffix(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone ?? '');

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }

    /**
     * Build a suffix lookup set from an array of phone numbers.
     * Returns [suffix => phone] for O(1) matching.
     *
     * @param  string[]  $phones  Raw phones from WhatsApp API
     * @return array<string, string>
     */
    public static function buildSuffixIndex(array $phones): array
    {
        $index = [];
        foreach ($phones as $phone) {
            $suffix = self::suffix($phone);
            if ($suffix) {
                $index[$suffix] = $phone;
            }
        }

        return $index;
    }

    /**
     * Check if a student's phone matches any sender in a pre-built suffix index.
     */
    public static function matchesAny(?string $phone, array $suffixIndex): bool
    {
        $suffix = self::suffix($phone);

        return $suffix && isset($suffixIndex[$suffix]);
    }

    /**
     * Format phone number for WhatsApp URL (with + prefix).
     */
    public static function formatForWhatsApp(?string $phone): ?string
    {
        $cleaned = self::cleanPhoneNumber($phone);

        return $cleaned ? '+'.$cleaned : null;
    }

    /**
     * Format phone number for display (with spaces).
     */
    public static function formatForDisplay(?string $phone): ?string
    {
        $cleanNumber = self::cleanPhoneNumber($phone);

        if (! $cleanNumber) {
            return null;
        }

        // Format as +212 6XX XXX XXX
        if (strlen($cleanNumber) === 12 && substr($cleanNumber, 0, 3) === '212') {
            return '+212 '.substr($cleanNumber, 3, 1).substr($cleanNumber, 4, 2).' '.
                   substr($cleanNumber, 6, 3).' '.substr($cleanNumber, 9, 3);
        }

        return $cleanNumber;
    }
}
