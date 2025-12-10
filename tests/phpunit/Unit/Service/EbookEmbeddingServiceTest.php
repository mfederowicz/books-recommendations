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

        // Should create collection
        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollection')
            ->with('ebooks', 1536)
            ->willReturn(true);

        // Should upsert point
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoint')
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
            ->method('createCollection');

        // Should upsert point
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoint')
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
            ->method('search')
            ->with('ebooks', $queryVector, $limit, [])
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
            ->method('search')
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
