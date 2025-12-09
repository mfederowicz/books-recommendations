<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Recommendation;
use App\Entity\Tag;

interface RecommendationServiceInterface
{
    /**
     * Creates a new book recommendation or updates an existing one for a given user.
     *
     * Logic:
     * 1. Normalizes the provided text and generates hash
     * 2. Checks if embedding exists for this hash in recommendations_embeddings table
     * 3. If recommendation exists for this user and hash, updates tags
     * 4. If not exists, creates a new recommendation
     *
     * @param int    $userId User ID
     * @param string $text   Original recommendation text
     * @param int[]  $tagIds List of tag IDs to assign
     *
     * @return Recommendation Created or updated recommendation
     */
    public function createOrUpdateRecommendation(int $userId, string $text, array $tagIds): Recommendation;

    /**
     * Znajdź tag po nazwie.
     *
     * @param string $name Nazwa tagu
     *
     * @return Tag|null Znaleziony tag lub null
     */
    public function findTagByName(string $name): ?Tag;

    /**
     * Znajdź podobne książki na podstawie tekstu rekomendacji za pomocą wyszukiwania wektorowego w Qdrant.
     * Używa embeddingu użytkownika do wyszukania podobnych książek w kolekcji ebooków.
     *
     * @param string $text  Tekst rekomendacji użytkownika do wyszukania podobnych książek
     * @param int    $limit Maksymalna liczba wyników
     *
     * @return array Lista podobnych książek z wynikami podobieństwa
     */
    public function findSimilarEbooks(string $text, int $limit = 10): array;
}
