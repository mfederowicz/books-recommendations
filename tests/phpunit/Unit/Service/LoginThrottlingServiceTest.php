<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\LoginThrottlingServiceInterface;
use App\Entity\UserFailedLogin;
use App\Service\LoginThrottlingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class LoginThrottlingServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private LoginThrottlingServiceInterface $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(UserFailedLogin::class)
            ->willReturn($this->repository);

        $this->service = new LoginThrottlingService(
            $this->entityManager,
            3, // maxAttempts
            15, // blockDurationMinutes
            60  // resetAttemptsAfterMinutes
        );
    }

    public function testIsUserBlockedReturnsFalseWhenNoFailedLoginRecordExists(): void
    {
        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->assertFalse($this->service->isUserBlocked('test@example.com'));
    }

    public function testIsUserBlockedReturnsFalseWhenUserIsNotBlocked(): void
    {
        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setAttemptsCount(2);
        $failedLogin->setBlockedUntil(null);

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->assertFalse($this->service->isUserBlocked('test@example.com'));
    }

    public function testIsUserBlockedReturnsTrueWhenUserIsBlocked(): void
    {
        $futureDate = new \DateTime('+1 hour');

        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setAttemptsCount(5);
        $failedLogin->setBlockedUntil($futureDate);

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->assertTrue($this->service->isUserBlocked('test@example.com'));
    }

    public function testRecordFailedLoginAttemptCreatesNewRecordForFirstAttempt(): void
    {
        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (UserFailedLogin $failedLogin) {
                return $failedLogin->getEmail() === 'test@example.com'
                    && $failedLogin->getAttemptsCount() === 1
                    && $failedLogin->getIpAddress() === '192.168.1.1';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt('test@example.com', '192.168.1.1');
    }

    public function testRecordFailedLoginAttemptIncrementsExistingRecord(): void
    {
        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setAttemptsCount(1);

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt('test@example.com', '192.168.1.1');

        $this->assertEquals(2, $failedLogin->getAttemptsCount());
    }

    public function testRecordFailedLoginAttemptBlocksUserAfterMaxAttempts(): void
    {
        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setAttemptsCount(2); // Will become 3 after increment, which equals maxAttempts

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->recordFailedLoginAttempt('test@example.com');

        $this->assertEquals(3, $failedLogin->getAttemptsCount());
        $this->assertNotNull($failedLogin->getBlockedUntil());
    }

    public function testClearFailedLoginAttemptsRemovesRecord(): void
    {
        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($failedLogin);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->clearFailedLoginAttempts('test@example.com');
    }

    public function testClearFailedLoginAttemptsDoesNothingWhenNoRecordExists(): void
    {
        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->service->clearFailedLoginAttempts('test@example.com');
    }

    public function testGetBlockedUntilReturnsNullWhenNoRecordExists(): void
    {
        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->assertNull($this->service->getBlockedUntil('test@example.com'));
    }

    public function testGetBlockedUntilReturnsBlockedUntilDate(): void
    {
        $blockedUntil = new \DateTime('+1 hour');

        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setBlockedUntil($blockedUntil);

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->assertEquals($blockedUntil, $this->service->getBlockedUntil('test@example.com'));
    }

    public function testGetFailedAttemptsCountReturnsZeroWhenNoRecordExists(): void
    {
        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->assertEquals(0, $this->service->getFailedAttemptsCount('test@example.com'));
    }

    public function testGetFailedAttemptsCountReturnsAttemptsCount(): void
    {
        $failedLogin = new UserFailedLogin();
        $failedLogin->setEmail('test@example.com');
        $failedLogin->setAttemptsCount(3);

        $this->repository
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($failedLogin);

        $this->assertEquals(3, $this->service->getFailedAttemptsCount('test@example.com'));
    }
}

