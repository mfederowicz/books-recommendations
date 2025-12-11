<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ebooks')]
#[ORM\UniqueConstraint(name: 'isbn', columns: ['isbn'])]
class Ebook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 13)]
    private string $isbn;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 255)]
    private string $author;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $offersCount = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hasEmbedding = false;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $comparisonLink = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $mainDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tags = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsbn(): string
    {
        return $this->isbn;
    }

    public function setIsbn(string $isbn): self
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getOffersCount(): int
    {
        return $this->offersCount;
    }

    public function setOffersCount(int $offersCount): self
    {
        $this->offersCount = $offersCount;

        return $this;
    }

    public function hasEmbedding(): bool
    {
        return $this->hasEmbedding;
    }

    public function setHasEmbedding(bool $hasEmbedding): self
    {
        $this->hasEmbedding = $hasEmbedding;

        return $this;
    }

    public function getComparisonLink(): ?string
    {
        return $this->comparisonLink;
    }

    public function setComparisonLink(?string $comparisonLink): self
    {
        $this->comparisonLink = $comparisonLink;

        return $this;
    }

    public function getMainDescription(): ?string
    {
        return $this->mainDescription;
    }

    public function setMainDescription(?string $mainDescription): self
    {
        $this->mainDescription = $mainDescription;

        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(?string $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
