<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Recommendation;
use App\Entity\Tag;

interface RecommendationServiceInterface
{
    /**
     * Tworzy nową rekomendację książki lub aktualizuje istniejącą dla danego użytkownika
     *
     * Logika:
     * 1. Normalizuje podany tekst i generuje hash
     * 2. Sprawdza czy istnieje embedding dla tego hash w tabeli recommendations_embeddings
     * 3. Jeśli istnieje rekomendacja dla tego użytkownika i hash, aktualizuje tagi
     * 4. Jeśli nie istnieje, tworzy nową rekomendację
     *
     * @param int $userId ID użytkownika
     * @param string $text Oryginalny tekst rekomendacji
     * @param int[] $tagIds Lista ID tagów do przypisania
     * @return Recommendation Utworzona lub zaktualizowana rekomendacja
     */
    public function createOrUpdateRecommendation(int $userId, string $text, array $tagIds): Recommendation;

    /**
     * Znajdź tag po nazwie
     *
     * @param string $name Nazwa tagu
     * @return \App\Entity\Tag|null Znaleziony tag lub null
     */
    public function findTagByName(string $name): ?\App\Entity\Tag;
}
