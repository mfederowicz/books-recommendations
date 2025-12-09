<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Interface for Qdrant vector database client operations.
 */
interface QdrantClientInterface
{
    /**
     * Create a collection if it doesn't exist.
     */
    public function createCollection(string $collectionName, int $vectorSize): bool;

    /**
     * Insert or update a point (vector) in the collection.
     */
    public function upsertPoint(string $collectionName, array $vector, string $id, array $payload = []): bool;

    /**
     * Insert or update multiple points in the collection.
     */
    public function upsertPoints(string $collectionName, array $points): bool;

    /**
     * Search for similar vectors.
     */
    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array;

    /**
     * Delete a point by ID.
     */
    public function deletePoint(string $collectionName, string $id): bool;

    /**
     * Delete points by filter.
     */
    public function deletePoints(string $collectionName, array $filter): bool;

    /**
     * Get collection info.
     */
    public function getCollectionInfo(string $collectionName): ?array;

    /**
     * Delete entire collection.
     */
    public function deleteCollection(string $collectionName): bool;
}
