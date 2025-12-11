<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Command;

use App\Command\CleanEbooksDataCommand;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

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
}
