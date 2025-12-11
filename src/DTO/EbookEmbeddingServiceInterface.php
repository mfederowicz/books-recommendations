<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\EbookEmbedding;

/**
 * Interface for ebook embedding vector database operations.
 */
interface EbookEmbeddingServiceInterface
{
    /**
     * Sync ebook embedding to Qdrant for fast vector search.
     */
    public function syncEbookEmbeddingToQdrant(EbookEmbedding $ebookEmbedding): bool;

    /**
     * Find similar ebooks using vector search in Qdrant.
     *
     * @param array $queryVector The embedding vector to search for similar ebooks
     * @param int   $limit       Maximum number of results
     * @param array $filter      Optional filters for the search
     *
     * @return array List of similar ebooks with similarity scores
     */
    public function findSimilarEbooks(array $queryVector, int $limit = 10, array $filter = []): array;

    /**
     * Remove ebook embedding from Qdrant.
     */
    public function removeEbookEmbeddingFromQdrant(string $isbn): bool;

    /**
     * Sync all ebook embeddings to Qdrant.
     * Useful for initial migration or rebuilding the vector index.
     */
    public function syncAllEbookEmbeddingsToQdrant(): array;

    /**
     * Get Qdrant collection statistics.
     */
    public function getQdrantCollectionStats(): ?array;
}
