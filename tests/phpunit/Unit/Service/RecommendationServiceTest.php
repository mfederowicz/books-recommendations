<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\OpenAIEmbeddingClientInterface;
use App\DTO\TextNormalizationServiceInterface;
use App\Entity\Recommendation;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class RecommendationServiceTest extends TestCase
{
    private TextNormalizationServiceInterface $textNormalizationService;
    private EntityManagerInterface $entityManager;
    private TagRepository $tagRepository;
    private OpenAIEmbeddingClientInterface $openAIEmbeddingClient;
    private RecommendationService $recommendationService;

    protected function setUp(): void
    {
        $this->textNormalizationService = $this->createMock(TextNormalizationServiceInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->openAIEmbeddingClient = $this->createMock(OpenAIEmbeddingClientInterface::class);

        // Configure entityManager to return appropriate repositories
        $recommendationRepository = $this->createMock(EntityRepository::class);
        $recommendationEmbeddingRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($entityClass) use ($recommendationRepository, $recommendationEmbeddingRepository) {
                if (Recommendation::class === $entityClass) {
                    return $recommendationRepository;
                }
                if (\App\Entity\RecommendationEmbedding::class === $entityClass) {
                    return $recommendationEmbeddingRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $this->recommendationService = new RecommendationService(
            $this->textNormalizationService,
            $this->entityManager,
            $this->tagRepository,
            $this->openAIEmbeddingClient
        );
    }

    public function testCreateOrUpdateRecommendationCreatesNewRecommendationWhenNotExists(): void
    {
        $userId = 1;
        $text = 'Test recommendation';
        $normalizedText = 'test recommendation';
        $hash = 'test_hash_123';
        $tagIds = [1, 2];

        $tags = [
            $this->createTag(1, 'fantasy'),
            $this->createTag(2, 'sci-fi'),
        ];

        // Mock text normalization
        $this->textNormalizationService
            ->expects($this->once())
            ->method('normalizeText')
            ->with($text)
            ->willReturn($normalizedText);

        $this->textNormalizationService
            ->expects($this->once())
            ->method('generateHash')
            ->with($normalizedText)
            ->willReturn($hash);

        // Configure mock repositories
        $recommendationRepository = $this->entityManager->getRepository(Recommendation::class);
        $recommendationEmbeddingRepository = $this->entityManager->getRepository(\App\Entity\RecommendationEmbedding::class);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash,
            ])
            ->willReturn(null);

        $recommendationEmbeddingRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn(null);

        // Mock OpenAI client to return embedding
        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with($text)
            ->willReturn([0.1, 0.2, 0.3]);

        // Mock tag finding
        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => $tagIds])
            ->willReturn($tags);

        // Mock user reference
        $userMock = $this->createMock(\App\Entity\User::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getReference')
            ->with(\App\Entity\User::class, $userId)
            ->willReturn($userMock);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->logicalOr(
                $this->isInstanceOf(\App\Entity\RecommendationEmbedding::class),
                $this->isInstanceOf(Recommendation::class)
            ));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $result = $this->recommendationService->createOrUpdateRecommendation($userId, $text, $tagIds);

        $this->assertInstanceOf(Recommendation::class, $result);
        $this->assertEquals($text, $result->getShortDescription());
        $this->assertEquals($hash, $result->getNormalizedTextHash());
        $this->assertEquals($tags, $result->getTags()->toArray());
    }

    public function testCreateOrUpdateRecommendationUpdatesExistingRecommendation(): void
    {
        $userId = 1;
        $text = 'Updated recommendation';
        $normalizedText = 'updated recommendation';
        $hash = 'existing_hash_456';
        $tagIds = [3, 4];

        $existingRecommendation = new Recommendation();
        $existingRecommendation->setNormalizedTextHash($hash);

        $tags = [
            $this->createTag(3, 'mystery'),
            $this->createTag(4, 'thriller'),
        ];

        // Mock text normalization
        $this->textNormalizationService
            ->expects($this->once())
            ->method('normalizeText')
            ->with($text)
            ->willReturn($normalizedText);

        $this->textNormalizationService
            ->expects($this->once())
            ->method('generateHash')
            ->with($normalizedText)
            ->willReturn($hash);

        // Configure mock repositories
        $recommendationRepository = $this->entityManager->getRepository(Recommendation::class);
        $recommendationEmbeddingRepository = $this->entityManager->getRepository(\App\Entity\RecommendationEmbedding::class);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash,
            ])
            ->willReturn($existingRecommendation);

        // Mock embedding exists, so no API call needed
        $recommendationEmbeddingRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn($this->createMock(\App\Entity\RecommendationEmbedding::class));

        // OpenAI client should not be called since embedding exists
        $this->openAIEmbeddingClient
            ->expects($this->never())
            ->method('getEmbedding');

        // Mock tag finding
        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => $tagIds])
            ->willReturn($tags);

        // Nie powinno być persist (tylko dla nowych)
        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->recommendationService->createOrUpdateRecommendation($userId, $text, $tagIds);

        $this->assertSame($existingRecommendation, $result);
        $this->assertEquals($tags, $result->getTags()->toArray());
    }

    public function testCreateOrUpdateRecommendationHandlesEmptyTags(): void
    {
        $userId = 1;
        $text = 'Recommendation without tags';
        $normalizedText = 'recommendation without tags';
        $hash = 'no_tags_hash';
        $tagIds = [];

        // Mock text normalization
        $this->textNormalizationService
            ->expects($this->once())
            ->method('normalizeText')
            ->with($text)
            ->willReturn($normalizedText);

        $this->textNormalizationService
            ->expects($this->once())
            ->method('generateHash')
            ->with($normalizedText)
            ->willReturn($hash);

        // Configure mock repositories
        $recommendationRepository = $this->entityManager->getRepository(Recommendation::class);
        $recommendationEmbeddingRepository = $this->entityManager->getRepository(\App\Entity\RecommendationEmbedding::class);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $recommendationEmbeddingRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn(null);

        // Mock OpenAI client to return embedding
        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with($text)
            ->willReturn([0.1, 0.2, 0.3]);

        // Mock tag finding - nie powinno być wywołane dla pustej tablicy
        $this->tagRepository
            ->expects($this->never())
            ->method('findBy');

        // Mock user reference
        $userMock = $this->createMock(\App\Entity\User::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getReference')
            ->with(\App\Entity\User::class, $userId)
            ->willReturn($userMock);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $result = $this->recommendationService->createOrUpdateRecommendation($userId, $text, $tagIds);

        $this->assertInstanceOf(Recommendation::class, $result);
        $this->assertEmpty($result->getTags()->toArray());
    }

    public function testFindTagByNameReturnsTagWhenFound(): void
    {
        $tagName = 'fantasy';
        $expectedTag = $this->createTag(1, $tagName);

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($tagName)
            ->willReturn($expectedTag);

        $result = $this->recommendationService->findTagByName($tagName);

        $this->assertSame($expectedTag, $result);
    }

    public function testFindTagByNameReturnsNullWhenNotFound(): void
    {
        $tagName = 'nonexistent';

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($tagName)
            ->willReturn(null);

        $result = $this->recommendationService->findTagByName($tagName);

        $this->assertNull($result);
    }

    private function createTag(int $id, string $name): Tag
    {
        $tag = new Tag();
        // Użyj reflection żeby ustawić ID (normalne dla testów Doctrine)
        $reflection = new \ReflectionClass($tag);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($tag, $id);

        $tag->setName($name);
        $tag->setActive(true);

        return $tag;
    }
}
