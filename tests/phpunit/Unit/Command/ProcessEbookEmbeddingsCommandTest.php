<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ProcessEbookEmbeddingsCommand;
use App\DTO\OpenAIEmbeddingClientInterface;
use App\Entity\Ebook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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

        // Mock repository for empty result
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], 100)
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

        // Mock repository for one result
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], 100)
            ->willReturn([$ebook]);

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running in dry-run mode', $output);
        $this->assertStringContainsString('Dry run completed. Would process 1 ebooks in 1 batches', $output);
    }

    public function testExecuteWithBatchSizeOption(): void
    {
        $commandTester = new CommandTester($this->command);
        $batchSize = 5;

        // Mock empty result (no ebooks to process)
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], 100)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All ebooks already have embeddings', $output);
    }

    public function testExecuteWithMaxBooksOption(): void
    {
        $commandTester = new CommandTester($this->command);
        $maxBooks = 50;

        // Mock empty result
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], $maxBooks)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--max-books' => $maxBooks]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All ebooks already have embeddings', $output);
    }

    public function testBatchSizeValidationExceedsMaximum(): void
    {
        $commandTester = new CommandTester($this->command);
        $batchSize = 15; // Exceeds maximum of 10

        $exitCode = $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertEquals(1, $exitCode); // FAILURE exit code
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Batch size cannot exceed 10', $output);
    }

    public function testBatchSizeValidationWithinLimit(): void
    {
        $commandTester = new CommandTester($this->command);
        $batchSize = 10; // Maximum allowed

        // Mock empty result
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], 100)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All ebooks already have embeddings', $output);
    }

    public function testBatchProcessingWithMultipleEbooks(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebooks
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('Description 1');
        $ebook1->method('getTags')->willReturn('tag1, tag2');

        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getIsbn')->willReturn('1234567891');
        $ebook2->method('getTitle')->willReturn('Book 2');
        $ebook2->method('getAuthor')->willReturn('Author 2');
        $ebook2->method('getMainDescription')->willReturn('Description 2');
        $ebook2->method('getTags')->willReturn('tag3, tag4');

        $ebook3 = $this->createMock(Ebook::class);
        $ebook3->method('getIsbn')->willReturn('1234567892');
        $ebook3->method('getTitle')->willReturn('Book 3');
        $ebook3->method('getAuthor')->willReturn('Author 3');
        $ebook3->method('getMainDescription')->willReturn('Description 3');
        $ebook3->method('getTags')->willReturn('tag5');

        // Mock repository
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($repository);

        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['hasEmbedding' => false], ['id' => 'ASC'], 100)
            ->willReturn([$ebook1, $ebook2, $ebook3]);

        // Mock OpenAI client to return embeddings
        $this->openAIEmbeddingClient
            ->expects($this->once())
            ->method('getEmbeddingsBatch')
            ->willReturn([
                [0.1, 0.2, 0.3], // embedding for first book
                [0.4, 0.5, 0.6], // embedding for second book
                [0.7, 0.8, 0.9], // embedding for third book
            ]);

        // Expect persist to be called for each ebook and ebookEmbedding
        $this->entityManager
            ->expects($this->exactly(3))
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute(['--batch-size' => 5, '--dry-run' => false]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully processed 3 ebooks in 1 batches', $output);
    }

    public function testUuidGeneration(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('generateUuid');
        $method->setAccessible(true);

        $uuid1 = $method->invoke($this->command);
        $uuid2 = $method->invoke($this->command);

        // Check UUID format (UUID v4)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid2);

        // Check UUIDs are unique
        $this->assertNotEquals($uuid1, $uuid2);
    }

    public function testPreparePayloadMethod(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('preparePayload');
        $method->setAccessible(true);

        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getTitle')->willReturn('Test Title');
        $ebook->method('getAuthor')->willReturn('Test Author');
        $ebook->method('getMainDescription')->willReturn('Test Description');

        $payload = $method->invoke($this->command, $ebook);

        $expected = "Test Title\nTest Author\nTest Description";
        $this->assertEquals($expected, $payload);
    }

    public function testParseTagsFromEbookMethod(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('parseTagsFromEbook');
        $method->setAccessible(true);

        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getTags')->willReturn('tag1, tag2, tag3');

        $tags = $method->invoke($this->command, $ebook);

        $this->assertEquals(['tag1', 'tag2', 'tag3'], $tags);
    }

    public function testParseTagsFromEbookWithEmptyTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('parseTagsFromEbook');
        $method->setAccessible(true);

        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getTags')->willReturn(null);

        $tags = $method->invoke($this->command, $ebook);

        $this->assertEquals([], $tags);
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:process:ebook-embeddings', $this->command->getName());
        $this->assertEquals('Process embeddings for ebooks that don\'t have them yet', $this->command->getDescription());
    }

    public function testCommandHasCorrectOptions(): void
    {
        $inputDefinition = $this->command->getDefinition();

        $this->assertTrue($inputDefinition->hasOption('max-books'));
        $this->assertTrue($inputDefinition->hasOption('batch-size'));
        $this->assertTrue($inputDefinition->hasOption('dry-run'));

        $maxBooksOption = $inputDefinition->getOption('max-books');
        $this->assertTrue($maxBooksOption->isValueOptional());
        $this->assertEquals('100', $maxBooksOption->getDefault());

        $batchSizeOption = $inputDefinition->getOption('batch-size');
        $this->assertTrue($batchSizeOption->isValueOptional());
        $this->assertEquals('10', $batchSizeOption->getDefault());
    }
}
