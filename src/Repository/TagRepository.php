<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Find active tags starting with given prefix (case insensitive)
     * Limited to max results for autocomplete functionality
     */
    public function findActiveTagsStartingWith(string $prefix, int $limit = 30): array
    {
        if (strlen($prefix) < 2) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.active = :active')
            ->andWhere('t.name LIKE :prefix')
            ->setParameter('active', true)
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active tags by exact name match (case insensitive)
     */
    public function findActiveTagByName(string $name): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->where('t.active = :active')
            ->andWhere('LOWER(t.name) = LOWER(:name)')
            ->setParameter('active', true)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
