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

    #[ORM\Column(type: 'string', length: 13)]
    private string $ebookId;

    #[ORM\Column(type: 'json')]
    private array $vector;

    #[ORM\Column(type: 'string', length: 255)]
    private string $payloadTitle;

    #[ORM\Column(type: 'string', length: 255)]
    private string $payloadAuthor;

    #[ORM\Column(type: 'json')]
    private array $payloadTags;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $payloadDescription = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $payloadUuid = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $syncedToQdrant = false;

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

    public function getEbookId(): string
    {
        return $this->ebookId;
    }

    public function setEbookId(string $ebookId): self
    {
        $this->ebookId = $ebookId;

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

    public function getPayloadDescription(): ?string
    {
        return $this->payloadDescription;
    }

    public function setPayloadDescription(?string $payloadDescription): self
    {
        $this->payloadDescription = $payloadDescription;

        return $this;
    }

    public function getPayloadUuid(): ?string
    {
        return $this->payloadUuid;
    }

    public function setPayloadUuid(?string $payloadUuid): self
    {
        $this->payloadUuid = $payloadUuid;

        return $this;
    }

    public function isSyncedToQdrant(): bool
    {
        return $this->syncedToQdrant;
    }

    public function setSyncedToQdrant(bool $syncedToQdrant): self
    {
        $this->syncedToQdrant = $syncedToQdrant;

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
