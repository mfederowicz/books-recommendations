<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagService;
use PHPUnit\Framework\TestCase;

final class TagServiceTest extends TestCase
{
    private TagRepository $tagRepository;
    private TagService $tagService;

    protected function setUp(): void
    {
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->tagService = new TagService($this->tagRepository);
    }

    public function testFindActiveTagsForAutocompleteReturnsEmptyArrayForShortQuery(): void
    {
        $result = $this->tagService->findActiveTagsForAutocomplete('a');

        $this->assertEquals([], $result);
    }

    public function testFindActiveTagsForAutocompleteCallsRepositoryWithCorrectQuery(): void
    {
        $query = 'fant';
        $expectedTags = [
            $this->createTag('fantasy'),
            $this->createTag('fantastic')
        ];

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagsStartingWith')
            ->with($query, 30)
            ->willReturn($expectedTags);

        $result = $this->tagService->findActiveTagsForAutocomplete($query);

        $this->assertEquals($expectedTags, $result);
    }

    public function testFindActiveTagsForAutocompleteTrimsQuery(): void
    {
        $query = '  fant  ';
        $expectedTags = [$this->createTag('fantasy')];

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagsStartingWith')
            ->with('fant', 30)
            ->willReturn($expectedTags);

        $result = $this->tagService->findActiveTagsForAutocomplete($query);

        $this->assertEquals($expectedTags, $result);
    }

    public function testFindActiveTagByNameCallsRepositoryWithCorrectName(): void
    {
        $name = 'fantasy';
        $expectedTag = $this->createTag($name);

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($name)
            ->willReturn($expectedTag);

        $result = $this->tagService->findActiveTagByName($name);

        $this->assertEquals($expectedTag, $result);
    }

    public function testFindActiveTagByNameReturnsNullWhenTagNotFound(): void
    {
        $name = 'nonexistent';

        $this->tagRepository
            ->expects($this->once())
            ->method('findActiveTagByName')
            ->with($name)
            ->willReturn(null);

        $result = $this->tagService->findActiveTagByName($name);

        $this->assertNull($result);
    }

    private function createTag(string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setAscii(strtolower($name));
        $tag->setActive(true);
        return $tag;
    }
}
