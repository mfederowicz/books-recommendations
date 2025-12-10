<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\DTO\EbookEmbeddingServiceInterface;
use App\DTO\OpenAIEmbeddingClientInterface;
use App\DTO\TextNormalizationServiceInterface;
use App\Entity\Recommendation;
use App\Entity\RecommendationEmbedding;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    private TextNormalizationServiceInterface $textNormalizationService;
    private EntityManagerInterface $entityManager;
    private TagRepository $tagRepository;
    private OpenAIEmbeddingClientInterface $openAIEmbeddingClient;
    private EbookEmbeddingServiceInterface $ebookEmbeddingService;
    private RecommendationService $recommendationService;

    protected function setUp(): void
    {
        $this->textNormalizationService = $this->createMock(TextNormalizationServiceInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->openAIEmbeddingClient = $this->createMock(OpenAIEmbeddingClientInterface::class);
        $this->ebookEmbeddingService = $this->createMock(EbookEmbeddingServiceInterface::class);

        $this->recommendationService = new RecommendationService(
            $this->textNormalizationService,
            $this->entityManager,
            $this->tagRepository,
            $this->openAIEmbeddingClient,
            $this->ebookEmbeddingService
        );
    }

    public function testCreateOrUpdateRecommendationCreatesNewRecommendationWhenNotExists(): void
    {
        $userId = 1;
        $text = 'Test book description';
        $normalizedText = 'test book description';
        $hash = 'test_hash';
        $tagIds = [1, 2];

        // Mock normalization and hash generation
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

        // Mock that embedding doesn't exist
        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);

        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn(null);

        // Mock OpenAI embedding call
        $embedding = [0.1, 0.2, 0.3];
        $this->openAIEmbeddingClient
            ->expects($this->once()) // Once for ensureEmbeddingExists
            ->method('getEmbedding')
            ->willReturn($embedding);

        // Mock that recommendation doesn't exist
        $recommendationRepo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationRepo, $recommendationEmbeddingRepo) {
                if (Recommendation::class === $class) {
                    return $recommendationRepo;
                }
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }

                return $this->createMock(EntityRepository::class);
            });

        $recommendationRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash,
            ])
            ->willReturn(null);

        // Mock user reference
        $user = $this->createMock(User::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getReference')
            ->with(User::class, $userId)
            ->willReturn($user);

        // Mock tags
        $tag1 = $this->createMock(Tag::class);
        $tag2 = $this->createMock(Tag::class);
        $tags = [$tag1, $tag2];

        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => $tagIds])
            ->willReturn($tags);

        // Mock entity manager operations
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        // Execute
        $result = $this->recommendationService->createOrUpdateRecommendation($userId, $text, $tagIds);

        // Assert
        $this->assertInstanceOf(Recommendation::class, $result);
    }

    public function testCreateOrUpdateRecommendationUpdatesExistingRecommendation(): void
    {
        $userId = 1;
        $text = 'Test book description';
        $normalizedText = 'test book description';
        $hash = 'test_hash';
        $tagIds = [1, 2];

        // Mock normalization and hash generation
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

        // Mock that embedding exists
        $existingEmbedding = $this->createMock(RecommendationEmbedding::class);
        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);

        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn($existingEmbedding);

        // Mock that recommendation exists
        $existingRecommendation = $this->createMock(Recommendation::class);
        $recommendationRepo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationRepo, $recommendationEmbeddingRepo) {
                if (Recommendation::class === $class) {
                    return $recommendationRepo;
                }
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }

                return $this->createMock(EntityRepository::class);
            });

        $recommendationRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $userId,
                'normalizedTextHash' => $hash,
            ])
            ->willReturn($existingRecommendation);

        // Mock tags
        $tag1 = $this->createMock(Tag::class);
        $tag2 = $this->createMock(Tag::class);
        $tags = [$tag1, $tag2];

        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => $tagIds])
            ->willReturn($tags);

        // Mock entity manager operations
        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Execute
        $result = $this->recommendationService->createOrUpdateRecommendation($userId, $text, $tagIds);

        // Assert
        $this->assertSame($existingRecommendation, $result);
    }

    public function testFindSimilarEbooks(): void
    {
        $text = 'Test book';
        $limit = 5;
        $expectedEmbedding = [0.1, 0.2, 0.3];
        $expectedResults = [
            ['title' => 'Book 1', 'similarity' => 0.95],
            ['title' => 'Book 2', 'similarity' => 0.90],
        ];

        // Mock OpenAI embedding
        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbedding')
            ->with($text)
            ->willReturn($expectedEmbedding);

        // Mock ebook embedding service
        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('findSimilarEbooks')
            ->with($expectedEmbedding, $limit)
            ->willReturn($expectedResults);

        // Execute
        $result = $this->recommendationService->findSimilarEbooks($text, $limit);

        // Assert
        $this->assertSame($expectedResults, $result);
    }

    public function testFindTagByName(): void
    {
        $tagName = 'Fiction';
        $expectedTag = $this->createMock(Tag::class);

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($tagName)
            ->willReturn($expectedTag);

        $result = $this->recommendationService->findTagByName($tagName);

        $this->assertSame($expectedTag, $result);
    }
}
