<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ProcessEbookEmbeddingsCommand;
use App\DTO\OpenAIEmbeddingClientInterface;
use App\Entity\Ebook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ProcessEbookEmbeddingsCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private OpenAIEmbeddingClientInterface $openAIEmbeddingClient;
    private ProcessEbookEmbeddingsCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->openAIEmbeddingClient = $this->createMock(OpenAIEmbeddingClientInterface::class);
        $this->command = new ProcessEbookEmbeddingsCommand($this->entityManager, $this->openAIEmbeddingClient);
    }

    public function testExecuteWhenNoEbooksWithoutEmbeddings(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock query builder for empty result
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([]); // No ebooks without embeddings

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All ebooks already have embeddings', $output);
    }

    public function testExecuteDryRunMode(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getTitle')->willReturn('Test Book');
        $ebook->method('getAuthor')->willReturn('Test Author');

        // Mock query builder for one result
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Setup query builder chain
        $queryBuilder->expects($this->once())->method('select')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('from')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('leftJoin')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setMaxResults')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);
        $query->expects($this->once())->method('getResult')->willReturn([$ebook]);

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running in dry-run mode', $output);
        $this->assertStringContainsString('Dry run completed. Would process 1 ebooks', $output);
    }

    public function testExecuteWithBatchSizeOption(): void
    {
        $commandTester = new CommandTester($this->command);
        $batchSize = 5;

        // Mock empty result (no ebooks to process)
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())->method('select')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('from')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('leftJoin')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setMaxResults')->with($batchSize)->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);
        $query->expects($this->once())->method('getResult')->willReturn([]);

        $exitCode = $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All ebooks already have embeddings', $output);
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:process:ebook-embeddings', $this->command->getName());
        $this->assertEquals('Process embeddings for ebooks that don\'t have them yet', $this->command->getDescription());
    }
}
