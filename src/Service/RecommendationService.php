<?php

declare(strict_types=1);

namespace App\Service;

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
    ) {
    }

    /**
     * Tworzy nową rekomendację książki lub aktualizuje istniejącą dla danego użytkownika.
     *
     * Logika:
     * 1. Normalizuje podany tekst i generuje hash
     * 2. Sprawdza czy istnieje embedding dla tego hash, jeśli nie - pobiera z OpenAI
     * 3. Sprawdza czy istnieje rekomendacja dla tego użytkownika i hash
     * 4. Jeśli istnieje, aktualizuje tagi
     * 5. Jeśli nie istnieje, tworzy nową rekomendację
     */
    public function createOrUpdateRecommendation(int $userId, string $text, array $tagIds): Recommendation
    {
        $normalizedText = $this->textNormalizationService->normalizeText($text);
        $hash = $this->textNormalizationService->generateHash($normalizedText);

        // Sprawdź czy istnieje embedding dla tego hash, jeśli nie - pobierz z OpenAI
        $this->ensureEmbeddingExists($hash, $text);

        // Znajdź istniejącą rekomendację dla tego użytkownika i hash
        $recommendation = $this->findRecommendationByUserAndHash($userId, $hash);

        if (null === $recommendation) {
            // Utwórz nową rekomendację
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
     * Znajdź rekomendację dla danego użytkownika i hash tekstu.
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
     * Znajdź tagi po ID.
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
     * Znajdź tag po nazwie.
     */
    public function findTagByName(string $name): ?Tag
    {
        return $this->tagRepository->findActiveTagByName($name);
    }

    /**
     * Zapewnia, że embedding istnieje dla danego hash tekstu
     * Jeśli nie istnieje, pobiera go z OpenAI i zapisuje w bazie.
     */
    private function ensureEmbeddingExists(string $hash, string $originalText): void
    {
        $existingEmbedding = $this->entityManager
            ->getRepository(RecommendationEmbedding::class)
            ->findOneBy(['normalizedTextHash' => $hash]);

        if (null !== $existingEmbedding) {
            return; // Embedding już istnieje
        }

        // Pobierz embedding z OpenAI
        $embedding = $this->openAIEmbeddingClient->getEmbedding($originalText);

        // Zapisz embedding w bazie
        $recommendationEmbedding = new RecommendationEmbedding();
        $recommendationEmbedding->setNormalizedTextHash($hash);
        $recommendationEmbedding->setDescription($originalText);
        $recommendationEmbedding->setEmbedding($embedding);

        $this->entityManager->persist($recommendationEmbedding);
        $this->entityManager->flush();
    }
}
