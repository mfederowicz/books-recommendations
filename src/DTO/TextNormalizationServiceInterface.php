<?php

declare(strict_types=1);

namespace App\DTO;

interface TextNormalizationServiceInterface
{
    /**
     * Normalizuje tekst wprowadzony przez użytkownika
     * - konwertuje na małe litery
     * - usuwa znaki specjalne, zostawiając tylko litery, cyfry i spacje
     * - zamienia wielokrotne spacje na pojedyncze
     * - usuwa spacje z początku i końca.
     *
     * @param string $text Tekst do normalizacji
     *
     * @return string Znormalizowany tekst
     */
    public function normalizeText(string $text): string;

    /**
     * Generuje hash SHA256 z znormalizowanego tekstu.
     *
     * @param string $normalizedText Znormalizowany tekst
     *
     * @return string Hash SHA256 w formacie hex
     */
    public function generateHash(string $normalizedText): string;
}
