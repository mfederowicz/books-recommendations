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

class EbookEmbeddingServiceTest extends TestCase
{
    private QdrantClientInterface $qdrantClient;
    private EntityManagerInterface $entityManager;
    private EbookEmbeddingService $service;

    protected function setUp(): void
    {
        $this->qdrantClient = $this->createMock(QdrantClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new EbookEmbeddingService($this->qdrantClient, $this->entityManager);
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
}
