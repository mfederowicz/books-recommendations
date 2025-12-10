<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\TestQdrantCommand;
use App\DTO\QdrantClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestQdrantCommandTest extends TestCase
{
    private QdrantClientInterface $qdrantClient;
    private TestQdrantCommand $command;

    protected function setUp(): void
    {
        $this->qdrantClient = $this->createMock(QdrantClientInterface::class);
        $this->command = new TestQdrantCommand($this->qdrantClient);
    }

    public function testExecuteSuccessWithRandomData(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock collection creation
        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollection')
            ->with('ebooks', 1536)
            ->willReturn(true);

        // Mock collection info
        $collectionInfo = [
            'result' => [
                'name' => 'ebooks',
                'vectors' => [
                    'size' => 1536,
                    'distance' => 'Cosine',
                ],
                'points_count' => 0,
            ],
        ];
        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with('ebooks')
            ->willReturn($collectionInfo);

        // Mock upsert points
        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->with('ebooks', $this->isArray())
            ->willReturn(true);

        // Mock search
        $searchResults = [
            [
                'id' => 'ebook_1',
                'score' => 0.95,
                'payload' => ['title' => 'Test Book'],
            ],
        ];
        $this->qdrantClient
            ->expects($this->once())
            ->method('search')
            ->with('ebooks', $this->isArray(), 3)
            ->willReturn($searchResults);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Collection \'ebooks\' created successfully', $output);
        $this->assertStringContainsString('Batch upsert completed successfully', $output);
        $this->assertStringContainsString('Search completed successfully', $output);
        $this->assertStringContainsString('All Qdrant tests completed successfully!', $output);
    }

    public function testExecuteWithCleanupOption(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock basic operations
        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollection')
            ->willReturn(true);

        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->willReturn(['result' => ['name' => 'ebooks', 'vectors' => ['size' => 1536], 'points_count' => 0]]);

        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->willReturn(true);

        $this->qdrantClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([]);

        // Mock cleanup
        $this->qdrantClient
            ->expects($this->once())
            ->method('deleteCollection')
            ->with('ebooks')
            ->willReturn(true);

        $exitCode = $commandTester->execute(['--cleanup' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Collection \'ebooks\' deleted successfully', $output);
    }

    public function testExecuteWithCustomCollectionName(): void
    {
        $commandTester = new CommandTester($this->command);
        $customCollection = 'test_collection';

        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollection')
            ->with($customCollection, 1536)
            ->willReturn(true);

        $this->qdrantClient
            ->expects($this->once())
            ->method('getCollectionInfo')
            ->with($customCollection)
            ->willReturn(null); // No info available

        $this->qdrantClient
            ->expects($this->once())
            ->method('upsertPoints')
            ->with($customCollection, $this->isArray())
            ->willReturn(true);

        $this->qdrantClient
            ->expects($this->once())
            ->method('search')
            ->with($customCollection, $this->isArray(), 3)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--collection' => $customCollection]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Collection '{$customCollection}' created successfully", $output);
    }

    public function testExecuteFailureOnCollectionCreation(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->qdrantClient
            ->expects($this->once())
            ->method('createCollection')
            ->willThrowException(new \Exception('Connection failed'));

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Qdrant test failed: Connection failed', $output);
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:test:qdrant', $this->command->getName());
        $this->assertEquals('Test Qdrant vector database operations', $this->command->getDescription());
    }
}
