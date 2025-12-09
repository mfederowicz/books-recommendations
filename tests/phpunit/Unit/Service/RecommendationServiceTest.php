<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

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
    private RecommendationService $recommendationService;

    protected function setUp(): void
    {
        $this->textNormalizationService = $this->createMock(TextNormalizationServiceInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->recommendationService = new RecommendationService(
            $this->textNormalizationService,
            $this->entityManager,
            $this->tagRepository
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
            $this->createTag(2, 'sci-fi')
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

        // Mock repository - nie ma istniejącej rekomendacji
        $recommendationRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Recommendation::class)
            ->willReturn($recommendationRepository);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash
            ])
            ->willReturn(null);

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
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Recommendation::class));

        $this->entityManager
            ->expects($this->once())
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
            $this->createTag(4, 'thriller')
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

        // Mock repository - istnieje rekomendacja
        $recommendationRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Recommendation::class)
            ->willReturn($recommendationRepository);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash
            ])
            ->willReturn($existingRecommendation);

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

        // Mock repository
        $recommendationRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Recommendation::class)
            ->willReturn($recommendationRepository);

        $recommendationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

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
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
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
