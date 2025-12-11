<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\DTO\EbookEmbeddingServiceInterface;
use App\DTO\OpenAIEmbeddingClientInterface;
use App\DTO\TextNormalizationServiceInterface;
use App\Entity\Ebook;
use App\Entity\Recommendation;
use App\Entity\RecommendationEmbedding;
use App\Entity\RecommendationResult;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecommendationServiceTest extends TestCase
{
    private TextNormalizationServiceInterface $textNormalizationService;
    private EntityManagerInterface $entityManager;
    private TagRepository $tagRepository;
    private OpenAIEmbeddingClientInterface $openAIEmbeddingClient;
    private EbookEmbeddingServiceInterface $ebookEmbeddingService;
    private LoggerInterface $logger;
    private RecommendationService $recommendationService;

    protected function setUp(): void
    {
        $this->textNormalizationService = $this->createMock(TextNormalizationServiceInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->openAIEmbeddingClient = $this->createMock(OpenAIEmbeddingClientInterface::class);
        $this->ebookEmbeddingService = $this->createMock(EbookEmbeddingServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->recommendationService = new RecommendationService(
            $this->textNormalizationService,
            $this->entityManager,
            $this->tagRepository,
            $this->openAIEmbeddingClient,
            $this->ebookEmbeddingService,
            $this->logger
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
        $recommendationResultRepo = $this->createMock(EntityRepository::class);
        $ebookRepo = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationRepo, $recommendationEmbeddingRepo, $recommendationResultRepo, $ebookRepo) {
                if (Recommendation::class === $class) {
                    return $recommendationRepo;
                }
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }
                if (RecommendationResult::class === $class) {
                    return $recommendationResultRepo;
                }
                if (Ebook::class === $class) {
                    return $ebookRepo;
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
        $recommendationResultRepo = $this->createMock(EntityRepository::class);
        $ebookRepo = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationRepo, $recommendationEmbeddingRepo, $recommendationResultRepo, $ebookRepo) {
                if (Recommendation::class === $class) {
                    return $recommendationRepo;
                }
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }
                if (RecommendationResult::class === $class) {
                    return $recommendationResultRepo;
                }
                if (Ebook::class === $class) {
                    return $ebookRepo;
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

    public function testCreateOrUpdateRecommendationWithEmptyTagIds(): void
    {
        $userId = 1;
        $text = 'Test book description';
        $normalizedText = 'test book description';
        $hash = 'test_hash';
        $tagIds = []; // Empty array to test findTagsByIds empty case

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
            ->expects($this->once())
            ->method('getEmbedding')
            ->willReturn($embedding);

        // Mock that recommendation doesn't exist
        $recommendationRepo = $this->createMock(EntityRepository::class);
        $recommendationResultRepo = $this->createMock(EntityRepository::class);
        $ebookRepo = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationRepo, $recommendationEmbeddingRepo, $recommendationResultRepo, $ebookRepo) {
                if (Recommendation::class === $class) {
                    return $recommendationRepo;
                }
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }
                if (RecommendationResult::class === $class) {
                    return $recommendationResultRepo;
                }
                if (Ebook::class === $class) {
                    return $ebookRepo;
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

        // Mock tags - should not be called for empty tagIds
        $this->tagRepository
            ->expects($this->never())
            ->method('findBy');

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

    public function testGetQdrantStats(): void
    {
        $expectedStats = [
            'collection_name' => 'ebooks',
            'vectors_count' => 1000,
            'status' => 'green',
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn($expectedStats);

        $result = $this->recommendationService->getQdrantStats();

        $this->assertSame($expectedStats, $result);
    }

    public function testGetQdrantStatsReturnsNullWhenServiceReturnsNull(): void
    {
        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn(null);

        $result = $this->recommendationService->getQdrantStats();

        $this->assertNull($result);
    }

    public function testSearchAndStoreSimilarEbooksWithExistingEmbedding(): void
    {
        $recommendation = $this->createMock(Recommendation::class);
        $text = 'Test recommendation text';
        $normalizedText = 'test recommendation text';
        $hash = 'test_hash';
        $embedding = [0.1, 0.2, 0.3];

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

        // Mock existing embedding
        $existingEmbedding = $this->createMock(RecommendationEmbedding::class);
        $existingEmbedding->method('getEmbedding')->willReturn($embedding);

        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);
        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn($existingEmbedding);

        // Mock similar ebooks results
        $similarEbooks = [
            ['isbn' => '1234567890', 'similarity_score' => 0.95],
            ['isbn' => '0987654321', 'similarity_score' => 0.90],
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('findSimilarEbooks')
            ->with($embedding, 20)
            ->willReturn($similarEbooks);

        // Mock ebook repository
        $ebook1 = $this->createMock(Ebook::class);
        $ebook2 = $this->createMock(Ebook::class);
        $ebookRepo = $this->createMock(EntityRepository::class);

        $ebookRepo
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                if ($criteria === ['isbn' => '1234567890']) {
                    return $this->createMock(Ebook::class);
                }
                if ($criteria === ['isbn' => '0987654321']) {
                    return $this->createMock(Ebook::class);
                }

                return null;
            });

        // Mock recommendation result repository
        $existingResult = $this->createMock(RecommendationResult::class);
        $recommendationResultRepo = $this->createMock(EntityRepository::class);
        $recommendationResultRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['recommendation' => $recommendation])
            ->willReturn([$existingResult]);

        // Setup entity manager
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationEmbeddingRepo, $recommendationResultRepo, $ebookRepo) {
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }
                if (RecommendationResult::class === $class) {
                    return $recommendationResultRepo;
                }
                if (Ebook::class === $class) {
                    return $ebookRepo;
                }

                return $this->createMock(EntityRepository::class);
            });

        // Mock recommendation methods
        $recommendation
            ->expects($this->once())
            ->method('getShortDescription')
            ->willReturn($text);

        $recommendation
            ->expects($this->once())
            ->method('setFoundBooksCount')
            ->with(2);

        $recommendation
            ->expects($this->once())
            ->method('setLastSearchAt')
            ->with($this->isInstanceOf(\DateTime::class));

        // Mock entity manager operations
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($existingResult);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(RecommendationResult::class));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        // Execute
        $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);
    }

    public function testSearchAndStoreSimilarEbooksWithNoResults(): void
    {
        $recommendation = $this->createMock(Recommendation::class);
        $text = 'Test recommendation text';
        $normalizedText = 'test recommendation text';
        $hash = 'test_hash';
        $embedding = [0.1, 0.2, 0.3];

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

        // Mock existing embedding
        $existingEmbedding = $this->createMock(RecommendationEmbedding::class);
        $existingEmbedding->method('getEmbedding')->willReturn($embedding);

        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);
        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn($existingEmbedding);

        // Mock no similar ebooks found
        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('findSimilarEbooks')
            ->with($embedding, 20)
            ->willReturn([]);

        // Mock recommendation methods for no results case
        $recommendation
            ->expects($this->once())
            ->method('getShortDescription')
            ->willReturn($text);

        $recommendation
            ->expects($this->once())
            ->method('setFoundBooksCount')
            ->with(0);

        $recommendation
            ->expects($this->once())
            ->method('setLastSearchAt')
            ->with($this->isInstanceOf(\DateTime::class));

        // Setup minimal entity manager
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationEmbeddingRepo) {
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }

                return $this->createMock(EntityRepository::class);
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Execute
        $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);
    }

    public function testSearchAndStoreSimilarEbooksHandlesExceptionGracefully(): void
    {
        $recommendation = $this->createMock(Recommendation::class);
        $text = 'Test recommendation text';
        $normalizedText = 'test recommendation text';
        $hash = 'test_hash';

        // Mock the recommendation to return text
        $recommendation
            ->expects($this->once())
            ->method('getShortDescription')
            ->willReturn($text);

        // Mock text normalization to succeed
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

        // Mock embedding repository to throw exception (simulating database error)
        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);
        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new \Exception('Database error'));

        // Setup entity manager to return the problematic repository
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(RecommendationEmbedding::class)
            ->willReturn($recommendationEmbeddingRepo);

        // Expect logger to be called with error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to search and store similar ebooks: {error}',
                $this->callback(function ($context) {
                    return isset($context['error']) && isset($context['exception']);
                })
            );

        // Execute - should not throw exception, should handle it gracefully
        $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);

        // Test passes if no exception is thrown from the service
        $this->assertTrue(true);
    }

    public function testSearchAndStoreSimilarEbooksSkipsMissingEbooks(): void
    {
        $recommendation = $this->createMock(Recommendation::class);
        $text = 'Test recommendation text';
        $normalizedText = 'test recommendation text';
        $hash = 'test_hash';
        $embedding = [0.1, 0.2, 0.3];

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

        // Mock existing embedding
        $existingEmbedding = $this->createMock(RecommendationEmbedding::class);
        $existingEmbedding->method('getEmbedding')->willReturn($embedding);

        $recommendationEmbeddingRepo = $this->createMock(EntityRepository::class);
        $recommendationEmbeddingRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['normalizedTextHash' => $hash])
            ->willReturn($existingEmbedding);

        // Mock similar ebooks results - one valid, one invalid
        $similarEbooks = [
            ['isbn' => '1234567890', 'similarity_score' => 0.95],
            ['isbn' => '9999999999', 'similarity_score' => 0.90], // This ebook won't be found
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('findSimilarEbooks')
            ->with($embedding, 20)
            ->willReturn($similarEbooks);

        // Mock ebook repository - only return ebook for first ISBN
        $ebook = $this->createMock(Ebook::class);
        $ebookRepo = $this->createMock(EntityRepository::class);

        $ebookRepo
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($ebook) {
                if ($criteria === ['isbn' => '1234567890']) {
                    return $ebook;
                }

                return null; // Second ebook not found
            });

        // Mock recommendation result repository
        $recommendationResultRepo = $this->createMock(EntityRepository::class);
        $recommendationResultRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['recommendation' => $recommendation])
            ->willReturn([]);

        // Setup entity manager
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($recommendationEmbeddingRepo, $recommendationResultRepo, $ebookRepo) {
                if (RecommendationEmbedding::class === $class) {
                    return $recommendationEmbeddingRepo;
                }
                if (RecommendationResult::class === $class) {
                    return $recommendationResultRepo;
                }
                if (Ebook::class === $class) {
                    return $ebookRepo;
                }

                return $this->createMock(EntityRepository::class);
            });

        // Mock recommendation methods
        $recommendation
            ->expects($this->once())
            ->method('getShortDescription')
            ->willReturn($text);

        $recommendation
            ->expects($this->once())
            ->method('setFoundBooksCount')
            ->with(1); // Only one ebook found

        $recommendation
            ->expects($this->once())
            ->method('setLastSearchAt')
            ->with($this->isInstanceOf(\DateTime::class));

        // Mock entity manager operations - only one result should be persisted
        $this->entityManager
            ->expects($this->exactly(1))
            ->method('persist')
            ->with($this->isInstanceOf(RecommendationResult::class));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush'); // One for clearing existing results, one for saving new results

        // Execute
        $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);
    }

    public function testConstructorInitializesDependencies(): void
    {
        $reflection = new \ReflectionClass(RecommendationService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(6, $parameters);
        $this->assertEquals('textNormalizationService', $parameters[0]->getName());
        $this->assertEquals('entityManager', $parameters[1]->getName());
        $this->assertEquals('tagRepository', $parameters[2]->getName());
        $this->assertEquals('openAIEmbeddingClient', $parameters[3]->getName());
        $this->assertEquals('ebookEmbeddingService', $parameters[4]->getName());
        $this->assertEquals('logger', $parameters[5]->getName());
    }
}
