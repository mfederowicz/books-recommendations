<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagService;
use PHPUnit\Framework\TestCase;

class TagServiceTest extends TestCase
{
    private TagRepository $tagRepository;
    private TagService $tagService;

    protected function setUp(): void
    {
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->tagService = new TagService($this->tagRepository);
    }

    public function testFindActiveTagsForAutocompleteReturnsEmptyForShortQuery(): void
    {
        $result = $this->tagService->findActiveTagsForAutocomplete('a');

        $this->assertEquals([], $result);
    }

    public function testFindActiveTagsForAutocompleteReturnsEmptyForWhitespaceQuery(): void
    {
        $result = $this->tagService->findActiveTagsForAutocomplete('  ');

        $this->assertEquals([], $result);
    }

    public function testFindActiveTagsForAutocompleteReturnsResultsForValidQuery(): void
    {
        $query = 'sci';
        $expectedTags = [
            $this->createMock(Tag::class),
            $this->createMock(Tag::class),
        ];

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagsStartingWith')
            ->with('sci', 30)
            ->willReturn($expectedTags);

        $result = $this->tagService->findActiveTagsForAutocomplete($query);

        $this->assertSame($expectedTags, $result);
    }

    public function testFindActiveTagsForAutocompleteTrimsQuery(): void
    {
        $query = '  sci  ';
        $expectedTags = [$this->createMock(Tag::class)];

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagsStartingWith')
            ->with('sci', 30)
            ->willReturn($expectedTags);

        $result = $this->tagService->findActiveTagsForAutocomplete($query);

        $this->assertSame($expectedTags, $result);
    }

    public function testFindActiveTagByName(): void
    {
        $name = 'Fiction';
        $expectedTag = $this->createMock(Tag::class);

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($name)
            ->willReturn($expectedTag);

        $result = $this->tagService->findActiveTagByName($name);

        $this->assertSame($expectedTag, $result);
    }

    public function testFindActiveTagByNameReturnsNullWhenNotFound(): void
    {
        $name = 'NonExistentTag';

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($name)
            ->willReturn(null);

        $result = $this->tagService->findActiveTagByName($name);

        $this->assertNull($result);
    }

    public function testFindAllActiveTags(): void
    {
        $expectedTags = [
            $this->createMock(Tag::class),
            $this->createMock(Tag::class),
            $this->createMock(Tag::class),
        ];

        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['active' => true], ['name' => 'ASC'])
            ->willReturn($expectedTags);

        $result = $this->tagService->findAllActiveTags();

        $this->assertSame($expectedTags, $result);
    }

    public function testFindAllActiveTagsReturnsEmptyArrayWhenNoTags(): void
    {
        $this->tagRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['active' => true], ['name' => 'ASC'])
            ->willReturn([]);

        $result = $this->tagService->findAllActiveTags();

        $this->assertEquals([], $result);
    }
}