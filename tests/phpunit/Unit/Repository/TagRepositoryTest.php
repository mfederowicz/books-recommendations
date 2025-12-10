<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\TagRepository;
use PHPUnit\Framework\TestCase;

class TagRepositoryTest extends TestCase
{
    private TagRepository $repository;

    protected function setUp(): void
    {
        // For unit testing, we'll test the repository class structure
        // Full testing would require integration tests with real Doctrine
        $this->repository = $this->getMockBuilder(TagRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testRepositoryIsInstanceOfTagRepository(): void
    {
        $this->assertInstanceOf(TagRepository::class, $this->repository);
    }

    public function testRepositoryHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findActiveTagsStartingWith'));
        $this->assertTrue(method_exists($this->repository, 'findActiveTagByName'));
    }

    // Note: Full testing of repository methods requires integration tests
    // with real Doctrine EntityManager. These unit tests verify the class structure.
}
