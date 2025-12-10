<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\TextNormalizationServiceInterface;

final class TextNormalizationService implements TextNormalizationServiceInterface
{
    /**
     * Normalizuje tekst wprowadzony przez użytkownika
     * - konwertuje na małe litery
     * - usuwa znaki specjalne, zostawiając tylko litery, cyfry i spacje
     * - zamienia wielokrotne spacje na pojedyncze
     * - usuwa spacje z początku i końca.
     */
    public function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Usuwa wszystkie znaki specjalne, zostawiając tylko litery, cyfry, polskie znaki i spacje
        $text = preg_replace('/[^a-z0-9ąćęłńóśżź ]+/u', ' ', $text);

        // Zamienia wielokrotne spacje na pojedyncze
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Generuje hash SHA256 z znormalizowanego tekstu.
     */
    public function generateHash(string $normalizedText): string
    {
        return hash('sha256', $normalizedText);
    }
}
