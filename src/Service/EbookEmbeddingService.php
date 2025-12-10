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

    public function __construct(
        private QdrantClientInterface $qdrantClient,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Ensure Qdrant collection for ebooks exists.
     */
    private function ensureQdrantCollectionExists(): void
    {
        $collectionInfo = $this->qdrantClient->getCollectionInfo(self::QDRANT_COLLECTION);

        if (null === $collectionInfo) {
            $this->qdrantClient->createCollection(self::QDRANT_COLLECTION, self::VECTOR_SIZE);
        }
    }

    /**
     * Sync ebook embedding to Qdrant for fast vector search.
     */
    public function syncEbookEmbeddingToQdrant(EbookEmbedding $ebookEmbedding): bool
    {
        $this->ensureQdrantCollectionExists();

        return $this->qdrantClient->upsertPoint(
            self::QDRANT_COLLECTION,
            $ebookEmbedding->getVector(),
            $ebookEmbedding->getEbookId(), // Use ISBN as Qdrant point ID
            [
                'title' => $ebookEmbedding->getPayloadTitle(),
                'author' => $ebookEmbedding->getPayloadAuthor(),
                'tags' => $ebookEmbedding->getPayloadTags(),
                'description' => $ebookEmbedding->getPayloadDescription(),
                'isbn' => $ebookEmbedding->getEbookId(),
                'created_at' => $ebookEmbedding->getCreatedAt()->format('c'),
            ]
        );
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

        $searchResults = $this->qdrantClient->search(
            self::QDRANT_COLLECTION,
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
     * Sync all ebook embeddings to Qdrant.
     * Useful for initial migration or rebuilding the vector index.
     */
    public function syncAllEbookEmbeddingsToQdrant(): array
    {
        $ebookEmbeddings = $this->entityManager
            ->getRepository(EbookEmbedding::class)
            ->findAll();

        $this->ensureQdrantCollectionExists();

        $syncedCount = 0;
        $errorCount = 0;

        foreach ($ebookEmbeddings as $ebookEmbedding) {
            try {
                $success = $this->syncEbookEmbeddingToQdrant($ebookEmbedding);
                if ($success) {
                    ++$syncedCount;
                } else {
                    ++$errorCount;
                }
            } catch (\Exception $e) {
                ++$errorCount;
                // Log error but continue with other embeddings
                error_log("Failed to sync ebook embedding {$ebookEmbedding->getEbook()->getId()}: ".$e->getMessage());
            }
        }

        return [
            'total' => count($ebookEmbeddings),
            'synced' => $syncedCount,
            'errors' => $errorCount,
        ];
    }

    /**
     * Get Qdrant collection statistics.
     */
    public function getQdrantCollectionStats(): ?array
    {
        return $this->qdrantClient->getCollectionInfo(self::QDRANT_COLLECTION);
    }
}
