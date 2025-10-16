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
     * Format phone number for display (with spaces)
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
