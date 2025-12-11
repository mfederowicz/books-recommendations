<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EbookEmbeddingServiceInterface;
use App\DTO\OpenAIEmbeddingClientInterface;
use App\DTO\RecommendationServiceInterface;
use App\DTO\TextNormalizationServiceInterface;
use App\Entity\Recommendation;
use App\Entity\RecommendationEmbedding;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RecommendationService implements RecommendationServiceInterface
{
    public function __construct(
        private TextNormalizationServiceInterface $textNormalizationService,
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private OpenAIEmbeddingClientInterface $openAIEmbeddingClient,
        private EbookEmbeddingServiceInterface $ebookEmbeddingService,
    ) {
    }

    /**
     * Creates a new book recommendation or updates an existing one for a given user.
     *
     * Logic:
     * 1. Normalizes the provided text and generates hash
     * 2. Checks if embedding exists for this hash, if not - fetches from OpenAI
     * 3. Checks if recommendation exists for this user and hash
     * 4. If exists, updates tags
     * 5. If not exists, creates a new recommendation
     */
    public function createOrUpdateRecommendation(int $userId, string $text, array $tagIds): Recommendation
    {
        $normalizedText = $this->textNormalizationService->normalizeText($text);
        $hash = $this->textNormalizationService->generateHash($normalizedText);

        // Check if embedding exists for this hash, if not - fetch from OpenAI
        $this->ensureEmbeddingExists($hash, $text);

        // Find existing recommendation for this user and hash
        $recommendation = $this->findRecommendationByUserAndHash($userId, $hash);

        if (null === $recommendation) {
            // Create a new recommendation
            $recommendation = new Recommendation();
            $user = $this->entityManager->getReference(\App\Entity\User::class, $userId);
            $recommendation->setUser($user);
            $recommendation->setShortDescription($text); // Oryginalny tekst
            $recommendation->setNormalizedTextHash($hash);
            $this->entityManager->persist($recommendation);
        }

        // Zaktualizuj tagi
        $tags = $this->findTagsByIds($tagIds);
        $recommendation->setTags($tags);
        $recommendation->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $recommendation;
    }

    /**
     * Find similar books based on recommendation text using vector search in Qdrant.
     * Uses user embedding to find similar books in the ebooks collection.
     *
     * @param string $text  User recommendation text to search for similar books
     * @param int    $limit Maximum number of results
     *
     * @return array List of similar books with similarity scores
     */
    public function findSimilarEbooks(string $text, int $limit = 10): array
    {
        // Get embedding for user recommendation text (with caching)
        $queryEmbedding = $this->getOrCreateQueryEmbedding($text);

        // Search for similar books in Qdrant using user embedding
        return $this->ebookEmbeddingService->findSimilarEbooks($queryEmbedding, $limit);
    }

    /**
     * Find recommendation for a given user and text hash.
     */
    private function findRecommendationByUserAndHash(int $userId, string $hash): ?Recommendation
    {
        return $this->entityManager
            ->getRepository(Recommendation::class)
            ->findOneBy([
                'user' => $userId,
                'normalizedTextHash' => $hash,
            ]);
    }

    /**
     * Find tags by IDs.
     *
     * @param int[] $tagIds
     *
     * @return Tag[]
     */
    private function findTagsByIds(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }

        return $this->tagRepository->findBy(['id' => $tagIds]);
    }

    /**
     * Find tag by name.
     */
    public function findTagByName(string $name): ?Tag
    {
        return $this->tagRepository->findActiveTagByName($name);
    }

    /**
     * Ensures that embedding exists for a given text hash.
     * If it doesn't exist, fetches it from OpenAI and saves it to the database.
     * User embeddings are stored only in MySQL for resource optimization.
     */
    private function ensureEmbeddingExists(string $hash, string $originalText): void
    {
        $existingEmbedding = $this->entityManager
            ->getRepository(RecommendationEmbedding::class)
            ->findOneBy(['normalizedTextHash' => $hash]);

        if (null !== $existingEmbedding) {
            return; // Embedding already exists
        }

        // Pobierz embedding z OpenAI
        $embedding = $this->openAIEmbeddingClient->getEmbedding($originalText);

        // Save embedding only to database (not to Qdrant - resource optimization)
        $recommendationEmbedding = new RecommendationEmbedding();
        $recommendationEmbedding->setNormalizedTextHash($hash);
        $recommendationEmbedding->setDescription($originalText);
        $recommendationEmbedding->setEmbedding($embedding);

        $this->entityManager->persist($recommendationEmbedding);
        $this->entityManager->flush();
    }

    /**
     * Get Qdrant collection statistics.
     */
    public function getQdrantStats(): ?array
    {
        return $this->ebookEmbeddingService->getQdrantCollectionStats();
    }

    /**
     * Get embedding for query text, creating it if it doesn't exist.
     * This helps avoid redundant OpenAI API calls.
     */
    private function getOrCreateQueryEmbedding(string $text): array
    {
        $normalizedText = $this->textNormalizationService->normalizeText($text);
        $hash = $this->textNormalizationService->generateHash($normalizedText);

        $existingEmbedding = $this->entityManager
            ->getRepository(RecommendationEmbedding::class)
            ->findOneBy(['normalizedTextHash' => $hash]);

        if (null !== $existingEmbedding) {
            return $existingEmbedding->getEmbedding();
        }

        // Generate new embedding from OpenAI
        $embedding = $this->openAIEmbeddingClient->getEmbedding($text);

        // Save embedding to database for future use
        $recommendationEmbedding = new RecommendationEmbedding();
        $recommendationEmbedding->setNormalizedTextHash($hash);
        $recommendationEmbedding->setDescription($text);
        $recommendationEmbedding->setEmbedding($embedding);

        $this->entityManager->persist($recommendationEmbedding);
        $this->entityManager->flush();

        return $embedding;
    }
}
