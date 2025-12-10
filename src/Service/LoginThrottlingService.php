<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LoginThrottlingServiceInterface;
use App\Entity\UserFailedLogin;
use Doctrine\ORM\EntityManagerInterface;

final class LoginThrottlingService implements LoginThrottlingServiceInterface
{
    private int $maxAttempts;
    private int $blockDurationMinutes;
    private int $resetAttemptsAfterMinutes;

    public function __construct(
        private EntityManagerInterface $entityManager,
        int $maxAttempts = 5,
        int $blockDurationMinutes = 15,
        int $resetAttemptsAfterMinutes = 60,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->blockDurationMinutes = $blockDurationMinutes;
        $this->resetAttemptsAfterMinutes = $resetAttemptsAfterMinutes;
    }

    public function isUserBlocked(string $email): bool
    {
        $failedLogin = $this->findFailedLoginByEmail($email);

        return null !== $failedLogin && $failedLogin->isBlocked();
    }

    public function recordFailedLoginAttempt(string $email, ?string $ipAddress = null): void
    {
        $failedLogin = $this->findFailedLoginByEmail($email);

        if (null === $failedLogin) {
            $failedLogin = new UserFailedLogin();
            $failedLogin->setEmail($email);
            $failedLogin->setIpAddress($ipAddress);
            $this->entityManager->persist($failedLogin);
        } else {
            $failedLogin->incrementAttemptsCount();
            $failedLogin->setLastAttemptAt(new \DateTime());
            $failedLogin->setIpAddress($ipAddress);

            // Sprawdź czy należy zablokować użytkownika
            if ($failedLogin->getAttemptsCount() >= $this->maxAttempts) {
                $blockedUntil = new \DateTime();
                $blockedUntil->modify("+{$this->blockDurationMinutes} minutes");
                $failedLogin->setBlockedUntil($blockedUntil);
            }
        }

        $this->entityManager->flush();
    }

    public function clearFailedLoginAttempts(string $email): void
    {
        $failedLogin = $this->findFailedLoginByEmail($email);

        if (null !== $failedLogin) {
            $this->entityManager->remove($failedLogin);
            $this->entityManager->flush();
        }
    }

    public function getBlockedUntil(string $email): ?\DateTimeInterface
    {
        $failedLogin = $this->findFailedLoginByEmail($email);

        return $failedLogin?->getBlockedUntil();
    }

    public function getFailedAttemptsCount(string $email): int
    {
        $failedLogin = $this->findFailedLoginByEmail($email);

        return $failedLogin?->getAttemptsCount() ?? 0;
    }

    private function findFailedLoginByEmail(string $email): ?UserFailedLogin
    {
        return $this->entityManager
            ->getRepository(UserFailedLogin::class)
            ->findOneBy(['email' => $email]);
    }
}
