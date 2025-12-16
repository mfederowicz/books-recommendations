<?php

declare(strict_types=1);

namespace App\Service;

final class ImageHelper
{
    public static function formatIsbnForImagePath(string $isbn): string
    {
        // Remove any hyphens or non-numeric characters
        $cleanIsbn = preg_replace('/[^0-9]/', '', $isbn);

        // ISBN-13 should be 13 digits
        if (13 !== strlen($cleanIsbn)) {
            throw new \InvalidArgumentException('ISBN must be 13 digits');
        }

        // Split into groups: first 3 digits, next 2, next 4, next 3, last 1
        // For 9788368590777: 978/83/6859/907/7
        return sprintf(
            '%s/%s/%s/%s/%s',
            substr($cleanIsbn, 0, 3),  // 978
            substr($cleanIsbn, 3, 2),  // 83
            substr($cleanIsbn, 5, 4),  // 6859
            substr($cleanIsbn, 9, 3),  // 907
            substr($cleanIsbn, 12, 1)  // 7
        );
    }

    public static function slugify(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Transliterate Polish characters to ASCII equivalents
        $transliteration = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ];
        $text = strtr($text, $transliteration);

        // Replace spaces and special characters with hyphens
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);

        // Remove leading/trailing hyphens
        return trim($text, '-');
    }
}
