<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ebooks_embeddings')]
#[ORM\UniqueConstraint(name: 'uk_ebook_id', columns: ['ebook_id'])]
class EbookEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Ebook::class)]
    #[ORM\JoinColumn(name: 'ebook_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Ebook $ebook;

    #[ORM\Column(type: 'json')]
    private array $vector;

    #[ORM\Column(type: 'string', length: 255)]
    private string $payloadTitle;

    #[ORM\Column(type: 'string', length: 255)]
    private string $payloadAuthor;

    #[ORM\Column(type: 'json')]
    private array $payloadTags;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEbook(): Ebook
    {
        return $this->ebook;
    }

    public function setEbook(Ebook $ebook): self
    {
        $this->ebook = $ebook;

        return $this;
    }

    public function getVector(): array
    {
        return $this->vector;
    }

    public function setVector(array $vector): self
    {
        $this->vector = $vector;

        return $this;
    }

    public function getPayloadTitle(): string
    {
        return $this->payloadTitle;
    }

    public function setPayloadTitle(string $payloadTitle): self
    {
        $this->payloadTitle = $payloadTitle;

        return $this;
    }

    public function getPayloadAuthor(): string
    {
        return $this->payloadAuthor;
    }

    public function setPayloadAuthor(string $payloadAuthor): self
    {
        $this->payloadAuthor = $payloadAuthor;

        return $this;
    }

    public function getPayloadTags(): array
    {
        return $this->payloadTags;
    }

    public function setPayloadTags(array $payloadTags): self
    {
        $this->payloadTags = $payloadTags;

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
}

