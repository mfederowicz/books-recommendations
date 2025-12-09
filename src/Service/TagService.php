<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;

final class TagService
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Find active tags starting with given prefix
     * Returns maximum 30 results for autocomplete functionality.
     *
     * @return Tag[]
     */
    public function findActiveTagsForAutocomplete(string $query): array
    {
        // Only search if query has at least 2 characters
        if (strlen(trim($query)) < 2) {
            return [];
        }

        return $this->tagRepository->findActiveTagsStartingWith(trim($query), 30);
    }

    /**
     * Find active tag by exact name match.
     */
    public function findActiveTagByName(string $name): ?Tag
    {
        return $this->tagRepository->findActiveTagByName($name);
    }

    /**
     * Get all active tags (for admin purposes).
     *
     * @return Tag[]
     */
    public function findAllActiveTags(): array
    {
        return $this->tagRepository->findBy(['active' => true], ['name' => 'ASC']);
    }
}
