<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EbookEmbeddingServiceInterface;
use App\DTO\QdrantClientInterface;
use App\Entity\Ebook;
use App\Entity\EbookEmbedding;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing ebook embeddings in Qdrant vector database.
 * Handles synchronization between MySQL storage and Qdrant for fast vector search.
 */
class EbookEmbeddingService implements EbookEmbeddingServiceInterface
{
    private const QDRANT_COLLECTION = 'ebooks';
    private const VECTOR_SIZE = 1536; // text-embedding-3-small dimension

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        // Use Symfony's Uuid if available, otherwise fallback to random
        if (class_exists(\Symfony\Component\Uid\Uuid::class)) {
            return \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
        }

        // Fallback implementation
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    public function __construct(
        private QdrantClientInterface $qdrantClient,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Ensure Qdrant collection for ebooks exists with named vectors.
     */
    private function ensureQdrantCollectionExists(): void
    {
        $collectionInfo = $this->qdrantClient->getCollectionInfo(self::QDRANT_COLLECTION);

        if (null === $collectionInfo) {
            $this->qdrantClient->createCollectionWithNamedVectors(self::QDRANT_COLLECTION, [
                'book_vector' => self::VECTOR_SIZE,
            ]);
        }
    }

    /**
     * Sync ebook embedding to Qdrant for fast vector search.
     */
    public function syncEbookEmbeddingToQdrant(EbookEmbedding $ebookEmbedding): bool
    {
        $this->ensureQdrantCollectionExists();

        // Generate UUID if not exists
        $uuid = $ebookEmbedding->getPayloadUuid();
        if (null === $uuid) {
            $uuid = $this->generateUuid();
            $ebookEmbedding->setPayloadUuid($uuid);
        }

        // Create Qdrant point with UUID as ID
        $point = [
            'id' => $uuid, // Use UUID as Qdrant point ID
            'vector' => [
                'book_vector' => $ebookEmbedding->getVector(),
            ],
            'payload' => [
                'isbn' => $ebookEmbedding->getEbookId(), // Store ISBN in payload
                'title' => $ebookEmbedding->getPayloadTitle(),
                'author' => $ebookEmbedding->getPayloadAuthor(),
                'tags' => $ebookEmbedding->getPayloadTags(),
                'created_at' => $ebookEmbedding->getCreatedAt()->format('c'),
            ],
        ];

        $success = $this->qdrantClient->upsertPoints(self::QDRANT_COLLECTION, [$point]);

        if ($success) {
            $ebookEmbedding->setSyncedToQdrant(true);
            $this->entityManager->flush();
        }

        return $success;
    }

    /**
     * Find similar ebooks using vector search in Qdrant.
     *
     * @param array $queryVector The embedding vector to search for similar ebooks
     * @param int   $limit       Maximum number of results
     * @param array $filter      Optional filters for the search
     *
     * @return array List of similar ebooks with similarity scores
     */
    public function findSimilarEbooks(array $queryVector, int $limit = 10, array $filter = []): array
    {
        $this->ensureQdrantCollectionExists();

        $searchResults = $this->qdrantClient->searchWithNamedVector(
            self::QDRANT_COLLECTION,
            'book_vector',
            $queryVector,
            $limit,
            $filter
        );

        $similarEbooks = [];
        foreach ($searchResults as $result) {
            $isbn = $result['payload']['isbn'] ?? null;
            if (null === $isbn) {
                continue;
            }

            // Get ebook from database by ISBN
            $ebook = $this->entityManager
                ->getRepository(Ebook::class)
                ->findOneBy(['isbn' => $isbn]);

            if (null === $ebook) {
                continue;
            }

            $similarEbooks[] = [
                'ebook' => $ebook,
                'similarity_score' => $result['score'],
                'isbn' => $isbn,
            ];
        }

        return $similarEbooks;
    }

    /**
     * Remove ebook embedding from Qdrant.
     */
    public function removeEbookEmbeddingFromQdrant(string $isbn): bool
    {
        return $this->qdrantClient->deletePoint(self::QDRANT_COLLECTION, $isbn);
    }

    /**
     * Sync ebook embeddings to Qdrant in batches.
     */
    public function syncEbookEmbeddingsBatchToQdrant(array $ebookEmbeddings): bool
    {
        if (empty($ebookEmbeddings)) {
            return true;
        }

        $this->ensureQdrantCollectionExists();

        $points = [];
        foreach ($ebookEmbeddings as $ebookEmbedding) {
            // Generate UUID if not exists
            $uuid = $ebookEmbedding->getPayloadUuid();
            if (null === $uuid) {
                $uuid = $this->generateUuid();
                $ebookEmbedding->setPayloadUuid($uuid);
            }

            $points[] = [
                'id' => $uuid, // Use UUID as Qdrant point ID
                'vector' => [
                    'book_vector' => $ebookEmbedding->getVector(),
                ],
                'payload' => [
                    'isbn' => $ebookEmbedding->getEbookId(), // Store ISBN in payload
                    'title' => $ebookEmbedding->getPayloadTitle(),
                    'author' => $ebookEmbedding->getPayloadAuthor(),
                    'tags' => $ebookEmbedding->getPayloadTags(),
                    'created_at' => $ebookEmbedding->getCreatedAt()->format('c'),
                ],
            ];
        }

        $success = $this->qdrantClient->upsertPoints(self::QDRANT_COLLECTION, $points);

        if ($success) {
            foreach ($ebookEmbeddings as $ebookEmbedding) {
                $ebookEmbedding->setSyncedToQdrant(true);
            }
            $this->entityManager->flush();
        }

        return $success;
    }

    /**
     * Common method for batch syncing embeddings with statistics.
     */
    private function syncEmbeddingsBatch(array $ebookEmbeddings): array
    {
        $this->ensureQdrantCollectionExists();

        $syncedCount = 0;
        $errorCount = 0;
        $batchSize = 50; // Process in batches of 50

        // Process in batches
        $batches = array_chunk($ebookEmbeddings, $batchSize);

        foreach ($batches as $batch) {
            try {
                $success = $this->syncEbookEmbeddingsBatchToQdrant($batch);
                if ($success) {
                    $syncedCount += count($batch);
                } else {
                    $errorCount += count($batch);
                }
            } catch (\Exception $e) {
                $errorCount += count($batch);
                // Log error but continue with other batches
                error_log('Failed to sync batch of '.count($batch).' ebook embeddings: '.$e->getMessage());
            }
        }

        return [
            'total' => count($ebookEmbeddings),
            'synced' => $syncedCount,
            'errors' => $errorCount,
        ];
    }

    /**
     * Sync only unsynchronized ebook embeddings to Qdrant.
     * Useful for resuming interrupted migration or syncing new embeddings.
     */
    public function syncUnsyncedEbookEmbeddingsToQdrant(): array
    {
        $ebookEmbeddings = $this->entityManager
            ->getRepository(EbookEmbedding::class)
            ->findBy(['syncedToQdrant' => false]);

        return $this->syncEmbeddingsBatch($ebookEmbeddings);
    }

    /**
     * Sync all ebook embeddings to Qdrant (including already synced ones).
     * Useful for initial migration or rebuilding the vector index.
     * Note: This will re-sync all embeddings, even those already synced.
     */
    public function syncAllEbookEmbeddingsToQdrant(): array
    {
        $ebookEmbeddings = $this->entityManager
            ->getRepository(EbookEmbedding::class)
            ->findAll();

        // Reset sync status for all embeddings before syncing
        foreach ($ebookEmbeddings as $ebookEmbedding) {
            $ebookEmbedding->setSyncedToQdrant(false);
        }
        $this->entityManager->flush();

        return $this->syncEmbeddingsBatch($ebookEmbeddings);
    }

    /**
     * Get Qdrant collection statistics.
     */
    public function getQdrantCollectionStats(): ?array
    {
        return $this->qdrantClient->getCollectionInfo(self::QDRANT_COLLECTION);
    }
}
