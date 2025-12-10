<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedTagsCommand;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SeedTagsCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SeedTagsCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new SeedTagsCommand($this->entityManager);
    }

    public function testExecuteCreatesNewTags(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock repository to return null for all tags (none exist)
        $tagRepository = $this->createMock(EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepository);

        $tagRepository
            ->expects($this->exactly(50)) // Number of tags in the command
            ->method('findOneBy')
            ->willReturn(null);

        // Expect persist to be called for each tag
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf(Tag::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Pomyślnie utworzono 50 nowych tagów', $commandTester->getDisplay());
    }

    public function testExecuteSkipsExistingTags(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock repository to return existing tag for first tag
        $existingTag = $this->createMock(Tag::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepository);

        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($existingTag) {
                // Return existing tag for first tag, null for others
                static $callCount = 0;
                ++$callCount;
                return $callCount === 1 ? $existingTag : null;
            });

        // Expect persist to be called for 46 tags (one skipped)
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf(Tag::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Pomyślnie utworzono 49 nowych tagów', $commandTester->getDisplay());
        $this->assertStringContainsString('Pominięto 1 istniejących tagów', $commandTester->getDisplay());
    }

    public function testExecuteWhenAllTagsExist(): void
    {
        $commandTester = new CommandTester($this->command);

        // Mock repository to return existing tag for all tags
        $existingTag = $this->createMock(Tag::class);
        $tagRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Tag::class)
            ->willReturn($tagRepository);

        $tagRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->willReturn($existingTag);

        // No tags should be persisted
        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->any())
            ->method('flush');

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Pominięto 50 istniejących tagów', $commandTester->getDisplay());
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('app:seed:tags', $this->command->getName());
        $this->assertEquals('Seed the tags table with initial data', $this->command->getDescription());
    }
}
