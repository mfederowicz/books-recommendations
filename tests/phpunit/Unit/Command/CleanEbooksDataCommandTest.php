<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Command;

use App\Command\CleanEbooksDataCommand;
use App\Entity\Ebook;
use App\Entity\Tag;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanEbooksDataCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CleanEbooksDataCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new CleanEbooksDataCommand($this->entityManager);
    }

    public function testCommandHasCorrectName(): void
    {
        $this->assertEquals('app:clean:ebooks-data', $this->command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $this->assertEquals('Clean and prepare ebooks data before embedding processing', $this->command->getDescription());
    }

    public function testConfigureSetsCorrectOptions(): void
    {
        $inputDefinition = $this->command->getDefinition();

        $this->assertTrue($inputDefinition->hasOption('dry-run'));
        $this->assertTrue($inputDefinition->hasOption('batch-size'));
        $this->assertTrue($inputDefinition->hasOption('max-iterations'));

        $batchSizeOption = $inputDefinition->getOption('batch-size');
        $this->assertTrue($batchSizeOption->isValueRequired());
        $this->assertEquals('10', $batchSizeOption->getDefault());
    }

    public function testCleanDescriptionRemovesHtmlTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('cleanDescription');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '<p>Hello <strong>world</strong>!</p>');
        $this->assertEquals('Hello world!', $result);
    }

    public function testCleanDescriptionLimitsLength(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('cleanDescription');
        $method->setAccessible(true);

        $longText = str_repeat('word ', 200); // Over 900 characters
        $result = $method->invoke($this->command, $longText);

        $this->assertLessThanOrEqual(900, strlen($result));
    }

    public function testCleanDescriptionHandlesParagraphs(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('cleanDescription');
        $method->setAccessible(true);

        $htmlWithParagraphs = "<p>First paragraph.</p>\n\n<p>Second paragraph.</p>\n\n<p>Third paragraph.</p>";
        $result = $method->invoke($this->command, $htmlWithParagraphs);

        $this->assertStringContainsString('First paragraph.', $result);
        $this->assertStringContainsString('Second paragraph.', $result);
    }

    public function testFormatDescriptionReturnsCleanDescription(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('formatDescription');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'Test Title', 'Test Author', 'Clean description');

        $this->assertEquals('Clean description', $result);
    }

    public function testSlugifyCreatesValidSlugs(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('slugify');
        $method->setAccessible(true);

        $testCases = [
            'Normal Tag' => 'normal-tag',
            'TAG with SPACES' => 'tag-with-spaces',
            'Tag-With-Dashes' => 'tag-with-dashes',
            'Tag with 123 numbers' => 'tag-with-123-numbers',
            'Tag@#$%^&*()' => 'tag',
            '  Leading and trailing spaces  ' => 'leading-and-trailing-spaces',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->command, $input);
            $this->assertEquals($expected, $result, "Failed for input: '$input'");
        }
    }

    public function testExtractAndSaveTagsHandlesEmptyTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('extractAndSaveTags');
        $method->setAccessible(true);

        // For null/empty tags, should not call getRepository
        $this->entityManager->expects($this->never())
            ->method('getRepository');

        $result = $method->invoke($this->command, null, false);
        $this->assertEquals(0, $result);
    }

    public function testExtractAndSaveTagsCreatesNewTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('extractAndSaveTags');
        $method->setAccessible(true);

        $this->entityManager->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $tagRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepo);

        // Tags don't exist
        $tagRepo->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf(Tag::class));

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $result = $method->invoke($this->command, 'new tag, another tag', false);
        $this->assertGreaterThan(0, $result);
    }

    public function testExtractAndSaveTagsSkipsExistingTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('extractAndSaveTags');
        $method->setAccessible(true);

        $existingTag = $this->createMock(Tag::class);

        $tagRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepo);

        $tagRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'existing tag'])
            ->willReturn($existingTag);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $method->invoke($this->command, 'existing tag', false);
        $this->assertEquals(0, $result);
    }

    public function testExtractAndSaveTagsHandlesTooLongTags(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('extractAndSaveTags');
        $method->setAccessible(true);

        $longTag = str_repeat('a', 51); // 51 characters, over limit

        $tagRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepo);

        $tagRepo->expects($this->never())
            ->method('findOneBy');

        $result = $method->invoke($this->command, $longTag, false);
        $this->assertEquals(0, $result);
    }

    // Integration tests with CommandTester
    public function testExecuteNoEbooksToProcess(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock repository for empty result
        $ebookRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository) {
                if ($class === Ebook::class) {
                    return $ebookRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([]);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteDryRunMode(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Test Book');
        $ebook->method('getAuthor')->willReturn('Test Author');
        $ebook->method('getMainDescription')->willReturn('<p>Test Description</p>');
        $ebook->method('getTags')->willReturn('test tag, another tag');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->any())
            ->method('findBy')
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any())
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // In dry-run mode, should not persist or flush
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE: No actual changes will be made', $output);
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteWithCustomBatchSize(): void
    {
        $commandTester = new CommandTester($this->command);
        $batchSize = 5;

        // Mock empty result
        $ebookRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository) {
                if ($class === Ebook::class) {
                    return $ebookRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], $batchSize)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteWithMaxIterations(): void
    {
        $commandTester = new CommandTester($this->command);
        $maxIterations = 2;

        // Mock empty result
        $ebookRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository) {
                if ($class === Ebook::class) {
                    return $ebookRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([]);

        $exitCode = $commandTester->execute(['--max-iterations' => $maxIterations]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteProcessesMultipleEbooks(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebooks
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('<p>Description 1</p>');
        $ebook1->method('getTags')->willReturn('tag1, tag2');

        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getIsbn')->willReturn('1234567891');
        $ebook2->method('getTitle')->willReturn('Book 2');
        $ebook2->method('getAuthor')->willReturn('Author 2');
        $ebook2->method('getMainDescription')->willReturn('<p>Description 2</p>');
        $ebook2->method('getTags')->willReturn('tag3, tag4');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->any())
            ->method('findBy')
            ->willReturn([$ebook1, $ebook2]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(2, 0, 0);

        // Tags don't exist, so they should be created
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Expect persist and flush calls
        $this->entityManager
            ->expects($this->exactly(6)) // 2 ebooks + 4 tags (2 per book)
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(6)) // 2 ebooks + 4 tags (2 per book)
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
        $this->assertStringContainsString('2 książek, 4 nowych tagów', $output);
    }

    public function testExecuteStopsAtMaxIterations(): void
    {
        $commandTester = new CommandTester($this->command);
        $maxIterations = 1;

        // Create mock ebooks
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('Description 1');
        $ebook1->method('getTags')->willReturn('tag1');

        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getIsbn')->willReturn('1234567891');
        $ebook2->method('getTitle')->willReturn('Book 2');
        $ebook2->method('getAuthor')->willReturn('Author 2');
        $ebook2->method('getMainDescription')->willReturn('Description 2');
        $ebook2->method('getTags')->willReturn('tag2');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        // Only one call should happen since max iterations is 1
        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook1, $ebook2]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(2, 2, 2);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Should process only the first batch
        $this->entityManager
            ->expects($this->exactly(3)) // 2 ebooks + 2 tags
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute(['--max-iterations' => $maxIterations]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wykryto zbyt wiele błędów w iteracji 1', $output);
    }

    public function testExecuteBatchProcessingWithRemainingBooks(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebooks for first batch
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('Description 1');
        $ebook1->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        // First iteration: return 1 ebook
        // Second iteration: return empty array (no more ebooks)
        $ebookRepository
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturnOnConsecutiveCalls([$ebook1], []);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Iteracja 1 zakończona', $output);
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteHandlesProcessingErrorsGracefully(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook that will throw exception
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('Description 1');
        $ebook1->method('getTags')->willThrowException(new \Exception('Processing error'));

        // Create normal ebook
        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getIsbn')->willReturn('1234567891');
        $ebook2->method('getTitle')->willReturn('Book 2');
        $ebook2->method('getAuthor')->willReturn('Author 2');
        $ebook2->method('getMainDescription')->willReturn('Description 2');
        $ebook2->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->any())
            ->method('findBy')
            ->willReturn([$ebook1, $ebook2]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(2, 0, 0);

        // Tags don't exist for successful ebook
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Should only process the successful ebook
        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('1 błędów', $output);
        $this->assertStringContainsString('1 książek, 1 nowych tagów', $output);
    }

    public function testExecuteHandlesEntityManagerClosed(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any())
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        // EntityManager is closed
        $this->entityManager
            ->expects($this->once())
            ->method('isOpen')
            ->willReturn(false);

        // Should not persist or flush when EntityManager is closed
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('1 błędów', $output);
        $this->assertStringContainsString('Pominięto 1 książek z powodu błędów', $output);
    }

    public function testExecuteHandlesUniqueConstraintViolation(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('duplicate tag, another tag');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any())
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(1, 0);

        // Tags don't exist initially
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // First persist succeeds, second throws UniqueConstraintViolationException
        $this->entityManager
            ->expects($this->exactly(3)) // 1 ebook + 2 tags (one will fail)
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Tag) {
                    static $callCount = 0;
                    ++$callCount;
                    if (2 === $callCount) {
                        throw new UniqueConstraintViolationException('Duplicate entry');
                    }
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
        $this->assertStringContainsString('1 książek, 1 nowych tagów', $output); // Only one tag was successfully created
    }

    public function testExecuteHandlesGenericExceptionDuringFlush(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any())
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('persist');

        // Flush throws exception
        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database connection lost'));

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('1 błędów', $output);
        $this->assertStringContainsString('Pominięto 1 książek z powodu błędów', $output);
    }

    public function testExecuteStopsWhenAllBatchesFail(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebooks that will all fail
        $ebook1 = $this->createMock(Ebook::class);
        $ebook1->method('getIsbn')->willReturn('1234567890');
        $ebook1->method('getTitle')->willReturn('Book 1');
        $ebook1->method('getAuthor')->willReturn('Author 1');
        $ebook1->method('getMainDescription')->willReturn('Description 1');
        $ebook1->method('getTags')->willThrowException(new \Exception('Processing error'));

        $ebook2 = $this->createMock(Ebook::class);
        $ebook2->method('getIsbn')->willReturn('1234567891');
        $ebook2->method('getTitle')->willReturn('Book 2');
        $ebook2->method('getAuthor')->willReturn('Author 2');
        $ebook2->method('getMainDescription')->willReturn('Description 2');
        $ebook2->method('getTags')->willThrowException(new \Exception('Processing error'));

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($ebookRepository);

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook1, $ebook2]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(2, 2, 2);

        // Should not persist or flush when all ebooks fail
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wykryto zbyt wiele błędów w iteracji 1', $output);
        $this->assertStringContainsString('Przerywam przetwarzanie', $output);
    }

    public function testExecuteHandlesNullDescriptions(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook with null description
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn(null);
        $ebook->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Expect ebook to be persisted with empty description
        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testExecuteHandlesEmptyTags(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook with empty tags
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->with(Ebook::class)
            ->willReturn($ebookRepository);

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Should persist ebook but not try to create tags
        $this->entityManager
            ->expects($this->exactly(1)) // Only ebook
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(1)) // Only ebook
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
        $this->assertStringContainsString('Pominięto 1 książek z powodu błędów', $output);
    }

    public function testExecuteHandlesTagsWithSpecialCharacters(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook with tags containing special characters
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('tag-with-dashes, tag_with_underscores, tag@#$%^&*()');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->any())
            ->method('findBy')
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(3);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Should create 3 tags (special characters get cleaned by slugify)
        $this->entityManager
            ->expects($this->exactly(4)) // 1 ebook + 3 tags
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(4)) // 1 ebook + 3 tags
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
        $this->assertStringContainsString('3 nowych tagów', $output);
    }

    public function testExecuteHandlesDuplicateTagsInSameEbook(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook with duplicate tags
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('Description 1');
        $ebook->method('getTags')->willReturn('duplicate, tag, duplicate, another, tag');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        // Should create only 3 unique tags (duplicate and tag appear twice)
        $this->entityManager
            ->expects($this->exactly(4)) // 1 ebook + 3 unique tags
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(4)) // 1 ebook + 3 unique tags
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
        $this->assertStringContainsString('3 nowych tagów', $output);
    }

    public function testExecuteHandlesVeryLongDescription(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create very long description (over 900 characters)
        $longDescription = str_repeat('This is a very long description that exceeds the limit. ', 50);

        // Create mock ebook with very long description
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn($longDescription);
        $ebook->method('getTags')->willReturn('tag1');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2)) // 1 ebook + 1 tag
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testBatchSizeValidationWithValidValues(): void
    {
        $commandTester = new CommandTester($this->command);

        // Test with minimum batch size (1)
        $exitCode = $commandTester->execute(['--batch-size' => 1]);
        $this->assertEquals(0, $exitCode);

        // Test with maximum reasonable batch size
        $exitCode = $commandTester->execute(['--batch-size' => 100]);
        $this->assertEquals(0, $exitCode);
    }

    public function testMaxIterationsValidation(): void
    {
        $commandTester = new CommandTester($this->command);

        // Test with valid max iterations
        $exitCode = $commandTester->execute(['--max-iterations' => 5]);
        $this->assertEquals(0, $exitCode);

        // Test with high max iterations
        $exitCode = $commandTester->execute(['--max-iterations' => 1000]);
        $this->assertEquals(0, $exitCode);
    }

    public function testDryRunDoesNotModifyData(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create mock ebook
        $ebook = $this->createMock(Ebook::class);
        $ebook->method('getIsbn')->willReturn('1234567890');
        $ebook->method('getTitle')->willReturn('Book 1');
        $ebook->method('getAuthor')->willReturn('Author 1');
        $ebook->method('getMainDescription')->willReturn('<p>Description</p>');
        $ebook->method('getTags')->willReturn('newtag');

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn([$ebook]);

        $ebookRepository
            ->expects($this->any())
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(1);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        // In dry-run mode, should not call any EntityManager modification methods
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->entityManager->expects($this->never())->method('clear');

        // Should not check if EntityManager is open for actual operations
        $this->entityManager->expects($this->never())->method('isOpen');

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testCommandHandlesInvalidOptionsGracefully(): void
    {
        $commandTester = new CommandTester($this->command);

        // Test with invalid batch size (negative)
        $exitCode = $commandTester->execute(['--batch-size' => -1]);
        // Command still executes but with default batch size
        $this->assertEquals(0, $exitCode);

        // Test with batch size as string
        $exitCode = $commandTester->execute(['--batch-size' => 'invalid']);
        // Symfony console handles this gracefully by using default
        $this->assertEquals(0, $exitCode);

        // Test with max iterations as string
        $exitCode = $commandTester->execute(['--max-iterations' => 'invalid']);
        // Symfony console handles this gracefully
        $this->assertEquals(0, $exitCode);
    }

    public function testCommandProgressOutput(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create multiple ebooks to test progress output
        $ebooks = [];
        for ($i = 1; $i <= 3; ++$i) {
            $ebook = $this->createMock(Ebook::class);
            $ebook->method('getIsbn')->willReturn('123456789'.$i);
            $ebook->method('getTitle')->willReturn('Book '.$i);
            $ebook->method('getAuthor')->willReturn('Author '.$i);
            $ebook->method('getMainDescription')->willReturn('Description '.$i);
            $ebook->method('getTags')->willReturn('tag'.$i);
            $ebooks[] = $ebook;
        }

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['comparisonLink' => null], ['id' => 'ASC'], 10)
            ->willReturn($ebooks);

        $ebookRepository
            ->expects($this->any()) 
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturn(3);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->exactly(6)) // 3 ebooks + 3 tags
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(6)) // 3 ebooks + 3 tags
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();

        // Check for progress indicators
        $this->assertStringContainsString('Iteracja 1: przetwarzanie 3 książek', $output);
        $this->assertStringContainsString('Iteracja 1 zakończona: 3 przetworzonych, 0 błędów', $output);
        $this->assertStringContainsString('3 książek, 3 nowych tagów', $output);
        $this->assertStringContainsString('Wszystkie książki zostały przetworzone!', $output);
    }

    public function testCommandSleepsBetweenIterations(): void
    {
        $commandTester = new CommandTester($this->command);

        // Create ebooks for two batches
        $ebooks1 = [];
        $ebooks2 = [];

        for ($i = 1; $i <= 2; ++$i) {
            $ebook = $this->createMock(Ebook::class);
            $ebook->method('getIsbn')->willReturn('123456789'.$i);
            $ebook->method('getTitle')->willReturn('Book '.$i);
            $ebook->method('getAuthor')->willReturn('Author '.$i);
            $ebook->method('getMainDescription')->willReturn('Description '.$i);
            $ebook->method('getTags')->willReturn('tag'.$i);
            $ebooks1[] = $ebook;
        }

        for ($i = 3; $i <= 3; ++$i) {
            $ebook = $this->createMock(Ebook::class);
            $ebook->method('getIsbn')->willReturn('123456789'.$i);
            $ebook->method('getTitle')->willReturn('Book '.$i);
            $ebook->method('getAuthor')->willReturn('Author '.$i);
            $ebook->method('getMainDescription')->willReturn('Description '.$i);
            $ebook->method('getTags')->willReturn('tag'.$i);
            $ebooks2[] = $ebook;
        }

        // Mock repositories
        $ebookRepository = $this->createMock(EntityRepository::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($ebookRepository, $tagRepository) {
                if (Ebook::class === $class) {
                    return $ebookRepository;
                }
                if (Tag::class === $class) {
                    return $tagRepository;
                }

                return $this->createMock(EntityRepository::class);
            });

        $ebookRepository
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturnOnConsecutiveCalls($ebooks1, $ebooks2);

        $ebookRepository
            ->expects($this->exactly(5)) // Start, after iter1, after iter2, after iter2 check, end
            ->method('count')
            ->with(['comparisonLink' => null])
            ->willReturnOnConsecutiveCalls(3, 1, 0, 0, 0);

        // Tags don't exist
        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->any())
            ->method('isOpen')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->exactly(6)) // 3 ebooks + 3 tags
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(6)) // 3 ebooks + 3 tags
            ->method('flush');

        // Start timing
        $startTime = microtime(true);

        $exitCode = $commandTester->execute(['--batch-size' => 2]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();

        // Should have slept for at least 1 second between iterations
        $this->assertGreaterThanOrEqual(1.0, $executionTime);

        // Check for progress output
        $this->assertStringContainsString('Iteracja 1 zakończona', $output);
        $this->assertStringContainsString('Iteracja 2 zakończona', $output);
    }
}
