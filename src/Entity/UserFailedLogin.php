<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users_failed_logins')]
#[ORM\Index(name: 'email_idx', columns: ['email'])]
#[ORM\Index(name: 'blocked_until_idx', columns: ['blocked_until'])]
class UserFailedLogin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $attemptsCount = 1;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $lastAttemptAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $blockedUntil = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lastAttemptAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAttemptsCount(): int
    {
        return $this->attemptsCount;
    }

    public function setAttemptsCount(int $attemptsCount): self
    {
        $this->attemptsCount = $attemptsCount;

        return $this;
    }

    public function incrementAttemptsCount(): self
    {
        $this->attemptsCount++;

        return $this;
    }

    public function getLastAttemptAt(): \DateTimeInterface
    {
        return $this->lastAttemptAt;
    }

    public function setLastAttemptAt(\DateTimeInterface $lastAttemptAt): self
    {
        $this->lastAttemptAt = $lastAttemptAt;

        return $this;
    }

    public function getBlockedUntil(): ?\DateTimeInterface
    {
        return $this->blockedUntil;
    }

    public function setBlockedUntil(?\DateTimeInterface $blockedUntil): self
    {
        $this->blockedUntil = $blockedUntil;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blockedUntil !== null && $this->blockedUntil > new \DateTime();
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

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

