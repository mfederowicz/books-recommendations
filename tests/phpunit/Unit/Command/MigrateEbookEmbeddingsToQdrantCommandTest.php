<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\MigrateEbookEmbeddingsToQdrantCommand;
use App\Service\EbookEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateEbookEmbeddingsToQdrantCommandTest extends TestCase
{
    private EbookEmbeddingService $ebookEmbeddingService;
    private EntityManagerInterface $entityManager;
    private MigrateEbookEmbeddingsToQdrantCommand $command;

    protected function setUp(): void
    {
        $this->ebookEmbeddingService = $this->createMock(EbookEmbeddingService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new MigrateEbookEmbeddingsToQdrantCommand($this->ebookEmbeddingService, $this->entityManager);
    }

    public function testExecuteStatsOnlySuccess(): void
    {
        $commandTester = new CommandTester($this->command);

        $stats = [
            'result' => [
                'config' => [
                    'params' => [
                        'vectors' => [
                            'book_vector' => [
                                'size' => 1536,
                                'distance' => 'Cosine',
                            ],
                        ],
                    ],
                ],
                'points_count' => 100,
                'status' => 'green',
            ],
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn($stats);

        // Mock repository calls for stats
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with(\App\Entity\EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnOnConsecutiveCalls(200, 100); // total and synced

        $exitCode = $commandTester->execute(['--stats-only' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Qdrant Collection Statistics', $output);
        $this->assertStringContainsString('ebooks', $output);
        $this->assertStringContainsString('1536', $output);
        $this->assertStringContainsString('100', $output);
    }

    public function testExecuteStatsOnlyFailureWhenNoStats(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn(null);

        $exitCode = $commandTester->execute(['--stats-only' => true]);

        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Qdrant collection does not exist or is not accessible', $output);
    }

    public function testExecuteDryRunMode(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock repository calls for stats
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with(\App\Entity\EbookEmbedding::class)
            ->willReturn($repository);

        $repository
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnOnConsecutiveCalls(100, 0); // total and synced

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('Would sync 100 unsynchronized embeddings', $output);
    }

    public function testExecuteMigrationSuccess(): void
    {
        $commandTester = new CommandTester($this->command);

        $migrationResult = [
            'total' => 50,
            'synced' => 48,
            'errors' => 2,
        ];

        $finalStats = [
            'result' => [
                'points_count' => 48,
                'status' => 'green',
            ],
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('syncUnsyncedEbookEmbeddingsToQdrant')
            ->willReturn($migrationResult);

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn($finalStats);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migration completed!', $output);
        $this->assertStringContainsString('Total Unsynced ebook embeddings', $output);
        $this->assertStringContainsString('48', $output); // synced count
        $this->assertStringContainsString('2', $output);  // errors count
    }

    public function testExecuteMigrationFailure(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('syncUnsyncedEbookEmbeddingsToQdrant')
            ->willThrowException(new \Exception('Database connection failed'));

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Migration failed: Database connection failed', $output);
    }

    public function testExecuteMigrationWithErrorsWarning(): void
    {
        $commandTester = new CommandTester($this->command);

        $migrationResult = [
            'total' => 10,
            'synced' => 8,
            'errors' => 2,
        ];

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('syncUnsyncedEbookEmbeddingsToQdrant')
            ->willReturn($migrationResult);

        $this->ebookEmbeddingService
            ->expects($this->once())
            ->method('getQdrantCollectionStats')
            ->willReturn(['result' => ['points_count' => 8]]);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('2 ebook embeddings failed to sync', $output);
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:migrate:ebook-embeddings-to-qdrant', $this->command->getName());
        $this->assertEquals('Migrate ebook embeddings from database to Qdrant vector database for fast similarity search', $this->command->getDescription());
    }
}
