<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Interfejs dla klienta OpenAI do pobierania embeddingów.
 */
interface OpenAIEmbeddingClientInterface
{
    /**
     * Pobiera embedding dla pojedynczego tekstu.
     *
     * @param string $text Tekst do przetworzenia
     *
     * @return array Wektor embedding
     *
     * @throws \RuntimeException Gdy nie uda się pobrać embedding
     */
    public function getEmbedding(string $text): array;

    /**
     * Pobiera embeddingi dla wielu tekstów w batchu (optymalizacja dla crona).
     *
     * @param string[] $texts Lista tekstów do przetworzenia
     *
     * @return array[] Lista wektorów embedding w tej samej kolejności co teksty
     *
     * @throws \RuntimeException Gdy nie uda się pobrać embeddingów
     */
    public function getEmbeddingsBatch(array $texts): array;
}

