<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'recommendation_results')]
#[ORM\UniqueConstraint(name: 'uk_recommendation_ebook', columns: ['recommendation_id', 'ebook_id'])]
class RecommendationResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recommendation::class)]
    #[ORM\JoinColumn(name: 'recommendation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Recommendation $recommendation;

    #[ORM\ManyToOne(targetEntity: Ebook::class)]
    #[ORM\JoinColumn(name: 'ebook_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Ebook $ebook;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4)]
    private float $similarityScore;

    #[ORM\Column(type: 'integer')]
    private int $rankOrder;

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

    public function getRecommendation(): Recommendation
    {
        return $this->recommendation;
    }

    public function setRecommendation(Recommendation $recommendation): self
    {
        $this->recommendation = $recommendation;

        return $this;
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

    public function getSimilarityScore(): float
    {
        return $this->similarityScore;
    }

    public function setSimilarityScore(float $similarityScore): self
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    public function getRankOrder(): int
    {
        return $this->rankOrder;
    }

    public function setRankOrder(int $rankOrder): self
    {
        $this->rankOrder = $rankOrder;

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
