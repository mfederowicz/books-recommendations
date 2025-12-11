<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\DTO\QdrantClientInterface;
use App\Entity\Ebook;
use App\Entity\EbookEmbedding;
use App\Service\EbookEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EbookEmbeddingServiceTest extends TestCase
{
    private QdrantClientInterface $qdrantClient;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private EbookEmbeddingService $service;

    protected function setUp(): void
    {
        $this->qdrantClient = $this->createMock(QdrantClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new EbookEmbeddingService($this->qdrantClient, $this->entityManager, $this->logger);
    }

    public function testSyncEbookEmbeddingToQdrantCreatesCollectionIfNotExists(): void
    {
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getId')->willReturn(123);
        $ebook->method('getIsbn')->willReturn('9781234567890');

        $ebookEmbedding = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding->method('getPayloadTitle')->willReturn('Test Book');
        $ebookEmbedding->method('getPayloadAuthor')->willReturn('Test Author');
        $ebookEmbedding->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding->method('getPayloadDescription')->willReturn('Test description');
        $ebookEmbedding->method('getCreatedAt')->willReturn(new \DateTime());

        // Collection doesn't exist
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(null);

        // Should create collection with named vectors
        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollectionWithNamedVectors')
            ->with('ebooks', ['book_vector' => 1536])
            ->willReturn(true);

        // Should upsert points (batch)
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncEbookEmbeddingToQdrant($ebookEmbedding);

        $this->assertTrue($result);
    }

    public function testSyncEbookEmbeddingToQdrantSkipsCollectionCreationIfExists(): void
    {
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getId')->willReturn(123);

        $ebookEmbedding = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding->method('getCreatedAt')->willReturn(new \DateTime());

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should NOT create collection
        $this->qdrantClient
            ->expects($this->never())
            ->method('createCollectionWithNamedVectors');

        // Should upsert points (batch)
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncEbookEmbeddingToQdrant($ebookEmbedding);

        $this->assertTrue($result);
    }

    public function testFindSimilarEbooks(): void
    {
        $queryVector = [0.1, 0.2, 0.3];
        $limit = 5;

        // Mock Qdrant search results
        $searchResults = [
            [
                'id' => '9781234567890',
                'score' => 0.95,
                'payload' => ['isbn' => '9781234567890'],
            ],
            [
                'id' => '9780987654321',
                'score' => 0.90,
                'payload' => ['isbn' => '9780987654321'],
            ],
        ];

        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getId')->willReturn(123);

        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getId')->willReturn(456);

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Search returns results
        $this->qdrantClient
            ->expects($this->once())
            ->method('searchWithNamedVector')
            ->with('ebooks', 'book_vector', $queryVector, $limit, [])
            ->willReturn($searchResults);

        // Mock repository to return ebooks
        $ebookRepo = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($ebookRepo);

        $ebookRepo
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($ebook1, $ebook2) {
                $isbn = $criteria['isbn'] ?? '';

                return match ($isbn) {
                    '9781234567890' => $ebook1,
                    '9780987654321' => $ebook2,
                    default => null,
                };
            });

        $result = $this->service->findSimilarEbooks($queryVector, $limit);

        $expected = [
            [
                'ebook' => $ebook1,
                'similarity_score' => 0.95,
                'isbn' => '9781234567890',
            ],
            [
                'ebook' => $ebook2,
                'similarity_score' => 0.90,
                'isbn' => '9780987654321',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFindSimilarEbooksSkipsInvalidResults(): void
    {
        $queryVector = [0.1, 0.2, 0.3];

        // Mock search result without ebook_id
        $searchResults = [
            [
                'id' => '123',
                'score' => 0.95,
                'payload' => [], // No ebook_id
            ],
        ];

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->willReturn(['status' => 'ok']);

        $this->qdrantClient
            ->expects($this->once())
            ->method('searchWithNamedVector')
            ->willReturn($searchResults);

        // Repository should not be called since ebook_id is missing
        $this->entityManager
            ->expects($this->never())
            ->method('getRepository');

        $result = $this->service->findSimilarEbooks($queryVector);

        $this->assertEquals([], $result);
    }

    public function testRemoveEbookEmbeddingFromQdrant(): void
    {
        $isbn = '9781234567890';

        $this->qdrantClient
            ->expects($this->once())
            ->method('deletePoint')
            ->with('ebooks', '9781234567890')
            ->willReturn(true);

        $result = $this->service->removeEbookEmbeddingFromQdrant($isbn);

        $this->assertTrue($result);
    }

    public function testSyncEbookEmbeddingsBatchToQdrant(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $ebookEmbedding2 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding2->method('getEbookId')->willReturn('9780987654321');
        $ebookEmbedding2->method('getVector')->willReturn([0.4, 0.5, 0.6]);
        $ebookEmbedding2->method('getPayloadTitle')->willReturn('Book 2');
        $ebookEmbedding2->method('getPayloadAuthor')->willReturn('Author 2');
        $ebookEmbedding2->method('getPayloadTags')->willReturn(['fantasy']);
        $ebookEmbedding2->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding2->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding2->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $embeddings = [$ebookEmbedding1, $ebookEmbedding2];

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points in batch
        // Note: IDs will be UUIDs generated in the service, so we use callback to match any string
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->with('ebooks', $this->callback(function ($points) {
                // Verify structure but not exact UUID values
                $this->assertCount(2, $points);
                $this->assertArrayHasKey('id', $points[0]);
                $this->assertArrayHasKey('vector', $points[0]);
                $this->assertArrayHasKey('payload', $points[0]);
                $this->assertEquals(['book_vector' => [0.1, 0.2, 0.3]], $points[0]['vector']);
                $this->assertEquals('9781234567890', $points[0]['payload']['isbn']);
                $this->assertEquals('Book 1', $points[0]['payload']['title']);
                $this->assertEquals('Author 1', $points[0]['payload']['author']);
                $this->assertEquals(['fiction'], $points[0]['payload']['tags']);

                return true;
            }))
            ->willReturn(true);

        // Should flush changes to database
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->syncEbookEmbeddingsBatchToQdrant($embeddings);

        $this->assertTrue($result);
    }

    public function testSyncAllEbookEmbeddingsToQdrantWithBatching(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $embeddings = [$ebookEmbedding1];

        // Mock repository
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($embeddings);

        // Collection exists (called in ensureQdrantCollectionExists)
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points in batch
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncAllEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 1,
            'synced' => 1,
            'errors' => 0,
        ], $result);
    }

    public function testGetQdrantCollectionStats(): void
    {
        $expectedStats = [
            'vectors_count' => 1000,
            'indexed_vectors_count' => 1000,
        ];

        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn($expectedStats);

        $result = $this->service->getQdrantCollectionStats();

        $this->assertEquals($expectedStats, $result);
    }

    public function testSyncUnsyncedEbookEmbeddingsToQdrant(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $unsyncedEmbeddings = [$ebookEmbedding1];

        // Mock repository for finding unsynced embeddings
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['syncedToQdrant' => false])
            ->willReturn($unsyncedEmbeddings);

        // Collection exists (called in ensureQdrantCollectionExists)
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points in batch
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncUnsyncedEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 1,
            'synced' => 1,
            'errors' => 0,
        ], $result);
    }

    public function testSyncUnsyncedEbookEmbeddingsToQdrantWithNoUnsynced(): void
    {
        // Mock repository for finding unsynced embeddings (returns empty)
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['syncedToQdrant' => false])
            ->willReturn([]);

        // syncEmbeddingsBatch is called even with empty array, so getCollectionInfo is called
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should not call upsertPoints if no embeddings to sync
        $this->qdrantClient
            ->expects($this->never())
            ->method('upsertPoints');

        $result = $this->service->syncUnsyncedEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 0,
            'synced' => 0,
            'errors' => 0,
        ], $result);
    }

    public function testSyncEmbeddingsBatchHandlesBatchFailures(): void
    {
        // This test is for the private syncEmbeddingsBatch method, but we'll test it indirectly
        // through syncUnsyncedEbookEmbeddingsToQdrant by making upsertPoints fail

        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $unsyncedEmbeddings = [$ebookEmbedding1];

        // Mock repository for finding unsynced embeddings
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['syncedToQdrant' => false])
            ->willReturn($unsyncedEmbeddings);

        // Collection exists
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Make upsertPoints fail to test error handling in batch processing
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(false); // Simulate failure

        $result = $this->service->syncUnsyncedEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 1,
            'synced' => 0,
            'errors' => 1,
        ], $result);
    }

    public function testSyncAllEbookEmbeddingsToQdrantResetsSyncStatus(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());
        $ebookEmbedding1->expects($this->exactly(2))->method('setSyncedToQdrant');

        $embeddings = [$ebookEmbedding1];

        // Mock repository
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($embeddings);

        // Should flush after resetting sync status and after successful batch sync
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        // Collection exists
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncAllEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 1,
            'synced' => 1,
            'errors' => 0,
        ], $result);
    }

    public function testGenerateUuidUsesSymfonyIfAvailable(): void
    {
        // Test the UUID generation by calling syncEbookEmbeddingToQdrant
        // which internally calls generateUuid when payloadUuid is null

        $ebookEmbedding = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding->method('getPayloadTitle')->willReturn('Test Book');
        $ebookEmbedding->method('getPayloadAuthor')->willReturn('Test Author');
        $ebookEmbedding->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding->method('getCreatedAt')->willReturn(new \DateTime());
        $ebookEmbedding->method('getPayloadUuid')->willReturn(null); // Force UUID generation
        $ebookEmbedding->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $result = $this->service->syncEbookEmbeddingToQdrant($ebookEmbedding);

        $this->assertTrue($result);
    }

    public function testSyncEbookEmbeddingsBatchToQdrantWithExistingUuids(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn('existing-uuid-123'); // Already has UUID
        $ebookEmbedding1->expects($this->never())->method('setPayloadUuid'); // Should not call setPayloadUuid

        $embeddings = [$ebookEmbedding1];

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should upsert points in batch with existing UUID
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->with('ebooks', $this->callback(function ($points) {
                $this->assertCount(1, $points);
                $this->assertEquals('existing-uuid-123', $points[0]['id']); // Should use existing UUID

                return true;
            }))
            ->willReturn(true);

        // Should flush changes to database
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->syncEbookEmbeddingsBatchToQdrant($embeddings);

        $this->assertTrue($result);
    }

    public function testSyncEbookEmbeddingsBatchToQdrantFailure(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());
        $ebookEmbedding1->expects($this->never())->method('setSyncedToQdrant'); // Should not mark as synced on failure

        $embeddings = [$ebookEmbedding1];

        // Collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Make upsertPoints fail
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(false);

        // Should not flush on failure
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $result = $this->service->syncEbookEmbeddingsBatchToQdrant($embeddings);

        $this->assertFalse($result);
    }

    public function testSyncEmbeddingsBatchWithLargeBatchSize(): void
    {
        // Test the batching logic by creating more embeddings than the batch size (50)
        $embeddings = [];
        for ($i = 0; $i < 75; ++$i) { // More than batch size of 50
            $embedding = $this->createMock(EbookEmbedding::class);
            $embedding->method('getEbookId')->willReturn('978'.str_pad((string) $i, 10, '0', STR_PAD_LEFT));
            $embedding->method('getVector')->willReturn([0.1, 0.2, 0.3]);
            $embedding->method('getPayloadTitle')->willReturn('Book '.$i);
            $embedding->method('getPayloadAuthor')->willReturn('Author '.$i);
            $embedding->method('getPayloadTags')->willReturn(['fiction']);
            $embedding->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
            $embedding->method('getPayloadUuid')->willReturn(null);
            $embedding->expects($this->once())->method('setPayloadUuid')->with($this->isString());

            $embeddings[] = $embedding;
        }

        // Mock repository to return the large batch
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['syncedToQdrant' => false])
            ->willReturn($embeddings);

        // Collection exists
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should call upsertPoints twice (75 items / 50 batch size = 2 batches, rounded up)
        $this->qdrantClient
            ->expects($this->exactly(2))
            ->method('upsertPoints')
            ->willReturn(true);

        // Should flush twice (once per successful batch)
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $result = $this->service->syncUnsyncedEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 75,
            'synced' => 75,
            'errors' => 0,
        ], $result);
    }

    public function testSyncEmbeddingsBatchHandlesBatchException(): void
    {
        $ebookEmbedding1 = $this->createMock(EbookEmbedding::class);
        $ebookEmbedding1->method('getEbookId')->willReturn('9781234567890');
        $ebookEmbedding1->method('getVector')->willReturn([0.1, 0.2, 0.3]);
        $ebookEmbedding1->method('getPayloadTitle')->willReturn('Book 1');
        $ebookEmbedding1->method('getPayloadAuthor')->willReturn('Author 1');
        $ebookEmbedding1->method('getPayloadTags')->willReturn(['fiction']);
        $ebookEmbedding1->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $ebookEmbedding1->method('getPayloadUuid')->willReturn(null);
        $ebookEmbedding1->expects($this->once())->method('setPayloadUuid')->with($this->isString());

        $unsyncedEmbeddings = [$ebookEmbedding1];

        // Mock repository for finding unsynced embeddings
        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['syncedToQdrant' => false])
            ->willReturn($unsyncedEmbeddings);

        // Collection exists
        $this->qdrantClient
            ->expects($this->atLeastOnce())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Make upsertPoints throw exception to test error handling in syncEmbeddingsBatch
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willThrowException(new \Exception('Qdrant connection failed'));

        // Expect logger to be called with error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to sync batch of {count} ebook embeddings: {error}',
                $this->callback(function ($context) {
                    return isset($context['count']) && isset($context['error']) && isset($context['exception']);
                })
            );

        $result = $this->service->syncUnsyncedEbookEmbeddingsToQdrant();

        $this->assertEquals([
            'total' => 1,
            'synced' => 0,
            'errors' => 1,
        ], $result);
    }

    public function testConstructorDependencyInjection(): void
    {
        // Test that constructor properly assigns dependencies
        $reflection = new \ReflectionClass(EbookEmbeddingService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('qdrantClient', $parameters[0]->getName());
        $this->assertEquals('entityManager', $parameters[1]->getName());
        $this->assertEquals('logger', $parameters[2]->getName());

        // Verify types
        $this->assertEquals('App\\DTO\\QdrantClientInterface', $parameters[0]->getType()->getName());
        $this->assertEquals('Doctrine\\ORM\\EntityManagerInterface', $parameters[1]->getType()->getName());
        $this->assertEquals('Psr\\Log\\LoggerInterface', $parameters[2]->getType()->getName());
    }

    public function testGenerateUuidUsesFallbackWhenSymfonyNotAvailable(): void
    {
        // Test the fallback UUID generation path
        $service = $this->getMockBuilder(EbookEmbeddingService::class)
            ->setConstructorArgs([$this->qdrantClient, $this->entityManager, $this->logger])
            ->onlyMethods([])
            ->getMock();

        // Use reflection to call the testable method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateUuidWithSymfonyCheck');
        $method->setAccessible(true);

        $result = $method->invoke($service, false);

        // Should return a UUID-formatted string from fallback
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(36, strlen($result));

        // Should match UUID v4 format pattern
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $result);
    }

    public function testGenerateUuidWithSymfonyCheckFalse(): void
    {
        // Test the testable method directly with Symfony not available
        $service = $this->getMockBuilder(EbookEmbeddingService::class)
            ->setConstructorArgs([$this->qdrantClient, $this->entityManager, $this->logger])
            ->onlyMethods([])
            ->getMock();

        // Use reflection to call the testable method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateUuidWithSymfonyCheck');
        $method->setAccessible(true);

        $result = $method->invoke($service, false);

        // Should return a UUID-formatted string
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(36, strlen($result));

        // Should match UUID v4 format pattern
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $result);
    }

    public function testGenerateUuidWithSymfonyCheckTrue(): void
    {
        // Test the testable method directly with Symfony available
        // Create a mock Symfony Uid class for this test
        $symfonyUidExistsOriginally = class_exists(\Symfony\Component\Uid\Uuid::class);

        if (!$symfonyUidExistsOriginally) {
            // Create mock namespace and class
            eval('
                namespace Symfony\Component\Uid {
                    class Uuid {
                        private string $uuidString;
                        public static function v4(): self {
                            $instance = new self();
                            $instance->uuidString = "550e8400-e29b-41d4-a716-446655440000";
                            return $instance;
                        }
                        public function toRfc4122(): string {
                            return $this->uuidString;
                        }
                    }
                }
            ');
        }

        try {
            $service = $this->getMockBuilder(EbookEmbeddingService::class)
                ->setConstructorArgs([$this->qdrantClient, $this->entityManager, $this->logger])
                ->onlyMethods([])
                ->getMock();

            // Use reflection to call the testable method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('generateUuidWithSymfonyCheck');
            $method->setAccessible(true);

            $result = $method->invoke($service, true);

            // Should return the mock UUID string
            $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result);
            $this->assertIsString($result);
            $this->assertEquals(36, strlen($result));
        } finally {
            // Clean up: Remove the mock class if we created it
            if (!$symfonyUidExistsOriginally && class_exists(\Symfony\Component\Uid\Uuid::class, false)) {
                // The eval'd class remains in memory, but this is acceptable for testing
            }
        }
    }

    public function testEnsureQdrantCollectionExistsWhenCollectionDoesNotExist(): void
    {
        // Test the ensureQdrantCollectionExists method directly when collection doesn't exist
        $service = $this->getMockBuilder(EbookEmbeddingService::class)
            ->setConstructorArgs([$this->qdrantClient, $this->entityManager, $this->logger])
            ->onlyMethods([])
            ->getMock();

        // Mock collection doesn't exist
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(null);

        // Should create collection with named vectors
        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollectionWithNamedVectors')
            ->with('ebooks', ['book_vector' => 1536])
            ->willReturn(true);

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureQdrantCollectionExists');
        $method->setAccessible(true);

        $method->invoke($service);
    }

    public function testEnsureQdrantCollectionExistsWhenCollectionExists(): void
    {
        // Test the ensureQdrantCollectionExists method directly when collection exists
        $service = $this->getMockBuilder(EbookEmbeddingService::class)
            ->setConstructorArgs([$this->qdrantClient, $this->entityManager, $this->logger])
            ->onlyMethods([])
            ->getMock();

        // Mock collection exists
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn(['status' => 'ok']);

        // Should NOT create collection
        $this->qdrantClient
            ->expects($this->never())
            ->method('createCollectionWithNamedVectors');

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureQdrantCollectionExists');
        $method->setAccessible(true);

        $method->invoke($service);
    }

    public function testConstructorInitializesProperties(): void
    {
        // Test that constructor properly initializes the service
        $service = new EbookEmbeddingService($this->qdrantClient, $this->entityManager, $this->logger);

        // Use reflection to check that properties are set
        $reflection = new \ReflectionClass($service);
        $qdrantClientProperty = $reflection->getProperty('qdrantClient');
        $entityManagerProperty = $reflection->getProperty('entityManager');
        $loggerProperty = $reflection->getProperty('logger');

        $qdrantClientProperty->setAccessible(true);
        $entityManagerProperty->setAccessible(true);
        $loggerProperty->setAccessible(true);

        $this->assertSame($this->qdrantClient, $qdrantClientProperty->getValue($service));
        $this->assertSame($this->entityManager, $entityManagerProperty->getValue($service));
        $this->assertSame($this->logger, $loggerProperty->getValue($service));
    }
}
